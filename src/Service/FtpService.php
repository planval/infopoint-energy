<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Service to handle FTP operations for direct file transfer
 */
class FtpService
{
    private array $configs;
    private LoggerInterface $logger;
    private $params;

    public function __construct(ParameterBagInterface $params, LoggerInterface $logger)
    {
        $this->params = $params;
        $this->logger = $logger;

        $this->configs = [
            'staging' => $this->parseFtpUrl($this->params->get('ftp_deployment_staging')),
            'production' => $this->parseFtpUrl($this->params->get('ftp_deployment_production')),
        ];
    }

    private function parseFtpUrl(string $url, string $defaultBasePath = ''): array
    {
        $parts = parse_url($url);

        if (!$parts || !isset($parts['host'], $parts['user'], $parts['pass'])) {
            throw new \InvalidArgumentException("Invalid FTP URL format: {$url}");
        }

        $scheme = strtolower($parts['scheme'] ?? 'ftp');
        if (!in_array($scheme, ['ftp', 'ftps'], true)) {
            throw new \InvalidArgumentException("Unsupported FTP scheme: {$scheme}");
        }

        return [
            'scheme' => $scheme,
            'host' => $parts['host'],
            'port' => $parts['port'] ?? 21,
            'username' => $parts['user'],
            'password' => $parts['pass'],
            'passive' => false,
            'timeout' => 30,
            'base_path' => rtrim($parts['path'] ?? $defaultBasePath, '/'),
        ];
    }

    /**
     * Upload files directly to FTP server
     */
    public function uploadFiles(string $localBasePath, array $files, string $environment): array
    {
        $config = $this->configs[$environment] ?? null;
        
        if (!$config) {
            return [
                'success' => false,
                'error' => "Configuration not found for environment: {$environment}"
            ];
        }

        $connection = $this->createConnection($config);
        
        if (!$connection) {
            return [
                'success' => false,
                'error' => 'Failed to connect to FTP server'
            ];
        }

        try {
            // Step 1: Upload files to *_new folders
            $this->logger->info('Starting atomic upload to *_new folders');
            
            $totalFiles = count($files);
            $uploadedFiles = 0;
            $failedFiles = [];
            $uploadResults = [];
            
            foreach ($files as $file) {
                try {
                    // Upload to *_new folder
                    $newRemotePath = $this->getNewRemotePath($file['remote'], $environment);
                    $result = $this->uploadSingleFile(
                        $connection,
                        $localBasePath . '/' . $file['local'],
                        $newRemotePath
                    );
                    
                    $uploadResults[] = $result;
                    
                    if ($result['success']) {
                        $uploadedFiles++;
                        $this->logger->info("Upload progress: {$uploadedFiles}/{$totalFiles} - {$newRemotePath}");
                    } else {
                        $failedFiles[] = $file;
                        $this->logger->error("Failed to upload: {$newRemotePath}");
                    }
                    
                } catch (\Exception $e) {
                    $failedFiles[] = array_merge($file, ['error' => $e->getMessage()]);
                    $this->logger->error("Exception during upload: {$file['remote']} - {$e->getMessage()}");
                }
            }
            
            // If upload failed, clean up and return error
            if (!empty($failedFiles)) {
                $this->logger->error('Upload failed, cleaning up *_new folders');
                $this->cleanupNewFolders($connection, $environment);
                ftp_close($connection);
                
                return [
                    'success' => false,
                    'error' => 'Upload failed, see logs for details',
                    'total_files' => $totalFiles,
                    'uploaded_files' => $uploadedFiles,
                    'failed_files' => $failedFiles
                ];
            }
            
            // Step 2: Clean up old folders from previous deployment
            $this->logger->info('Cleaning up *_old folders from previous deployment');
            try {
                $this->cleanupOldFolders($connection, $environment);
            } catch (\Exception $e) {
                $this->logger->warning('Failed to cleanup old folders, continuing anyway: ' . $e->getMessage());
            }
            
            // Step 3: Atomic switchover - rename current to old, new to current
            $this->logger->info('Performing atomic switchover');
            $switchoverSuccess = $this->performAtomicSwitchover($connection, $environment);
            
            ftp_close($connection);
            
            if (!$switchoverSuccess) {
                return [
                    'success' => false,
                    'error' => 'Atomic switchover failed'
                ];
            }
            
            return [
                'success' => empty($failedFiles),
                'message' => empty($failedFiles) 
                    ? "Successfully uploaded {$uploadedFiles} files to {$environment}"
                    : "Uploaded {$uploadedFiles}/{$totalFiles} files to {$environment}",
                'total_files' => $totalFiles,
                'uploaded_files' => $uploadedFiles,
                'failed_files' => $failedFiles,
                'details' => $uploadResults
            ];
            
        } catch (\Exception $e) {
            if ($connection) {
                ftp_close($connection);
            }
            
            $this->logger->error('FTP upload failed', [
                'environment' => $environment,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'FTP upload failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Test FTP connection
     */
    public function testConnection(string $environment): bool
    {
        $config = $this->configs[$environment] ?? null;
        
        if (!$config) {
            return false;
        }

        $connection = $this->createConnection($config);
        
        if ($connection) {
            ftp_close($connection);
            return true;
        }
        
        return false;
    }

    /**
     * Create FTP connection
     */
    private function createConnection(array $config)
    {
        $useSsl = isset($config['scheme']) && strtolower($config['scheme']) === 'ftps';

        $this->logger->info(sprintf(
            'Connecting to FTP server: %s:%s using %s',
            $config['host'],
            $config['port'],
            $useSsl ? 'FTPS (ftp_ssl_connect)' : 'FTP (ftp_connect)'
        ));

        $connection = $useSsl
            ? ftp_ssl_connect($config['host'], $config['port'], $config['timeout'])
            : ftp_connect($config['host'], $config['port'], $config['timeout']);

        if (!$connection) {
            $this->logger->error('Failed to connect to FTP server', [
                'host' => $config['host'],
                'ssl' => $useSsl,
            ]);
            return false;
        }

        if (!ftp_login($connection, $config['username'], $config['password'])) {
            $this->logger->error('FTP login failed', ['username' => $config['username']]);
            ftp_close($connection);
            return false;
        }

        if (!ftp_pasv($connection, $config['passive'] ?? true)) {
            $this->logger->warning('Failed to enable passive mode');
        }

        return $connection;
    }


    /**
     * Upload a single file
     */
    private function uploadSingleFile($connection, string $localFile, string $remoteFile): array
    {
        // Ensure local file exists
        if (!file_exists($localFile)) {
            return [
                'local_file' => $localFile,
                'remote_file' => $remoteFile,
                'success' => false,
                'error' => 'Local file does not exist',
                'size' => 0
            ];
        }

        // Ensure remote directory exists
        $this->ensureRemoteDirectoryExists($connection, dirname($remoteFile));
        
        $result = ftp_put($connection, $remoteFile, $localFile, FTP_BINARY);
        
        return [
            'local_file' => $localFile,
            'remote_file' => $remoteFile,
            'success' => $result,
            'size' => filesize($localFile),
            'error' => $result ? null : 'FTP upload failed'
        ];
    }

    /**
     * Ensure remote directory exists
     */
    private function ensureRemoteDirectoryExists($connection, string $remotePath): void
    {
        if ($remotePath === '.' || $remotePath === '/' || empty($remotePath)) {
            return;
        }

        $parts = explode('/', ltrim($remotePath, '/'));
        $currentPath = '';
        
        foreach ($parts as $part) {
            $currentPath .= '/' . $part;
            
            if (!$this->isDirectory($connection, $currentPath)) {
                if (!ftp_mkdir($connection, $currentPath)) {
                    $this->logger->error("Failed to create directory: {$currentPath}");
                } else {
                    $this->logger->info("Created directory: {$currentPath}");
                }
            }
        }
    }

    /**
     * Check if remote path is a directory
     */
    private function isDirectory($connection, string $path): bool
    {
        $originalPath = ftp_pwd($connection);
        $result = @ftp_chdir($connection, $path);
        
        if ($result) {
            ftp_chdir($connection, $originalPath);
        }
        
        return $result;
    }

    /**
     * Get remote path for environment
     */
    private function getRemotePath(string $relativePath, string $environment): string
    {
        $config = $this->configs[$environment] ?? null;
        $basePath = $config ? $config['base_path'] : '';
        return $basePath . '/' . ltrim($relativePath, '/');
    }

    /**
     * Get remote path for *_new folders
     */
    private function getNewRemotePath(string $relativePath, string $environment): string
    {
        $config = $this->configs[$environment] ?? null;
        $basePath = $config ? $config['base_path'] : '';
        
        // Convert data/file.json to data_new/file.json
        $parts = explode('/', ltrim($relativePath, '/'), 2);
        if (count($parts) === 2) {
            $folder = $parts[0];
            $file = $parts[1];
            return $basePath . '/' . $folder . '_new/' . $file;
        }
        
        return $basePath . '/' . ltrim($relativePath, '/') . '_new';
    }

    /**
     * Clean up *_new folders (on upload failure)
     */
    private function cleanupNewFolders($connection, string $environment): void
    {
        $config = $this->configs[$environment] ?? null;
        $basePath = $config ? $config['base_path'] : '';
        
        $newFolders = [
            $basePath . '/data_new',
            $basePath . '/logos_new',
            $basePath . '/pdfs_new'
        ];
        
        foreach ($newFolders as $folder) {
            $this->removeDirectory($connection, $folder);
        }
    }

    /**
     * Clean up *_old folders from previous deployment
     */
    private function cleanupOldFolders($connection, string $environment): void
    {
        $config = $this->configs[$environment] ?? null;
        $basePath = $config ? $config['base_path'] : '';
        
        $oldFolders = [
            $basePath . '/data_old',
            $basePath . '/logos_old',
            $basePath . '/pdfs_old'
        ];
        
        foreach ($oldFolders as $folder) {
            $this->removeDirectory($connection, $folder);
        }
    }

    /**
     * Perform atomic switchover: current → old, new → current
     */
    private function performAtomicSwitchover($connection, string $environment): bool
    {
        $config = $this->configs[$environment] ?? null;
        $basePath = $config ? $config['base_path'] : '';
        
        $folders = ['data', 'logos', 'pdfs'];
        
        foreach ($folders as $folder) {
            $current = $basePath . '/' . $folder;
            $old = $basePath . '/' . $folder . '_old';
            $new = $basePath . '/' . $folder . '_new';
            
            // Check if new folder exists
            if (!$this->isDirectory($connection, $new)) {
                $this->logger->error("New folder does not exist: {$new}");
                return false;
            }
            
            // Rename current to old (if current exists)
            if ($this->isDirectory($connection, $current)) {
                if (!ftp_rename($connection, $current, $old)) {
                    $this->logger->error("Failed to rename {$current} to {$old}");
                    return false;
                }
                $this->logger->info("Renamed {$current} to {$old}");
            }
            
            // Rename new to current
            if (!ftp_rename($connection, $new, $current)) {
                $this->logger->error("Failed to rename {$new} to {$current}");
                // Try to rollback - rename old back to current
                if ($this->isDirectory($connection, $old)) {
                    ftp_rename($connection, $old, $current);
                }
                return false;
            }
            $this->logger->info("Renamed {$new} to {$current}");
        }
        
        return true;
    }

    /**
     * Remove directory and all its contents
     */
    private function removeDirectory($connection, string $remotePath): void
    {
        if (!$this->isDirectory($connection, $remotePath)) {
            return;
        }
        
        // List files in folder with error handling
        $files = @ftp_nlist($connection, $remotePath);
        
        if ($files === false) {
            $this->logger->warning("Could not list files in directory: {$remotePath}");
            return;
        }
        
        foreach ($files as $file) {
            $fileName = basename($file);
            
            // Skip system folders
            if ($this->isSystemFolder($fileName)) {
                continue;
            }
            
            $fullPath = $remotePath . '/' . $fileName;
            
            // Check if it's a directory
            if ($this->isDirectory($connection, $fullPath)) {
                $this->removeDirectory($connection, $fullPath); // Recursive
                if (ftp_rmdir($connection, $fullPath)) {
                    $this->logger->info("Removed directory: {$fullPath}");
                }
            } else {
                if (ftp_delete($connection, $fullPath)) {
                    $this->logger->info("Removed file: {$fullPath}");
                }
            }
        }
        
        // Remove the directory itself
        if (ftp_rmdir($connection, $remotePath)) {
            $this->logger->info("Removed directory: {$remotePath}");
        }
    }


    /**
     * Check if folder is a system folder
     */
    private function isSystemFolder(string $folderName): bool
    {
        // System folders that should be skipped during processing
        return in_array($folderName, ['.', '..']);
    }


    /**
     * Ensure the directory path exists on the FTP server
     * Creates directories recursively if needed
     *
     * @param resource $ftpConnection An FTP connection
     * @param string $directory The directory path to ensure exists
     * @return bool Success or failure
     */
    private function ensureDirectoryExists($ftpConnection, string $directory): bool
    {
        // Remove leading slash
        $directory = ltrim($directory, '/');
        
        if (empty($directory)) {
            return true; // Root directory always exists
        }

        // Split the path into segments
        $segments = explode('/', $directory);
        $path = '';

        // Try to create each directory segment
        foreach ($segments as $segment) {
            if (empty($segment)) {
                continue;
            }

            $path .= '/' . $segment;

            // Check if directory exists
            $currentDir = @ftp_pwd($ftpConnection);
            $dirExists = false;

            // Try to change to the directory to check if it exists
            if (@ftp_chdir($ftpConnection, $path)) {
                $dirExists = true;
                // Change back to the original directory
                @ftp_chdir($ftpConnection, $currentDir);
            }

            // Create the directory if it doesn't exist
            if (!$dirExists) {
                if (!@ftp_mkdir($ftpConnection, $path)) {
                    return false; // Failed to create directory
                }
            }
        }

        return true;
    }
} 