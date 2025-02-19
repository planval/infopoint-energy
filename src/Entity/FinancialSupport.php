<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use OpenApi\Attributes as OA;

/**
 * FinancialSupport
 */
#[ORM\Table(name: 'pv_financial_support')]
#[ORM\Entity(repositoryClass: 'App\Repository\FinancialSupportRepository')]
class FinancialSupport
{

    #[ORM\Column(name: 'id', type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[Groups(['id', 'financial_support'])]
    private $id;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    #[Groups(['financial_support'])]
    private $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime', nullable: true)]
    #[Groups(['financial_support'])]
    private $updatedAt;

    #[ORM\Column(name: 'is_public', type: 'boolean')]
    #[Groups(['financial_support'])]
    private $isPublic;

    #[ORM\Column(name: 'position', type: 'integer')]
    #[Groups(['financial_support'])]
    private $position;

    #[ORM\Column(name: 'name', type: 'text', nullable: true)]
    #[Groups(['financial_support'])]
    private $name;

    #[ORM\Column(name: 'description', type: 'text', nullable: true)]
    #[Groups(['financial_support'])]
    private $description;

    #[ORM\Column(name: 'policies', type: 'string', nullable: true)]
    #[Groups(['financial_support'])]
    private $policies;

    #[ORM\Column(name: 'additional_information', type: 'text', nullable: true)]
    #[Groups(['financial_support'])]
    private $additionalInformation;

    #[ORM\Column(name: 'inclusion_criteria', type: 'text', nullable: true)]
    #[Groups(['financial_support'])]
    private $inclusionCriteria;

    #[ORM\Column(name: 'exclusion_criteria', type: 'text', nullable: true)]
    #[Groups(['financial_support'])]
    private $exclusionCriteria;

    #[ORM\Column(name: 'application', type: 'text', nullable: true)]
    #[Groups(['financial_support'])]
    private $application;

    #[ORM\Column(name: 'application_tips', type: 'text', nullable: true)]
    #[Groups(['financial_support'])]
    private $applicationTips;

    #[ORM\Column(name: 'financing_ratio', type: 'text', nullable: true)]
    #[Groups(['financial_support'])]
    private $financingRatio;

    #[ORM\Column(name: 'res', type: 'text', nullable: true)]
    #[Groups(['financial_support'])]
    private $res;

    #[ORM\Column(name: 'start_date', type: 'datetime', nullable: true)]
    #[Groups(['financial_support'])]
    private $startDate;

    #[ORM\Column(name: 'end_date', type: 'datetime', nullable: true)]
    #[Groups(['financial_support'])]
    private $endDate;

    #[ORM\Column(name: 'logo', type: 'json', nullable: true)]
    #[Groups(['financial_support'])]
    #[OA\Property(properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'extension', type: 'string'),
        new OA\Property(property: 'mimeType', type: 'string'),
        new OA\Property(property: 'copyright', type: 'string'),
        new OA\Property(property: 'description', type: 'string'),
    ], type: 'object')]
    private $logo;

    #[ORM\Column(name: 'links', type: 'json')]
    #[Groups(['financial_support'])]
    #[OA\Property(type: 'array', items: new OA\Items(
        properties: [
            new OA\Property(property: 'value', type: 'string'),
            new OA\Property(property: 'label', type: 'string'),
        ],
        type: 'object'
    ))]
    private $links = [];

    #[ORM\Column(name: 'contacts', type: 'json')]
    #[Groups(['financial_support'])]
    #[OA\Property(type: 'array', items: new OA\Items(
        properties: [
            new OA\Property(property: 'foreignId', type: 'integer'),
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'salutation', type: 'string', enum: ['m', 'f', 'd']),
            new OA\Property(property: 'title', type: 'string'),
            new OA\Property(property: 'firstName', type: 'string'),
            new OA\Property(property: 'lastName', type: 'string'),
            new OA\Property(property: 'role', type: 'string'),
            new OA\Property(property: 'country', type: 'string'),
            new OA\Property(property: 'zipCode', type: 'string'),
            new OA\Property(property: 'city', type: 'string'),
            new OA\Property(property: 'email', type: 'string'),
            new OA\Property(property: 'phone', type: 'string'),
            new OA\Property(property: 'phoneMobile', type: 'string'),
            new OA\Property(property: 'public', type: 'boolean'),
        ],
        type: 'object'
    ))]
    private $contacts = [];

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    #[ORM\ManyToMany(targetEntity: 'Authority')]
    #[ORM\JoinTable(name: 'pv_financial_support_authority')]
    #[ORM\JoinColumn(name: 'financial_support_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'authority_id', referencedColumnName: 'id')]
    #[Groups(['financial_support'])]
    private $authorities;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    #[ORM\ManyToMany(targetEntity: 'Beneficiary')]
    #[ORM\JoinTable(name: 'pv_financial_support_beneficiary')]
    #[ORM\JoinColumn(name: 'financial_support_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'beneficiary_id', referencedColumnName: 'id')]
    #[Groups(['financial_support'])]
    private $beneficiaries;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    #[ORM\ManyToMany(targetEntity: 'Topic')]
    #[ORM\JoinTable(name: 'pv_financial_support_topic')]
    #[ORM\JoinColumn(name: 'financial_support_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'topic_id', referencedColumnName: 'id')]
    #[Groups(['financial_support'])]
    private $topics;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    #[ORM\ManyToMany(targetEntity: 'ProjectType')]
    #[ORM\JoinTable(name: 'pv_financial_support_project_type')]
    #[ORM\JoinColumn(name: 'financial_support_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'project_type_id', referencedColumnName: 'id')]
    #[Groups(['financial_support'])]
    private $projectTypes;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    #[ORM\ManyToMany(targetEntity: 'Instrument')]
    #[ORM\JoinTable(name: 'pv_financial_support_instrument')]
    #[ORM\JoinColumn(name: 'financial_support_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'instrument_id', referencedColumnName: 'id')]
    #[Groups(['financial_support'])]
    private $instruments;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    #[ORM\ManyToMany(targetEntity: 'GeographicRegion')]
    #[ORM\JoinTable(name: 'pv_financial_support_geographic_region')]
    #[ORM\JoinColumn(name: 'financial_support_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'geographic_region_id', referencedColumnName: 'id')]
    #[Groups(['financial_support'])]
    private $geographicRegions;

    /**
     * @var \Doctrine\Common\Collections\Collection
     */
    #[ORM\ManyToMany(targetEntity: 'State')]
    #[ORM\JoinTable(name: 'pv_financial_support_state')]
    #[ORM\JoinColumn(name: 'financial_support_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'state_id', referencedColumnName: 'id')]
    #[Groups(['financial_support'])]
    private $states;

    #[ORM\Column(name: 'translations', type: 'json')]
    #[Groups(['financial_support'])]
    #[OA\Property(properties: [
        new OA\Property(property: 'fr', type: 'object'),
        new OA\Property(property: 'it', type: 'object'),
    ], type: 'object')]
    private $translations = [];

    #[ORM\Column(name: 'appointments', type: 'json', nullable: true)]
    #[Groups(['financial_support'])]
    private $appointments = [];

    #[ORM\Column(name: 'search_index', type: 'text', nullable: true)]
    private $searchIndex;

    #[ORM\Column(name: 'assignment', type: 'text', nullable: true)]
    #[Groups(['financial_support'])]
    private $assignment;

    #[ORM\Column(name: 'other_option_values', type: 'json')]
    #[Groups(['financial_support'])]
    private $otherOptionValues = [];

    #[ORM\Column(name: 'funding_provider', type: 'string', length: 255, nullable: true)]
    #[Groups(['financial_support'])]
    private $fundingProvider;

    public function __construct()
    {
        $this->authorities = new ArrayCollection();
        $this->beneficiaries = new ArrayCollection();
        $this->topics = new ArrayCollection();
        $this->projectTypes = new ArrayCollection();
        $this->instruments = new ArrayCollection();
        $this->geographicRegions = new ArrayCollection();
        $this->states = new ArrayCollection();
    }

    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set createdAt
     *
     * @param \DateTime $createdAt
     *
     * @return FinancialSupport
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Get createdAt
     *
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Set updatedAt
     *
     * @param \DateTime $updatedAt
     *
     * @return FinancialSupport
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * Get updatedAt
     *
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * Set isPublic
     *
     * @param boolean $isPublic
     *
     * @return FinancialSupport
     */
    public function setIsPublic($isPublic)
    {
        $this->isPublic = $isPublic;

        return $this;
    }

    /**
     * Get isPublic
     *
     * @return bool
     */
    public function getIsPublic()
    {
        return $this->isPublic;
    }

    /**
     * Set position
     *
     * @param boolean $position
     *
     * @return FinancialSupport
     */
    public function setPosition($position)
    {
        $this->position = $position;

        return $this;
    }

    /**
     * Get position
     *
     * @return bool
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * Set name
     *
     * @param string $name
     *
     * @return FinancialSupport
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set description
     *
     * @param string $description
     *
     * @return FinancialSupport
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get description
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set policies
     *
     * @param string|null $policies
     *
     * @return FinancialSupport
     */
    public function setPolicies($policies)
    {
        $this->policies = $policies;

        return $this;
    }

    /**
     * Get policies
     *
     * @return string
     */
    public function getPolicies()
    {
        return $this->policies;
    }

    /**
     * Set additionalInformation
     *
     * @param string $additionalInformation
     *
     * @return FinancialSupport
     */
    public function setAdditionalInformation($additionalInformation)
    {
        $this->additionalInformation = $additionalInformation;

        return $this;
    }

    /**
     * Get additionalInformation
     *
     * @return string
     */
    public function getAdditionalInformation()
    {
        return $this->additionalInformation;
    }

    /**
     * Set inclusionCriteria
     *
     * @param string $inclusionCriteria
     *
     * @return FinancialSupport
     */
    public function setInclusionCriteria($inclusionCriteria)
    {
        $this->inclusionCriteria = $inclusionCriteria;

        return $this;
    }

    /**
     * Get inclusionCriteria
     *
     * @return string
     */
    public function getInclusionCriteria()
    {
        return $this->inclusionCriteria;
    }

    /**
     * Set exclusionCriteria
     *
     * @param string $exclusionCriteria
     *
     * @return FinancialSupport
     */
    public function setExclusionCriteria($exclusionCriteria)
    {
        $this->exclusionCriteria = $exclusionCriteria;

        return $this;
    }

    /**
     * Get exclusionCriteria
     *
     * @return string
     */
    public function getExclusionCriteria()
    {
        return $this->exclusionCriteria;
    }

    /**
     * Set application
     *
     * @param string $application
     *
     * @return FinancialSupport
     */
    public function setApplication($application)
    {
        $this->application = $application;

        return $this;
    }

    /**
     * Get application
     *
     * @return string
     */
    public function getApplication()
    {
        return $this->application;
    }

    /**
     * Set applicationTips
     *
     * @param string $applicationTips
     *
     * @return FinancialSupport
     */
    public function setApplicationTips($applicationTips)
    {
        $this->applicationTips = $applicationTips;

        return $this;
    }

    /**
     * Get applicationTips
     *
     * @return string
     */
    public function getApplicationTips()
    {
        return $this->applicationTips;
    }

    /**
     * Set financingRatio
     *
     * @param string $financingRatio
     *
     * @return FinancialSupport
     */
    public function setFinancingRatio($financingRatio)
    {
        $this->financingRatio = $financingRatio;

        return $this;
    }

    /**
     * Get financingRatio
     *
     * @return string
     */
    public function getFinancingRatio()
    {
        return $this->financingRatio;
    }

    /**
     * Set res
     *
     * @param string $res
     *
     * @return FinancialSupport
     */
    public function setRes($res)
    {
        $this->res = $res;

        return $this;
    }

    /**
     * Get res
     *
     * @return string
     */
    public function getRes()
    {
        return $this->res;
    }

    /**
     * Set startDate
     *
     * @param \DateTime $startDate
     *
     * @return FinancialSupport
     */
    public function setStartDate($startDate)
    {
        $this->startDate = $startDate;

        return $this;
    }

    /**
     * Get startDate
     *
     * @return \DateTime
     */
    public function getStartDate()
    {
        return $this->startDate;
    }

    /**
     * Set endDate
     *
     * @param \DateTime $endDate
     *
     * @return FinancialSupport
     */
    public function setEndDate($endDate)
    {
        $this->endDate = $endDate;

        return $this;
    }

    /**
     * Get endDate
     *
     * @return \DateTime
     */
    public function getEndDate()
    {
        return $this->endDate;
    }

    /**
     * Set logo
     *
     * @param array $logo
     *
     * @return FinancialSupport
     */
    public function setLogo($logo)
    {
        $this->logo = $logo;

        return $this;
    }

    /**
     * Get logo
     *
     * @return array
     */
    public function getLogo()
    {
        return $this->logo;
    }

    /**
     * Set links
     *
     * @param array $links
     *
     * @return FinancialSupport
     */
    public function setLinks($links)
    {
        $this->links = $links;

        return $this;
    }

    /**
     * Get links
     *
     * @return array
     */
    public function getLinks()
    {
        return $this->links;
    }

    /**
     * Set contacts
     *
     * @param array $contacts
     *
     * @return FinancialSupport
     */
    public function setContacts($contacts)
    {
        $this->contacts = $contacts;

        return $this;
    }

    /**
     * Get contacts
     *
     * @return array
     */
    public function getContacts()
    {
        return $this->contacts;
    }

    /**
     * Get authorities
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getAuthorities() {
        return $this->authorities;
    }

    /**
     * Set authorities
     *
     * @return $this
     */
    public function setAuthorities($authorities) {
        $this->authorities = $authorities;

        return $this;
    }

    /**
     * Add to authorities
     *
     * @param $authority
     * @return FinancialSupport
     */
    public function addAuthority($authority) {
        if (!$this->authorities->contains($authority)) {
            $this->authorities->add($authority);
        }

        return $this;
    }

    /**
     * Remove authorities
     *
     * @param $authorities
     */
    public function removeAuthority($authorities) {
        $this->authorities->removeElement($authorities);
    }

    /**
     * Get beneficiaries
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getBeneficiaries() {
        return $this->beneficiaries;
    }

    /**
     * Set beneficiaries
     *
     * @return $this
     */
    public function setBeneficiaries($beneficiaries) {
        $this->beneficiaries = $beneficiaries;

        return $this;
    }

    /**
     * Add to beneficiaries
     *
     * @param $beneficiary
     * @return FinancialSupport
     */
    public function addBeneficiary($beneficiary) {
        if (!$this->beneficiaries->contains($beneficiary)) {
            $this->beneficiaries->add($beneficiary);
        }

        return $this;
    }

    /**
     * Remove beneficiaries
     *
     * @param $beneficiaries
     */
    public function removeBeneficiary($beneficiaries) {
        $this->beneficiaries->removeElement($beneficiaries);
    }

    /**
     * Get topics
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getTopics() {
        return $this->topics;
    }

    /**
     * Set topics
     *
     * @return $this
     */
    public function setTopics($topics) {
        $this->topics = $topics;

        return $this;
    }

    /**
     * Add to topics
     *
     * @param $topic
     * @return FinancialSupport
     */
    public function addTopic($topic) {
        if (!$this->topics->contains($topic)) {
            $this->topics->add($topic);
        }

        return $this;
    }

    /**
     * Remove topics
     *
     * @param $topics
     */
    public function removeTopic($topics) {
        $this->topics->removeElement($topics);
    }

    /**
     * Get projectTypes
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getProjectTypes() {
        return $this->projectTypes;
    }

    /**
     * Set projectTypes
     *
     * @return $this
     */
    public function setProjectTypes($projectTypes) {
        $this->projectTypes = $projectTypes;

        return $this;
    }

    /**
     * Add to projectTypes
     *
     * @param $projectType
     * @return FinancialSupport
     */
    public function addProjectType($projectType) {
        if (!$this->projectTypes->contains($projectType)) {
            $this->projectTypes->add($projectType);
        }

        return $this;
    }

    /**
     * Remove projectTypes
     *
     * @param $projectTypes
     */
    public function removeProjectType($projectTypes) {
        $this->projectTypes->removeElement($projectTypes);
    }

    /**
     * Get instruments
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getInstruments() {
        return $this->instruments;
    }

    /**
     * Set instruments
     *
     * @return $this
     */
    public function setInstruments($instruments) {
        $this->instruments = $instruments;

        return $this;
    }

    /**
     * Add to instruments
     *
     * @param $instrument
     * @return FinancialSupport
     */
    public function addInstrument($instrument) {
        if (!$this->instruments->contains($instrument)) {
            $this->instruments->add($instrument);
        }

        return $this;
    }

    /**
     * Remove instruments
     *
     * @param $instruments
     */
    public function removeInstrument($instruments) {
        $this->instruments->removeElement($instruments);
    }

    /**
     * Get geographicRegions
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getGeographicRegions() {
        return $this->geographicRegions;
    }

    /**
     * Set geographicRegions
     *
     * @return $this
     */
    public function setGeographicRegions($geographicRegions) {
        $this->geographicRegions = $geographicRegions;

        return $this;
    }

    /**
     * Add to geographicRegions
     *
     * @param $geographicRegion
     * @return FinancialSupport
     */
    public function addGeographicRegion($geographicRegion) {
        if (!$this->geographicRegions->contains($geographicRegion)) {
            $this->geographicRegions->add($geographicRegion);
        }

        return $this;
    }

    /**
     * Remove geographicRegions
     *
     * @param $geographicRegions
     */
    public function removeGeographicRegion($geographicRegions) {
        $this->geographicRegions->removeElement($geographicRegions);
    }

    /**
     * Get states
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getStates() {
        return $this->states;
    }

    /**
     * Set states
     *
     * @return $this
     */
    public function setStates($states) {
        $this->states = $states;

        return $this;
    }

    /**
     * Add to states
     *
     * @param $state
     * @return FinancialSupport
     */
    public function addState($state) {
        if (!$this->states->contains($state)) {
            $this->states->add($state);
        }

        return $this;
    }

    /**
     * Remove states
     *
     * @param $states
     */
    public function removeState($states) {
        $this->states->removeElement($states);
    }

    /**
     * Set translations
     *
     * @param array $translations
     *
     * @return FinancialSupport
     */
    public function setTranslations($translations)
    {
        $this->translations = $translations;

        return $this;
    }

    /**
     * Get translations
     *
     * @return array
     */
    public function getTranslations()
    {
        return $this->translations;
    }

    /**
     * @return array
     */
    public function getAppointments()
    {
        return $this->appointments;
    }

    /**
     * @param array $appointments
     * @return $this
     */
    public function setAppointments($appointments)
    {
        $this->appointments = $appointments;
        return $this;
    }

    /**
     * @return string
     */
    public function getSearchIndex()
    {
        return $this->searchIndex;
    }

    /**
     * @param string $searchIndex
     * @return $this
     */
    public function setSearchIndex($searchIndex)
    {
        $this->searchIndex = $searchIndex;
        return $this;
    }

    /**
     * Set assignment
     *
     * @param string $assignment
     *
     * @return FinancialSupport
     */
    public function setAssignment($assignment)
    {
        $this->assignment = $assignment;

        return $this;
    }

    /**
     * Get assignment
     *
     * @return string
     */
    public function getAssignment()
    {
        return $this->assignment;
    }

    /**
     * Set otherOptionValues
     *
     * @param array $otherOptionValues
     *
     * @return FinancialSupport
     */
    public function setOtherOptionValues($otherOptionValues)
    {
        $this->otherOptionValues = $otherOptionValues;

        return $this;
    }

    /**
     * Get otherOptionValues
     *
     * @return array
     */
    public function getOtherOptionValues()
    {
        return $this->otherOptionValues;
    }

    /**
     * Set fundingProvider
     *
     * @param string|null $fundingProvider
     *
     * @return FinancialSupport
     */
    public function setFundingProvider($fundingProvider)
    {
        $this->fundingProvider = $fundingProvider;

        return $this;
    }

    /**
     * Get fundingProvider
     *
     * @return string|null
     */
    public function getFundingProvider()
    {
        return $this->fundingProvider;
    }

}

