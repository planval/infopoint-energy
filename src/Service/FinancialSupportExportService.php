<?php

namespace App\Service;

use App\Entity\FinancialSupport;
use App\Util\PvTrans;
use Doctrine\ORM\EntityManagerInterface;
use Mpdf\Mpdf;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface as TranslatorInterfaceNew;
use Twig\Environment;

class FinancialSupportExportService
{
    private $em;
    private $twig;
    private $requestStack;
    private $params;
    private $translator;
    private $logoDataCache = []; // Cache for logo data to prevent repeated database fetches

    public function __construct(
        EntityManagerInterface $em,
        Environment $twig,
        RequestStack $requestStack,
        ParameterBagInterface $params,
        TranslatorInterfaceNew $translator
    ) {
        $this->em = $em;
        $this->twig = $twig;
        $this->requestStack = $requestStack;
        $this->params = $params;
        $this->translator = $translator;
    }

    private function cleanup(): void
    {
        $exportDir = sys_get_temp_dir() . '/financial-support-export';
        if (file_exists($exportDir)) {
            try {
                error_log("Starting cleanup of export directory: " . $exportDir);
                
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($exportDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::CHILD_FIRST
                );

                $fileCount = 0;
                $dirCount = 0;
                $errorCount = 0;
                
                foreach ($files as $fileinfo) {
                    try {
                        if ($fileinfo->isDir()) {
                            rmdir($fileinfo->getRealPath());
                            $dirCount++;
                        } else {
                            unlink($fileinfo->getRealPath());
                            $fileCount++;
                        }
                    } catch (\Throwable $e) {
                        error_log("Error cleaning up file: " . $fileinfo->getRealPath() . " - " . $e->getMessage());
                        $errorCount++;
                    }
                }

                rmdir($exportDir);
                error_log("Cleanup complete: Removed {$fileCount} files, {$dirCount} directories, with {$errorCount} errors.");
            } catch (\Throwable $e) {
                error_log("Error during cleanup: " . $e->getMessage());
            }
        } else {
            error_log("Export directory does not exist, no cleanup needed: " . $exportDir);
        }
    }

    private function addFolderToZip(\ZipArchive $zip, string $folderPath, string $zipFolder): void
    {
        if (!is_dir($folderPath)) {
            error_log("addFolderToZip: Folder does not exist: {$folderPath}");
            return;
        }
        
        // Add the root directory
        if ($zipFolder !== '') {
            $zip->addEmptyDir($zipFolder);
            error_log("Added empty directory to ZIP: {$zipFolder}");
        }
        
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($folderPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        
        $addedFileCount = 0;
        $failedFileCount = 0;
        
        foreach ($files as $file) {
            $localPath = $file->getRealPath();
            $relativePath = $zipFolder !== '' ? 
                $zipFolder . '/' . substr($localPath, strlen($folderPath) + 1) : 
                substr($localPath, strlen($folderPath) + 1);
            
            if ($file->isDir()) {
                if ($zip->addEmptyDir($relativePath)) {
                    error_log("Added directory to ZIP: {$relativePath}");
                } else {
                    error_log("Failed to add directory to ZIP: {$relativePath}");
                }
            } else {
                if ($zip->addFile($localPath, $relativePath)) {
                    $addedFileCount++;
                    $fileSize = filesize($localPath);
                    error_log("Added file to ZIP: {$relativePath} ({$fileSize} bytes)");
                } else {
                    $failedFileCount++;
                    error_log("Failed to add file to ZIP: {$relativePath}");
                }
            }
        }
        
        error_log("addFolderToZip summary: Added {$addedFileCount} files, failed to add {$failedFileCount} files");
    }

    public function exportAllToZip(): string
    {
        try {
            // Reset the logo data cache for a fresh export
            $this->logoDataCache = [];
            
            // Create temp export directory if it doesn't exist
            $exportDir = sys_get_temp_dir() . '/financial-support-export';
            if (!file_exists($exportDir)) {
                if (!mkdir($exportDir, 0777, true)) {
                    error_log("Failed to create export directory: $exportDir");
                    throw new \RuntimeException("Failed to create export directory: $exportDir");
                }
                error_log("Created export directory: $exportDir");
            }
            
            // Create subdirectories for PDFs and logos
            $pdfDir = $exportDir . '/pdfs';
            $logoDir = $exportDir . '/logos';
            
            if (!file_exists($pdfDir)) {
                if (!mkdir($pdfDir, 0777, true)) {
                    error_log("Failed to create PDF directory: $pdfDir");
                    throw new \RuntimeException("Failed to create PDF directory: $pdfDir");
                }
                error_log("Created PDF directory: $pdfDir");
            }
            
            if (!file_exists($logoDir)) {
                if (!mkdir($logoDir, 0777, true)) {
                    error_log("Failed to create logo directory: $logoDir");
                    throw new \RuntimeException("Failed to create logo directory: $logoDir");
                }
                error_log("Created logo directory: $logoDir");
            }
            
            // Check if logo directory is writable
            if (!is_writable($logoDir)) {
                error_log("Logo directory is not writable: $logoDir");
                if (!chmod($logoDir, 0777)) {
                    error_log("Failed to change permissions on logo directory");
                }
            }
            
            // Load financial supports with associations
            $financialSupports = $this->em->getRepository(FinancialSupport::class)
                ->findBy(['isPublic' => true], ['position' => 'ASC']);
            
            error_log('Exporting ' . count($financialSupports) . ' financial supports');
            
            // Generate JSONs first to ensure logos are created (using our direct DB fetch if needed)
            error_log("Starting JSON generation and logo processing for DE");
            $deJson = $this->generateLocalizedJson($financialSupports, $pdfDir, $logoDir, 'de');
            if (!file_put_contents($exportDir . '/de.json', json_encode($deJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))) {
                error_log("Failed to write DE JSON file");
                throw new \RuntimeException("Failed to write DE JSON file");
            }
            $this->checkLogoDirectory($logoDir, 'After generating DE JSON');
            
            error_log("Starting JSON generation and logo processing for FR");
            $frJson = $this->generateLocalizedJson($financialSupports, $pdfDir, $logoDir, 'fr');
            if (!file_put_contents($exportDir . '/fr.json', json_encode($frJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))) {
                error_log("Failed to write FR JSON file");
                throw new \RuntimeException("Failed to write FR JSON file");
            }
            $this->checkLogoDirectory($logoDir, 'After generating FR JSON');
            
            error_log("Starting JSON generation and logo processing for IT");
            $itJson = $this->generateLocalizedJson($financialSupports, $pdfDir, $logoDir, 'it');
            if (!file_put_contents($exportDir . '/it.json', json_encode($itJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))) {
                error_log("Failed to write IT JSON file");
                throw new \RuntimeException("Failed to write IT JSON file");
            }
            $this->checkLogoDirectory($logoDir, 'After generating IT JSON');
            
            // Now generate PDFs with proper logos
            error_log("Starting PDF generation for all languages");
            foreach ($financialSupports as $financialSupport) {
                // Generate PDFs for each language
                $this->generatePdf($financialSupport, $pdfDir . '/' . $financialSupport->getId() . '.pdf', null, 'de');
                $this->generatePdf($financialSupport, $pdfDir . '/' . $financialSupport->getId() . '_fr.pdf', null, 'fr');
                $this->generatePdf($financialSupport, $pdfDir . '/' . $financialSupport->getId() . '_it.pdf', null, 'it');
            }
            error_log("Completed PDF generation for all languages");
            
            // Validate all exported logos to remove any corrupted ones
            $this->validateExportedLogos($logoDir);
            $this->checkLogoDirectory($logoDir, 'After validating exported logos');
            
            // Create ZIP file
            $zipFileName = sys_get_temp_dir() . '/financial-support-export.zip';
            if (file_exists($zipFileName)) {
                unlink($zipFileName);
            }
            
            // Log the contents of the export directory before creating the ZIP
            $this->listDirectoryContents($exportDir, 'Before creating ZIP');
            
            $zip = new \ZipArchive();
            if ($zip->open($zipFileName, \ZipArchive::CREATE) !== true) {
                throw new \RuntimeException("Could not create ZIP file: $zipFileName");
            }
            
            // Add the exported files to the ZIP
            $this->addFolderToZip($zip, $exportDir, '');
            
            error_log("Added " . $zip->numFiles . " files to ZIP archive");
            
            $zip->close();
            
            // Cleanup the temporary directory
            $this->cleanup();
            
            return $zipFileName;
        } catch (\Throwable $e) {
            // Cleanup in case of an error
            $this->cleanup();
            error_log('Error in exportAllToZip: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check the logo directory and log its contents
     */
    private function checkLogoDirectory(string $logoDir, string $context): void
    {
        if (!is_dir($logoDir)) {
            error_log("[{$context}] Logo directory does not exist: {$logoDir}");
            return;
        }
        
        $files = glob($logoDir . '/*.*');
        if ($files === false) {
            error_log("[{$context}] Error globbing logo directory: {$logoDir}");
            return;
        }
        
        $count = count($files);
        error_log("[{$context}] Logo directory has {$count} files");
        
        if ($count > 0) {
            // Group files by language
            $deFiles = $frFiles = $itFiles = $otherFiles = [];
            
            foreach ($files as $file) {
                $filename = basename($file);
                if (preg_match('/_fr\.(jpg|png|gif|svg|webp)$/i', $filename)) {
                    $frFiles[] = $file;
                } else if (preg_match('/_it\.(jpg|png|gif|svg|webp)$/i', $filename)) {
                    $itFiles[] = $file;
                } else if (preg_match('/\.(jpg|png|gif|svg|webp)$/i', $filename)) {
                    $deFiles[] = $file;
                } else {
                    $otherFiles[] = $file;
                }
            }
            
            error_log("[{$context}] Found: " . count($deFiles) . " German logos, " . 
                      count($frFiles) . " French logos, " . 
                      count($itFiles) . " Italian logos, " . 
                      count($otherFiles) . " other files");
            
            foreach ($files as $file) {
                $fileSize = filesize($file);
                $imageInfo = @getimagesize($file);
                $dimensions = $imageInfo ? "{$imageInfo[0]}x{$imageInfo[1]}" : "not a valid image";
                error_log("[{$context}] Logo file: " . basename($file) . " - {$fileSize} bytes - {$dimensions}");
            }
        }
    }

    /**
     * List contents of a directory recursively for debugging
     */
    private function listDirectoryContents(string $dir, string $context): void
    {
        if (!is_dir($dir)) {
            error_log("[{$context}] Directory does not exist: {$dir}");
            return;
        }
        
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        
        $fileCount = 0;
        $directoryCount = 0;
        $logosCount = 0;
        $pdfsCount = 0;
        $jsonCount = 0;
        
        foreach ($files as $file) {
            if ($file->isDir()) {
                error_log("[{$context}] Directory: " . $file->getPathname());
                $directoryCount++;
            } else {
                $path = $file->getPathname();
                $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
                
                if (in_array($extension, ['jpg', 'png', 'gif', 'svg', 'webp'])) {
                    $logosCount++;
                } else if ($extension === 'pdf') {
                    $pdfsCount++;
                } else if ($extension === 'json') {
                    $jsonCount++;
                }
                
                error_log("[{$context}] File: " . $path . " - " . $file->getSize() . " bytes");
                $fileCount++;
            }
        }
        
        error_log("[{$context}] Total: {$fileCount} files ({$logosCount} logos, {$pdfsCount} PDFs, {$jsonCount} JSON files), {$directoryCount} directories");
    }

    /**
     * Validate exported logo files and remove any corrupted ones
     */
    private function validateExportedLogos(string $logoDir): void
    {
        try {
            if (!is_dir($logoDir)) {
                error_log("Warning: Logo directory does not exist: " . $logoDir);
                // Try to create it if it doesn't exist
                if (!mkdir($logoDir, 0777, true)) {
                    error_log("Failed to create logo directory: " . $logoDir);
                    return;
                }
                error_log("Created logo directory: " . $logoDir);
            }

            // Check directory permissions
            if (!is_writable($logoDir)) {
                error_log("Logo directory is not writable: " . $logoDir);
                chmod($logoDir, 0777);
                if (!is_writable($logoDir)) {
                    error_log("Still cannot write to logo directory after chmod: " . $logoDir);
                    return;
                }
            }

            $logoFiles = glob($logoDir . '/*.*');
            if ($logoFiles === false) {
                error_log("Error globbing logo directory: " . $logoDir);
                return;
            }
            
            $validCount = 0;
            $removedCount = 0;
            $totalCount = count($logoFiles);
            
            error_log("Found " . $totalCount . " logo files to validate in " . $logoDir);
            
            // If no logo files were found, check if any should have been created
            if (empty($logoFiles)) {
                error_log("Warning: No logo files found in directory. This may indicate an issue with logo file creation.");
                return;
            }

            foreach ($logoFiles as $logoFile) {
                // Skip directories
                if (is_dir($logoFile)) {
                    error_log("Skipping directory in logo folder: " . $logoFile);
                    continue;
                }

                // Check if file exists and has content
                if (!file_exists($logoFile)) {
                    error_log("Logo file doesn't exist (should never happen): " . $logoFile);
                    continue;
                }
                
                $fileSize = filesize($logoFile);
                if ($fileSize === 0) {
                    error_log("Removing empty logo file (0 bytes): " . $logoFile);
                    @unlink($logoFile);
                    $removedCount++;
                    continue;
                } else if ($fileSize < 100) { // Arbitrary small size that's unlikely for a valid image
                    error_log("Warning: Very small logo file (" . $fileSize . " bytes): " . $logoFile);
                    // Don't remove very small files - they might be valid SVG or other formats
                }

                // Try to get image size to validate it's a proper image - be more lenient and only remove if obviously corrupted
                try {
                    // First check for SVG files - getimagesize doesn't work with SVG
                    if (strtolower(pathinfo($logoFile, PATHINFO_EXTENSION)) === 'svg') {
                        // For SVG, just check if the file starts with the SVG header
                        $content = file_get_contents($logoFile, false, null, 0, 100);
                        if ($content && (strpos($content, '<svg') !== false || strpos($content, '<?xml') !== false)) {
                            error_log("Validated SVG logo file: " . $logoFile . " (" . $fileSize . " bytes)");
                            $validCount++;
                            continue;
                        }
                    }
                    
                    $imageInfo = @getimagesize($logoFile);
                    if ($imageInfo === false) {
                        // Only remove if the file is definitely not an image format we know
                        $ext = strtolower(pathinfo($logoFile, PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'])) {
                            error_log("Removing invalid image file (not a valid image despite extension): " . $logoFile);
                            @unlink($logoFile);
                            $removedCount++;
                        } else {
                            // For unknown extensions, keep the file
                            error_log("Could not validate image format for: " . $logoFile . " but keeping it");
                            $validCount++;
                        }
                    } else {
                        error_log("Validated logo file: " . $logoFile . " - " . $imageInfo[0] . "x" . $imageInfo[1] . " (" . $fileSize . " bytes)");
                        $validCount++;
                    }
                } catch (\Throwable $e) {
                    error_log("Error validating logo file: " . $logoFile . " - " . $e->getMessage() . " - keeping the file");
                    // Don't remove the file if we can't validate it
                    $validCount++;
                }
            }
            
            error_log("Logo validation complete: {$validCount} valid files, {$removedCount} removed files out of {$totalCount} total");
        } catch (\Throwable $e) {
            error_log("Error in validateExportedLogos: " . $e->getMessage());
        }
    }

    private function fetchLogoData(FinancialSupport $financialSupport, string $locale = 'de'): ?array
    {
        // Check cache first
        $cacheKey = $financialSupport->getId() . '_' . $locale;
        if (isset($this->logoDataCache[$cacheKey])) {
            error_log("Using cached logo data for ID " . $financialSupport->getId() . " with locale " . $locale);
            return $this->logoDataCache[$cacheKey];
        }

        try {
            // Get the logo from the specific locale in translations if available
            $logo = null;
            
            // First try to get the logo from translations for non-German locales
            if ($locale !== 'de') {
                $translations = $financialSupport->getTranslations();
                if (isset($translations[$locale]['logo']) && !empty($translations[$locale]['logo']['id'])) {
                    $logo = $translations[$locale]['logo'];
                    error_log("Found translated logo for locale {$locale}, ID: " . $logo['id']);
                } else {
                    error_log("No logo found in translations for locale {$locale}, falling back to default");
                }
            }
            
            // If no translated logo found, fall back to default logo
            if ($logo === null) {
                $logo = $financialSupport->getLogo();
                if ($logo && isset($logo['id'])) {
                    error_log("Using default logo, ID: " . $logo['id'] . " for locale: " . $locale);
                }
            }
            
            if (!$logo || !isset($logo['id'])) {
                error_log('No logo found for financial support ID: ' . $financialSupport->getId() . ' with locale: ' . $locale);
                return null;
            }

            // Detach and refresh the entity manager to ensure we get fresh data
            $this->em->clear();
            
            // Fetch the file entity fresh from the database
            $file = $this->em->getRepository(\App\Entity\File::class)->find($logo['id']);
            if (!$file) {
                error_log('Logo file not found in database for ID: ' . $logo['id'] . ' with locale: ' . $locale);
                return null;
            }

            // Get the raw data from the blob
            $fileData = $file->getData();
            if (!$fileData) {
                error_log('File data is null for logo ID: ' . $logo['id'] . ' with locale: ' . $locale);
                return null;
            }
            
            $data = stream_get_contents($fileData);
            if ($data === false) {
                error_log('Failed to read data from file blob for logo ID: ' . $logo['id'] . ' with locale: ' . $locale);
                return null;
            }
            
            $initialDataLength = strlen($data);
            error_log('Initial logo data length for ID ' . $logo['id'] . ' with locale ' . $locale . ': ' . $initialDataLength . ' bytes');
            
            if ($initialDataLength === 0) {
                error_log('Logo data is empty for ID: ' . $logo['id'] . ' with locale: ' . $locale);
                
                // Try a different approach - fetch directly from database using a new connection
                try {
                    error_log('Attempting direct database fetch for logo ID: ' . $logo['id']);
                    
                    // Get database connection parameters from the entity manager
                    $conn = $this->em->getConnection();
                    $params = $conn->getParams();
                    
                    // Create a new PDO connection
                    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8', $params['host'], $params['dbname']);
                    $pdo = new \PDO($dsn, $params['user'], $params['password']);
                    
                    // Get the data directly from the database
                    $stmt = $pdo->prepare('SELECT data FROM file WHERE id = ?');
                    $stmt->execute([$logo['id']]);
                    $blobData = $stmt->fetchColumn();
                    
                    if ($blobData && strlen($blobData) > 0) {
                        error_log('Successfully fetched ' . strlen($blobData) . ' bytes directly from database for logo ID: ' . $logo['id']);
                        $data = $blobData;
                    } else {
                        error_log('Failed to fetch data directly from database for logo ID: ' . $logo['id']);
                        return null;
                    }
                } catch (\Throwable $dbEx) {
                    error_log('Error in direct database fetch: ' . $dbEx->getMessage());
                    return null;
                }
            }
            
            // Handle data URI format if present
            if (strpos($data, 'data:') === 0 && strpos($data, ';base64,') !== false) {
                error_log('Logo data is in data URI format, extracting base64 part');
                // Extract the base64 part and decode it
                $parts = explode(';base64,', $data);
                if (count($parts) < 2) {
                    error_log('Invalid data URI format for logo ID: ' . $logo['id'] . ' with locale: ' . $locale);
                    return null;
                }
                
                $base64Data = $parts[1];
                $binaryData = base64_decode($base64Data, true);
                if ($binaryData === false) {
                    error_log('Failed to decode base64 data for logo ID: ' . $logo['id'] . ' with locale: ' . $locale);
                    return null;
                }
                $data = $binaryData;
                error_log('Converted data URI to binary data, new length: ' . strlen($data) . ' bytes');
            }
            
            // If data is still empty after processing, return null
            if (empty($data)) {
                error_log('Logo data is empty after processing for ID: ' . $logo['id'] . ' with locale: ' . $locale);
                return null;
            }
            
            // Log the final data length
            error_log('Final logo data length for ID ' . $logo['id'] . ' with locale ' . $locale . ': ' . strlen($data) . ' bytes');
            
            // Return only if we have actual data
            $result = [
                'data' => $data,
                'name' => $logo['name'],
                'id' => $file->getId()
            ];
            
            // Cache the result
            $this->logoDataCache[$cacheKey] = $result;
            
            return $result;
        } catch (\Throwable $e) {
            // Log error for debugging
            error_log('Error processing logo data: ' . $e->getMessage() . ' for logo ID: ' . ($logo['id'] ?? 'unknown') . ' with locale: ' . $locale);
            return null;
        }
    }

    private function writeLogoToDisk(FinancialSupport $financialSupport, string $logoDir, array $logoData, string $locale = 'de'): ?string
    {
        try {
            // First, ensure we have valid data
            if (empty($logoData['data'])) {
                error_log("Cannot write logo for financial support ID: " . $financialSupport->getId() . " with locale: " . $locale . " - Logo data is empty");
                return null;
            }

            // Ensure logo directory exists
            if (!is_dir($logoDir)) {
                if (!mkdir($logoDir, 0777, true)) {
                    error_log("Failed to create logo directory: {$logoDir}");
                    return null;
                }
                error_log("Created logo directory: {$logoDir}");
            }
            
            // Make sure logoDir is writable
            if (!is_writable($logoDir)) {
                error_log("Logo directory is not writable: {$logoDir}");
                chmod($logoDir, 0777);
                if (!is_writable($logoDir)) {
                    error_log("Still cannot write to logo directory after chmod: {$logoDir}");
                    return null;
                }
            }

            // Determine the file extension
            $extension = '';
            if (!empty($logoData['name'])) {
                $extension = pathinfo($logoData['name'], PATHINFO_EXTENSION);
            }
            
            // If no extension found, try to detect from the data
            if (empty($extension)) {
                // Use finfo to detect file type from the binary data
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->buffer($logoData['data']);
                
                // Map common mime types to extensions
                $mimeToExt = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/gif' => 'gif',
                    'image/svg+xml' => 'svg',
                    'image/webp' => 'webp'
                ];
                
                $extension = $mimeToExt[$mime] ?? 'png'; // Default to png if mime type not recognized
                error_log("Determined extension {$extension} from MIME type {$mime} for logo ID: " . $logoData['id'] . " with locale: " . $locale);
            }
            
            // Add language suffix to the filename
            $filename = $financialSupport->getId();
            if ($locale !== 'de') {
                $filename .= '_' . $locale;
            }
            $filename .= '.' . $extension;
            
            $logoPath = $logoDir . '/' . $filename;
            
            // Log binary data size before writing
            $dataSize = strlen($logoData['data']);
            error_log("About to write {$dataSize} bytes of logo data for financial support ID: " . $financialSupport->getId() . " with locale: " . $locale);
            
            // Write the binary data to the export logo file
            $bytesWritten = file_put_contents($logoPath, $logoData['data']);
            if ($bytesWritten === false) {
                error_log("Failed to write logo file for financial support ID: " . $financialSupport->getId() . " with locale: " . $locale);
                return null;
            }
            
            if ($bytesWritten === 0) {
                error_log("Wrote 0 bytes to logo file for financial support ID: " . $financialSupport->getId() . " with locale: " . $locale);
                // Remove the empty file
                @unlink($logoPath);
                return null;
            }
            
            // Log success message with file size for debugging
            error_log("Successfully wrote logo for financial support ID: " . $financialSupport->getId() . 
                     ", Size: " . $bytesWritten . " bytes, Path: " . $logoPath . ", Locale: " . $locale);
                     
            // Validate the image file
            $this->validateImageFile($logoPath, "LOGO_EXPORT_" . strtoupper($locale));
            
            return $logoPath;
        } catch (\Throwable $e) {
            error_log("Error writing logo to disk: " . $e->getMessage() . " for locale: " . $locale);
            return null;
        }
    }

    private function generatePdf(FinancialSupport $financialSupport, string $outputPath, ?array $logoData = null, string $locale = 'de'): void
    {
        try {
            $mpdf = new Mpdf([
                'fontDir' => [
                    __DIR__.'/../../assets/fonts/',
                ],
                'fontdata' => [
                    'notosans' => [
                        'R' => 'NotoSans.ttf',
                        'B' => 'NotoSans.ttf',
                        'I' => 'NotoSans-Italic.ttf',
                    ]
                ],
                'margin_left' => 20,
                'margin_right' => 15,
                'margin_top' => 20,
                'margin_bottom' => 25,
                'margin_header' => 10,
                'margin_footer' => 10,
                'default_font' => 'notosans',
            ]);

            // Use the provided locale
            $mpdf->SetTitle(PvTrans::translate($financialSupport, 'name', $locale));
            $mpdf->SetDisplayMode('fullpage');
            $mpdf->shrink_tables_to_fit = 1;

            // If no logo data provided, fetch it with the correct locale
            if (!$logoData) {
                $logoData = $this->fetchLogoData($financialSupport, $locale);
            }

            $tempLogoPath = null;
            if ($logoData && !empty($logoData['data'])) {
                // Create a unique temp file with proper file extension based on the logo name
                $extension = pathinfo($logoData['name'], PATHINFO_EXTENSION);
                if (empty($extension)) {
                    $finfo = new \finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->buffer($logoData['data']);
                    $mimeToExt = [
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/gif' => 'gif',
                        'image/svg+xml' => 'svg',
                        'image/webp' => 'webp'
                    ];
                    $extension = $mimeToExt[$mime] ?? 'png';
                }
                
                $tempDir = sys_get_temp_dir();
                $tempLogoPath = tempnam($tempDir, 'logo_' . $logoData['id'] . '_');
                
                // Rename the temp file to have the proper extension
                $tempLogoPathWithExt = $tempLogoPath . '.' . $extension;
                rename($tempLogoPath, $tempLogoPathWithExt);
                $tempLogoPath = $tempLogoPathWithExt;
                
                $bytesWritten = file_put_contents($tempLogoPath, $logoData['data']);
                if ($bytesWritten === false) {
                    error_log("Failed to write temporary logo file for PDF generation, financial support ID: " . $financialSupport->getId() . ", locale: " . $locale);
                    $tempLogoPath = null;
                } else {
                    error_log("Successfully wrote temporary logo for PDF, Size: " . $bytesWritten . " bytes, Path: " . $tempLogoPath . ", locale: " . $locale);
                    
                    // Validate the logo file
                    $this->validateImageFile($tempLogoPath, "PDF_LOGO_" . strtoupper($locale));
                    
                    // If we can't get image dimensions, it's not a valid image - don't use it
                    if (!getimagesize($tempLogoPath)) {
                        error_log("Logo is not a valid image for PDF generation, will not use it. Locale: " . $locale);
                        unlink($tempLogoPath);
                        $tempLogoPath = null;
                    }
                }
            }

            // Create a proper request with the locale
            $request = new \Symfony\Component\HttpFoundation\Request();
            $request->setLocale($locale);
            
            // Set the request in the stack to make app.request.locale work
            $currentRequest = $this->requestStack->getCurrentRequest();
            $this->requestStack->push($request);
            
            // Create translations for the template
            $translations = [];
            $transFiles = ['Kurzbeschrieb', 'Teilnahmekriterien', 'Ausschlusskriterien', 'Finanzierung', 'Beantragung', 
                           'Tipps zur Beantragung', 'Kontakt', 'Mehr Informationen', 'Termine', 'Laufzeit', 'Thema', 'Zuteilung',
                           'Start', 'Ende', 'Zuteilung', 'Förderstelle', 'Unterstützungsform', 
                           'Begünstigte', 'Themenschwerpunkt', 'Innovationsphasen', 'Fördergebiet'];
            
            foreach ($transFiles as $key) {
                $translations[$key] = $this->translator->trans($key, [], null, $locale);
            }
            
            error_log("Generating PDF with locale: $locale - Sample translations: Kurzbeschrieb -> " . $translations['Kurzbeschrieb']);

            // Format assignment value for display in the PDF
            // Apply formatting for all assignment values, not just 'beides'
            if ($financialSupport->getAssignment()) {
                $clonedFinancialSupport = clone $financialSupport;
                $formattedAssignment = $this->formatAssignmentForDisplay($financialSupport->getAssignment(), $locale);
                $clonedFinancialSupport->setAssignment($formattedAssignment);
                $financialSupport = $clonedFinancialSupport;
            }

            // Pass everything to the template
            $html = $this->twig->render('pdf/financial-support.html.twig', [
                'financialSupport' => $financialSupport,
                'logo' => $tempLogoPath,
                'app' => ['request' => $request],
                'locale' => $locale,
                'translations' => $translations
            ]);
            
            // Restore the original request
            if ($currentRequest) {
                $this->requestStack->pop();
                $this->requestStack->push($currentRequest);
            }
            
            $mpdf->WriteHTML($html);
            $mpdf->Output($outputPath, \Mpdf\Output\Destination::FILE);

            if (!file_exists($outputPath)) {
                throw new \RuntimeException('PDF file was not created at expected path: ' . $outputPath . ' for locale: ' . $locale);
            }
            
            error_log("Successfully generated PDF at: " . $outputPath . " for locale: " . $locale);

            // Clean up temporary logo file if it was created
            if ($tempLogoPath && file_exists($tempLogoPath)) {
                unlink($tempLogoPath);
                error_log("Deleted temporary logo file: " . $tempLogoPath);
            }
        } catch (\Throwable $e) {
            // Clean up temporary logo file if it exists
            if (isset($tempLogoPath) && file_exists($tempLogoPath)) {
                unlink($tempLogoPath);
            }
            error_log("Error generating PDF for locale " . $locale . ": " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Format the assignment value for display in the PDF
     * 
     * @param string|null $assignment The assignment value
     * @param string $locale The current locale
     * @return string The formatted assignment value
     */
    public function formatAssignmentForDisplay(?string $assignment, string $locale): string
    {
        if (!$assignment) {
            return '';
        }
        
        if ($assignment === 'beides') {
            if ($locale === 'de') {
                return 'Finanzielle und Nicht-Finanzielle';
            } elseif ($locale === 'fr') {
                return 'Financière et Non-Financière';
            } elseif ($locale === 'it') {
                return 'Finanziario e Non-Finanziario';
            }
        } else if ($assignment === 'Finanzielle' || $assignment === 'Finanziell') {
            // For German, return the original value without modification
            if ($locale === 'de') {
                return 'Finanzielle';
            } else if ($locale === 'fr') {
                return 'Financière';
            } elseif ($locale === 'it') {
                return 'Finanziario';
            }
        } else if ($assignment === 'Nicht-Finanzielle' || $assignment === 'Nicht-Finanziell') {
            // For German, return the original value without modification
            if ($locale === 'de') {
                return 'Nicht-Finanzielle';
            } else if ($locale === 'fr') {
                return 'Non-Financière';
            } elseif ($locale === 'it') {
                return 'Non-Finanziario';
            }
        }
        
        return $assignment;
    }

    private function generateLocalizedJson(array $financialSupports, string $pdfDir, string $logoDir, string $locale): array
    {
        $localizedJson = [];

        foreach ($financialSupports as $financialSupport) {
            try {
                // First fetch the logo data if it exists
                $logoData = $this->fetchLogoData($financialSupport, $locale);
                
                // Handle logo first
                $logoPath = null;
                if ($logoData && !empty($logoData['data'])) {
                    $logoPath = $this->writeLogoToDisk($financialSupport, $logoDir, $logoData, $locale);
                    if ($logoPath) {
                        error_log("Logo written successfully to: " . $logoPath . " for ID " . $financialSupport->getId() . " with locale " . $locale);
                    } else {
                        error_log("Failed to write logo to disk for ID " . $financialSupport->getId() . " with locale " . $locale);
                    }
                } else {
                    error_log("No logo data available for financial support ID: " . $financialSupport->getId() . " with locale: " . $locale);
                }
                
                // Determine if logo file actually exists on disk regardless of what writeLogoToDisk returned
                $expectedLogoFilename = $financialSupport->getId();
                if ($locale !== 'de') {
                    $expectedLogoFilename .= '_' . $locale;
                }
                
                // Check for common image extensions
                $foundLogo = false;
                foreach (['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'] as $ext) {
                    $testPath = $logoDir . '/' . $expectedLogoFilename . '.' . $ext;
                    if (file_exists($testPath) && filesize($testPath) > 0) {
                        $logoPath = $testPath;
                        $foundLogo = true;
                        error_log("Found existing logo file: " . $testPath . " for ID " . $financialSupport->getId() . " with locale " . $locale);
                        break;
                    }
                }
                
                if (!$foundLogo) {
                    error_log("No logo file found on disk for ID " . $financialSupport->getId() . " with locale " . $locale);
                }
                
                // Generate PDF with ID as filename if it doesn't exist already
                $pdfFilename = $financialSupport->getId();
                if ($locale !== 'de') {
                    $pdfFilename .= '_' . $locale;
                }
                $pdfFilename .= '.pdf';
                
                $pdfPath = $pdfDir . '/' . $pdfFilename;
                if (!file_exists($pdfPath)) {
                    $this->generatePdf($financialSupport, $pdfPath, $logoData, $locale);
                }

                // Convert arrays to <br> separated strings
                $authorities = array_map(
                    fn($authority) => PvTrans::translate($authority, 'name', $locale),
                    $financialSupport->getAuthorities() ? $financialSupport->getAuthorities()->toArray() : []
                );
                $instruments = array_map(
                    fn($instrument) => PvTrans::translate($instrument, 'name', $locale),
                    $financialSupport->getInstruments() ? $financialSupport->getInstruments()->toArray() : []
                );
                $beneficiaries = array_map(
                    fn($beneficiary) => PvTrans::translate($beneficiary, 'name', $locale),
                    $financialSupport->getBeneficiaries() ? $financialSupport->getBeneficiaries()->toArray() : []
                );

                // Handle otherNames for Weitere options
                $otherOptionValues = isset($financialSupport->getTranslations()[$locale]['otherOptionValues']) ? 
                    $financialSupport->getTranslations()[$locale]['otherOptionValues'] : 
                    ($locale === 'de' ? $financialSupport->getOtherOptionValues() : null);

                // "Weitere" might need to be translated for fr/it
                $weitereText = $locale === 'de' ? 'Weitere' : 
                               ($locale === 'fr' ? 'Autres' : 'Altri');

                if ($otherOptionValues) {
                    // Add otherOptionValues.authority if Weitere is in authorities
                    if (in_array($weitereText, $authorities) && !empty($otherOptionValues['authority'])) {
                        $authorities[] = $otherOptionValues['authority'];
                    }
                    // Add otherOptionValues.instrument if Weitere is in instruments
                    if (in_array($weitereText, $instruments) && !empty($otherOptionValues['instrument'])) {
                        $instruments[] = $otherOptionValues['instrument'];
                    }
                    // Add otherOptionValues.beneficiary if Weitere is in beneficiaries
                    if (in_array($weitereText, $beneficiaries) && !empty($otherOptionValues['beneficiary'])) {
                        $beneficiaries[] = $otherOptionValues['beneficiary'];
                    }
                }

                $topics = array_map(
                    fn($topic) => PvTrans::translate($topic, 'name', $locale),
                    $financialSupport->getTopics() ? $financialSupport->getTopics()->toArray() : []
                );
                $projectTypes = array_map(
                    fn($projectType) => PvTrans::translate($projectType, 'name', $locale),
                    $financialSupport->getProjectTypes() ? $financialSupport->getProjectTypes()->toArray() : []
                );
                $geographicRegions = array_map(
                    fn($region) => PvTrans::translate($region, 'name', $locale),
                    $financialSupport->getGeographicRegions() ? $financialSupport->getGeographicRegions()->toArray() : []
                );

                // Format appointments
                $appointments = [];
                $appointmentTexts = [];
                $appointmentsList = isset($financialSupport->getTranslations()[$locale]['appointments']) ? 
                    $financialSupport->getTranslations()[$locale]['appointments'] : 
                    ($locale === 'de' ? $financialSupport->getAppointments() : []);
                
                foreach ($appointmentsList ?? [] as $appointment) {
                    if (isset($appointment['date'])) {
                        $date = new \DateTime($appointment['date']);
                        $appointments[] = $date->format('Y-m-d H:i:s');
                        if (isset($appointment['description'])) {
                            $appointmentTexts[] = $date->format('d.m.Y') . ': ' . strip_tags($appointment['description']);
                        }
                    }
                }

                // Format links for mehrinfos
                $mehrinfos = [];
                $linksList = isset($financialSupport->getTranslations()[$locale]['links']) ? 
                    $financialSupport->getTranslations()[$locale]['links'] : 
                    ($locale === 'de' ? $financialSupport->getLinks() : []);
                
                foreach ($linksList ?? [] as $link) {
                    if (!empty($link['value']) && !empty($link['label'])) {
                        $mehrinfos[] = sprintf(
                            '<a href="%s" target="_blank" class="contLinks" title="%s">%s</a>',
                            stristr($link['value'], '://') ? $link['value'] : 'https://'.$link['value'],
                            htmlspecialchars($link['label']),
                            htmlspecialchars($link['label'])
                        );
                    }
                }

                // Convert HTML lists to bullet points with • symbol
                $convertHtmlListToBullets = function($html) {
                    if (!$html) return '';
                    $text = $html;
                    return $text;
                };

                // Handle assignment translation
                $assignment = $financialSupport->getAssignment();
                
                if ($assignment === 'beides') {
                    if ($locale === 'de') {
                        $assignment = 'Finanzielle<br>Nicht-Finanzielle';
                    } elseif ($locale === 'fr') {
                        $assignment = 'Financière<br>Non-Financière';
                    } elseif ($locale === 'it') {
                        $assignment = 'Finanziario<br>Non-Finanziario';
                    }
                } else if ($assignment === 'Finanzielle' || $assignment === 'Finanziell') {
                    if ($locale === 'de') {
                        $assignment = 'Finanzielle';
                    } elseif ($locale === 'fr') {
                        $assignment = 'Financière';
                    } elseif ($locale === 'it') {
                        $assignment = 'Finanziario';
                    }
                } else if ($assignment === 'Nicht-Finanzielle' || $assignment === 'Nicht-Finanziell') {
                    if ($locale === 'de') {
                        $assignment = 'Nicht-Finanzielle';
                    } elseif ($locale === 'fr') {
                        $assignment = 'Non-Financière';
                    } elseif ($locale === 'it') {
                        $assignment = 'Non-Finanziario';
                    }
                }

                // Construct the logo path only if we have a valid logo
                $logoFileName = null;
                if ($logoPath) {
                    $baseName = basename($logoPath);
                    // Use "logos" as the directory name in the JSON
                    $logoFileName = 'logos/' . $baseName;
                    error_log("Setting logo path in JSON for ID " . $financialSupport->getId() . " with locale " . $locale . ": " . $logoFileName);
                }

                // Construct the PDF path with proper language suffix
                $pdfFileName = 'pdfs/' . $pdfFilename;

                $localizedJson[] = [
                    'id' => $financialSupport->getId(),
                    'titel' => PvTrans::translate($financialSupport, 'name', $locale) ?? '',
                    'logo' => $logoFileName,
                    'pdf' => $pdfFileName,
                    'foerderstelle' => PvTrans::translate($financialSupport, 'fundingProvider', $locale) ?? '',
                    'unterstuetzungsform' => implode('<br>', $instruments),
                    'beguenstigte' => implode('<br>', $beneficiaries),
                    'lead' => PvTrans::translate($financialSupport, 'description', $locale) ?? '',
                    'kurzbeschrieb' => $convertHtmlListToBullets(PvTrans::translate($financialSupport, 'additionalInformation', $locale)),
                    'teilkrit' => $convertHtmlListToBullets(PvTrans::translate($financialSupport, 'inclusionCriteria', $locale)),
                    'auskrit' => $convertHtmlListToBullets(PvTrans::translate($financialSupport, 'exclusionCriteria', $locale)),
                    'beantragung' => $convertHtmlListToBullets(PvTrans::translate($financialSupport, 'application', $locale)),
                    'tippsbeantragung' => PvTrans::translate($financialSupport, 'applicationTips', $locale) ?? '',
                    'themenschwerpunkt' => implode('<br>', $topics),
                    'innovationsphasen' => implode('<br>', $projectTypes),
                    'finanzierung' => $convertHtmlListToBullets(PvTrans::translate($financialSupport, 'financingRatio', $locale)),
                    'foerdergebiet' => implode('<br>', $geographicRegions),
                    'kontakt' => $this->formatContacts($this->getTranslatedContacts($financialSupport, $locale)),
                    'laufzeitstart' => $financialSupport->getStartDate() ? $financialSupport->getStartDate()->format('d.m.Y') : '',
                    'laufzeitende' => $financialSupport->getEndDate() ? $financialSupport->getEndDate()->format('d.m.Y') : '',
                    'termine' => implode('<br>', $appointments),
                    'terminetxt' => implode('<br>', $appointmentTexts),
                    'mehrinfos' => implode('<br>', $mehrinfos),
                    'zuteilung' => $assignment
                ];

            } catch (\Throwable $e) {
                error_log("Error generating JSON for financial support ID: " . $financialSupport->getId() . " with locale: " . $locale . " - " . $e->getMessage());
                throw $e;
            }
        }

        return ['angebote' => $localizedJson];
    }

    /**
     * Get translated contacts for a financial support in the specified locale
     */
    private function getTranslatedContacts(FinancialSupport $financialSupport, string $locale): array
    {
        $contacts = $locale === 'de' ? 
            $financialSupport->getContacts() : 
            (isset($financialSupport->getTranslations()[$locale]['contacts']) ? 
                $financialSupport->getTranslations()[$locale]['contacts'] : []);

        return $contacts ?? [];
    }

    private function formatContacts(array $contacts): string
    {
        if (empty($contacts)) {
            return '';
        }

        $formattedContacts = [];
        foreach ($contacts as $contact) {
            $parts = [];
            
            if (!empty($contact['name'])) {
                $parts[] = sprintf('<b>%s</b>', htmlspecialchars($contact['name']));
            }

            $addressParts = [];
            if (!empty($contact['firstName']) && !empty($contact['lastName'])) {
                $addressParts[] = htmlspecialchars($contact['firstName'] . ' ' . $contact['lastName']);
            }
            if (!empty($contact['role'])) {
                $addressParts[] = htmlspecialchars($contact['role']);
            }
            if (!empty($contact['street'])) {
                $addressParts[] = htmlspecialchars($contact['street']);
            }
            if (!empty($contact['zipCode']) || !empty($contact['city'])) {
                $addressParts[] = htmlspecialchars(trim($contact['zipCode'] . ' ' . $contact['city']));
            }
            
            if (!empty($addressParts)) {
                $parts[] = implode('<br>', $addressParts);
            }

            $contactParts = [];
            if (!empty($contact['email'])) {
                $contactParts[] = htmlspecialchars($contact['email']);
            }
            if (!empty($contact['phone'])) {
                $contactParts[] = htmlspecialchars($contact['phone']);
            }
            if (!empty($contact['web'])) {
                $contactParts[] = htmlspecialchars($contact['web']);
            }
            
            if (!empty($contactParts)) {
                $parts[] = implode('<br>', $contactParts);
            }

            if (!empty($parts)) {
                $formattedContacts[] = implode('<br><br>', $parts);
            }
        }

        return implode('<br><br>', $formattedContacts);
    }

    /**
     * Debug helper to validate an image file
     */
    private function validateImageFile(string $path, string $context): void 
    {
        try {
            if (!file_exists($path)) {
                error_log("[$context] Image file doesn't exist: $path");
                return;
            }
            
            $fileSize = filesize($path);
            if ($fileSize === 0) {
                error_log("[$context] Image file is empty (0 bytes): $path");
                return;
            }
            
            error_log("[$context] Image file exists with size: $fileSize bytes at path: $path");
            
            // Try to get image dimensions to validate it's a proper image
            $imageInfo = getimagesize($path);
            if ($imageInfo === false) {
                error_log("[$context] Not a valid image file: $path");
            } else {
                error_log("[$context] Valid image: {$imageInfo[0]}x{$imageInfo[1]} of type {$imageInfo['mime']}");
            }
        } catch (\Throwable $e) {
            error_log("[$context] Error validating image: " . $e->getMessage());
        }
    }
} 