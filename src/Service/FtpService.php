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
            $this->deleteRecursive($ftp, rtrim($base, '/') . '/' . $dir . $suffix);
        }
    }

    private function deleteRecursive($ftp, string $path): void
    {
        $items = @ftp_nlist($ftp, $path);
        if ($items === false) return;

        foreach ($items as $item) {
            if (in_array(basename($item), ['.', '..'])) continue;

            if (@ftp_chdir($ftp, $item)) {
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