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
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Twig\Environment;

class FinancialSupportExportService
{
    private $em;
    private $twig;
    private $requestStack;
    private $params;
    private $translator;
    private $cache;

    private $logoDataCache = []; // Cache for logo data to prevent repeated database fetches

    public function __construct(
        EntityManagerInterface $em,
        Environment $twig,
        RequestStack $requestStack,
        ParameterBagInterface $params,
        TranslatorInterfaceNew $translator,
        CacheInterface $cache
    ) {
        $this->em = $em;
        $this->twig = $twig;
        $this->requestStack = $requestStack;
        $this->params = $params;
        $this->translator = $translator;
        $this->cache = $cache;
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
            
            // Generate JSON files in data folder with correct naming
            error_log("Starting JSON generation in data folder");
            $dataDir = $exportDir . '/data';
            mkdir($dataDir, 0777, true);
            
            $this->generateJsonFiles($dataDir);
            
            error_log("Generated JSON files in data folder");
            
            // Still process logos for the existing logo directory structure
            error_log("Processing logos for backward compatibility");
            $deJson = $this->generateLocalizedJson($financialSupports, $pdfDir, $logoDir, 'de');
            $frJson = $this->generateLocalizedJson($financialSupports, $pdfDir, $logoDir, 'fr');
            $itJson = $this->generateLocalizedJson($financialSupports, $pdfDir, $logoDir, 'it');
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
     * Generate files for direct FTP upload (without ZIP)
     */
    public function generateFilesForFtp(): array
    {
        try {
            $tempDir = sys_get_temp_dir() . '/financial-support-ftp-' . uniqid();
            mkdir($tempDir, 0777, true);
            
            // Create folder structure (same as ZIP export)
            $folders = [
                'data',
                'logos',
                'pdfs'
            ];
            
            foreach ($folders as $folder) {
                mkdir($tempDir . '/' . $folder, 0777, true);
            }
            
            // First generate JSON files for both ZIP export and FTP upload
            $this->generateJsonFiles($tempDir . '/data');
            
            // Generate and save logos
            $this->generateLogos($tempDir . '/logos');
            
            // Generate PDFs
            $this->generatePdfs($tempDir . '/pdfs');
            
            return [
                'base_path' => $tempDir,
                'files' => $this->getFileManifest($tempDir)
            ];
            
        } catch (\Throwable $e) {
            error_log('Error in generateFilesForFtp: ' . $e->getMessage());
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
        // request-run in-memory cache
        $memKey = $financialSupport->getId() . '_' . $locale;
        if (isset($this->logoDataCache[$memKey])) {
            error_log("Using cached logo data for ID {$financialSupport->getId()} with locale {$locale}");
            return $this->logoDataCache[$memKey];
        }

        try {
            // resolve $logo (translations → fallback)
            $logo = null;
            if ($locale !== 'de') {
                $translations = $financialSupport->getTranslations();
                if (!empty($translations[$locale]['logo']['id'])) {
                    $logo = $translations[$locale]['logo'];
                    error_log("Found translated logo for locale {$locale}, ID: {$logo['id']}");
                } else {
                    error_log("No translated logo for {$locale}, falling back to default");
                }
            }
            if ($logo === null) {
                $logo = $financialSupport->getLogo();
                if (!empty($logo['id'])) {
                    error_log("Using default logo, ID: {$logo['id']} for locale: {$locale}");
                }
            }
            if (empty($logo['id'])) {
                error_log("No logo for financial support ID: {$financialSupport->getId()} / {$locale}");
                return null;
            }

            $file = $this->em->getRepository(\App\Entity\File::class)->find($logo['id']);
            $updated = method_exists($file, 'getUpdatedAt') && $file->getUpdatedAt() ? $file->getUpdatedAt()->format('YmdHis') : 'na';
            $symfonyKey = sprintf('logo-data-%d-%s-%s', $logo['id'], $locale, $updated);

            $result = $this->cache->get($symfonyKey, function (ItemInterface $item) use ($logo, $locale) {
                $item->expiresAfter(3600 * 24 * 31 * 6); // ~6 months

                $file = $this->em->getRepository(\App\Entity\File::class)->find($logo['id']);
                if (!$file) {
                    error_log("Logo file not found (ID {$logo['id']}) / {$locale}");
                    return null;
                }

                $data = $file->getData();

                if (is_resource($data)) {
                    @rewind($data);
                    $data = stream_get_contents($data);
                }

                if (!is_string($data) || $data === '') {
                    error_log("Empty/invalid blob for logo ID {$logo['id']} / {$locale}");
                    return null;
                }

                if (strncmp($data, 'data:', 5) === 0) {
                    $parts = explode(';base64,', $data, 2);
                    if (count($parts) === 2) {
                        $decoded = base64_decode($parts[1], true);
                        if ($decoded !== false) {
                            $data = $decoded;
                        }
                    }
                }

                $name = $logo['name'] ?? (method_exists($file, 'getName') ? $file->getName() : ('logo_' . $file->getId()));

                return [
                    'data' => $data,
                    'name' => $name,
                    'id'   => $file->getId(),
                ];
            });

            if ($result) {
                $this->logoDataCache[$memKey] = $result;
            }

            return $result;
        } catch (\Throwable $e) {
            error_log('Error processing logo data: ' . $e->getMessage() . ' for logo ID: ' . ($logo['id'] ?? 'unknown') . " / {$locale}");
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
            $basename = (string) $logoData['id'];
            if ($locale !== 'de') {
                $basename .= '_' . $locale;
            }
            $filename = $basename . '.' . $extension;
            
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

            $lastChange = ($financialSupport->getUpdatedAt() ?: $financialSupport->getCreatedAt())
                ->format('Y-m-d-H-i-s');

            $cacheKey = sprintf(
                'financial-support-%s-%s-%s.pdf',
                $financialSupport->getId(),
                $locale,
                $lastChange
            );

            $pdfBytes = $this->cache->get($cacheKey, function (ItemInterface $item) use ($financialSupport, $logoData, $locale) {

                $item->expiresAfter(3600 * 24 * 31 * 6);

                $mpdf = new Mpdf([
                    'fontDir' => [__DIR__ . '/../../assets/fonts/'],
                    'fontdata' => [
                        'notosans' => [
                            'R' => 'NotoSans.ttf',
                            'B' => 'NotoSans.ttf',
                            'I' => 'NotoSans-Italic.ttf',
                        ],
                    ],
                    'margin_left'   => 20,
                    'margin_right'  => 15,
                    'margin_top'    => 20,
                    'margin_bottom' => 25,
                    'margin_header' => 10,
                    'margin_footer' => 10,
                    'default_font'  => 'notosans',
                ]);

                $mpdf->SetTitle(PvTrans::translate($financialSupport, 'name', $locale));
                $mpdf->SetDisplayMode('fullpage');
                $mpdf->shrink_tables_to_fit = 1;

                // If no logo data provided, fetch it with the correct locale
                if (!$logoData) {
                    $logoData = $this->fetchLogoData($financialSupport, $locale);
                }

                $tempLogoPath = null;
                if ($logoData && !empty($logoData['data'])) {
                    $extension = pathinfo($logoData['name'] ?? '', PATHINFO_EXTENSION);
                    if (empty($extension)) {
                        $finfo = new \finfo(FILEINFO_MIME_TYPE);
                        $mime = $finfo->buffer($logoData['data']);
                        $mimeToExt = [
                            'image/jpeg' => 'jpg',
                            'image/png' => 'png',
                            'image/gif' => 'gif',
                            'image/svg+xml' => 'svg',
                            'image/webp' => 'webp',
                        ];
                        $extension = $mimeToExt[$mime] ?? 'png';
                    }

                    $tempDir = sys_get_temp_dir();
                    $tempLogoPath = tempnam($tempDir, 'logo_' . ($logoData['id'] ?? 'x') . '_');
                    $tempLogoPathWithExt = $tempLogoPath . '.' . $extension;
                    @rename($tempLogoPath, $tempLogoPathWithExt);
                    $tempLogoPath = $tempLogoPathWithExt;

                    $bytesWritten = @file_put_contents($tempLogoPath, $logoData['data']);
                    if ($bytesWritten === false) {
                        error_log("Failed to write temporary logo file for PDF generation, financial support ID: " . $financialSupport->getId() . ", locale: " . $locale);
                        $tempLogoPath = null;
                    } else {
                        error_log("Successfully wrote temporary logo for PDF, Size: " . $bytesWritten . " bytes, Path: " . $tempLogoPath . ", locale: " . $locale);

                        $this->validateImageFile($tempLogoPath, "PDF_LOGO_" . strtoupper($locale));

                        if ($extension === 'svg') {
                            $convertedPath = $this->convertSvgToPng($tempLogoPath, $logoData['id'] ?? 'x', $locale);
                            if ($convertedPath) {
                                @unlink($tempLogoPath);
                                $tempLogoPath = $convertedPath;
                                error_log("Successfully converted SVG to PNG for PDF generation: " . $tempLogoPath);
                            } else {
                                error_log("Failed to convert SVG to PNG for PDF generation, will not use logo. Locale: " . $locale);
                                @unlink($tempLogoPath);
                                $tempLogoPath = null;
                            }
                        } else {
                            if (!@getimagesize($tempLogoPath)) {
                                error_log("Logo is not a valid image for PDF generation, will not use it. Locale: " . $locale);
                                @unlink($tempLogoPath);
                                $tempLogoPath = null;
                            }
                        }
                    }
                }

                // Request/locale scaffolding
                $request = new \Symfony\Component\HttpFoundation\Request();
                $request->setLocale($locale);

                $currentRequest = $this->requestStack->getCurrentRequest();
                $this->requestStack->push($request);

                // Translations for template
                $translations = [];
                $transFiles = [
                    'Kurzbeschrieb', 'Teilnahmekriterien', 'Ausschlusskriterien', 'Finanzierung', 'Beantragung',
                    'Tipps zur Beantragung', 'Kontakt', 'Mehr Informationen', 'Termine', 'Laufzeit', 'Thema', 'Zuteilung',
                    'Start', 'Ende', 'Zuteilung', 'Förderstelle', 'Unterstützungsform',
                    'Begünstigte', 'Themenschwerpunkt', 'Innovationsphasen', 'Fördergebiet'
                ];

                foreach ($transFiles as $key) {
                    $translations[$key] = $this->translator->trans($key, [], null, $locale);
                }

                // Format assignment for display
                $fsForTemplate = $financialSupport;
                if ($financialSupport->getAssignment()) {
                    $cloned = clone $financialSupport;
                    $cloned->setAssignment($this->formatAssignmentForDisplay($financialSupport->getAssignment(), $locale));
                    $fsForTemplate = $cloned;
                }

                // Render HTML
                $html = $this->twig->render('pdf/financial-support.html.twig', [
                    'financialSupport' => $fsForTemplate,
                    'logo'             => $tempLogoPath,
                    'app'              => ['request' => $request],
                    'locale'           => $locale,
                    'translations'     => $translations,
                ]);

                // Restore original request
                if ($currentRequest) {
                    $this->requestStack->pop();
                    $this->requestStack->push($currentRequest);
                }

                // Generate *binary* PDF and clean up temp logo
                $mpdf->WriteHTML($html);
                $bytes = $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);

                if ($tempLogoPath && file_exists($tempLogoPath)) {
                    @unlink($tempLogoPath);
                    error_log("Deleted temporary logo file: " . $tempLogoPath);
                }

                if (!is_string($bytes) || $bytes === '') {
                    throw new \RuntimeException('Failed to produce PDF bytes for locale: ' . $locale);
                }

                return $bytes;
                // ---------- END: generation logic ----------
            });

            // Write the cached bytes to the requested file path
            if (@file_put_contents($outputPath, $pdfBytes) === false) {
                throw new \RuntimeException('Failed to write PDF to path: ' . $outputPath . ' for locale: ' . $locale);
            }

            if (!file_exists($outputPath)) {
                throw new \RuntimeException('PDF file was not created at expected path: ' . $outputPath . ' for locale: ' . $locale);
            }

            error_log("Successfully generated (or served from cache) PDF at: " . $outputPath . " for locale: " . $locale);
        } catch (\Throwable $e) {
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

                // Determine expected filename using FILE id to match writeLogoToDisk()/getLogoFilename()
                $expectedLogoFilename = isset($logoData['id']) ? (string) $logoData['id'] : (string) $financialSupport->getId();
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
                            '<a href="%s" target="_blank" class="contLinks liZusinf" title="%s" target="_blank">%s</a>',
                            'https://'.$this->normalizeUrl($link['value']),
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

            if (!empty($contact['department'])) {
                $parts[] = sprintf('<b>%s</b>', htmlspecialchars($contact['department']));
            }

            $addressParts = [];
            
            // Handle person vs institution display
            $contactType = $contact['type'] ?? 'person';
            
            // Only show person-specific fields for persons
            if ($contactType === 'person' && (!empty($contact['firstName']) || !empty($contact['lastName']))) {
                $nameParts = [];
                if (!empty($contact['salutation'])) {
                    $nameParts[] = $contact['salutation'] === 'm' ? 'Herr' : 'Frau';
                }
                if (!empty($contact['title'])) {
                    $nameParts[] = $contact['title'];
                }
                if (!empty($contact['firstName'])) {
                    $nameParts[] = $contact['firstName'];
                }
                if (!empty($contact['lastName'])) {
                    $nameParts[] = $contact['lastName'];
                }
                if (!empty($nameParts)) {
                    $addressParts[] = htmlspecialchars(implode(' ', $nameParts));
                }
            }
            
            // Role and department
            $roleAndDept = [];
            if (!empty($contact['role'])) {
                $roleAndDept[] = $contact['role'];
            }
            if (!empty($roleAndDept)) {
                $addressParts[] = htmlspecialchars(implode(', ', $roleAndDept));
            }
            
            // Address
            if (!empty($contact['street'])) {
                $addressParts[] = htmlspecialchars($contact['street']);
            }
            if (!empty($contact['zipCode']) || !empty($contact['city'])) {
                $addressParts[] = htmlspecialchars(trim($contact['zipCode'] . ' ' . $contact['city']));
            }
            if (!empty($contact['addressSupplement'])) {
                $addressParts[] = htmlspecialchars($contact['addressSupplement']);
            }
            
            if (!empty($addressParts)) {
                $parts[] = implode('<br>', $addressParts);
            }

            $contactParts = [];
            if (!empty($contact['email'])) {
                $contactParts[] = '<a href="mailto:'.$contact['email'].'">'.htmlspecialchars($contact['email']).'</a>';
            }
            if (!empty($contact['phone'])) {
                $contactParts[] = htmlspecialchars($contact['phone']);
            }
            if (!empty($contact['website'])) {
                $url = $this->normalizeUrl($contact['website']);
                $contactParts[] = '<a href="https://'.$url.'" target="_blank">'.htmlspecialchars($url).'</a>';
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

    private function normalizeUrl(string $url): string
    {
        return stristr($url, '://') ? explode('://', $url, 2)[1] : $url;
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

    /**
     * Convert SVG to PNG for PDF generation with improved gradient support
     */
    private function convertSvgToPng(string $svgPath, int $logoId, string $locale): ?string
    {
        try {
            if (!class_exists('Imagick')) {
                error_log("Imagick not available for SVG conversion, skipping logo conversion");
                return null;
            }

            // Build a deterministic cache key for this specific SVG content + locale
            $cacheKey = sprintf('financial-support-logo-svg2png-%d-%s.png', $logoId, $locale);

            // Cache the *PNG bytes*. On a hit we just write them to a temp file and return the path.
            $pngBytes = $this->cache->get($cacheKey, function (ItemInterface $item) use ($svgPath, $logoId, $locale) {
                // Reasonable TTL; adjust to your rotation cycle
                $item->expiresAfter(3600 * 24 * 31 * 6); // ~6 months

                $imagick = new \Imagick();
                $imagick->setResolution(600, 600);
                $imagick->setBackgroundColor(new \ImagickPixel('transparent'));
                $imagick->readImage($svgPath);
                $imagick->setImageFormat('png32');
                $imagick->setImageColorspace(\Imagick::COLORSPACE_SRGB);
                $imagick->setImageDepth(8);
                $imagick->setImageInterpolateMethod(\Imagick::INTERPOLATE_BICUBIC);

                // Composite on white (preserve gradients + predictable background)
                $background = new \Imagick();
                $background->newImage($imagick->getImageWidth(), $imagick->getImageHeight(), new \ImagickPixel('white'));
                $background->setImageFormat('png32');
                $background->compositeImage($imagick, \Imagick::COMPOSITE_OVER, 0, 0);

                // Return *bytes* to cache (no temp file lives in the cache)
                $bytes = $background->getImageBlob();

                $imagick->clear();      $imagick->destroy();
                $background->clear();   $background->destroy();

                if (!is_string($bytes) || $bytes === '') {
                    throw new \RuntimeException('PNG conversion produced empty data');
                }

                error_log("Converted SVG to PNG for caching (logoId=$logoId, locale=$locale, bytes=".strlen($bytes).")");
                return $bytes;
            });

            // Materialize cached bytes to a temp file path for the caller
            $temp = tempnam(sys_get_temp_dir(), 'logo_' . $logoId . '_converted_');
            $pngPath = $temp . '.png';
            @rename($temp, $pngPath);

            if (@file_put_contents($pngPath, $pngBytes) === false || !file_exists($pngPath) || filesize($pngPath) <= 0) {
                @is_file($pngPath) && @unlink($pngPath);
                error_log("PNG write failed after cache retrieval");
                return null;
            }

            error_log("Using cached PNG at: {$pngPath} (" . filesize($pngPath) . " bytes)");
            return $pngPath;

        } catch (\Throwable $e) {
            error_log("Error converting SVG to PNG: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate JSON files for both ZIP export and FTP upload (same structure)
     */
    private function generateJsonFiles(string $dataDir): void
    {
        $financialSupports = $this->em->getRepository(FinancialSupport::class)->findBy(['isPublic' => true]);
        
        foreach (['de', 'fr', 'it'] as $locale) {
            $jsonData = $this->generateLocalizedJsonForFtp($financialSupports, $locale);
            file_put_contents(
                $dataDir . "/{$locale}.json", 
                json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
        }
    }

    /**
     * Generate logos for each locale (directly in logos folder)
     */
    private function generateLogos(string $logoDir): void
    {
        $financialSupports = $this->em->getRepository(FinancialSupport::class)->findBy(['isPublic' => true]);
        
        foreach ($financialSupports as $financialSupport) {
            foreach (['de', 'fr', 'it'] as $locale) {
                $logoData = $this->fetchLogoData($financialSupport, $locale);
                
                if ($logoData && !empty($logoData['data'])) {
                    // Get file extension
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
                    
                    // Create filename with language suffix (e.g., 2.png, 2_fr.png, 2_it.png)
                    $filename = $locale === 'de' 
                        ? $logoData['id'] . '.' . $extension
                        : $logoData['id'] . '_' . $locale . '.' . $extension;
                    
                    $filePath = $logoDir . '/' . $filename;
                    file_put_contents($filePath, $logoData['data']);
                    
                    // If SVG, also create a PNG version
                    if ($extension === 'svg') {
                        $convertedPath = $this->convertSvgToPng($filePath, $logoData['id'], $locale);
                        if ($convertedPath) {
                            $pngFilename = $locale === 'de' 
                                ? $logoData['id'] . '.png'
                                : $logoData['id'] . '_' . $locale . '.png';
                            $pngPath = $logoDir . '/' . $pngFilename;
                            copy($convertedPath, $pngPath);
                            unlink($convertedPath);
                        }
                    }
                }
            }
        }
    }

    /**
     * Generate PDFs for each locale (directly in pdfs folder)
     */
    private function generatePdfs(string $pdfDir): void
    {
        $financialSupports = $this->em->getRepository(FinancialSupport::class)->findBy(['isPublic' => true]);
        
        foreach ($financialSupports as $financialSupport) {
            foreach (['de', 'fr', 'it'] as $locale) {
                $logoData = $this->fetchLogoData($financialSupport, $locale);
                
                // Create filename with language suffix (e.g., 2.pdf, 2_fr.pdf, 2_it.pdf)
                $filename = $locale === 'de' 
                    ? $financialSupport->getId() . '.pdf'
                    : $financialSupport->getId() . '_' . $locale . '.pdf';
                
                $pdfPath = $pdfDir . '/' . $filename;
                $this->generatePdf($financialSupport, $pdfPath, $logoData, $locale);
            }
        }
    }

    /**
     * Process logos for a specific locale
     */
    private function processLogosForLocale(string $localeDir, string $locale): void
    {
        $financialSupports = $this->em->getRepository(FinancialSupport::class)->findBy(['isPublic' => true]);
        
        foreach ($financialSupports as $financialSupport) {
            $logoData = $this->fetchLogoData($financialSupport, $locale);
            
            if ($logoData && !empty($logoData['data'])) {
                // Get file extension from filename
                $extension = pathinfo($logoData['name'], PATHINFO_EXTENSION);
                if (empty($extension)) {
                    // Detect extension from file data if not in filename
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
                
                $filename = $logoData['id'] . '_' . $locale . '.' . $extension;
                $filePath = $localeDir . '/' . $filename;
                
                file_put_contents($filePath, $logoData['data']);
                
                // If SVG, also create a PNG version
                if ($extension === 'svg') {
                    $convertedPath = $this->convertSvgToPng($filePath, $logoData['id'], $locale);
                    if ($convertedPath) {
                        $pngFilename = $logoData['id'] . '_' . $locale . '.png';
                        $pngPath = $localeDir . '/' . $pngFilename;
                        copy($convertedPath, $pngPath);
                        unlink($convertedPath);
                    }
                }
            }
        }
    }

    /**
     * Process PDFs for a specific locale
     */
    private function processPdfsForLocale(string $localeDir, string $locale): void
    {
        $financialSupports = $this->em->getRepository(FinancialSupport::class)->findBy(['isPublic' => true]);
        
        foreach ($financialSupports as $financialSupport) {
            $logoData = $this->fetchLogoData($financialSupport, $locale);
            $pdfPath = $localeDir . '/' . $financialSupport->getId() . '_' . $locale . '.pdf';
            
            $this->generatePdf($financialSupport, $pdfPath, $logoData, $locale);
        }
    }

    /**
     * Generate localized JSON data for FTP upload
     */
    private function generateLocalizedJsonForFtp(array $financialSupports, string $locale): array
    {
        $angebote = [];
        
        foreach ($financialSupports as $financialSupport) {
            // Get logo filename
            $logoFilename = $this->getLogoFilename($financialSupport, $locale);
            
            // Get PDF filename
            $pdfFilename = $locale === 'de' 
                ? $financialSupport->getId() . '.pdf'
                : $financialSupport->getId() . '_' . $locale . '.pdf';
            
            $angebot = [
                'id' => $financialSupport->getId(),
                'titel' => PvTrans::translate($financialSupport, 'name', $locale) ?: '',
                'logo' => $logoFilename ? 'logos/' . $logoFilename : '',
                'pdf' => 'pdfs/' . $pdfFilename,
                'foerderstelle' => PvTrans::translate($financialSupport, 'fundingProvider', $locale) ?: '',
                'unterstuetzungsform' => $this->formatArrayAsString($this->getInstrumentsData($financialSupport, $locale)),
                'beguenstigte' => $this->formatArrayAsString($this->getBeneficiariesData($financialSupport, $locale)),
                'lead' => PvTrans::translate($financialSupport, 'description', $locale) ?: '',
                'kurzbeschrieb' => PvTrans::translate($financialSupport, 'additionalInformation', $locale) ?: '',
                'teilkrit' => PvTrans::translate($financialSupport, 'inclusionCriteria', $locale) ?: '',
                'auskrit' => PvTrans::translate($financialSupport, 'exclusionCriteria', $locale) ?: '',
                'beantragung' => PvTrans::translate($financialSupport, 'application', $locale) ?: '',
                'tippsbeantragung' => PvTrans::translate($financialSupport, 'applicationTips', $locale) ?: '',
                'themenschwerpunkt' => $this->formatArrayAsString($this->getTopicsData($financialSupport, $locale)),
                'innovationsphasen' => $this->formatArrayAsString($this->getProjectTypesData($financialSupport, $locale)),
                'finanzierung' => PvTrans::translate($financialSupport, 'financingRatio', $locale) ?: '',
                'foerdergebiet' => $this->formatArrayAsString($this->getGeographicRegionsData($financialSupport, $locale)),
                'kontakt' => $this->formatContactsAsString($financialSupport, $locale),
                'laufzeitstart' => $financialSupport->getStartDate() ? $financialSupport->getStartDate()->format('d.m.Y') : '',
                'laufzeitende' => $financialSupport->getEndDate() ? $financialSupport->getEndDate()->format('d.m.Y') : '',
                'termine' => $this->formatAppointmentsAsString($financialSupport, $locale),
                'terminetxt' => '', // Empty as in original format
                'mehrinfos' => $this->formatLinksAsString($financialSupport, $locale),
                'zuteilung' => PvTrans::translate($financialSupport, 'assignment', $locale) ?: ''
            ];
            
            $angebote[] = $angebot;
        }
        
        return ['angebote' => $angebote];
    }

    /**
     * Get logo filename for a financial support
     */
    private function getLogoFilename(FinancialSupport $financialSupport, string $locale): ?string
    {
        $logoData = $this->fetchLogoData($financialSupport, $locale);
        
        if (!$logoData || empty($logoData['data'])) {
            return null;
        }
        
        // Get file extension from filename
        $extension = pathinfo($logoData['name'], PATHINFO_EXTENSION);
        if (empty($extension)) {
            // Detect extension from file data if not in filename
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
        
        // Create filename with language suffix (e.g., 2.svg, 2_fr.svg, 2_it.svg)
        return $locale === 'de' 
            ? $logoData['id'] . '.' . $extension
            : $logoData['id'] . '_' . $locale . '.' . $extension;
    }

    /**
     * Format array of data items as string separated by <br>
     */
    private function formatArrayAsString(array $items): string
    {
        $names = array_map(function($item) {
            return $item['name'] ?? '';
        }, $items);
        
        return implode('<br>', array_filter($names));
    }

    /**
     * Format contacts as string in the original format
     */
    private function formatContactsAsString(FinancialSupport $financialSupport, string $locale): string
    {
        $contacts = PvTrans::translate($financialSupport, 'contacts', $locale) ?: [];
        
        if (empty($contacts)) {
            return '';
        }
        
        $contactStrings = [];
        
        foreach ($contacts as $contact) {
            $contactParts = [];
            
            // Institution/Organization name
            if (!empty($contact['name'])) {
                $contactParts[] = '<b>' . $contact['name'] . '</b>';
            }

            if (!empty($contact['department'])) {
                $contactParts[] = '<b>' . $contact['department'] . '</b>';
            }
            
            // Person details for person contacts
            if (!isset($contact['type']) || $contact['type'] === 'person') {
                $personName = '';
                if (!empty($contact['salutation'])) {
                    $personName .= ($contact['salutation'] === 'm' ? 'Herr ' : 'Frau ');
                }
                if (!empty($contact['firstName']) || !empty($contact['lastName'])) {
                    $personName .= trim(($contact['firstName'] ?? '') . ' ' . ($contact['lastName'] ?? ''));
                }
                if ($personName) {
                    $contactParts[] = $personName;
                }
                
                if (!empty($contact['role'])) {
                    $contactParts[] = $contact['role'];
                }
            }
            
            // Address
            if (!empty($contact['street'])) {
                $contactParts[] = $contact['street'];
            }
            
            $addressLine = '';
            if (!empty($contact['zipCode'])) {
                $addressLine .= $contact['zipCode'];
            }
            if (!empty($contact['city'])) {
                $addressLine .= ($addressLine ? '  ' : '') . $contact['city'];
            }
            if ($addressLine) {
                $contactParts[] = $addressLine;
            }
            
            if (!empty($contact['addressSupplement'])) {
                $contactParts[] = $contact['addressSupplement'];
            }
            
            // Contact details
            if (!empty($contact['email'])) {
                $contactParts[] = '<a href="mailto:'.$contact['email'].'">'.htmlspecialchars($contact['email']).'</a>';
            }
            if (!empty($contact['phone'])) {
                $contactParts[] = $contact['phone'];
            }
            if (!empty($contact['website'])) {
                $url = $this->normalizeUrl($contact['website']);
                $contactParts[] = '<a href="https://'.$url.'" target="_blank">'.htmlspecialchars($url).'</a>';
            }
            
            if (!empty($contactParts)) {
                $contactStrings[] = implode('<br>', $contactParts);
            }
        }
        
        return implode('<br><br>', $contactStrings);
    }

    /**
     * Format appointments as string
     */
    private function formatAppointmentsAsString(FinancialSupport $financialSupport, string $locale): string
    {
        $appointments = PvTrans::translate($financialSupport, 'appointments', $locale) ?: [];
        
        if (empty($appointments)) {
            return '';
        }
        
        $appointmentStrings = [];
        
        foreach ($appointments as $appointment) {
            $parts = [];
            
            if (!empty($appointment['date'])) {
                $date = new \DateTime($appointment['date']);
                $parts[] = $date->format('d.m.Y');
            }
            
            if (!empty($appointment['description'])) {
                $parts[] = strip_tags($appointment['description']);
            }
            
            if (!empty($parts)) {
                $appointmentStrings[] = implode(': ', $parts);
            }
        }
        
        return implode('<br>', $appointmentStrings);
    }

    /**
     * Format links as string in the original format
     */
    private function formatLinksAsString(FinancialSupport $financialSupport, string $locale): string
    {
        $links = PvTrans::translate($financialSupport, 'links', $locale) ?: [];
        
        if (empty($links)) {
            return '';
        }
        
        $linkStrings = [];
        
        foreach ($links as $link) {
            if (!empty($link['value']) && !empty($link['label'])) {
                $linkStrings[] = '<a href="https://' . $this->normalizeUrl($link['value']) . '" target="_blank" class="contLinks" title="' . htmlspecialchars($link['label']) . '">' . htmlspecialchars($link['label']) . '</a>';
            }
        }
        
        return implode('<br>', $linkStrings);
    }

    /**
     * Get file manifest for FTP upload
     */
    private function getFileManifest(string $basePath): array
    {
        $files = [];
        
        // Data files
        foreach (['de', 'fr', 'it'] as $locale) {
            $files[] = [
                'local' => "data/{$locale}.json",
                'remote' => "data/{$locale}.json",
                'type' => 'json'
            ];
        }
        
        // Logo files (directly in logos folder)
        $logoFiles = $this->scanDirectory($basePath . "/logos");
        foreach ($logoFiles as $logoFile) {
            $files[] = [
                'local' => "logos/{$logoFile}",
                'remote' => "logos/{$logoFile}",
                'type' => 'logo'
            ];
        }
        
        // PDF files (directly in pdfs folder)
        $pdfFiles = $this->scanDirectory($basePath . "/pdfs");
        foreach ($pdfFiles as $pdfFile) {
            $files[] = [
                'local' => "pdfs/{$pdfFile}",
                'remote' => "pdfs/{$pdfFile}",
                'type' => 'pdf'
            ];
        }
        
        return $files;
    }

    /**
     * Scan directory for files
     */
    private function scanDirectory(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        return array_values(array_filter($files, function($file) use ($dir) {
            return is_file($dir . '/' . $file);
        }));
    }

    /**
     * Helper methods for getting related data
     */
    private function getStatesData(FinancialSupport $financialSupport, string $locale): array
    {
        $entities = $financialSupport->getStates()->toArray();

        usort($entities, function($a, $b) {
            $posA = $a->getPosition() ?? PHP_INT_MAX;
            $posB = $b->getPosition() ?? PHP_INT_MAX;

            return $posA <=> $posB ?: ($a->getId() <=> $b->getId());
        });

        return array_map(function($entity) use ($locale) {
            return [
                'id'   => $entity->getId(),
                'name' => PvTrans::translate($entity, 'name', $locale),
            ];
        }, $entities);
    }

    private function getTopicsData(FinancialSupport $financialSupport, string $locale): array
    {
        $entities = $financialSupport->getTopics()->toArray();

        usort($entities, function($a, $b) {
            $posA = $a->getPosition() ?? PHP_INT_MAX;
            $posB = $b->getPosition() ?? PHP_INT_MAX;

            return $posA <=> $posB ?: ($a->getId() <=> $b->getId());
        });

        return array_map(function($entity) use ($locale) {
            return [
                'id'   => $entity->getId(),
                'name' => PvTrans::translate($entity, 'name', $locale),
            ];
        }, $entities);
    }

    private function getInstrumentsData(FinancialSupport $financialSupport, string $locale): array
    {
        $entities = $financialSupport->getInstruments()->toArray();

        usort($entities, function($a, $b) {
            $posA = $a->getPosition() ?? PHP_INT_MAX;
            $posB = $b->getPosition() ?? PHP_INT_MAX;

            return $posA <=> $posB ?: ($a->getId() <=> $b->getId());
        });

        return array_map(function($entity) use ($locale) {
            return [
                'id'   => $entity->getId(),
                'name' => PvTrans::translate($entity, 'name', $locale),
            ];
        }, $entities);
    }

    private function getBeneficiariesData(FinancialSupport $financialSupport, string $locale): array
    {
        $entities = $financialSupport->getBeneficiaries()->toArray();

        usort($entities, function($a, $b) {
            $posA = $a->getPosition() ?? PHP_INT_MAX;
            $posB = $b->getPosition() ?? PHP_INT_MAX;

            return $posA <=> $posB ?: ($a->getId() <=> $b->getId());
        });

        return array_map(function($entity) use ($locale) {
            return [
                'id'   => $entity->getId(),
                'name' => PvTrans::translate($entity, 'name', $locale),
            ];
        }, $entities);
    }

    private function getProjectTypesData(FinancialSupport $financialSupport, string $locale): array
    {
        $entities = $financialSupport->getProjectTypes()->toArray();

        usort($entities, function($a, $b) {
            $posA = $a->getPosition() ?? PHP_INT_MAX;
            $posB = $b->getPosition() ?? PHP_INT_MAX;

            return $posA <=> $posB ?: ($a->getId() <=> $b->getId());
        });

        return array_map(function($entity) use ($locale) {
            return [
                'id'   => $entity->getId(),
                'name' => PvTrans::translate($entity, 'name', $locale),
            ];
        }, $entities);
    }

    private function getGeographicRegionsData(FinancialSupport $financialSupport, string $locale): array
    {
        $entities = $financialSupport->getGeographicRegions()->toArray();

        usort($entities, function($a, $b) {
            $posA = $a->getPosition() ?? PHP_INT_MAX;
            $posB = $b->getPosition() ?? PHP_INT_MAX;

            return $posA <=> $posB ?: ($a->getId() <=> $b->getId());
        });

        return array_map(function($entity) use ($locale) {
            return [
                'id'   => $entity->getId(),
                'name' => PvTrans::translate($entity, 'name', $locale),
            ];
        }, $entities);
    }
} 