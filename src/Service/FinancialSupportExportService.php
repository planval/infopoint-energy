<?php

namespace App\Service;

use App\Entity\FinancialSupport;
use App\Util\PvTrans;
use Doctrine\ORM\EntityManagerInterface;
use Mpdf\Mpdf;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;


class FinancialSupportExportService
{
    private $em;
    private $twig;
    private $requestStack;
    private $params;
    private $exportDir;

    public function __construct(
        EntityManagerInterface $em,
        Environment $twig,
        RequestStack $requestStack,
        ParameterBagInterface $params
    ) {
        $this->em = $em;
        $this->twig = $twig;
        $this->requestStack = $requestStack;
        $this->params = $params;
        $this->exportDir = sys_get_temp_dir() . '/financial-supports-export-' . uniqid();
    }

    private function cleanup(): void
    {
        if (file_exists($this->exportDir)) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->exportDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($files as $fileinfo) {
                try {
                    if ($fileinfo->isDir()) {
                        rmdir($fileinfo->getRealPath());
                    } else {
                        unlink($fileinfo->getRealPath());
                    }
                } catch (\Throwable $e) {
                }
            }

            rmdir($this->exportDir);
        }
    }

    private function addFolderToZip(\ZipArchive $zip, string $folderPath, string $zipFolder): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($folderPath),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = $zipFolder . '/' . substr($filePath, strlen($folderPath) + 1);
                
                if ($zip->addFile($filePath, $relativePath)) {
                } else {
                }
            }
        }
    }

    public function exportAllToZip(): string
    {
        try {
            // Create export directory if it doesn't exist
            if (!file_exists($this->exportDir)) {
                mkdir($this->exportDir, 0777, true);
            }

            // Create subdirectories
            $pdfDir = $this->exportDir . '/pdf';
            $logoDir = $this->exportDir . '/logo';
            mkdir($pdfDir, 0777, true);
            mkdir($logoDir, 0777, true);

            // Load financial supports with all necessary associations
            $financialSupports = $this->em->getRepository(FinancialSupport::class)
                ->createQueryBuilder('fs')
                ->leftJoin('fs.authorities', 'authorities')
                ->leftJoin('fs.states', 'states')
                ->leftJoin('fs.beneficiaries', 'beneficiaries')
                ->leftJoin('fs.topics', 'topics')
                ->leftJoin('fs.projectTypes', 'projectTypes')
                ->leftJoin('fs.instruments', 'instruments')
                ->leftJoin('fs.geographicRegions', 'geographicRegions')
                ->addSelect('authorities', 'states', 'beneficiaries', 'topics', 'projectTypes', 'instruments', 'geographicRegions')
                ->getQuery()
                ->getResult();

            // Generate de.json
            $deJson = $this->generateDeJson($financialSupports, $pdfDir, $logoDir);
            
            $jsonContent = json_encode(['angebote' => $deJson], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($jsonContent === false) {
                throw new \RuntimeException('Failed to encode JSON: ' . json_last_error_msg());
            }
            
            $jsonPath = $this->exportDir . '/de.json';
            if (file_put_contents($jsonPath, $jsonContent) === false) {
                throw new \RuntimeException('Failed to write JSON file');
            }

            $zipPath = $this->exportDir . '.zip';
            $zip = new \ZipArchive();

            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Could not create ZIP file');
            }

            // Add all files from the export directory to the ZIP
            $this->addFolderToZip($zip, $this->exportDir, '');

            $zip->close();

            // Clean up the temporary directory
            $this->cleanup();

            if (!file_exists($zipPath)) {
                throw new \RuntimeException('ZIP file was not created at the expected path');
            }

            return $zipPath;
            
        } catch (\Throwable $e) {
            // Clean up in case of error
            $this->cleanup();
            
            throw $e;
        }
    }

    private function generateDeJson(array $financialSupports, string $pdfDir, string $logoDir): array
    {
        $deJson = [];
        $locale = 'de';

        foreach ($financialSupports as $financialSupport) {
            try {
                // Generate PDF with ID as filename
                $pdfPath = $pdfDir . '/' . $financialSupport->getId() . '.pdf';
                $this->generatePdf($financialSupport, $pdfPath);
                
                // Handle logo
                $logoPath = null;
                if ($financialSupport->getLogo()) {
                    $logoPath = $this->handleLogo($financialSupport, $logoDir);
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
                foreach ($financialSupport->getAppointments() ?? [] as $appointment) {
                    if (isset($appointment['date'])) {
                        $date = new \DateTime($appointment['date']);
                        $appointments[] = $date->format('d.m.Y');
                        if (isset($appointment['description'])) {
                            $appointmentTexts[] = $date->format('d.m.Y') . ': ' . strip_tags($appointment['description']);
                        }
                    }
                }

                // Format links for mehrinfos
                $mehrinfos = [];
                foreach ($financialSupport->getLinks() ?? [] as $link) {
                    if (!empty($link['value']) && !empty($link['label'])) {
                        $mehrinfos[] = sprintf(
                            '<a href="%s" target="_blank" class="contLinks" title="%s">%s</a>',
                            $link['value'],
                            htmlspecialchars($link['label']),
                            htmlspecialchars($link['label'])
                        );
                    }
                }

                // Convert HTML lists to bullet points with • symbol
                $convertHtmlListToBullets = function($html) {
                    if (!$html) return '';
                    // Remove <p> tags but keep line breaks
                    $text = str_replace(['<p>', '</p>'], '', $html);
                    // Convert <ul> and <li> to bullet points
                    $text = preg_replace('/<ul[^>]*>/', "\n", $text);
                    $text = preg_replace('/<li[^>]*>/', '• ', $text);
                    $text = preg_replace('/<\/li>/', "\n", $text);
                    $text = preg_replace('/<\/ul>/', "\n", $text);
                    // Clean up extra newlines and spaces
                    $text = trim(preg_replace('/\n\s*\n/', "\n\n", $text));
                    return $text;
                };

                $deJson[] = [
                    'id' => $financialSupport->getId(),
                    'titel' => PvTrans::translate($financialSupport, 'name', $locale) ?? '',
                    'logo' => $logoPath ? 'logo/' . basename($logoPath) : null,
                    'foerderstelle' => implode('<br>', $authorities),
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
                    'kontakt' => $this->formatContacts($financialSupport->getContacts() ?? []),
                    'laufzeitstart' => $financialSupport->getStartDate() ? $financialSupport->getStartDate()->format('d.m.Y') : '',
                    'laufzeitende' => $financialSupport->getEndDate() ? $financialSupport->getEndDate()->format('d.m.Y') : '',
                    'termine' => implode('<br>', $appointments),
                    'terminetxt' => implode('<br>', $appointmentTexts),
                    'mehrinfos' => implode('<br>', $mehrinfos),
                    'zuteilung' => $financialSupport->getAssignment() ?? ''
                ];

            } catch (\Throwable $e) {
                throw $e;
            }
        }

        return $deJson;
    }

    private function handleLogo(FinancialSupport $financialSupport, string $logoDir): ?string
    {
        $logo = $financialSupport->getLogo();
        if ($logo && isset($logo['id'])) {
            $file = $this->em->getRepository(\App\Entity\File::class)->find($logo['id']);
            if ($file) {
                try {
                    $data = stream_get_contents($file->getData());
                    $data = count(explode(';base64,', $data)) >= 2 ? explode(';base64,', $data, 2)[1] : $data;
                    $extension = pathinfo($logo['name'], PATHINFO_EXTENSION);
                    $logoPath = $logoDir . '/' . $financialSupport->getId() . '.' . $extension;
                    
                    file_put_contents($logoPath, base64_decode($data));

                    return $logoPath;
                } catch (\Throwable $e) {
                    throw $e;
                }
            }
        }
        return null;
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

    private function generatePdf(FinancialSupport $financialSupport, string $outputPath): void
    {
        try {
            $mpdf = new Mpdf([
                'fontDir' => [
                    __DIR__.'/../../assets/fonts/',
                ],
                'fontdata' => [
                    'helveticaneue' => [
                        'R' => 'helveticaneue.ttf',
                        'B' => 'helveticaneuebold.ttf',
                    ]
                ],
                'margin_left' => 20,
                'margin_right' => 15,
                'margin_top' => 20,
                'margin_bottom' => 25,
                'margin_header' => 10,
                'margin_footer' => 10,
                'default_font' => 'helveticaneue',
            ]);

            // Always use German as the base locale for exports
            $locale = 'de';

            $mpdf->SetTitle(PvTrans::translate($financialSupport, 'name', $locale));
            $mpdf->SetDisplayMode('fullpage');
            $mpdf->shrink_tables_to_fit = 1;

            $logo = PvTrans::translate($financialSupport, 'logo', $locale);
            $tempLogoPath = null;

            if($logo) {
                $file = $this->em->getRepository(\App\Entity\File::class)
                    ->find($logo['id']);
                
                if ($file) {
                    $imagick = new \Imagick();
                    $data = stream_get_contents($file->getData());
                    $data = count(explode(';base64,', $data)) >= 2 ? explode(';base64,', $data, 2)[1] : $data;
                    $imagick->readImageBlob(base64_decode($data));

                    $tempLogoPath = tempnam(sys_get_temp_dir(), 'logo'.$file->getId());
                    file_put_contents($tempLogoPath, $imagick->getImageBlob());
                }
            }

            $mpdf->WriteHTML($this->twig->render('pdf/financial-support.html.twig', [
                'financialSupport' => $financialSupport,
                'logo' => $tempLogoPath,
                'app' => ['request' => ['locale' => $locale]],
            ]));

            $mpdf->Output($outputPath, \Mpdf\Output\Destination::FILE);

            if (!file_exists($outputPath)) {
                throw new \RuntimeException('PDF file was not created at expected path');
            }

            // Clean up temporary logo file if it was created
            if ($tempLogoPath && file_exists($tempLogoPath)) {
                unlink($tempLogoPath);
            }

        } catch (\Throwable $e) {
            throw $e;
        }
    }
} 