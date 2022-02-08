<?php declare(strict_types=1);

namespace GWSN\Microsoft\Sharepoint;

use Exception;
use GWSN\Microsoft\ApiConnector;


class SharepointService
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
    public function requestRootSite(): array {

        // /v1.0/sites/root
        $url = '/v1.0/sites/root';

        $response =  $this->apiConnector->request('GET', $url);


        if(!isset($response['id'], $response['name'], $response['webUrl'], $response['displayName'])) {
            throw new \Exception('Microsoft SP Site Request: Cannot parse the body of the sharepoint root site request', 500);
        }

        return $response;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function requestSharepointHostname(): string {

        $site = $this->requestRootSite();

        if(!isset($site['siteCollection'], $site['siteCollection']['hostname'])) {
            throw new \Exception('Microsoft SP Site Request: Cannot parse the body of the sharepoint root site request', 500);
        }

        return $site['siteCollection']['hostname'];
    }

    /**
     * @param string $siteHostname
     * @param string $siteName
     * @return array
     * @throws Exception
     */
    public function requestSiteBySiteName(string $siteHostname, string $siteName): array {

        // /v1.0/sites/{tenant}.sharepoint.com:/sites/{sharepoint-web-url}
        // or
        // /v1.0/sites/{siteHostname}:/sites/{sharepoint-web-url}
        $url = sprintf('/v1.0/sites/%s:/sites/%s', $siteHostname, $siteName);

        $response =  $this->apiConnector->request('GET', $url);

        if(!isset($response['id'], $response['name'], $response['webUrl'], $response['displayName'])) {
            throw new \Exception('Microsoft SP Site Request: Cannot parse the body of the sharepoint site request', 500);
        }

        return $response;
    }

    /**
     * @param string $siteHostname
     * @param string $siteName
     * @return string
     * @throws Exception
     */
    public function requestSiteIdBySiteName(string $siteHostname, string $siteName): string {

        $site = $this->requestSiteBySiteName($siteHostname, $siteName);

        if(!isset($site['id'])) {
            throw new \Exception('Microsoft SP Site Request: Cannot parse the body of the sharepoint site request', 500);
        }

        return $site['id'];
    }

}
