# CommonGateway\SimXMLToZGWBundle\Service\SimXMLToZGWService

This class handles the interaction with componentencatalogus.commonground.nl.

## Methods

| Name | Description |
|------|-------------|
|[\_\_construct](#simxmltozgwservice__construct)||
|[connectEigenschappen](#simxmltozgwserviceconnecteigenschappen)|Connects Eigenschappen to ZaakType if eigenschap does not exist yet, or connect existing Eigenschap to ZaakEigenschap.|
|[connectRolTypes](#simxmltozgwserviceconnectroltypes)|Connects RoleTypes to ZaakType if RoleType does not exist yet, or connect existing RoleType to Role.|
|[connectZaakInformatieObjecten](#simxmltozgwserviceconnectzaakinformatieobjecten)|Connects ZaakInfromatieObjecten .|
|[convertZaakType](#simxmltozgwserviceconvertzaaktype)|Creates ZaakType if no ZaakType exists, connect existing ZaakType if ZaakType with identifier exists.|
|[createResponse](#simxmltozgwservicecreateresponse)|Creates a response based on content.|
|[getEntity](#simxmltozgwservicegetentity)|Get an entity by reference.|
|[getMapping](#simxmltozgwservicegetmapping)|Gets mapping for reference.|
|[setStyle](#simxmltozgwservicesetstyle)|Set symfony style in order to output to the console.|
|[unescapeEigenschappen](#simxmltozgwserviceunescapeeigenschappen)|Unescapes dots in eigenschap-names and definition.|
|[zaakActionHandler](#simxmltozgwservicezaakactionhandler)|Receives a case and maps it to a ZGW case.|

### SimXMLToZGWService::\_\_construct

**Description**

```php
 __construct (void)
```

**Parameters**

`This function has no parameters.`

**Return Values**

`void`

<hr />

### SimXMLToZGWService::connectEigenschappen

**Description**

```php
public connectEigenschappen (array $zaakArray, \ObjectEntity $zaakType)
```

Connects Eigenschappen to ZaakType if eigenschap does not exist yet, or connect existing Eigenschap to ZaakEigenschap.

**Parameters**

*   `(array) $zaakArray`
    : The mapped zaak
*   `(\ObjectEntity) $zaakType`
    : The zaakType to connect

**Return Values**

`array`

<hr />

### SimXMLToZGWService::connectRolTypes

**Description**

```php
public connectRolTypes (array $zaakArray, \ObjectEntity $zaakType)
```

Connects RoleTypes to ZaakType if RoleType does not exist yet, or connect existing RoleType to Role.

**Parameters**

*   `(array) $zaakArray`
    : The mapped zaak
*   `(\ObjectEntity) $zaakType`
    : The zaakType to connect

**Return Values**

`array`

<hr />

### SimXMLToZGWService::connectZaakInformatieObjecten

**Description**

```php
public connectZaakInformatieObjecten (array $zaakArray)
```

Connects ZaakInfromatieObjecten .

.. @TODO

**Parameters**

*   `(array) $zaakArray`
    : The mapped zaak

**Return Values**

`array`

<hr />

### SimXMLToZGWService::convertZaakType

**Description**

```php
public convertZaakType (array $zaakArray)
```

Creates ZaakType if no ZaakType exists, connect existing ZaakType if ZaakType with identifier exists.

**Parameters**

*   `(array) $zaakArray`
    : The mapped case

**Return Values**

`array`

<hr />

### SimXMLToZGWService::createResponse

**Description**

```php
public createResponse (array $content, int $status)
```

Creates a response based on content.

**Parameters**

*   `(array) $content`
    : The content to incorporate in the response
*   `(int) $status`
    : The status code of the response

**Return Values**

`\Response`

<hr />

### SimXMLToZGWService::getEntity

**Description**

```php
public getEntity (string $reference)
```

Get an entity by reference.

**Parameters**

*   `(string) $reference`
    : The reference to look for

**Return Values**

`\Entity|null`

<hr />

### SimXMLToZGWService::getMapping

**Description**

```php
public getMapping (string $reference)
```

Gets mapping for reference.

**Parameters**

*   `(string) $reference`
    : The reference to look for

**Return Values**

`\Mapping`

<hr />

### SimXMLToZGWService::setStyle

**Description**

```php
public setStyle (\SymfonyStyle $io)
```

Set symfony style in order to output to the console.

**Parameters**

*   `(\SymfonyStyle) $io`

**Return Values**

`self`

<hr />

### SimXMLToZGWService::unescapeEigenschappen

**Description**

```php
public unescapeEigenschappen (array $zaakArray)
```

Unescapes dots in eigenschap-names and definition.

**Parameters**

*   `(array) $zaakArray`
    : The case aray to unescape.

**Return Values**

`array`

> The unescaped array.

<hr />

### SimXMLToZGWService::zaakActionHandler

**Description**

```php
public zaakActionHandler (array $data, array $config)
```

Receives a case and maps it to a ZGW case.

**Parameters**

*   `(array) $data`
    : The inbound data for the case
*   `(array) $config`
    : The configuration for the action

**Return Values**

`array`

<hr />
