<?php

namespace App\Controller;

use App\Entity\Instrument;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Route(path: '/api/v1/instruments', name: 'api_instruments_')]
class ApiInstrumentsController extends AbstractController
{
    
    #[Route(path: '', name: 'index', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Returns all instruments',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Instrument::class, groups: ['id', 'instrument']))
        )
    )]
    #[OA\Tag(name: 'Instruments')]
    public function index(EntityManagerInterface $em, NormalizerInterface $normalizer): JsonResponse
    {
        $instruments = $em->getRepository(Instrument::class)->findBy([
            //'isPublic' => 1
        ], [], 10000);

        $result = $normalizer->normalize($instruments, null, [
            'groups' => ['id', 'instrument'],
        ]);

        return $this->json($result);
    }
    
    #[Route(path: '/{id}', name: 'get', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Returns a single instrument',
        content: new OA\JsonContent(
            ref: new Model(type: Instrument::class, groups: ['id', 'instrument'])
        )
    )]
    #[OA\Tag(name: 'Instruments')]
    public function find(Request $request, EntityManagerInterface $em, NormalizerInterface $normalizer): JsonResponse
    {
        $instrument = $em->getRepository(Instrument::class)
            ->find($request->get('id'));

        $result = $normalizer->normalize($instrument, null, [
            'groups' => ['id', 'instrument'],
        ]);

        return $this->json($result);
    }

    #[OA\Tag(name: 'Instruments')]
    #[Route(path: '/create', name: 'create', methods: ['POST'])]
    #[Security(name: 'cookieAuth')]
    #[OA\RequestBody(
        description: 'Create a new instrument',
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'name', type: 'string', description: 'The name of the instrument'),
                new OA\Property(property: 'context', type: 'string', description: 'The context of the instrument')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Creates a new instrument',
        content: new OA\JsonContent(
            ref: new Model(type: Instrument::class, groups: ['id', 'instrument'])
        )
    )]
    public function create(Request $request, EntityManagerInterface $em, NormalizerInterface $normalizer): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        // Get max position
        $maxPosition = $em->createQueryBuilder()
            ->select('MAX(i.position)')
            ->from(Instrument::class, 'i')
            ->getQuery()
            ->getSingleScalarResult();
        
        $instrument = new Instrument();
        $instrument->setName($data['name']);
        $instrument->setIsPublic(true);
        $instrument->setCreatedAt(new \DateTime());
        $instrument->setUpdatedAt(new \DateTime());
        $instrument->setContext($data['context'] ? $data['context'] : 'financial-support');
        $instrument->setPosition($maxPosition ? $maxPosition + 1 : 1);
        $instrument->setTranslations(['fr' => new \stdClass(), 'it' => new \stdClass()]);
        $instrument->setSynonyms(new \stdClass());
        
        $em->persist($instrument);
        $em->flush();
    
        $result = $normalizer->normalize($instrument, null, ['groups' => ['id', 'instrument']]);
    
        return $this->json($result);
    }
}