<?php

namespace App\Controller;

use App\Entity\File;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Attributes as OA;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Route(path: '/api/v1/files', name: 'api_files_')]
class ApiFilesController extends AbstractController
{
    
    #[Route(path: '', name: 'index', methods: ['GET'])]
    #[IsGranted('ROLE_EDITOR')]
    #[OA\Response(
        response: 200,
        description: 'Returns all files',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: File::class, groups: ['id', 'file']))
        )
    )]
    #[OA\Tag(name: 'Files')]
    #[Security(name: 'cookieAuth')]
    public function index(EntityManagerInterface $em,
                          NormalizerInterface $normalizer): JsonResponse
    {
        $files = $em->getRepository(File::class)->findBy([
            //'isPublic' => 1
        ], [], 10000);

        $result = $normalizer->normalize($files, null, [
            'groups' => ['id', 'file'],
        ]);

        return $this->json($result);
    }
    
    #[Route(path: '/{id}', name: 'get', methods: ['GET'])]
    #[IsGranted('ROLE_EDITOR')]
    #[OA\Response(
        response: 200,
        description: 'Returns a single file',
        content: new OA\JsonContent(
            ref: new Model(type: File::class, groups: ['id', 'file'])
        )
    )]
    #[OA\Tag(name: 'Files')]
    #[Security(name: 'cookieAuth')]
    public function find(Request $request, EntityManagerInterface $em,
                         NormalizerInterface $normalizer): JsonResponse
    {
        $file = $em->getRepository(File::class)
            ->find($request->get('id'));

        if(!$file) {
            throw $this->createNotFoundException();
        }

        $result = $normalizer->normalize($file, null, [
            'groups' => ['id', 'file'],
        ]);

        return $this->json($result);
    }
    
    #[Route(path: '', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_EDITOR')]
    #[OA\Response(
        response: 200,
        description: 'Create a file',
        content: new OA\JsonContent(
            ref: new Model(type: File::class, groups: ['id', 'file'])
        )
    )]
    #[OA\Tag(name: 'Files')]
    #[Security(name: 'cookieAuth')]
    public function create(Request $request, EntityManagerInterface $em,
                           NormalizerInterface $normalizer): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        $file = new File();
        $file
            ->setName($payload['name'])
            ->setCreatedAt(new \DateTime())
            ->setData($payload['data'])
            ->setHash(md5($payload['data']))
            ->setMimeType($payload['mimeType'])
            ->setExtension(strtolower($payload['extension']))
        ;

        $existing = $em->getRepository(File::class)->findOneBy([
            'hash' => $file->getHash(),
        ]);

        if(!$existing) {
            $em->persist($file);
            $em->flush();
        } else {
            $file = $existing;
        }

        $result = $normalizer->normalize($file, null, [
            'groups' => ['id', 'file'],
        ]);

        return $this->json($result);
    }
    
    #[Route(path: '/view/{id}.{extension}', name: 'view_image', requirements: ['extension' => 'jpg|png|gif|JPG|PNG|GIF|svg|SVG'], methods: ['GET'])]
    public function viewImage(Request $request, EntityManagerInterface $em,
                              NormalizerInterface $normalizer): Response
    {
        $file = $em->getRepository(File::class)
            ->findOneBy([
                'id' => $request->get('id'),
                'extension' => $request->get('extension')
            ]);

        if(!$file) {
            throw $this->createNotFoundException();
        }

        $data = stream_get_contents($file->getData());
        $data = count(explode(';base64,', $data)) >= 2 ? explode(';base64,', $data, 2)[1] : $data;
        
        // Handle SVG files directly without ImageMagick processing
        if (strtolower($file->getExtension()) === 'svg') {
            $response = new Response(base64_decode($data), 200, [
                'Content-Type' => 'image/svg+xml',
            ]);
        } else {
            $imagick = new \Imagick();
            $imagick->readImageBlob(base64_decode($data));

            $response = new Response($imagick->getImageBlob(), 200, [
                'Content-Type' => $imagick->getImageMimeType(),
            ]);
        }

        $response->setMaxAge(3600);
        $response->setSharedMaxAge(3600);

        return $response;
    }
    
    #[Route(path: '/view/{id}.{extension}', name: 'preview_pdf', requirements: ['extension' => 'pdf|PDF'], methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Returns a file',
        content: new OA\MediaType(mediaType: '*/*', schema: new OA\Schema(type: 'string', format: 'binary'))
    )]
    #[OA\Tag(name: 'Files')]
    public function previewPdf(Request $request, EntityManagerInterface $em,
                               NormalizerInterface $normalizer): Response
    {
        $file = $em->getRepository(File::class)
            ->findOneBy([
                'id' => $request->get('id'),
                'extension' => $request->get('extension')
            ]);

        if(!$file) {
            throw $this->createNotFoundException();
        }

        $data = stream_get_contents($file->getData());
        $data = count(explode(';base64,', $data)) >= 2 ? explode(';base64,', $data, 2)[1] : $data;
        $content = base64_decode($data);

        return new Response($content, 200, [
            'Content-Type' => $file->getMimeType(),
        ]);

    }
    
    #[Route(path: '/download/{id}.{extension}', name: 'download', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Returns a file',
        headers: [
            new OA\Header(
                header: 'Content-Disposition',
                schema: new OA\Schema( type: 'string', default: 'attachment; filename="*.*"' )
            )
        ],
        content: new OA\MediaType(mediaType: '*/*', schema: new OA\Schema(type: 'string', format: 'binary'))
    )]
    #[OA\Tag(name: 'Files')]
    public function download(Request $request, EntityManagerInterface $em,
                             NormalizerInterface $normalizer): Response
    {
        $file = $em->getRepository(File::class)
            ->findOneBy([
                'id' => $request->get('id'),
                'extension' => $request->get('extension')
            ]);

        if(!$file) {
            throw $this->createNotFoundException();
        }

        $data = stream_get_contents($file->getData());
        $data = count(explode(';base64,', $data)) >= 2 ? explode(';base64,', $data, 2)[1] : $data;
        $content = base64_decode($data);

        $filename = $request->get('id').'.'.$request->get('extension');

        if($file->getName()) {
            $filename = $file->getName();
        }

        return new Response($content, 200, [
            'Content-Type' => $file->getMimeType(),
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);

    }
    
}