<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class FtpService
{
    private LoggerInterface $logger;
    private array $configs;

    /** Cache of directories confirmed/created this run to avoid repeated RTTs */
    private array $dirCache = [];

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
            // 0) Clean and recreate staging roots to guarantee a *fresh* upload every time
            $this->resetStaging($ftp, $cfg['base_path']);

            // 1) Batch-ensure unique parent dirs for all files (minimizes mkdir/chdir chatter)
            $parentDirs = [];
            foreach ($files as $file) {
                $remote = $this->mapToNew($cfg['base_path'], $file['remote']);
                $parentDirs[] = dirname($remote);
            }
            if (!$this->ensureDirectoriesOnce($ftp, $parentDirs)) {
                $this->cleanup($ftp, $cfg['base_path'], '_new');
                return ['success' => false, 'error' => 'Failed to create remote directories'];
            }

            // 2) Upload files
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
                // Leave live site untouched, wipe staging
                $this->cleanup($ftp, $cfg['base_path'], '_new');
                return ['success' => false, 'failed_files' => $failed];
            }

            // 3) Atomic switchover keeping _old as the previous live
            if (!$this->switchover($ftp, $cfg['base_path'])) {
                // If promotion failed, do not leave broken staging folders around
                $this->cleanup($ftp, $cfg['base_path'], '_new');
                return ['success' => false, 'error' => 'Switchover failed'];
            }

            return ['success' => true];
        } finally {
            @ftp_close($ftp);
        }
    }

    private function connect(array $cfg)
    {
        $fn   = $cfg['scheme'] === 'ftps' ? 'ftp_ssl_connect' : 'ftp_connect';
        $conn = @$fn($cfg['host'], $cfg['port'], $cfg['timeout']);
        if (!$conn) return false;

        if (!@ftp_login($conn, $cfg['username'], $cfg['password'])) return false;

        // Tweak connection options
        @ftp_set_option($conn, FTP_TIMEOUT_SEC, (int)$cfg['timeout']);
        if (defined('FTP_AUTOSEEK')) {
            @ftp_set_option($conn, FTP_AUTOSEEK, true);
        }
        // Helps when the server is behind NAT
        if (defined('FTP_USEPASVADDRESS')) {
            @ftp_set_option($conn, FTP_USEPASVADDRESS, false);
        }

        @ftp_pasv($conn, (bool)$cfg['passive']);
        return $conn;
    }

    private function normalizePath(string $path): string
    {
        $path = preg_replace('#/+#', '/', $path);
        return '/' . ltrim($path, '/');
    }

    private function mapToNew(string $base, string $path): string
    {
        $parts  = explode('/', ltrim($path, '/'), 2);
        $folder = $parts[0] . '_new';
        $base   = rtrim($base, '/');
        return ($base ? $base : '') . '/' . $folder . '/' . ($parts[1] ?? '');
    }

    /** Clean and recreate staging roots so previous bricked runs can't leak stale files */
    private function resetStaging($ftp, string $base): void
    {
        foreach (['data', 'logos', 'pdfs'] as $name) {
            $staging = rtrim($base, '/') . '/' . $name . '_new';
            $staging = $this->normalizePath($staging);
            // Remove any leftovers from a crashed/aborted run
            $this->deleteRecursive($ftp, $staging);
            // Recreate and cache
            @ftp_mkdir($ftp, $staging);
            $this->dirCache[$staging] = true;
        }
    }

    /**
     * Ensure a full absolute path exists, with caching and minimal round-trips.
     * Strategy: try MKD first (fast if missing; harmless if present on many servers),
     *           else CWD to confirm existence.
     */
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

    /** Create all unique parent directories once, sorted by depth (shallow → deep) */
    private function ensureDirectoriesOnce($ftp, array $paths): bool
    {
        $uniq = [];
        foreach ($paths as $p) {
            $uniq[$this->normalizePath($p)] = true;
        }

        $list = array_keys($uniq);
        usort($list, fn($a, $b) => substr_count($a, '/') <=> substr_count($b, '/'));

        foreach ($list as $dir) {
            if (!$this->ensureDirectoryExistsCached($ftp, $dir)) return false;
        }
        return true;
    }

    private function cleanup($ftp, string $base, string $suffix): void
    {
        foreach (['data', 'logos', 'pdfs'] as $dir) {
            $this->deleteRecursive($ftp, rtrim($base, '/') . '/' . $dir . $suffix);
        }
    }

    private function deleteRecursive($ftp, string $path): void
    {
        $path  = $this->normalizePath($path);
        $items = @ftp_nlist($ftp, $path);
        if ($items === false) {
            @ftp_rmdir($ftp, $path);
            return;
        }

        foreach ($items as $item) {
            $base = basename($item);
            if ($base === '.' || $base === '..') continue;

            if (@ftp_chdir($ftp, $item)) {
                // directory
                @ftp_chdir($ftp, '/'); // reset before recursion
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
     * - if * exists:
     *      delete *_old (we keep exactly one backup: previous live),
     *      rename * -> *_old
     *   else (no current *):
     *      keep any existing *_old (don’t delete your only backup)
     * - rename *_new -> *
     * - on failure after we moved current to *_old`, attempt rollback
     */
    private function switchover($ftp, string $base): bool
    {
        $ok = true;

        foreach (['data', 'logos', 'pdfs'] as $folder) {
            $curr = rtrim($base, '/') . '/' . $folder;
            $old  = $curr . '_old';
            $new  = $curr . '_new';

            $currExists = $this->dirExists($ftp, $curr);
            $newExists  = $this->dirExists($ftp, $new);
            $oldExists  = $this->dirExists($ftp, $old);

            if (!$newExists) {
                // Nothing to promote for this folder; skip it
                continue;
            }

            $movedCurrToOld = false;

            // Only delete _old if a real current exists and we are about to rotate it
            if ($currExists && $oldExists) {
                $this->deleteRecursive($ftp, $old);
                $oldExists = false;
            }

            if ($currExists) {
                if (!@ftp_rename($ftp, $curr, $old)) {
                    $this->logger->error("Switchover: rename {$curr} -> {$old} failed");
                    $ok = false;
                    // Do not try to promote _new if we couldn't park current
                    continue;
                }
                $movedCurrToOld = true;
            }

            if (!@ftp_rename($ftp, $new, $curr)) {
                $this->logger->error("Switchover: rename {$new} -> {$curr} failed");
                $ok = false;

                // Try rollback if we moved current to old already
                if ($movedCurrToOld) {
                    if (!@ftp_rename($ftp, $old, $curr)) {
                        $this->logger->error("Switchover: rollback {$old} -> {$curr} failed (manual intervention may be required)");
                    }
                }
                // If we didn’t move current, keep old as-is and leave _new in place for inspection
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
