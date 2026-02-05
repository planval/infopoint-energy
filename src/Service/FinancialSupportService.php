<?php

namespace App\Service;

use App\Entity\Authority;
use App\Entity\Beneficiary;
use App\Entity\GeographicRegion;
use App\Entity\Instrument;
use App\Entity\ProjectType;
use App\Entity\State;
use App\Entity\Topic;
use App\Util\PvTrans;
use Doctrine\Common\Collections\ArrayCollection;
use App\Entity\FinancialSupport;
use Doctrine\ORM\EntityManagerInterface;

class FinancialSupportService {

    protected $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function validateFields($payload, $fields = [])
    {
        foreach($fields as $field) {
            if(!array_key_exists($field, $payload)) {
                return [
                    [
                        'field' => $field,
                    ]
                ];
            }
        }

        return true;
    }

    public function validateFinancialSupportPayload($payload)
    {
        if(($errors = $this->validateFields($payload, [
            'position',
            'isPublic',
            'name',
            'description',
            'additionalInformation',
            'policies',
            'application',
            'applicationTips',
            'inclusionCriteria',
            'exclusionCriteria',
            'financingRatio',
            'res',
            'startDate',
            'endDate',
            'beneficiaries',
            'topics',
            'projectTypes',
            'instruments',
            'geographicRegions',
            'links',
            'examples',
            'contacts',
            'logo',
            'translations',
            'appointments',
            'assignment',
            'fundingProvider',
        ])) !== true) {
            return $errors;
        }

        return true;
    }

    public function createFinancialSupport($payload)
    {
        $financialSupport = new FinancialSupport();

        $financialSupport->setCreatedAt(new \DateTime());

        $financialSupport = $this->applyFinancialSupportPayload($payload, $financialSupport);
        $financialSupport->setSearchIndex($this->buildSearchIndex($financialSupport));

        $this->em->persist($financialSupport);
        $this->em->flush();

        return $financialSupport;
    }

    public function updateFinancialSupport($financialSupport, $payload)
    {
        $financialSupport->setUpdatedAt(new \DateTime());

        $financialSupport = $this->applyFinancialSupportPayload($payload, $financialSupport);
        $financialSupport->setSearchIndex($this->buildSearchIndex($financialSupport));

        $this->em->persist($financialSupport);
        $this->em->flush();

        return $financialSupport;
    }

    public function deleteFinancialSupport($financialSupport)
    {
        $this->em->remove($financialSupport);
        $this->em->flush();

        return $financialSupport;
    }

    public function buildSearchIndex(FinancialSupport $financialSupport): string
    {
        $searchIndex = [];

        foreach(['de', 'fr', 'it'] as $locale) {
            $searchIndex[] = PvTrans::translate($financialSupport, 'name', $locale);
            $searchIndex[] = PvTrans::translate($financialSupport, 'description', $locale);
            $searchIndex[] = html_entity_decode(strip_tags(PvTrans::translate($financialSupport, 'description', $locale)));
            $searchIndex[] = PvTrans::translate($financialSupport, 'additionalInformation', $locale);
            $searchIndex[] = PvTrans::translate($financialSupport, 'inclusionCriteria', $locale);
            $searchIndex[] = PvTrans::translate($financialSupport, 'exclusionCriteria', $locale);
            $searchIndex[] = PvTrans::translate($financialSupport, 'application', $locale);
            $searchIndex[] = PvTrans::translate($financialSupport, 'financingRatio', $locale);
            $searchIndex[] = PvTrans::translate($financialSupport, 'res', $locale);
            $searchIndex[] = PvTrans::translate($financialSupport, 'fundingProvider', $locale);

            foreach(PvTrans::translate($financialSupport, 'contacts', $locale) as $contact) {
                $searchIndex[] = implode(', ', array_filter($contact));
            }

            foreach($financialSupport->getAuthorities() as $e) {
                $searchIndex[] = PvTrans::translate($e, 'name', $locale);
            }

            foreach($financialSupport->getStates() as $e) {
                $searchIndex[] = PvTrans::translate($e, 'name', $locale);
            }

            foreach($financialSupport->getBeneficiaries() as $e) {
                $searchIndex[] = PvTrans::translate($e, 'name', $locale);
            }

            foreach($financialSupport->getTopics() as $e) {
                $searchIndex[] = PvTrans::translate($e, 'name', $locale);
            }

            foreach($financialSupport->getProjectTypes() as $e) {
                $searchIndex[] = PvTrans::translate($e, 'name', $locale);
            }

            foreach($financialSupport->getInstruments() as $e) {
                $searchIndex[] = PvTrans::translate($e, 'name', $locale);
            }

            foreach($financialSupport->getGeographicRegions() as $e) {
                $searchIndex[] = PvTrans::translate($e, 'name', $locale);
            }       
        }

        return implode(PHP_EOL, array_unique(array_filter($searchIndex)));
    }

    public function applyFinancialSupportPayload($payload, FinancialSupport $financialSupport)
    {
        // Initialize translations if not set
        $translations = $payload['translations'] ?: [];
        if (!isset($translations['de'])) {
            $translations['de'] = [];
        }

        // Move fundingProvider to translations
        if (isset($payload['fundingProvider'])) {
            $translations['de']['fundingProvider'] = $payload['fundingProvider'];
            unset($payload['fundingProvider']);
        }

        $financialSupport
            ->setPosition($payload['position'])
            ->setIsPublic($payload['isPublic'])
            ->setName($payload['name'])
            ->setLogo($payload['logo'])
            ->setDescription($payload['description'])
            ->setAdditionalInformation($payload['additionalInformation'])
            ->setPolicies($payload['policies'])
            ->setApplication($payload['application'])
            ->setApplicationTips($payload['applicationTips'])
            ->setInclusionCriteria($payload['inclusionCriteria'])
            ->setExclusionCriteria($payload['exclusionCriteria'])
            ->setFinancingRatio($payload['financingRatio'])
            ->setRes($payload['res'])
            ->setStartDate($payload['startDate'] ? new \DateTime(date('Y-m-d H:i:s', strtotime($payload['startDate']))) : null)
            ->setEndDate($payload['endDate'] ? new \DateTime(date('Y-m-d H:i:s', strtotime($payload['endDate']))) : null)
            ->setLinks($payload['links'] ?: [])
            ->setExamples($payload['examples'] ?: [])
            ->setContacts($payload['contacts'] ?: [])
            ->setAuthorities(new ArrayCollection())
            ->setStates(new ArrayCollection())
            ->setBeneficiaries(new ArrayCollection())
            ->setTopics(new ArrayCollection())
            ->setProjectTypes(new ArrayCollection())
            ->setInstruments(new ArrayCollection())
            ->setGeographicRegions(new ArrayCollection())
            ->setTranslations($translations)
            ->setAppointments($payload['appointments'] ?: [])
            ->setAssignment($payload['assignment'] ?: null)
            ->setOtherOptionValues($payload['otherOptionValues'] ?: null)
            ->setFundingProvider($translations['de']['fundingProvider'] ?? null);

        // Only process authorities if they exist in the payload
        if (isset($payload['authorities']) && is_array($payload['authorities'])) {
            foreach($payload['authorities'] as $item) {
                $entity = null;
                if(array_key_exists('id', $item) && $item['id']) {
                    $entity = $this->em->getRepository(Authority::class)->find($item['id']);
                }
                if(!$entity && array_key_exists('name', $item)) {
                    $entity = $this->em->getRepository(Authority::class)
                        ->findOneBy(['name' => $item['name']]);
                }
                if($entity) {
                    $financialSupport->addAuthority($entity);
                }
            }
        }

        // Only process states if they exist in the payload
        if (isset($payload['states']) && is_array($payload['states'])) {
            foreach($payload['states'] as $item) {
                $entity = null;
                if(array_key_exists('id', $item) && $item['id']) {
                $entity = $this->em->getRepository(State::class)->find($item['id']);
            }
            if(!$entity && array_key_exists('name', $item)) {
                $entity = $this->em->getRepository(State::class)
                    ->findOneBy(['name' => $item['name']]);
                }
                if($entity) {
                    $financialSupport->addState($entity);
                }
            }
        }

        foreach($payload['beneficiaries'] as $item) {
            $entity = null;
            if(array_key_exists('id', $item) && $item['id']) {
                $entity = $this->em->getRepository(Beneficiary::class)->find($item['id']);
            }
            if(!$entity && array_key_exists('name', $item)) {
                $entity = $this->em->getRepository(Beneficiary::class)
                    ->findOneBy(['name' => $item['name']]);
            }
            if($entity) {
                $financialSupport->addBeneficiary($entity);
            }
        }

        foreach($payload['topics'] as $item) {
            $entity = null;
            if(array_key_exists('id', $item) && $item['id']) {
                $entity = $this->em->getRepository(Topic::class)->find($item['id']);
            }
            if(!$entity && array_key_exists('name', $item)) {
                $entity = $this->em->getRepository(Topic::class)
                    ->findOneBy(['name' => $item['name']]);
            }
            if($entity) {
                $financialSupport->addTopic($entity);
            }
        }

        foreach($payload['projectTypes'] as $item) {
            $entity = null;
            if(array_key_exists('id', $item) && $item['id']) {
                $entity = $this->em->getRepository(ProjectType::class)->find($item['id']);
            }
            if(!$entity && array_key_exists('name', $item)) {
                $entity = $this->em->getRepository(ProjectType::class)
                    ->findOneBy(['name' => $item['name']]);
            }
            if($entity) {
                $financialSupport->addProjectType($entity);
            }
        }

        foreach($payload['instruments'] as $item) {
            $entity = null;
            if(array_key_exists('id', $item) && $item['id']) {
                $entity = $this->em->getRepository(Instrument::class)->find($item['id']);
            }
            if(!$entity && array_key_exists('name', $item)) {
                $entity = $this->em->getRepository(Instrument::class)
                    ->findOneBy(['name' => $item['name']]);
            }
            if($entity) {
                $financialSupport->addInstrument($entity);
            }
        }

        foreach($payload['geographicRegions'] as $item) {
            $entity = null;
            if(array_key_exists('id', $item) && $item['id']) {
                $entity = $this->em->getRepository(GeographicRegion::class)->find($item['id']);
            }
            if(!$entity && array_key_exists('name', $item)) {
                $entity = $this->em->getRepository(GeographicRegion::class)
                    ->findOneBy(['name' => $item['name']]);
            }
            if($entity) {
                $financialSupport->addGeographicRegion($entity);
            }
        }

        return $financialSupport;
    }

}