# CommonGateway\SimXMLToZGWBundle\Service\SimXMLToZGWService  

This class handles the interaction with componentencatalogus.commonground.nl.





## Methods

| Name | Description |
|------|-------------|
|[__construct](#simxmltozgwservice__construct)||
|[connectEigenschappen](#simxmltozgwserviceconnecteigenschappen)|Connects Eigenschappen to ZaakType if eigenschap does not exist yet, or connect existing Eigenschap to ZaakEigenschap.|
|[connectRolTypes](#simxmltozgwserviceconnectroltypes)|Connects RoleTypes to ZaakType if RoleType does not exist yet, or connect existing RoleType to Role.|
|[connectZaakInformatieObjecten](#simxmltozgwserviceconnectzaakinformatieobjecten)|Connects ZaakInfromatieObjecten .|
|[convertZaakType](#simxmltozgwserviceconvertzaaktype)|Creates ZaakType if no ZaakType exists, connect existing ZaakType if ZaakType with identifier exists.|
|[createResponse](#simxmltozgwservicecreateresponse)|Creates a response based on content.|
|[unescapeEigenschappen](#simxmltozgwserviceunescapeeigenschappen)|Unescapes dots in eigenschap-names and definition.|
|[zaakActionHandler](#simxmltozgwservicezaakactionhandler)|Receives a case and maps it to a ZGW case.|




### SimXMLToZGWService::__construct  

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

* `(array) $zaakArray`
: The mapped zaak  
* `(\ObjectEntity) $zaakType`
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

* `(array) $zaakArray`
: The mapped zaak  
* `(\ObjectEntity) $zaakType`
: The zaakType to connect  

**Return Values**

`array`




<hr />


### SimXMLToZGWService::connectZaakInformatieObjecten  

**Description**

```php
public connectZaakInformatieObjecten (array $zaakArray, \ObjectEntity $zaak)
```

Connects ZaakInfromatieObjecten . 

.. @TODO 

**Parameters**

* `(array) $zaakArray`
: The mapped zaak  
* `(\ObjectEntity) $zaak`

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

* `(array) $zaakArray`
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

* `(array) $content`
: The content to incorporate in the response  
* `(int) $status`
: The status code of the response  

**Return Values**

`\Response`




<hr />


### SimXMLToZGWService::unescapeEigenschappen  

**Description**

```php
public unescapeEigenschappen (array $zaakArray)
```

Unescapes dots in eigenschap-names and definition. 

 

**Parameters**

* `(array) $zaakArray`
: The case aray to unescape.  

**Return Values**

`array`

> The unescaped array.


<hr />


### SimXMLToZGWService::zaakActionHandler  

**Description**

```php
public zaakActionHandler (array $data, array $configuration)
```

Receives a case and maps it to a ZGW case. 

 

**Parameters**

* `(array) $data`
: The inbound data for the case  
* `(array) $configuration`
: The configuration for the action  

**Return Values**

`array`




<hr />

