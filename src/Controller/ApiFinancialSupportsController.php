<?php

namespace App\Controller;

use App\Entity\File;
use App\Entity\FinancialSupport;
use App\Service\FinancialSupportService;
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
    
    #[Route(path: '/export-all-zip', name: 'export_all_zip', methods: ['GET'])]
    #[IsGranted('ROLE_EDITOR')]
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
            $imagick = new \Imagick();
            $data = stream_get_contents($file->getData());
            $data = count(explode(';base64,', $data)) >= 2 ? explode(';base64,', $data, 2)[1] : $data;
            $imagick->readImageBlob(base64_decode($data));

            $logo = tempnam(sys_get_temp_dir(), 'logo'.$file->getId());
            file_put_contents($logo, $imagick->getImageBlob());
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
}