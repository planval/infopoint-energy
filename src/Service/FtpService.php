<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBagInterface;

/**
 * Service to handle FTP operations
 */
class FtpService
{
    private $params;

    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
    }

    /**
     * Upload a file to an FTP server
     *
     * @param string $localFilePath The path to the local file
     * @param string $environment The environment to upload to ('production' or 'staging')
     * @return array Result of the upload operation with success status and message
     */
    public function uploadFile(string $localFilePath, string $environment): array
    {
        // Get the appropriate FTP URL from environment variables
        $ftpUrl = $environment === 'production' 
            ? $this->params->get('ftp_deployment_production')
            : $this->params->get('ftp_deployment_staging');

        if (empty($ftpUrl) || $ftpUrl === 'ftp://<username>:<password>@<hostname>:<port>/<path>') {
            return [
                'success' => false,
                'message' => "FTP configuration for $environment environment is not set"
            ];
        }

        // Parse the FTP URL
        $parsedUrl = parse_url($ftpUrl);
        if ($parsedUrl === false) {
            return [
                'success' => false,
                'message' => "Invalid FTP URL format"
            ];
        }

        // Extract components
        $host = $parsedUrl['host'] ?? '';
        $port = $parsedUrl['port'] ?? 21;
        $username = $parsedUrl['user'] ?? '';
        $password = $parsedUrl['pass'] ?? '';
        $remotePath = $parsedUrl['path'] ?? '/';

        // Make sure the remote path ends with a slash
        if (substr($remotePath, -1) !== '/') {
            $remotePath .= '/';
        }

        // Get the filename from the local path
        $filename = basename($localFilePath);
        $remoteFilePath = $remotePath . $filename;

        // Establish FTP connection
        $conn = @ftp_connect($host, $port);
        if (!$conn) {
            return [
                'success' => false,
                'message' => "Could not connect to FTP server: $host:$port"
            ];
        }

        try {
            // Login to FTP server
            if (!@ftp_login($conn, $username, $password)) {
                throw new \Exception("FTP login failed with username: $username");
            }

            // Enable passive mode (better for firewalls and NAT)
            ftp_pasv($conn, true);

            // Check if the remote directory exists
            $this->ensureDirectoryExists($conn, $remotePath);

            // Upload the file
            if (!@ftp_put($conn, $remoteFilePath, $localFilePath, FTP_BINARY)) {
                throw new \Exception("Failed to upload file to $remoteFilePath");
            }

            return [
                'success' => true,
                'message' => "File successfully uploaded to $environment environment"
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        } finally {
            // Close the connection
            if ($conn) {
                ftp_close($conn);
            }
        }
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