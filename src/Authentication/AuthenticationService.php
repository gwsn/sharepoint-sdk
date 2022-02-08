<?php declare(strict_types=1);

namespace GWSN\Microsoft\Authentication;

use Exception;
use GWSN\Microsoft\ApiConnector;


class AuthenticationService
{
    /** @var ApiConnector|null */
    private ?ApiConnector $apiConnector;

    /**
     * @param int $requestTimeout
     * @param bool $verify
     */
    public function __construct(
        int $requestTimeout = 60,
        bool $verify = true
    )
    {

        $apiConnector = new ApiConnector(null, $requestTimeout, $verify);
        $this->apiConnector = $apiConnector;
    }


    /**
     * tenant could be one of the following values:
     * 'common' => Allows users with both personal Microsoft accounts and work/school accounts from Azure AD to sign into the application.
     * 'organizations' => Allows only users with work/school accounts from Azure AD to sign into the application.
     * 'consumers' => Allows only users with personal Microsoft accounts (MSA) to sign into the application.
     * 'tenantId' => tenant's GUID identifier, example: 8eaef023-2b34-4da1-9baa-8bc8c9d6a490
     * 'msdomain' => Either the friendly domain name of the Azure AD tenant
     *
     * @link https://docs.microsoft.com/en-us7/azure/active-directory/develop/active-directory-v2-protocols#endpoints
     * @param string $tenantId
     * @param string $clientId
     * @param string $clientSecret
     * @return array
     * @throws Exception
     */
    public function requestToken(string $tenantId, string $clientId, string $clientSecret): array {
        $url = sprintf('/%s/oauth2/v2.0/token', $tenantId);

        $this->apiConnector->setBaseUrl('https://login.microsoftonline.com');
        $this->apiConnector->setClient();

        $response =  $this->apiConnector->request('POST', $url, [], [
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'scope' => 'https://graph.microsoft.com/.default',
            'client_secret' => $clientSecret
        ]);

        if(!isset($response['token_type'], $response['expires_in'], $response['ext_expires_in'], $response['access_token'])) {
            throw new \Exception('Microsoft Authenticate Request: Cannot parse the body of the authentication request', 500);
        }

        $this->apiConnector->setBearerToken($response['access_token']);
        return $response;
    }

    /**
     * @link https://docs.microsoft.com/en-us7/azure/active-directory/develop/active-directory-v2-protocols#endpoints
     *
     * @param string $tenantId
     * @param string $clientId
     * @param string $clientSecret
     * @return string
     * @throws Exception
     */
    public function getAccessToken(string $tenantId, string $clientId, string $clientSecret): string {
        $token = $this->requestToken($tenantId, $clientId, $clientSecret);

        if(!isset($token['access_token'])) {
            throw new \Exception('Microsoft Authenticate Request: Cannot parse the body of the token request', 500);
        }

        return $token['access_token'];
    }

}
