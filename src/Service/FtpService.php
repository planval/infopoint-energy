<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class FtpService
{
    private LoggerInterface $logger;
    private array $configs;

    /** Cache of remote dirs we’ve already created/seen this run */
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
            // 1) Ensure all parent directories ONCE (shallow -> deep)
            $parentDirs = [];
            foreach ($files as $file) {
                $remoteNew = $this->mapToNew($cfg['base_path'], $file['remote']);
                $parentDirs[] = dirname($remoteNew);
            }
            if (!$this->ensureDirectoriesOnce($ftp, $parentDirs)) {
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

                $t0 = microtime(true);
                if (!@ftp_put($ftp, $remote, $local, FTP_BINARY)) {
                    $failed[] = $file + ['error' => 'Upload failed'];
                } else {
                    // Optional: quick timing log (comment out if too chatty)
                    $dt = (microtime(true) - $t0) * 1000;
                    $this->logger->info(sprintf('Uploaded %s -> %s in %.0f ms', $file['local'], $remote, $dt));
                }
            }

            if ($failed) {
                // Clean up the staging area if upload wasn’t complete
                $this->cleanup($ftp, $cfg['base_path'], '_new');
                return ['success' => false, 'failed_files' => $failed];
            }

            // 3) Keep one backup and switch over
            $this->cleanup($ftp, $cfg['base_path'], '_old');
            $this->switchover($ftp, $cfg['base_path']);

            return ['success' => true];
        } finally {
            @ftp_close($ftp);
            $this->dirCache = [];
        }
    }

    private function connect(array $cfg)
    {
        $fn   = $cfg['scheme'] === 'ftps' ? 'ftp_ssl_connect' : 'ftp_connect';
        $conn = @$fn($cfg['host'], $cfg['port'], $cfg['timeout']);

        if (!$conn || !@ftp_login($conn, $cfg['username'], $cfg['password'])) {
            return false;
        }

        // Helpful runtime options
        @ftp_set_option($conn, FTP_TIMEOUT_SEC, (int)$cfg['timeout']);
        if (defined('FTP_AUTOSEEK'))       @ftp_set_option($conn, FTP_AUTOSEEK, true);
        if (defined('FTP_USEPASVADDRESS')) @ftp_set_option($conn, FTP_USEPASVADDRESS, false);

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
        $mapped = rtrim($base, '/') . '/' . $folder . '/' . ($parts[1] ?? '');
        return $this->normalizePath($mapped);
    }

    /** Ensure a bunch of dirs once, shallow -> deep, with a local cache to avoid repeated round-trips */
    private function ensureDirectoriesOnce($ftp, array $paths): bool
    {
        $uniq = [];
        foreach ($paths as $p) {
            $p = $this->normalizePath($p);
            $uniq[$p] = true;
        }
        $list = array_keys($uniq);

        // Create shallowest first
        usort($list, fn($a, $b) => substr_count($a, '/') <=> substr_count($b, '/'));

        foreach ($list as $dir) {
            if (!$this->ensureDirectoryExists($ftp, $dir)) {
                return false;
            }
        }
        return true;
    }

    private function ensureDirectoryExists($ftp, string $path): bool
    {
        $path = $this->normalizePath($path);
        if (isset($this->dirCache[$path])) return true;

        $accum  = '';
        foreach (explode('/', trim($path, '/')) as $seg) {
            if ($seg === '') continue;
            $accum = $accum . '/' . $seg;

            if (isset($this->dirCache[$accum])) continue;

            // Try create; if it already exists this returns false, so we probe with chdir
            if (@ftp_mkdir($ftp, $accum) !== false) {
                $this->dirCache[$accum] = true;
                continue;
            }
            if (@ftp_chdir($ftp, $accum)) {
                $this->dirCache[$accum] = true;
                // Go back to whatever it was; exact PWD isn’t critical since we use absolute paths, but tidy up.
                @ftp_chdir($ftp, '/');
                continue;
            }

            $this->logger->error("mkdir/chdir failed: {$accum}");
            return false;
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
                @ftp_chdir($ftp, '/');
                $this->deleteRecursive($ftp, $item);
                @ftp_rmdir($ftp, $item);
            } else {
                @ftp_delete($ftp, $item);
            }
        }

        @ftp_rmdir($ftp, $path);
    }

    private function switchover($ftp, string $base): void
    {
        foreach (['data', 'logos', 'pdfs'] as $folder) {
            $curr = $this->normalizePath(rtrim($base, '/') . '/' . $folder);
            $old  = $curr . '_old';
            $new  = $curr . '_new';

            if ($this->dirExists($ftp, $new)) {
                if ($this->dirExists($ftp, $curr)) {
                    @ftp_rename($ftp, $curr, $old);
                }
                @ftp_rename($ftp, $new, $curr);
            }
        }
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
