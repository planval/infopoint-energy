<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class FtpService
{
    private LoggerInterface $logger;
    private array $configs;

    public function __construct(ParameterBagInterface $params, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->configs = [
            'staging' => $this->parseFtpUrl($params->get('ftp_deployment_staging')),
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
            'scheme' => strtolower($parts['scheme'] ?? 'ftp'),
            'host' => $parts['host'],
            'port' => $parts['port'] ?? 21,
            'username' => urldecode($parts['user']),
            'password' => urldecode($parts['pass']),
            'base_path' => rtrim($parts['path'] ?? '', '/'),
            'timeout' => 30,
            'passive' => true,
        ];
    }

    private function normalizePath(string $path): string
    {
        if ($path === '') return '';

        // unify separators and collapse repeats
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#/+#', '/', $path);

        // remember if it was absolute
        $absolute = ($path[0] ?? '') === '/';

        // split and resolve segments
        $segments = explode('/', $path);
        $stack = [];
        foreach ($segments as $seg) {
            if ($seg === '' || $seg === '.') {
                continue;
            }
            if ($seg === '..') {
                array_pop($stack);
                continue;
            }
            $stack[] = $seg;
        }

        $normalized = implode('/', $stack);
        return $absolute ? '/' . $normalized : $normalized;
    }

    public function uploadFiles(string $localBasePath, array $files, string $environment): array
    {
        $cfg = $this->configs[$environment] ?? null;
        if (!$cfg) return ['success' => false, 'error' => "Missing config for {$environment}"];

        $ftp = $this->connect($cfg);
        if (!$ftp) return ['success' => false, 'error' => 'FTP connection failed'];

        $failed = [];
        foreach ($files as $file) {
            $remote = $this->mapToNew($cfg['base_path'], $file['remote']);
            $local = rtrim($localBasePath, '/') . '/' . $file['local'];

            if (!is_file($local)) {
                $failed[] = $file + ['error' => 'Local file missing'];
                continue;
            }

            if (!$this->ensureDirectoryExists($ftp, dirname($remote))) {
                $failed[] = $file + ['error' => 'Remote dir failed'];
                continue;
            }

            if (!ftp_put($ftp, $remote, $local, FTP_BINARY)) {
                $failed[] = $file + ['error' => 'Upload failed'];
            }
        }

        if ($failed) {
            $this->cleanup($ftp, $cfg['base_path'], '_new');
            ftp_close($ftp);
            return ['success' => false, 'failed_files' => $failed];
        }

        $this->cleanup($ftp, $cfg['base_path'], '_old');
        $this->switchover($ftp, $cfg['base_path']);
        ftp_close($ftp);

        return ['success' => true];
    }

    private function connect(array $cfg)
    {
        $fn = $cfg['scheme'] === 'ftps' ? 'ftp_ssl_connect' : 'ftp_connect';
        $conn = @$fn($cfg['host'], $cfg['port'], $cfg['timeout']);

        if (!$conn || !@ftp_login($conn, $cfg['username'], $cfg['password'])) return false;

        ftp_pasv($conn, $cfg['passive']);
        return $conn;
    }

    private function mapToNew(string $base, string $path): string
    {
        $parts = explode('/', ltrim($path, '/'), 2);
        $folder = $parts[0] . '_new';
        return rtrim($base, '/') . '/' . $folder . '/' . ($parts[1] ?? '');
    }

    private function ensureDirectoryExists($ftp, string $path): bool
    {
        $segments = explode('/', trim($path, '/'));
        $accum = '';

        foreach ($segments as $seg) {
            $accum .= '/' . $seg;
            if (@ftp_chdir($ftp, $accum)) continue;
            if (!@ftp_mkdir($ftp, $accum) && !@ftp_chdir($ftp, $accum)) {
                $this->logger->error("mkdir failed: {$accum}");
                return false;
            }
        }

        return true;
    }

    private function cleanup($ftp, string $base, string $suffix): void
    {
        foreach (['data', 'logos', 'pdfs'] as $dir) {
            $this->deleteTree($ftp, rtrim($base, '/') . '/' . $dir . $suffix);
        }
    }

    private function deleteTree($ftp, string $path): void
    {
        $path = $this->normalizePath($path);

        // 1) Try fast, server-side recursive delete (vendor-specific). If it works, we're done.
        if ($this->tryServerRecursiveDelete($ftp, $path)) {
            return;
        }

        // 2) Typed listing path (no chdir round-trips)
        if (function_exists('ftp_mlsd')) {
            $this->deleteRecursiveMlsd($ftp, $path);
            @ftp_rmdir($ftp, $path);
            return;
        }

        // 3) Fallback: NLST + chdir probing (last resort)
        $this->deleteRecursiveNlist($ftp, $path);
        @ftp_rmdir($ftp, $path);
    }

    /** Attempt vendor recursive delete. Returns true on success, false on unknown/unsupported. */
    private function tryServerRecursiveDelete($ftp, string $path): bool
    {
        // Common variants seen in the wild (Pure-FTPd, ProFTPD, some IIS, etc.)
        $candidates = [
            "SITE RMDIR -R %s",
            "SITE RMDIR %s -R",
            "SITE RMDIR %s",
            "SITE DELE -R %s",
            "RMDIR -R %s",
            "XRMD %s", // some servers alias this
        ];

        foreach ($candidates as $fmt) {
            $cmd = sprintf($fmt, $path);
            $resp = @ftp_raw($ftp, $cmd);
            if ($this->is2xx($resp)) {
                return true;
            }
        }
        return false;
    }

    private function is2xx(?array $resp): bool
    {
        if (!$resp) return false;
        foreach ($resp as $line) {
            $code = (int)substr(trim($line), 0, 3);
            if ($code >= 200 && $code < 300) return true;
        }
        return false;
    }

    private function deleteRecursiveMlsd($ftp, string $dir): void
    {
        $dir = $this->normalizePath($dir);
        // Ask for typed MLSD entries (helps some servers)
        @ftp_raw($ftp, 'OPTS MLST type;size;modify;');
        $entries = @ftp_mlsd($ftp, $dir);
        if ($entries === false) return;

        foreach ($entries as $e) {
            $name = $e['name'] ?? '';
            if ($name === '.' || $name === '..') continue;

            $full = $dir . '/' . $name;
            $type = strtolower($e['type'] ?? '');

            if ($type === 'dir') {
                $this->deleteRecursiveMlsd($ftp, $full);
                @ftp_rmdir($ftp, $full);
            } else {
                @ftp_delete($ftp, $full);
            }
        }
    }

    private function deleteRecursiveNlist($ftp, string $dir): void
    {
        $dir = $this->normalizePath($dir);
        $items = @ftp_nlist($ftp, $dir);
        if ($items === false) return;

        foreach ($items as $item) {
            $base = basename($item);
            if ($base === '.' || $base === '..') continue;

            $full = $dir . '/' . $base;

            // Probe directory with one chdir; always use absolute path and reset back
            if (@ftp_chdir($ftp, $full)) {
                @ftp_chdir($ftp, '/');
                $this->deleteRecursiveNlist($ftp, $full);
                @ftp_rmdir($ftp, $full);
            } else {
                @ftp_delete($ftp, $full);
            }
        }
    }

    private function switchover($ftp, string $base): void
    {
        foreach (['data', 'logos', 'pdfs'] as $folder) {
            $curr = rtrim($base, '/') . '/' . $folder;
            $old = $curr . '_old';
            $new = $curr . '_new';

            if ($this->dirExists($ftp, $new)) {
                if ($this->dirExists($ftp, $curr)) @ftp_rename($ftp, $curr, $old);
                @ftp_rename($ftp, $new, $curr);
            }
        }
    }

    private function dirExists($ftp, string $path): bool
    {
        $origin = @ftp_pwd($ftp);
        if (@ftp_chdir($ftp, $path)) {
            @ftp_chdir($ftp, $origin);
            return true;
        }
        return false;
    }
}