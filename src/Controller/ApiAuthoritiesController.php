<?php

namespace App\Controller;

use App\Entity\Authority;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Security\Core\Security;

#[Route(path: '/api/v1/authorities', name: 'api_authorities_')]
class ApiAuthoritiesController extends AbstractController
{

    #[Route(path: '', name: 'index', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Returns all authorities',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Authority::class, groups: ['id', 'authority']))
        )
    )]
    #[OA\Tag(name: 'Authorities')]
    public function index(EntityManagerInterface $em, NormalizerInterface $normalizer): JsonResponse
    {
        $authorities = $em->getRepository(Authority::class)->findBy([
            //'isPublic' => 1
        ], [], 10000);

        $result = $normalizer->normalize($authorities, null, [
            'groups' => ['id', 'authority'],
        ]);

        return $this->json($result);
    }

    #[Route(path: '/{id}', name: 'get', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Returns a single authority',
        content: new OA\JsonContent(
            ref: new Model(type: Authority::class, groups: ['id', 'authority'])
        )
    )]
    #[OA\Tag(name: 'Authorities')]
    public function find(Request $request, EntityManagerInterface $em, NormalizerInterface $normalizer): JsonResponse
    {
        $authority = $em->getRepository(Authority::class)
            ->find($request->get('id'));

        $result = $normalizer->normalize($authority, null, [
            'groups' => ['id', 'authority'],
        ]);

        return $this->json($result);
    }

    #[OA\Tag(name: 'Authorities')]
    #[Route(path: '/create', name: 'create', methods: ['POST'])]
    #[Security(name: 'cookieAuth')]
    #[OA\RequestBody(
        description: 'Create a new authority',
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'name', type: 'string', description: 'The name of the authority'),
                new OA\Property(property: 'context', type: 'string', description: 'The context of the authority')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Creates a new authority',
        content: new OA\JsonContent(
            ref: new Model(type: Authority::class, groups: ['id', 'authority'])
        )
    )]
    public function create(Request $request, EntityManagerInterface $em, NormalizerInterface $normalizer): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        // Get max position
        $maxPosition = $em->createQueryBuilder()
            ->select('MAX(a.position)')
            ->from(Authority::class, 'a')
            ->getQuery()
            ->getSingleScalarResult();
        
        $authority = new Authority();
        $authority->setName($data['name']);
        $authority->setIsPublic(true);
        $authority->setCreatedAt(new \DateTime());
        $authority->setUpdatedAt(new \DateTime());
        $authority->setContext($data['context'] ? $data['context'] : 'financial-support');
        $authority->setPosition($maxPosition ? $maxPosition + 1 : 1);
        $authority->setTranslations(['fr' => new \stdClass(), 'it' => new \stdClass()]);
        $authority->setSynonyms(new \stdClass());
        $authority->setIsStateSupported(false);
        
        $em->persist($authority);
        $em->flush();
    
        $result = $normalizer->normalize($authority, null, ['groups' => ['id', 'authority']]);
    
        return $this->json($result);
    }
}