<?php

namespace CommonGateway\SimXMLToZGWBundle\Service;

use Adbar\Dot;
use App\Entity\Entity;
use App\Entity\Mapping;
use App\Entity\ObjectEntity;
use App\Event\ActionEvent;
use CommonGateway\CoreBundle\Service\CacheService;
use CommonGateway\CoreBundle\Service\GatewayResourceService;
use CommonGateway\CoreBundle\Service\MappingService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use function PHPUnit\Framework\countOf;

/**
 *  This class handles the interaction with componentencatalogus.commonground.nl.
 */
class SimXMLToZGWService
{

    /**
     * @var EntityManagerInterface
     */
    private EntityManagerInterface $entityManager;

    /**
     * @var GatewayResourceService
     */
    private GatewayResourceService $resourceService;

    /**
     * @var MappingService
     */
    private MappingService $mappingService;

    /**
     * @var CacheService
     */
    private CacheService $cacheService;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var array
     */
    private array $data;

    /**
     * @var array
     */
    private array $configuration;

    /**
     * The plugin name of this plugin.
     */
    private const PLUGIN_NAME = 'common-gateway/sim-xml-to-zgw-bundle';

    /**
     * The mapping references used in this service.
     */
    private const MAPPING_REFS = [
        "ZdsDocumentToZgwDocument" => "https://zds.nl/mapping/zds.zdsDocumentToZgwDocument.mapping.json",
        "SimxmlZaakToZgwZaak"      => "https://simxml.nl/mapping/simxml.simxmlZaakToZgwZaak.mapping.json",
        "SimxmlZgwZaakToBv03"      => "https://simxml.nl/mapping/simxml.zgwZaakToBv03.mapping.json",
    ];

    /**
     * The schema references used in this service.
     */
    private const SCHEMA_REFS = [
        "ZtcEigenschap"                  => "https://vng.opencatalogi.nl/schemas/ztc.eigenschap.schema.json",
        "ZtcRolType"                     => "https://vng.opencatalogi.nl/schemas/ztc.rolType.schema.json",
        "ZtcZaakType"                    => "https://vng.opencatalogi.nl/schemas/ztc.zaakType.schema.json",
        "ZrcZaak"                        => "https://vng.opencatalogi.nl/schemas/zrc.zaak.schema.json",
        "ZrcZaakInformatieObject"        => "https://vng.opencatalogi.nl/schemas/zrc.zaakInformatieObject.schema.json",
        "DrcEnkelvoudigInformatieObject" => "https://vng.opencatalogi.nl/schemas/drc.enkelvoudigInformatieObject.schema.json",
    ];


    /**
     * @param EntityManagerInterface $entityManager   The Entity Manager
     * @param GatewayResourceService $resourceService The Gateway Resource Service.
     * @param MappingService         $mappingService  The MappingService
     * @param CacheService           $cacheService    The CacheService
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        GatewayResourceService $resourceService,
        MappingService $mappingService,
        CacheService $cacheService,
        LoggerInterface $actionLogger
    ) {
        $this->entityManager   = $entityManager;
        $this->resourceService = $resourceService;
        $this->mappingService  = $mappingService;
        $this->cacheService    = $cacheService;
        $this->logger          = $actionLogger;

    }//end __construct()


    /**
     * Creates a response based on content.
     *
     * @param array $content The content to incorporate in the response
     * @param int   $status  The status code of the response
     *
     * @return Response
     */
    public function createResponse(array $content, int $status): Response
    {
        $this->logger->debug('Creating XML response');
        $xmlEncoder    = new XmlEncoder(['xml_root_node_name' => 'SOAP-ENV:Envelope']);
        $contentString = $xmlEncoder->encode($content, 'xml', ['xml_encoding' => 'utf-8', 'remove_empty_tags' => true]);

        return new Response($contentString, $status, ['Content-Type' => 'application/soap+xml']);

    }//end createResponse()


    /**
     * Connects Eigenschappen to ZaakType if eigenschap does not exist yet, or connect existing Eigenschap to ZaakEigenschap.
     *
     * @param array        $zaakArray The mapped zaak
     * @param ObjectEntity $zaakType  The zaakType to connect
     *
     * @return array
     */
    public function connectEigenschappen(array $zaakArray, ObjectEntity $zaakType): array
    {
        $this->logger->info('Trying to connect case type properties to existing properties');

        $eigenschapEntity  = $this->resourceService->getSchema($this::SCHEMA_REFS['ZtcEigenschap'], $this::PLUGIN_NAME);
        $eigenschapObjects = [];
        foreach ($zaakArray['eigenschappen'] as $key => $eigenschap) {
            $eigenschappen = $this->cacheService->searchObjects(null, ['naam' => $eigenschap['eigenschap']['naam'], 'zaaktype' => $zaakType->getUri()], [$eigenschapEntity->getId()->toString()])['results'];
            if ($eigenschappen !== []) {
                $this->logger->debug('Property has been found, connecting to property');

                $zaakArray['eigenschappen'][$key]['eigenschap'] = $eigenschappen[0]['_self']['id'];
            } else {
                $this->logger->debug('No existing property found, creating new property');

                $eigenschapObject                     = new ObjectEntity($eigenschapEntity);
                $eigenschap['eigenschap']['zaaktype'] = $zaakType->getUri();

                $eigenschapObject->hydrate($eigenschap['eigenschap']);
                $this->entityManager->persist($eigenschapObject);
                $this->entityManager->flush();
                $this->entityManager->flush();
                $this->cacheService->cacheObject($eigenschapObject);

                $eigenschapObjects[] = $zaakArray['eigenschappen'][$key]['eigenschap'] = $eigenschapObject->getId()->toString();
            }//end if
        }//end foreach

        $zaakType->hydrate(['eigenschappen' => $eigenschapObjects]);

        $this->logger->info('Connected case properties to case type properties');

        return $zaakArray;

    }//end connectEigenschappen()


    /**
     * Connects RoleTypes to ZaakType if RoleType does not exist yet, or connect existing RoleType to Role.
     *
     * @param array        $zaakArray The mapped zaak
     * @param ObjectEntity $zaakType  The zaakType to connect
     *
     * @return array
     */
    public function connectRolTypes(array $zaakArray, ObjectEntity $zaakType): array
    {
        $this->logger->info('Trying to connect roles to existing role types');
        $rolTypeEntity  = $this->resourceService->getSchema($this::SCHEMA_REFS['ZtcRolType'], $this::PLUGIN_NAME);
        $rolTypeObjects = $zaakType->getValue('roltypen');

        foreach ($zaakArray['rollen'] as $key => $role) {
            $rollen = $this->cacheService->searchObjects(null, ['omschrijvingGeneriek' => $role['roltype']['omschrijvingGeneriek'], 'zaaktype' => $zaakType->getUri()], [$rolTypeEntity->getId()->toString()])['results'];
            if ($rollen !== []) {
                $this->logger->debug('Role type has been found, connecting to existing role type');
                $zaakArray['rollen'][$key]['roltype'] = $rollen[0]['_self']['id'];
                $rolType                              = $this->entityManager->find('App:ObjectEntity', $rollen[0]['_self']['id']);
            } else {
                $this->logger->debug('No existing role type has been found, creating new role type');
                $rolType                     = new ObjectEntity($rolTypeEntity);
                $role['roltype']['zaaktype'] = $zaakType->getUri();
                $rolType->hydrate($role['roltype']);

                $this->entityManager->persist($rolType);
                $this->entityManager->flush();

                $rolTypeObjects[] = $zaakArray['rollen'][$key]['roltype'] = $rolType->getId()->toString();
            }//end if
        }//end foreach

        $zaakType->hydrate(['roltypen' => $rolTypeObjects]);

        $this->logger->info('Connected roles to role types');

        return $zaakArray;

    }//end connectRolTypes()


    /**
     * Connects ZaakInfromatieObjecten ... @TODO
     *
     * @param array        $zaakArray The mapped zaak
     * @param ObjectEntity $zaak
     *
     * @return array
     */
    public function connectZaakInformatieObjecten(array $zaakArray, ObjectEntity $zaak): array
    {
        $this->logger->info('Populating document');

        $zaakEntity         = $this->resourceService->getSchema($this::SCHEMA_REFS['ZrcZaak'], $this::PLUGIN_NAME);
        $zaakDocumentEntity = $this->resourceService->getSchema($this::SCHEMA_REFS['ZrcZaakInformatieObject'], $this::PLUGIN_NAME);
        $documentEntity     = $this->resourceService->getSchema($this::SCHEMA_REFS['DrcEnkelvoudigInformatieObject'], $this::PLUGIN_NAME);
        $mapping            = $this->resourceService->getMapping($this::MAPPING_REFS['ZdsDocumentToZgwDocument'], $this::PLUGIN_NAME);

        $zaken                  = $this->cacheService->searchObjects(null, ['identificatie' => $zaakArray['identificatie']], [$zaakEntity->getId()->toString()])['results'];
        $zaakinformatieobjecten = $zaak->getValue('zaakinformatieobjecten');

        foreach ($zaakinformatieobjecten as $key => $zaakInformatieObject) {
            $documenten = $this->cacheService->searchObjects(null, ['identificatie' => $zaakInformatieObject->getValue('informatieobject')->getValue('identificatie')], [$documentEntity->getId()->toString()])['results'];

            if ($documenten !== []) {
                $this->logger->debug('Populating document with identification'.$zaakInformatieObject->getValue('informatieobject')->getValue('identificatie'));

                $informatieobject = $zaakInformatieObject->getValue('informatieobject');
                $informatieobject->setValue('identificatie', $zaak->getValue('identificatie').'-'.$informatieobject->getValue('identificatie'));
                $this->entityManager->persist($informatieobject);

                $this->logger->info('Connected document to zaak');

                $zaakInformatieObject->hydrate(['zaak' => $zaak, 'informatieobject' => $informatieobject->getId()->toString()]);
                $this->entityManager->persist($zaakInformatieObject);
                $this->entityManager->flush();

                $this->data['documents'][] = $zaakInformatieObject->toArray();
            } else {
                $this->logger->warning('The case with id '.$zaakArray['informatieobject']['identificatie'].' does not exist');
                $data['response'] = $this->createResponse(['Error' => 'The case with id '.$zaakArray['informatieobject']['identificatie'].' does not exist'], 400);
            }//end if
        }//end foreach

        return $zaakArray;

    }//end connectZaakInformatieObjecten()


    /**
     * Creates ZaakType if no ZaakType exists, connect existing ZaakType if ZaakType with identifier exists.
     *
     * @param array $zaakArray The mapped case
     *
     * @return array
     */
    public function convertZaakType(array $zaakArray): array
    {
        $this->logger->debug('Trying to connect case to existing case type');

        $zaakTypeEntity = $this->resourceService->getSchema($this::SCHEMA_REFS['ZtcZaakType'], $this::PLUGIN_NAME);
        $zaaktypes      = $this->cacheService->searchObjects(null, ['identificatie' => $zaakArray['zaaktype']['identificatie']], [$zaakTypeEntity->getId()->toString()])['results'];
        if (count($zaaktypes) > 0) {
            $this->logger->debug('Case type found, connecting case to case type');

            $zaaktype              = $this->entityManager->find('App:ObjectEntity', $zaaktypes[0]['_self']['id']);
            $zaakArray['zaaktype'] = $zaaktype;
        } else {
            $this->logger->debug('No existing case type found, creating new case type');

            $zaaktype = new ObjectEntity($zaakTypeEntity);
            $zaaktype->hydrate($zaakArray['zaaktype']);

            $this->entityManager->persist($zaaktype);
            $this->entityManager->flush();

            $zaakArray['zaaktype'] = $zaaktype;
        }//end if

        $this->logger->info('Case connected to case type with identification'.$zaaktype->toArray()['identificatie']);

        $zaakArray = $this->connectEigenschappen($zaakArray, $zaaktype);
        $zaakArray = $this->connectRolTypes($zaakArray, $zaaktype);

        return $zaakArray;

    }//end convertZaakType()


    /**
     * Unescapes dots in eigenschap-names and definition.
     *
     * @param array $zaakArray The case aray to unescape.
     *
     * @return array The unescaped array.
     */
    public function unescapeEigenschappen(array $zaakArray): array
    {
        // Remember the ; after &#46
        foreach ($zaakArray['eigenschappen'] as $key => $eigenschap) {
            $eigenschap['naam']                    = str_replace(['&#46;', '&amp;#46;', '&amp;amp;#46;'], ['.', '.', '.'], $eigenschap['naam']);
            $eigenschap['eigenschap']['naam']      = str_replace(['&#46;', '&amp;#46;', '&amp;amp;#46;'], ['.', '.', '.'], $eigenschap['eigenschap']['naam']);
            $eigenschap['eigenschap']['definitie'] = str_replace(['&#46;', '&amp;#46;', '&amp;amp;#46;'], ['.', '.', '.'], $eigenschap['eigenschap']['definitie']);
            $zaakArray['eigenschappen'][$key]      = $eigenschap;
        }

        return $zaakArray;

    }//end unescapeEigenschappen()


    /**
     * Receives a case and maps it to a ZGW case.
     *
     * @param array $data          The inbound data for the case
     * @param array $configuration The configuration for the action
     *
     * @return array
     */
    public function zaakActionHandler(array $data, array $configuration): array
    {
        $this->logger->info('Populate case');
        $this->configuration = $configuration;
        $this->data          = $data;

        $elementen                                                                                            = new Dot($this->data['body']['SOAP-ENV:Body']['ns2:OntvangenIntakeNotificatie']['Body']['SIMXML']['ELEMENTEN']);
        $this->data['body']['SOAP-ENV:Body']['ns2:OntvangenIntakeNotificatie']['Body']['SIMXML']['ELEMENTEN'] = $elementen->flatten();

        $zaakEntity = $this->resourceService->getSchema($this::SCHEMA_REFS['ZrcZaak'], $this::PLUGIN_NAME);
        $mapping    = $this->resourceService->getMapping($this::MAPPING_REFS['SimxmlZaakToZgwZaak'], $this::PLUGIN_NAME);

        $zaakArray = $this->mappingService->mapping($mapping, $this->data['body']);

        $zaakArray = $this->unescapeEigenschappen($zaakArray);

        $zaakArray = $this->convertZaakType($zaakArray);

        $zaken = $this->cacheService->searchObjects(null, ['identificatie' => $zaakArray['identificatie']], [$zaakEntity->getId()->toString()])['results'];
        if ($zaken === []) {
            $this->logger->debug('Creating new case with identifier'.$zaakArray['identificatie']);
            $zaak = new ObjectEntity($zaakEntity);
            $zaak->hydrate($zaakArray);

            $this->entityManager->persist($zaak);
            $this->entityManager->flush();
            $this->data['object'] = $zaak->toArray();
            $zaakArray            = $this->connectZaakInformatieObjecten($zaakArray, $zaak);

            $this->logger->info('Created case with identifier '.$zaakArray['identificatie']);
            $mappingOut = $this->resourceService->getMapping($this::MAPPING_REFS['SimxmlZgwZaakToBv03'], $this::PLUGIN_NAME);

            $this->data['response'] = $this->createResponse($this->mappingService->mapping($mappingOut, $zaak->toArray()), 200);
        } else {
            $this->logger->warning('Case with identifier '.$zaakArray['identificatie'].' found, returning bad request error');
            $this->data['response'] = $this->createResponse(['Error' => 'The case with id '.$zaakArray['identificatie'].' already exists'], 400);
        }//end if

        return $this->data;

    }//end zaakActionHandler()


}//end class
