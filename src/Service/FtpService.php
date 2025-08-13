<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class FtpService
{
    private LoggerInterface $logger;
    private array $configs;

    /** cache of remote dirs confirmed/created this run */
    private array $dirCache = [];

    /** per-run staging roots: ['data' => '/.../data_new_<token>', ...] */
    private array $sessionNewRoots = [];

    public function __construct(ParameterBagInterface $params, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->configs = [
            'staging'    => $this->parseFtpUrl($params->get('ftp_deployment_staging')),
            'production' => $this->parseFtpUrl($params->get('ftp_deployment_production')),
        ];
    }

    private function parseFtpUrl(string $url): array
    {
        $parts = parse_url($url);

        if (!$parts || !isset($parts['host'], $parts['user'], $parts['pass'])) {
            throw new \InvalidArgumentException("Invalid FTP URL format: {$url}");
        }

        return [
            'scheme'    => strtolower($parts['scheme'] ?? 'ftp'),
            'host'      => $parts['host'],
            'port'      => $parts['port'] ?? 21,
            'username'  => urldecode($parts['user']),
            'password'  => urldecode($parts['pass']),
            'base_path' => rtrim($parts['path'] ?? '', '/'),
            'timeout'   => 30,
            'passive'   => true,
        ];
    }

    public function uploadFiles(string $localBasePath, array $files, string $environment): array
    {
        $cfg = $this->configs[$environment] ?? null;
        if (!$cfg) return ['success' => false, 'error' => "Missing config for {$environment}"];

        $ftp = $this->connect($cfg);
        if (!$ftp) return ['success' => false, 'error' => 'FTP connection failed'];

        try {
            // 0) Prepare unique per-run staging roots (no slow recursive delete)
            $this->prepareRunStagingRoots($ftp, $cfg['base_path']);

            // 1) Ensure all parent dirs once (shallow -> deep)
            $parentDirs = [];
            foreach ($files as $file) {
                $remote = $this->mapToNew($cfg['base_path'], $file['remote']);
                $parentDirs[] = dirname($remote);
            }
            if (!$this->ensureDirectoriesOnce($ftp, $parentDirs)) {
                $this->deleteThisRunStaging($ftp);
                return ['success' => false, 'error' => 'Failed to create remote directories'];
            }

            // 2) Upload
            $failed = [];
            foreach ($files as $file) {
                $remote = $this->mapToNew($cfg['base_path'], $file['remote']);
                $local  = rtrim($localBasePath, '/') . '/' . $file['local'];

                if (!is_file($local)) {
                    $failed[] = $file + ['error' => 'Local file missing'];
                    continue;
                }
                if (!@ftp_put($ftp, $remote, $local, FTP_BINARY)) {
                    $failed[] = $file + ['error' => 'Upload failed'];
                }
            }

            if ($failed) {
                // leave live site untouched; remove only this run's staging roots
                $this->deleteThisRunStaging($ftp);
                return ['success' => false, 'failed_files' => $failed];
            }

            // 3) Atomic switchover (keeps exactly one _old backup)
            if (!$this->switchover($ftp, $cfg['base_path'])) {
                $this->deleteThisRunStaging($ftp);
                return ['success' => false, 'error' => 'Switchover failed'];
            }

            return ['success' => true];
        } finally {
            @ftp_close($ftp);
            $this->dirCache = [];
            $this->sessionNewRoots = [];
        }
    }

    private function connect(array $cfg)
    {
        $fn   = $cfg['scheme'] === 'ftps' ? 'ftp_ssl_connect' : 'ftp_connect';
        $conn = @$fn($cfg['host'], $cfg['port'], $cfg['timeout']);
        if (!$conn) return false;

        if (!@ftp_login($conn, $cfg['username'], $cfg['password'])) return false;

        @ftp_set_option($conn, FTP_TIMEOUT_SEC, (int)$cfg['timeout']);
        if (defined('FTP_AUTOSEEK'))         @ftp_set_option($conn, FTP_AUTOSEEK, true);
        if (defined('FTP_USEPASVADDRESS'))   @ftp_set_option($conn, FTP_USEPASVADDRESS, false);

        @ftp_pasv($conn, (bool)$cfg['passive']);
        return $conn;
    }

    private function normalizePath(string $path): string
    {
        $path = preg_replace('#/+#', '/', $path);
        return '/' . ltrim($path, '/');
    }

    /**
     * Create unique staging roots for this run: data_new_<token>, logos_new_<token>, pdfs_new_<token>
     * (Also ensures /base_path/data etc. exist.)
     */
    private function prepareRunStagingRoots($ftp, string $base): void
    {
        $token = gmdate('YmdHis') . '_' . substr(bin2hex($this->randBytes()), 0, 6);

        foreach (['data', 'logos', 'pdfs'] as $folder) {
            $curr = rtrim($base, '/') . '/' . $folder;
            $new  = $curr . '_new_' . $token;

            $this->ensureDirectoryExistsCached($ftp, $curr);
            $this->ensureDirectoryExistsCached($ftp, $new);

            $this->sessionNewRoots[$folder] = $this->normalizePath($new);
        }
    }

    private function randBytes(): string
    {
        try { return random_bytes(8); } catch (\Throwable $e) { return uniqid('', true); }
    }

    /**
     * Map a "remote" like "logos/2.png" to this run's staging root, e.g.
     *   /base/logos_new_<token>/2.png
     */
    private function mapToNew(string $base, string $path): string
    {
        $path  = ltrim($path, '/');
        [$top, $rest] = array_pad(explode('/', $path, 2), 2, null);

        if (!isset($this->sessionNewRoots[$top])) {
            // Fallback to classic *_new if something unexpected shows up
            $fallback = rtrim($base, '/') . '/' . $top . '_new';
            $root     = $this->normalizePath($fallback);
        } else {
            $root = $this->sessionNewRoots[$top];
        }

        return rtrim($root, '/') . '/' . ($rest ?? '');
    }

    /** Create many directories once, shallow → deep, with caching */
    private function ensureDirectoriesOnce($ftp, array $paths): bool
    {
        $uniq = [];
        foreach ($paths as $p) $uniq[$this->normalizePath($p)] = true;

        $list = array_keys($uniq);
        usort($list, fn($a, $b) => substr_count($a, '/') <=> substr_count($b, '/'));

        foreach ($list as $dir) {
            if (!$this->ensureDirectoryExistsCached($ftp, $dir)) return false;
        }
        return true;
    }

    /** fast-ish mkdir chain with cache; avoids repeated chdir */
    private function ensureDirectoryExistsCached($ftp, string $path): bool
    {
        $path = $this->normalizePath($path);
        if (isset($this->dirCache[$path])) return true;

        $origin = @ftp_pwd($ftp) ?: '/';
        $acc    = '';
        foreach (explode('/', trim($path, '/')) as $seg) {
            if ($seg === '') continue;
            $acc .= '/' . $seg;
            if (isset($this->dirCache[$acc])) continue;

            if (@ftp_mkdir($ftp, $acc) !== false) {
                $this->dirCache[$acc] = true;
                continue;
            }
            if (@ftp_chdir($ftp, $acc)) {
                $this->dirCache[$acc] = true;
                @ftp_chdir($ftp, $origin);
                continue;
            }

            $this->logger->error("mkdir/chdir failed: {$acc}");
            return false;
        }
        return true;
    }

    /** Delete just this run’s staging roots (used on failure) */
    private function deleteThisRunStaging($ftp): void
    {
        foreach ($this->sessionNewRoots as $root) {
            $this->deleteRecursive($ftp, $root);
        }
    }

    private function deleteRecursive($ftp, string $path): void
    {
        $path  = $this->normalizePath($path);
        $items = @ftp_nlist($ftp, $path);
        if ($items === false) { @ftp_rmdir($ftp, $path); return; }

        foreach ($items as $item) {
            $base = basename($item);
            if ($base === '.' || $base === '..') continue;

            if (@ftp_chdir($ftp, $item)) {
                @ftp_chdir($ftp, '/');
                $this->deleteRecursive($ftp, $item);
                @ftp_rmdir($ftp, $item);
            } else {
                @ftp_delete($ftp, $item);
            }
        }

        @ftp_rmdir($ftp, $path);
    }

    /**
     * Atomic switchover:
     * Keep one backup (*_old). Promote only this run’s staging roots.
     */
    private function switchover($ftp, string $base): bool
    {
        $ok = true;

        foreach (['data', 'logos', 'pdfs'] as $folder) {
            $curr = rtrim($base, '/') . '/' . $folder;
            $old  = $curr . '_old';

            $new = $this->sessionNewRoots[$folder] ?? null;
            if (!$new) continue; // nothing staged for this folder

            $curr = $this->normalizePath($curr);
            $old  = $this->normalizePath($old);
            $new  = $this->normalizePath($new);

            $currExists = $this->dirExists($ftp, $curr);
            $oldExists  = $this->dirExists($ftp, $old);
            $newExists  = $this->dirExists($ftp, $new);

            if (!$newExists) continue;

            $movedCurrToOld = false;

            if ($currExists && $oldExists) {
                $this->deleteRecursive($ftp, $old);
            }

            if ($currExists) {
                if (!@ftp_rename($ftp, $curr, $old)) {
                    $this->logger->error("Switchover: rename {$curr} -> {$old} failed");
                    $ok = false;
                    continue;
                }
                $movedCurrToOld = true;
            }

            if (!@ftp_rename($ftp, $new, $curr)) {
                $this->logger->error("Switchover: rename {$new} -> {$curr} failed");
                $ok = false;

                if ($movedCurrToOld) {
                    if (!@ftp_rename($ftp, $old, $curr)) {
                        $this->logger->error("Switchover: rollback {$old} -> {$curr} failed");
                    }
                }
            }
        }

        return $ok;
    }

    private function dirExists($ftp, string $path): bool
    {
        $path   = $this->normalizePath($path);
        $origin = @ftp_pwd($ftp);
        if (@ftp_chdir($ftp, $path)) {
            @ftp_chdir($ftp, $origin ?: '/');
            return true;
        }
        return false;
    }
}
