<?php

namespace App\Controller;

use App\Entity\Beneficiary;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Route(path: '/api/v1/beneficiaries', name: 'api_beneficiaries_')]
class ApiBeneficiariesController extends AbstractController
{

    #[Route(path: '', name: 'index', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Returns all beneficiaries',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(ref: new Model(type: Beneficiary::class, groups: ['id', 'beneficiary']))
        )
    )]
    #[OA\Tag(name: 'Beneficiaries')]
    public function index(EntityManagerInterface $em, NormalizerInterface $normalizer): JsonResponse
    {
        $beneficiaries = $em->getRepository(Beneficiary::class)->findBy([
            //'isPublic' => 1
        ], [], 10000);

        $result = $normalizer->normalize($beneficiaries, null, [
            'groups' => ['id', 'beneficiary'],
        ]);

        return $this->json($result);
    }

    #[Route(path: '/{id}', name: 'get', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: 'Returns a single beneficiary',
        content: new OA\JsonContent(
            ref: new Model(type: Beneficiary::class, groups: ['id', 'beneficiary'])
        )
    )]
    #[OA\Tag(name: 'Beneficiaries')]
    public function find(Request $request, EntityManagerInterface $em, NormalizerInterface $normalizer): JsonResponse
    {
        $beneficiary = $em->getRepository(Beneficiary::class)
            ->find($request->get('id'));

        $result = $normalizer->normalize($beneficiary, null, [
            'groups' => ['id', 'beneficiary'],
        ]);

        return $this->json($result);
    }

    #[OA\Tag(name: 'Beneficiaries')]
    #[Route(path: '/create', name: 'create', methods: ['POST'])]
    #[Security(name: 'cookieAuth')]
    #[OA\RequestBody(
        description: 'Create a new beneficiary',
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'name', type: 'string', description: 'The name of the beneficiary'),
                new OA\Property(property: 'context', type: 'string', description: 'The context of the beneficiary')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Creates a new beneficiary',
        content: new OA\JsonContent(
            ref: new Model(type: Beneficiary::class, groups: ['id', 'beneficiary'])
        )
    )]
    public function create(Request $request, EntityManagerInterface $em, NormalizerInterface $normalizer): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        // Get max position
        $maxPosition = $em->createQueryBuilder()
            ->select('MAX(b.position)')
            ->from(Beneficiary::class, 'b')
            ->getQuery()
            ->getSingleScalarResult();
        
        $beneficiary = new Beneficiary();
        $beneficiary->setName($data['name']);
        $beneficiary->setIsPublic(true);
        $beneficiary->setCreatedAt(new \DateTime());
        $beneficiary->setUpdatedAt(new \DateTime());
        $beneficiary->setContext($data['context'] ? $data['context'] : 'financial-support');
        $beneficiary->setPosition($maxPosition ? $maxPosition + 1 : 1);
        $beneficiary->setTranslations(['fr' => new \stdClass(), 'it' => new \stdClass()]);
        $beneficiary->setSynonyms(new \stdClass());
        
        $em->persist($beneficiary);
        $em->flush();
    
        $result = $normalizer->normalize($beneficiary, null, ['groups' => ['id', 'beneficiary']]);
    
        return $this->json($result);
    }
}