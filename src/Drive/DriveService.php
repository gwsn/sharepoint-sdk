<?php declare(strict_types=1);

namespace GWSN\Microsoft\Drive;

use Exception;
use GWSN\Microsoft\ApiConnector;


class DriveService
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
     * @param string $sharepointSiteId
     * @return array
     * @throws Exception
     */
    public function requestDrive(string $sharepointSiteId): array {

        // /sites/{siteId}/drive
        $url = sprintf('/v1.0/sites/%s/drive', $sharepointSiteId);


        $response =  $this->apiConnector->request('GET', $url);


        if(!isset($response['id'], $response['description'], $response['name'], $response['webUrl'], $response['owner'], $response['quota'])) {
            throw new \Exception('Microsoft SP Drive Request: Cannot parse the body of the sharepoint drive request. '.__FUNCTION__, 2111);
        }

        return $response;
    }

    /**
     * @param string $sharepointSiteId
     * @return string
     * @throws Exception
     */
    public function requestDriveId(string $sharepointSiteId): string {

        $drive = $this->requestDrive($sharepointSiteId);

        if(!isset($drive['id'])) {
            throw new \Exception('Microsoft SP Drive Request: Cannot parse the body of the sharepoint drive request. '.__FUNCTION__, 2121);
        }

        return $drive['id'];
    }

    /**
     * @param string $driveId
     * @param string|null $path
     * @param string|null $itemId
     * @return array
     * @throws Exception
     */
    public function requestResourceMetadata(string $driveId, ?string $path = null, ?string $itemId = null): ?array
    {
        if ($path === null && $itemId === null) {
            throw new \Exception('Microsoft SP Drive Request: Not all the parameters are correctly set. ' . __FUNCTION__, 2131);
        }

        // /drives/{drive-id}/items/{item-id}
        // /drives/{drive-id}/root:/{item-path}
        // https://docs.microsoft.com/en-us/graph/api/driveitem-get?view=graph-rest-1.0&tabs=http
        $path = ltrim($path, '/');
        $url = sprintf('/v1.0/drives/%s/root:/%s', $driveId, $path);

        // Overwrite if itemId is set
        if ($itemId !== null) {
            $url = sprintf('/v1.0/drives/%s/items/%s', $driveId, $itemId);
        }

        $response = $this->apiConnector->request('GET', $url);

        if (isset($response['error'], $response['error']['code']) && $response['error']['code'] === 'itemNotFound') {
            return null;
        }

        if ( ! isset($response['id'], $response['name'], $response['webUrl'])) {
            throw new \Exception('Microsoft SP Drive Request: Cannot parse the body of the sharepoint drive request. ' . __FUNCTION__, 2132);
        }

        return $response;
    }

    /**
     * @param string $driveId
     * @param string|null $path
     * @param string|null $itemId
     * @return bool
     * @throws Exception
     */
    public function checkResourceExists(string $driveId, ?string $path = null, ?string $itemId = null): bool
    {
        $fileMetaData = $this->requestResourceMetadata($driveId, $path, $itemId);

        return ($fileMetaData !== null);
    }


}
