<?php

namespace App\Controller;

use App\Entity\File;
use App\Entity\FinancialSupport;
use App\Entity\Log;
use App\Service\FinancialSupportService;
use App\Service\FtpService;
use App\Util\PvTrans;
use Doctrine\ORM\EntityManagerInterface;
use Mpdf\Mpdf;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Attributes as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Service\FinancialSupportExportService;
use \Imagick;

#[Route(path: '/api/v1/financial-supports', name: 'api_financial_supports_')]
class ApiFinancialSupportsController extends AbstractController
{
    
    #[Route(path: '', name: 'index', methods: ['GET'])]
    #[OA\Parameter(
        name: 'term',
        description: 'Search term',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string'),
    )]
    #[OA\Parameter(
        name: 'status[]',
        description: 'Return financial supports by status',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'string', enum: ['public', 'draft'])),
    )]
    #[OA\Parameter(
        name: 'fundingProvider',
        description: 'Filter by funding provider (Förderstelle)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'string'),
    )]
    #[OA\Parameter(
        name: 'state[]',
        description: 'Include only specific states (both name or id are valid values)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'string')),
    )]
    #[OA\Parameter(
        name: 'topic[]',
        description: 'Include only specific topics (both name or id are valid values)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'string')),
    )]
    #[OA\Parameter(
        name: 'instrument[]',
        description: 'Include only specific instruments (both name or id are valid values)',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'string')),
    )]
    #[OA\Parameter(
        name: 'limit',
        description: 'Limit returned items',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Parameter(
        name: 'offset',
        description: 'Skip returned items',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'integer'),
    )]
    #[OA\Parameter(
        name: 'orderBy[]',
        description: 'Order items by field',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'string', enum: ['id', 'position', 'createdAt', 'updatedAt'])),
    )]
    #[OA\Parameter(
        name: 'orderDirection[]',
        description: 'Set order direction',
        in: 'query',
        required: false,
        schema: new OA\Schema(type: 'array', items: new OA\Items(type: 'string', enum: ['ASC', 'DESC'])),
    )]
    #[OA\Response(
        response: 200,
        description: 'Returns all financial supports',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: FinancialSupport::class, groups: ['id', 'financial_support']))
        )
    )]
    #[OA\Tag(name: 'Financial Supports')]
    public function index(Request $request, EntityManagerInterface $em, NormalizerInterface $normalizer): JsonResponse
    {
        $qb = $em->createQueryBuilder();

        $qb
            ->select('DISTINCT fs')
            ->from(FinancialSupport::class, 'fs')
            ->leftJoin('fs.states', 'state')
            ->leftJoin('fs.topics', 'topic')
            ->leftJoin('fs.instruments', 'instrument')
        ;

        if($request->get('term')) {
            $qb
                ->andWhere('(fs.searchIndex LIKE :term OR fs.translations LIKE :term)')
                ->setParameter('term', '%'.$request->get('term').'%');
        }

        if($request->get('status') && is_array($request->get('status'))) {
            $qb
                ->andWhere('fs.isPublic = :isPublic')
                ->setParameter('isPublic', $request->get('status')[0] === 'public');
        }

        if($request->get('fundingProvider') && is_array($request->get('fundingProvider'))) {
            foreach($request->get('fundingProvider') as $key => $provider) {
                $qb
                    ->andWhere('fs.fundingProvider LIKE :provider'.$key.' OR fs.translations LIKE :providerTrans'.$key)
                    ->setParameter('provider'.$key, '%'.$provider.'%')
                    ->setParameter('providerTrans'.$key, '%"fundingProvider":"'.$provider.'%');
            }
        }

        if($request->get('state') && is_array($request->get('state'))) {
            foreach($request->get('state') as $key => $state) {
                $qb
                    ->andWhere('state.name = :state'.$key.' OR state.id = :stateId'.$key)
                    ->setParameter('state'.$key, $state)
                    ->setParameter('stateId'.$key, $state)
                ;
            }
        }

        if($request->get('topic') && is_array($request->get('topic'))) {
            foreach($request->get('topic') as $key => $topic) {
                $qb
                    ->andWhere('topic.name = :topic'.$key.' OR topic.id = :topicId'.$key)
                    ->setParameter('topic'.$key, $topic)
                    ->setParameter('topicId'.$key, $topic)
                ;
            }
        }

        if($request->get('instrument') && is_array($request->get('instrument'))) {
            foreach($request->get('instrument') as $key => $instrument) {
                $qb
                    ->andWhere('instrument.name = :instrument'.$key.' OR instrument.id = :instrumentId'.$key)
                    ->setParameter('instrument'.$key, $instrument)
                    ->setParameter('instrumentId'.$key, $instrument)
                ;
            }
        }

        if($request->get('limit')) {
            $qb->setMaxResults($request->get('limit'));
        }

        if($request->get('offset')) {
            $qb->setFirstResult($request->get('offset'));
        }

        if($request->get('orderBy') && is_array($request->get('orderBy'))) {
            foreach($request->get('orderBy') as $key => $orderBy) {
                $direction = $request->get('orderDirection')[$key] ?? 'ASC';
                $qb->addOrderBy('fs.'.$orderBy, $direction);
            }
        } else {
            $qb->addOrderBy('fs.position', 'ASC');
        }

        $financialSupports = $qb->getQuery()->getResult();

        $result = $normalizer->normalize($financialSupports, null, [
            'groups' => ['id', 'financial_support'],
        ]);

        return $this->json($result);
    }
    
    #[Route(path: '/export-all.zip', name: 'export_all_zip', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Returns a ZIP file containing all financial supports',
        content: new OA\MediaType(mediaType: 'application/zip', schema: new OA\Schema(type: 'string', format: 'binary'))
    )]
    #[OA\Tag(name: 'Financial Supports')]
    #[Security(name: 'cookieAuth')]
    public function exportAllZip(FinancialSupportExportService $exportService): Response
    {
        try {
            $zipPath = $exportService->exportAllToZip();
            
            if (!file_exists($zipPath)) {
                throw new \RuntimeException('ZIP file could not be created');
            }
            
            $response = new BinaryFileResponse($zipPath);
            $response->setContentDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                'financial-supports-export.zip'
            );
            $response->headers->set('Content-Type', 'application/zip');
            $response->deleteFileAfterSend(true);
            
            return $response;
        } catch (\Throwable $e) {
            throw $e;
        }
    }
    
    #[Route(path: '/publication-status', name: 'publication_status', methods: ['GET'])]
    public function getPublicationStatus(EntityManagerInterface $em): JsonResponse
    {
        try {
            $financialSupports = $em->getRepository(FinancialSupport::class)->findAll();
            $result = [];
            
            foreach ($financialSupports as $fs) {
                // Get latest publication logs (both publish and unpublish) for this specific financial support
                $stagingLog = $em->createQueryBuilder()
                    ->select('l')
                    ->from(Log::class, 'l')
                    ->where('l.context = :context')
                    ->andWhere('l.category = :staging')
                    ->andWhere('l.action IN (:actions)')
                    ->andWhere('l.value LIKE :fsId')
                    ->setParameter('context', 'financial_support_publish')
                    ->setParameter('staging', 'staging')
                    ->setParameter('actions', ['publish', 'unpublish'])
                    ->setParameter('fsId', '% ID: ' . $fs->getId() . ' %')
                    ->orderBy('l.createdAt', 'DESC')
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult();
                
                $productionLog = $em->createQueryBuilder()
                    ->select('l')
                    ->from(Log::class, 'l')
                    ->where('l.context = :context')
                    ->andWhere('l.category = :production')
                    ->andWhere('l.action IN (:actions)')
                    ->andWhere('l.value LIKE :fsId')
                    ->setParameter('context', 'financial_support_publish')
                    ->setParameter('production', 'production')
                    ->setParameter('actions', ['publish', 'unpublish'])
                    ->setParameter('fsId', '% ID: ' . $fs->getId() . ' %')
                    ->orderBy('l.createdAt', 'DESC')
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult();
                
                $result[] = [
                    'id' => $fs->getId(),
                    'name' => $fs->getName(),
                    'isPublic' => $fs->getIsPublic(),
                    'staging' => ($stagingLog && $stagingLog->getAction() === 'publish') ? [
                        'publishedAt' => $stagingLog->getCreatedAt()->format('Y-m-d H:i:s'),
                        'publishedBy' => $stagingLog->getUsername()
                    ] : null,
                    'production' => ($productionLog && $productionLog->getAction() === 'publish') ? [
                        'publishedAt' => $productionLog->getCreatedAt()->format('Y-m-d H:i:s'),
                        'publishedBy' => $productionLog->getUsername()
                    ] : null
                ];
            }
            
            return $this->json($result);
            
        } catch (\Exception $e) {
            return $this->json([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
    
    #[Route(path: '/{id}', name: 'get', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Returns a single financial support',
        content: new OA\JsonContent(
            ref: new Model(type: FinancialSupport::class, groups: ['id', 'financial_support'])
        )
    )]
    #[OA\Tag(name: 'Financial Supports')]
    public function find(Request $request, EntityManagerInterface $em, NormalizerInterface $normalizer): JsonResponse
    {
        $financialSupport = $em->getRepository(FinancialSupport::class)
            ->find($request->get('id'));

        $result = $normalizer->normalize($financialSupport, null, [
            'groups' => ['id', 'financial_support'],
        ]);

        return $this->json($result);
    }
    
    #[Route(path: '', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_EDITOR')]
    #[OA\Response(
        response: 200,
        description: 'Create a financial support',
        content: new OA\JsonContent(
            ref: new Model(type: FinancialSupport::class, groups: ['id', 'financial_support'])
        )
    )]
    #[OA\Tag(name: 'Financial Supports')]
    #[Security(name: 'cookieAuth')]
    public function create(Request $request, EntityManagerInterface $em, NormalizerInterface $normalizer, 
                           FinancialSupportService $financialSupportService): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        
        if(($errors = $financialSupportService->validateFinancialSupportPayload($payload)) !== true) {
            return $this->json($errors, 400);
        }
        
        $financialSupport = $financialSupportService->createFinancialSupport($payload);

        $result = $normalizer->normalize($financialSupport, null, [
            'groups' => ['id', 'financial_support'],
        ]);

        return $this->json($result);
    }
    
    #[Route(path: '/{id}', name: 'update', methods: ['PUT'])]
    #[IsGranted('ROLE_EDITOR')]
    #[OA\Response(
        response: 200,
        description: 'Update a financial support',
        content: new OA\JsonContent(
            ref: new Model(type: FinancialSupport::class, groups: ['id', 'financial_support'])
        )
    )]
    #[OA\Tag(name: 'Financial Supports')]
    #[Security(name: 'cookieAuth')]
    public function update(Request $request, EntityManagerInterface $em, NormalizerInterface $normalizer,
                           FinancialSupportService $financialSupportService): JsonResponse
    {
        $financialSupport = $em->getRepository(FinancialSupport::class)
            ->find($request->get('id'));
        
        $payload = json_decode($request->getContent(), true);
        
        if(($errors = $financialSupportService->validateFinancialSupportPayload($payload)) !== true) {
            return $this->json($errors, 400);
        }
        
        $financialSupport = $financialSupportService->updateFinancialSupport($financialSupport, $payload);

        $result = $normalizer->normalize($financialSupport, null, [
            'groups' => ['id', 'financial_support'],
        ]);

        return $this->json($result);
    }
    
    #[Route(path: '/{id}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_EDITOR')]
    #[OA\Response(
        response: 200,
        description: 'Delete a financial support',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(type: 'string'),
            default: []
        )
    )]
    #[OA\Tag(name: 'Financial Supports')]
    #[Security(name: 'cookieAuth')]
    public function delete(Request $request, EntityManagerInterface $em, NormalizerInterface $normalizer,
                           FinancialSupportService $financialSupportService): JsonResponse
    {
        $financialSupport = $em->getRepository(FinancialSupport::class)
            ->find($request->get('id'));
        
        $financialSupportService->deleteFinancialSupport($financialSupport);
        
        return $this->json([]);
    }

    #[Route(path: '/export/{id}-{_locale}.pdf', name: 'export_pdf', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Returns a file',
        content: new OA\MediaType(mediaType: 'application/pdf', schema: new OA\Schema(type: 'string', format: 'binary'))
    )]
    #[OA\Tag(name: 'Financial Supports')]
    public function exportPdf(Request $request, EntityManagerInterface $em,
                              TranslatorInterface $translator, 
                              FinancialSupportExportService $exportService): Response
    {
        $financialSupport = $em->getRepository(FinancialSupport::class)
            ->find($request->get('id'));

        if(!$financialSupport) {
            throw $this->createNotFoundException();
        }

        if(!$financialSupport->getIsPublic() && !$this->isGranted('ROLE_EDITOR')) {
            throw $this->createNotFoundException();
        }

        // Clone the financial support to avoid changing the original entity
        if ($financialSupport->getAssignment()) {
            $clonedFinancialSupport = clone $financialSupport;
            $formattedAssignment = $exportService->formatAssignmentForDisplay($financialSupport->getAssignment(), $request->getLocale());
            $clonedFinancialSupport->setAssignment($formattedAssignment);
            $financialSupport = $clonedFinancialSupport;
        }

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

        $mpdf->SetTitle(PvTrans::translate($financialSupport, 'name', $request->getLocale()));
        $mpdf->SetDisplayMode('fullpage');
        $mpdf->shrink_tables_to_fit = 1;

        $logo = PvTrans::translate($financialSupport, 'logo', $request->getLocale());

        if($logo) {
            $file = $em->getRepository(File::class)
                ->find($logo['id']);
            $data = stream_get_contents($file->getData());
            $data = count(explode(';base64,', $data)) >= 2 ? explode(';base64,', $data, 2)[1] : $data;
            $decodedData = base64_decode($data);
            
            // Get file extension from filename or detect from data
            $filename = $file->getName();
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            if (empty($extension)) {
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->buffer($decodedData);
                $mimeToExt = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/gif' => 'gif',
                    'image/svg+xml' => 'svg',
                    'image/webp' => 'webp'
                ];
                $extension = $mimeToExt[$mime] ?? 'png';
            }
            
            // Create temporary file with proper extension
            $tempLogoPath = tempnam(sys_get_temp_dir(), 'logo'.$file->getId().'_') . '.' . $extension;
            file_put_contents($tempLogoPath, $decodedData);
            
            // Special handling for SVG files - convert to PNG for PDF generation
            if (strtolower($extension) === 'svg') {
                $convertedPath = $this->convertSvgToPng($tempLogoPath, $file->getId());
                if ($convertedPath) {
                    unlink($tempLogoPath); // Remove the original SVG
                    $logo = $convertedPath;
                } else {
                    // If conversion fails, skip logo
                    unlink($tempLogoPath);
                    $logo = null;
                }
            } else {
                // For non-SVG files, use ImageMagick as before
                $imagick = new \Imagick();
                $imagick->readImageBlob($decodedData);
                $logo = tempnam(sys_get_temp_dir(), 'logo'.$file->getId());
                file_put_contents($logo, $imagick->getImageBlob());
                unlink($tempLogoPath);
            }
        }

        $mpdf->WriteHTML($this->renderView('pdf/financial-support.html.twig', [
            'financialSupport' => $financialSupport,
            'logo' => $logo,
        ]));

        $extension = 'pdf';
        $fileName = $translator->trans('Finanzhilfen')
            .' - '.PvTrans::translate($financialSupport, 'name', $request->getLocale())
            .'.'.$extension;

        $tmpFile = tempnam(sys_get_temp_dir(), 'fs'.$financialSupport->getId());

        $mpdf->Output($tmpFile, \Mpdf\Output\Destination::FILE);

        $response = $this->file($tmpFile, $fileName, ResponseHeaderBag::DISPOSITION_INLINE);
        $response->headers->set('Content-Type', 'application/pdf');

        $response->deleteFileAfterSend(true);

        return $response;
    }

    #[Route(path: '/publish', name: 'publish', methods: ['POST'])]
    #[IsGranted('ROLE_EDITOR')]
    #[OA\RequestBody(
        description: 'Environment to publish to',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'environment', type: 'string', enum: ['production', 'staging'])
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Result of publishing operation',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(property: 'message', type: 'string')
            ]
        )
    )]
    #[OA\Tag(name: 'Financial Supports')]
    #[Security(name: 'cookieAuth')]
    public function publish(
        Request $request,
        FinancialSupportExportService $exportService,
        FtpService $ftpService,
        EntityManagerInterface $em
    ): JsonResponse
    {
        try {
            // Get the environment from the request body
            $data = json_decode($request->getContent(), true);
            $environment = $data['environment'] ?? 'staging';
            
            // Validate environment
            if (!in_array($environment, ['production', 'staging'])) {
                return $this->json([
                    'success' => false,
                    'error' => 'Invalid environment specified. Valid values are "production" or "staging".'
                ], 400);
            }

            // Generate files for direct FTP upload
            $fileData = $exportService->generateFilesForFtp();
            
            // Upload files directly to FTP
            $result = $ftpService->uploadFiles(
                $fileData['base_path'], 
                $fileData['files'], 
                $environment
            );
            
            // Clean up temporary files
            $this->cleanupTempFiles($fileData['base_path']);
            
            if (!$result['success']) {
                return $this->json([
                    'success' => false,
                    'error' => $result['error'] ?? $result['message'] ?? 'Upload failed'
                ], 500);
            }
            
            // Check for draft items that were previously published in this environment and mark them as unpublished
            $allFinancialSupports = $em->getRepository(FinancialSupport::class)->findAll();
            foreach ($allFinancialSupports as $fs) {
                if (!$fs->getIsPublic()) {
                    // Check if this specific draft item was previously published in this environment
                    $previousPublicationLog = $em->createQueryBuilder()
                        ->select('l')
                        ->from(Log::class, 'l')
                        ->where('l.context = :context')
                        ->andWhere('l.category = :environment')
                        ->andWhere('l.action = :action')
                        ->andWhere('l.value LIKE :fsId')
                        ->setParameter('context', 'financial_support_publish')
                        ->setParameter('environment', $environment)
                        ->setParameter('action', 'publish')
                        ->setParameter('fsId', '% ID: ' . $fs->getId() . ' %')
                        ->orderBy('l.createdAt', 'DESC')
                        ->setMaxResults(1)
                        ->getQuery()
                        ->getOneOrNullResult();
                    
                    if ($previousPublicationLog) {
                        // Create unpublish log entry for this specific draft item
                        $unpublishLog = new Log();
                        $unpublishLog->setCreatedAt(new \DateTime());
                        $unpublishLog->setContext('financial_support_publish');
                        $unpublishLog->setCategory($environment);
                        $unpublishLog->setAction('unpublish');
                        $unpublishLog->setValue('Financial support unpublished due to draft status - ID: ' . $fs->getId() . ' - Name: ' . $fs->getName());
                        $unpublishLog->setUsername($this->getUser() ? $this->getUser()->getUserIdentifier() : 'system');
                        
                        $em->persist($unpublishLog);
                    }
                }
            }
            
            // Create log entries for each published financial support
            $publishedFinancialSupports = $em->getRepository(FinancialSupport::class)->findBy(['isPublic' => true]);
            foreach ($publishedFinancialSupports as $fs) {
                $log = new Log();
                $log->setCreatedAt(new \DateTime());
                $log->setContext('financial_support_publish');
                $log->setCategory($environment);
                $log->setAction('publish');
                $log->setValue('Financial support published - ID: ' . $fs->getId() . ' - Name: ' . $fs->getName());
                $log->setUsername($this->getUser() ? $this->getUser()->getUserIdentifier() : 'system');
                
                $em->persist($log);
            }
            
            // Create general log entry for successful publication
            $generalLog = new Log();
            $generalLog->setCreatedAt(new \DateTime());
            $generalLog->setContext('financial_support_publish');
            $generalLog->setCategory($environment);
            $generalLog->setAction('publish_summary');
            $generalLog->setValue('Published ' . count($publishedFinancialSupports) . ' financial supports to ' . $environment);
            $generalLog->setUsername($this->getUser() ? $this->getUser()->getUserIdentifier() : 'system');
            
            $em->persist($generalLog);
            $em->flush();
            
            return $this->json([
                'success' => true,
                'message' => $result['message'],
                'details' => [
                    'total_files' => $result['total_files'] ?? 0,
                    'uploaded_files' => $result['uploaded_files'] ?? 0,
                    'failed_files' => count($result['failed_files'] ?? [])
                ]
            ]);
            
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'error' => 'Error during publish operation: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route(path: '/deploy', name: 'deploy', methods: ['POST'])]
    #[IsGranted('ROLE_EDITOR')]
    #[OA\RequestBody(
        description: 'Environment to deploy to',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'environment', type: 'string', enum: ['production', 'staging'])
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Result of publishing operation',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(property: 'message', type: 'string')
            ]
        )
    )]
    #[OA\Tag(name: 'Financial Supports')]
    #[Security(name: 'cookieAuth')]
    public function deploy(
        Request $request,
        FinancialSupportExportService $exportService,
        DeployService $deployService,
        EntityManagerInterface $em
    ): JsonResponse
    {
        try {
            // Get the environment from the request body
            $data = json_decode($request->getContent(), true);
            $environment = $data['environment'] ?? 'staging';

            // Validate environment
            if (!in_array($environment, ['production', 'staging'])) {
                return $this->json([
                    'success' => false,
                    'error' => 'Invalid environment specified. Valid values are "production" or "staging".'
                ], 400);
            }

            $result = $deployService->deploy(
                $environment
            );

            if (!$result['success']) {
                return $this->json([
                    'success' => false,
                    'error' => $result['error'] ?? $result['message'] ?? 'Upload failed'
                ], 500);
            }

            // Check for draft items that were previously published in this environment and mark them as unpublished
            $allFinancialSupports = $em->getRepository(FinancialSupport::class)->findAll();
            foreach ($allFinancialSupports as $fs) {
                if (!$fs->getIsPublic()) {
                    // Check if this specific draft item was previously published in this environment
                    $previousPublicationLog = $em->createQueryBuilder()
                        ->select('l')
                        ->from(Log::class, 'l')
                        ->where('l.context = :context')
                        ->andWhere('l.category = :environment')
                        ->andWhere('l.action = :action')
                        ->andWhere('l.value LIKE :fsId')
                        ->setParameter('context', 'financial_support_publish')
                        ->setParameter('environment', $environment)
                        ->setParameter('action', 'publish')
                        ->setParameter('fsId', '% ID: ' . $fs->getId() . ' %')
                        ->orderBy('l.createdAt', 'DESC')
                        ->setMaxResults(1)
                        ->getQuery()
                        ->getOneOrNullResult();

                    if ($previousPublicationLog) {
                        // Create unpublish log entry for this specific draft item
                        $unpublishLog = new Log();
                        $unpublishLog->setCreatedAt(new \DateTime());
                        $unpublishLog->setContext('financial_support_publish');
                        $unpublishLog->setCategory($environment);
                        $unpublishLog->setAction('unpublish');
                        $unpublishLog->setValue('Financial support unpublished due to draft status - ID: ' . $fs->getId() . ' - Name: ' . $fs->getName());
                        $unpublishLog->setUsername($this->getUser() ? $this->getUser()->getUserIdentifier() : 'system');

                        $em->persist($unpublishLog);
                    }
                }
            }

            // Create log entries for each published financial support
            $publishedFinancialSupports = $em->getRepository(FinancialSupport::class)->findBy(['isPublic' => true]);
            foreach ($publishedFinancialSupports as $fs) {
                $log = new Log();
                $log->setCreatedAt(new \DateTime());
                $log->setContext('financial_support_publish');
                $log->setCategory($environment);
                $log->setAction('publish');
                $log->setValue('Financial support published - ID: ' . $fs->getId() . ' - Name: ' . $fs->getName());
                $log->setUsername($this->getUser() ? $this->getUser()->getUserIdentifier() : 'system');

                $em->persist($log);
            }

            // Create general log entry for successful publication
            $generalLog = new Log();
            $generalLog->setCreatedAt(new \DateTime());
            $generalLog->setContext('financial_support_publish');
            $generalLog->setCategory($environment);
            $generalLog->setAction('publish_summary');
            $generalLog->setValue('Deployed ' . count($publishedFinancialSupports) . ' financial supports to ' . $environment);
            $generalLog->setUsername($this->getUser() ? $this->getUser()->getUserIdentifier() : 'system');

            $em->persist($generalLog);
            $em->flush();

            return $this->json([
                'success' => true,
                'message' => $result['message'] ?? 'Deployment completed',
                'result' => $result,
            ]);

        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'error' => 'Error during deploy operation: ' . $e->getMessage()
            ], 500);
        }
    }
    
    #[Route(path: '/publication-logs', name: 'publication_logs', methods: ['GET'])]
    #[IsGranted('ROLE_EDITOR')]
    #[OA\Response(
        response: 200,
        description: 'Returns publication logs for environments',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Log::class, groups: ['log']))
        )
    )]
    #[OA\Tag(name: 'Financial Supports')]
    #[Security(name: 'cookieAuth')]
    public function getPublicationLogs(EntityManagerInterface $em): JsonResponse
    {
        $logs = $em->getRepository(Log::class)->findBy(
            ['context' => 'financial_support_publish'],
            ['createdAt' => 'DESC'],
            10
        );
        
        $result = [];
        foreach ($logs as $log) {
            $result[] = [
                'id' => $log->getId(),
                'createdAt' => $log->getCreatedAt()->format('Y-m-d H:i:s'),
                'environment' => $log->getCategory(),
                'action' => $log->getAction(),
                'value' => $log->getValue(),
                'username' => $log->getUsername()
            ];
        }
        
        return $this->json($result);
    }
    
    /**
     * Convert SVG to PNG for PDF generation with improved gradient support
     */
    private function convertSvgToPng(string $svgPath, int $logoId): ?string
    {
        try {
            // Check if Imagick is available
            if (!class_exists('Imagick')) {
                return null;
            }

            $imagick = new \Imagick();
            
            // Set higher resolution for better gradient rendering
            $imagick->setResolution(600, 600);
            
            // Set background color to transparent first to preserve gradients
            $imagick->setBackgroundColor(new \ImagickPixel('transparent'));
            
            // Read the SVG file
            $imagick->readImage($svgPath);
            
            // Convert to PNG with 32-bit color depth for better gradient quality
            $imagick->setImageFormat('png32');
            $imagick->setImageColorspace(\Imagick::COLORSPACE_SRGB);
            $imagick->setImageDepth(8);
            
            // Enable better quality rendering
            $imagick->setImageInterpolateMethod(\Imagick::INTERPOLATE_BICUBIC);
            
            // Create a white background image and composite the SVG on top
            // This preserves gradients while ensuring white background
            $background = new \Imagick();
            $background->newImage($imagick->getImageWidth(), $imagick->getImageHeight(), new \ImagickPixel('white'));
            $background->setImageFormat('png32');
            
            // Composite the SVG onto the white background
            $background->compositeImage($imagick, \Imagick::COMPOSITE_OVER, 0, 0);
            
            // Create output path
            $tempDir = sys_get_temp_dir();
            $pngPath = tempnam($tempDir, 'logo_' . $logoId . '_converted_') . '.png';
            
            // Write the PNG file
            $background->writeImage($pngPath);
            
            // Clean up
            $imagick->clear();
            $imagick->destroy();
            $background->clear();
            $background->destroy();
            
            // Verify the converted file exists and has content
            if (file_exists($pngPath) && filesize($pngPath) > 0) {
                return $pngPath;
            } else {
                return null;
            }
            
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Clean up temporary files and directories
     */
    private function cleanupTempFiles(string $basePath): void
    {
        if (!is_dir($basePath)) {
            return;
        }

        try {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($basePath, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($files as $fileinfo) {
                if ($fileinfo->isDir()) {
                    rmdir($fileinfo->getRealPath());
                } else {
                    unlink($fileinfo->getRealPath());
                }
            }

            rmdir($basePath);
        } catch (\Exception $e) {
            // Log error but don't fail the request
            error_log('Failed to cleanup temporary files: ' . $e->getMessage());
        }
    }
}