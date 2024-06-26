<?php

namespace CommonGateway\SimXMLToZGWBundle\Service;

use Adbar\Dot;
use App\Entity\Endpoint;
use App\Entity\Entity;
use App\Entity\File;
use App\Entity\Mapping;
use App\Entity\ObjectEntity;
use App\Event\ActionEvent;
use CommonGateway\CoreBundle\Service\CacheService;
use CommonGateway\CoreBundle\Service\GatewayResourceService;
use CommonGateway\CoreBundle\Service\MappingService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
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
     * ParameterBagInterface
     */
    private ParameterBagInterface $parameterBag;

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
        LoggerInterface $actionLogger,
        ParameterBagInterface $parameterBag
    ) {
        $this->entityManager   = $entityManager;
        $this->resourceService = $resourceService;
        $this->mappingService  = $mappingService;
        $this->cacheService    = $cacheService;
        $this->logger          = $actionLogger;
        $this->parameterBag    = $parameterBag;

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
     * Generates a download endpoint from the id of an 'Enkelvoudig Informatie Object' and the endpoint for downloads.
     *
     * @param string   $id               The id of the Enkelvoudig Informatie Object.
     * @param Endpoint $downloadEndpoint The endpoint for downloads.
     *
     * @return string The endpoint to download the document from.
     */
    private function generateDownloadEndpoint(string $id, Endpoint $downloadEndpoint): string
    {
        // Unset the last / from the app_url.
        $baseUrl = rtrim($this->parameterBag->get('app_url'), '/');

        $pathArray = $downloadEndpoint->getPath();
        foreach ($pathArray as $key => $value) {
            if ($value == 'id' || $value == '[id]' || $value == '{id}') {
                $pathArray[$key] = $id;
            }
        }

        return $baseUrl.'/api/'.implode('/', $pathArray);

    }//end generateDownloadEndpoint()


    /**
     * Creates a new file for storing attachment contents if the createOrUpdateFile decides it should use a new file.
     *
     * @param ObjectEntity $objectEntity The object entity associated with the file.
     * @param array        $data         Data associated with the file such as title, format, and content.
     *
     * @return void
     */
    public function createFile(ObjectEntity $objectEntity, array $data): File
    {
        if (isset($data['versie']) === false || $data['versie'] === null) {
            $objectEntity->hydrate(['versie' => 1]);
        }

        if (isset($data['versie']) === true && $data['versie'] !== null) {
            $objectEntity->hydrate(['versie' => ++$data['versie']]);
        }

        $file = new File();
        $file->setBase64('');
        $file->setMimeType(($data['formaat'] ?? 'application/pdf'));
        $file->setName($data['titel']);
        $file->setExtension('');
        $file->setSize(0);

        return $file;

    }//end createFile()


    /**
     * Creates or updates a file associated with a given ObjectEntity instance.
     *
     * This method handles the logic for creating or updating a file based on
     * provided data. If an existing file is associated with the ObjectEntity,
     * it updates the file's properties; otherwise, it creates a new file.
     * It also sets the response data based on the method used (POST or other)
     * and if the `$setResponse` parameter is set to `true`.
     *
     * @param ObjectEntity $objectEntity     The object entity associated with the file.
     * @param array        $data             Data associated with the file such as title, format, and content.
     * @param Endpoint     $downloadEndpoint Endpoint to use for downloading the file.
     * @param bool         $setResponse      Determines if a response should be set, default is `true`.
     *
     * @return void
     */
    public function createOrUpdateFile(ObjectEntity $objectEntity, array $data, Endpoint $downloadEndpoint, bool $setResponse=true): void
    {
        if ($objectEntity->getValueObject('inhoud') !== false && $objectEntity->getValueObject('inhoud')->getFiles()->count() > 0) {
            // Get the file from the inhoud object.
            $file = $objectEntity->getValueObject('inhoud')->getFiles()->first();
        }

        if ($objectEntity->getValueObject('inhoud') !== false && $objectEntity->getValueObject('inhoud')->getFiles()->count() === 0 || $objectEntity->getValueObject('inhoud') === false) {
            // Create the file with the data.
            $file = $this->createFile($objectEntity, $data);
        }

        if ($data['inhoud'] !== null && $data['inhoud'] !== '' && filter_var($data['inhoud'], FILTER_VALIDATE_URL) === false) {
            $file->setSize(mb_strlen(base64_decode($data['inhoud'])));
            $file->setBase64($data['inhoud']);
        }

        $file->setValue($objectEntity->getValueObject('inhoud'));
        $this->entityManager->persist($file);
        $this->entityManager->persist($objectEntity);
        $objectEntity->getValueObject('inhoud')->addFile($file)->setStringValue($this->generateDownloadEndpoint($objectEntity->getId()->toString(), $downloadEndpoint));
        $this->entityManager->persist($objectEntity);
        $this->entityManager->flush();

        if ($setResponse === true) {
            $this->data['response'] = new Response(
                \Safe\json_encode($objectEntity->toArray(['embedded' => true])),
                $this->data['method'] === 'POST' ? 201 : 200,
                ['content-type' => 'application/json']
            );
        }

    }//end createOrUpdateFile()


    /**
     * Creates an enkelvoudiginformatieobject with an informatieobjecttype.
     * Creates a zaakinformatieobject with the zaak and created enkelvoudiginformatieobject
     *
     * @param array        $zaakDocumentArray The mapped zaak document array
     * @param string       $documentId        The id of the enkelvoudiginformatieobject document.
     * @param ObjectEntity $zaak              The zaak object.
     *
     * @return ObjectEntity|null The created zaakinformatieobject
     */
    public function createDocuments(array $zaakDocumentArray, ObjectEntity $zaak): ?ObjectEntity
    {
        $caseInfoObjectSchema          = $this->resourceService->getSchema('https://vng.opencatalogi.nl/schemas/zrc.zaakInformatieObject.schema.json', 'common-gateway/zds-to-zgw-bundle');
        $singleInformationObjectSchema = $this->resourceService->getSchema('https://vng.opencatalogi.nl/schemas/drc.enkelvoudigInformatieObject.schema.json', 'common-gateway/zds-to-zgw-bundle');
        if ($caseInfoObjectSchema === null) {
            return null;
        }

        // Enkelvoudiginformatieobject
        $informatieobject = new ObjectEntity($singleInformationObjectSchema);

        // var_dump($zaakDocumentArray['informatieobject']);
        $informatieobject->hydrate($zaakDocumentArray['informatieobject']);
        $this->entityManager->persist($informatieobject);

        $endpoint = $this->resourceService->getEndpoint('https://vng.opencatalogi.nl/endpoints/drc.downloadEnkelvoudigInformatieObject.endpoint.json', 'common-gateway/zds-to-zgw-bundle');
        $this->createOrUpdateFile($informatieobject, $zaakDocumentArray['informatieobject'], $endpoint, false);

        $this->entityManager->persist($informatieobject);
        $this->entityManager->flush();

        $zaakInformatieObject = new ObjectEntity($caseInfoObjectSchema);
        // TODO: Set status.
        $zaakInformatieObject->hydrate(['zaak' => $zaak, 'informatieobject' => $informatieobject]);

        $this->entityManager->persist($zaakInformatieObject);
        $this->entityManager->flush();

        return $zaakInformatieObject;

    }//end createDocuments()


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
            $zaak = new ObjectEntity($zaakEntity);

            $zaakDocuments                       = $zaakArray['zaakinformatieobjecten'];
            $zaakArray['zaakinformatieobjecten'] = [];

            foreach ($zaakDocuments as $zaakDocumentArray) {
                $zaakArray['zaakinformatieobjecten'][] = $this->createDocuments($zaakDocumentArray, $zaak);
            }

            $this->logger->debug('Creating new case with identifier'.$zaakArray['identificatie']);

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
