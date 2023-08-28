<?php

namespace CommonGateway\SimXMLToZGWBundle\ActionHandler;

use CommonGateway\CoreBundle\ActionHandler\ActionHandlerInterface;
use CommonGateway\SimXMLToZGWBundle\Service\SimXMLToZGWService;

/**
 * Sim xml to a zgw zaak
 */
class SimXmlZaakActionHandler implements ActionHandlerInterface
{

    /**
     * The Sim XML to ZGW service used by the handler
     *
     * @var SimXmlToZgwService
     */
    private SimXmlToZgwService $simXmlToZgwService;


    /**
     * @param SimXmlToZgwService $simXmlToZgwService The Sim XML to ZGW service
     */
    public function __construct(SimXmlToZgwService $simXmlToZgwService)
    {
        $this->simXmlToZgwService = $simXmlToZgwService;

    }//end __construct()


    /**
     *  Returns the required configuration as a https://json-schema.org array.
     *
     * @return array The configuration that this action should comply to
     */
    public function getConfiguration(): array
    {
        return [
            '$id'         => 'https://simxml.nl/simxml.creerzaak.handler.json',
            '$schema'     => 'https://docs.commongateway.nl/schemas/ActionHandler.schema.json',
            'title'       => 'SimXmlZaak ActionHandler',
            'description' => 'This is an action ...',
            'required'    => [],
            'properties'  => [],
        ];

    }//end getConfiguration()


    /**
     * This function runs the service.
     *
     * @param array $data          The data from the call
     * @param array $configuration The configuration of the action
     *
     * @return array
     *
     * @SuppressWarnings("unused") Handlers ara strict implementations
     */
    public function run(array $data, array $configuration): array
    {
        return $this->simXmlToZgwService->zaakActionHandler($data, $configuration);

    }//end run()


}//end class
