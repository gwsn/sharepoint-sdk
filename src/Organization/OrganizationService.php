<?php declare(strict_types=1);

namespace GWSN\Microsoft\Organization;

use Exception;
use GWSN\Microsoft\ApiConnector;


class OrganizationService
{

    /** @var ApiConnector|null */
    private ?ApiConnector $apiConnector;


    /**
     * @param string $accessToken
     * @param int $requestTimeout
     * @param bool $verify
     */
    public function __construct(
        string $accessToken,
        int $requestTimeout = 60,
        bool $verify = true
    )
    {

        $apiConnector = new ApiConnector($accessToken, $requestTimeout, $verify);
        $this->apiConnector = $apiConnector;
    }

    /**
     * @return array
     * @throws Exception
     */
    public function requestDefaultOrganization(): array {

        $url = '/v1.0/organization';
        $response =  $this->apiConnector->request('GET', $url);


        if(!isset($response['value'])) {
            throw new \Exception('Microsoft Organization Request: Cannot parse the body of the sharepoint root site request', 500);
        }

        if(count($response['value']) === 1) {
            return $response['value'][0];
        }

        return $response['value'];
    }
}
