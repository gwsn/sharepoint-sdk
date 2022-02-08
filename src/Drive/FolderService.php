<?php declare(strict_types=1);

namespace GWSN\Microsoft\Drive;

use Exception;
use GuzzleHttp\RequestOptions;
use GWSN\Microsoft\ApiConnector;


class FolderService
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
        int    $requestTimeout = 60,
        bool   $verify = true
    )
    {

        $apiConnector = new ApiConnector($accessToken, $requestTimeout, $verify);
        $this->apiConnector = $apiConnector;
    }

    /**
     * @param string $driveId
     * @param string|null $path
     * @param string|null $itemId
     * @param string|null $suffix
     * @return string
     * @throws Exception
     */
    private function getFolderBaseUrl(string $driveId, ?string $path = '/', ?string $itemId = null, ?string $suffix = null): string
    {
        if ($path === null && $itemId === null) {
            throw new \Exception('Microsoft SP Drive Request: Not all the parameters are correctly set. ' . __FUNCTION__, 2311);
        }

        // /drives/{drive-id}/items/{item-id}
        // /drives/{drive-id}/root:/{item-path}
        // https://docs.microsoft.com/en-us/graph/api/driveitem-get?view=graph-rest-1.0&tabs=http
        if ($itemId !== null) {
            return sprintf('/v1.0/drives/%s/items/%s%s', $driveId, $itemId, ($suffix ?? ''));
        }

        if ($path === '/' || $path === '') {
            return sprintf('/v1.0/drives/%s/items/root%s', $driveId, ($suffix ?? ''));
        }

        $path = ltrim($path, '/');
        return sprintf('/v1.0/drives/%s/items/root:/%s%s', $driveId, $path, ($suffix !== null ? ':'.$suffix : ''));
    }

    /**
     * List all items in a specific folder
     *
     * @param string $driveId
     * @param string|null $folder
     * @param string|null $itemId
     * @return array
     * @throws Exception
     */
    public function requestFolderItems(string $driveId, ?string $folder = '/', ?string $itemId = null): array
    {
        $url = $this->getFolderBaseUrl($driveId, $folder, $itemId, '/children');

        // /sites/{siteId}/drive
        $response = $this->apiConnector->request('GET', $url);


        if ( ! isset($response['value'])) {
            throw new \Exception('Microsoft SP Drive Request: Cannot parse the body of the sharepoint drive request. ' . __FUNCTION__, 2321);
        }

        return $response['value'];
    }


    /**
     * Read the folder metadata and so check if it exists
     *
     * @param string $driveId
     * @param string|null $folder
     * @param string|null $itemId
     * @return array
     * @throws Exception
     */
    public function requestFolderMetadata(string $driveId, ?string $folder = null, ?string $itemId = null): ?array
    {
        $url = $this->getFolderBaseUrl($driveId, $folder, $itemId);

        $response = $this->apiConnector->request('GET', $url);

        if (isset($response['error'], $response['error']['code']) && $response['error']['code'] === 'itemNotFound') {
            return null;
        }

        if ( ! isset($response['id'], $response['name'], $response['webUrl'])) {
            throw new \Exception('Microsoft SP Drive Request: Cannot parse the body of the sharepoint drive request. ' . __FUNCTION__, 2331);
        }

        return $response;
    }


    /**
     * @param string $driveId
     * @param string|null $folder
     * @param string|null $itemId
     * @return bool
     * @throws Exception
     */
    public function checkFolderExists(string $driveId, ?string $folder = null, ?string $itemId = null): bool
    {
        $folderMetaData = $this->requestFolderMetadata($driveId, $folder, $itemId);

        if (isset($folderMetaData['file'])) {
            throw new \Exception('Check for file exists but path is actually a folder', 2231);
        }

        return ($folderMetaData !== null);
    }

    /**
     * @param string $driveId
     * @param string|null $folder
     * @return array|null
     * @throws Exception
     */
    public function createFolder(string $driveId, ?string $folder = null, ?string $parentFolderId = null): ?array
    {
        if($folder === '/') {
            throw new \Exception('Cannot create the root folder, this already exists', 2351);
        }

        // Explode the path
        $parent = explode( '/', $folder);
        $folderName = array_pop($parent);


        // build url to fetch the parentItemId if not provided
        if($parentFolderId === null) {
            $parentFolderMeta = $this->requestFolderMetadata($driveId, sprintf('/%s', ltrim(implode('/', $parent), '/')));
            if($parentFolderMeta === null) {
                throw new \Exception('Parent folder does not exists', 2352);
            }
            $parentFolderId = $parentFolderMeta['id'];
        }

        $url = $this->getFolderBaseUrl($driveId, null, $parentFolderId, '/children');

        // Build request
        $body = [
            'name' => $folderName,
            'folder' => []
        ];

        try {
            $response = $this->apiConnector->request('POST', $url, [], [], null, [
                RequestOptions::JSON => $body
            ]);
            var_dump($response);

            return $response;
        } catch (\Exception $exception) {
            return null;
        }
    }

    /**
     * @param string $driveId
     * @param string|null $folder
     * @param string|null $itemId
     * @return bool
     * @throws Exception
     */
    public function createFolderRecursive(string $driveId, ?string $folder = null, ?string $itemId = null): ?array
    {
        $pathParts = explode("/", $folder);

        $buildPath = '';
        $parentFolderId = null;
        $createFolderResponse = null;
        foreach($pathParts as $path) {
            $buildPath .= $path;
            $folderMeta = $this->requestFolderMetadata($driveId, $buildPath);

            if($folderMeta !== null) {
                $parentFolderId = $folderMeta['id'];
                continue;
            }

            $createFolderResponse = $this->createFolder($driveId, $buildPath, $parentFolderId);
            if($createFolderResponse === null) {
                throw new \Exception(sprintf('Cannot create recursive the folder %s', $buildPath), 2361);
            }

            $parentFolderId = $createFolderResponse['id'];
        }

        return $createFolderResponse;
    }

    /**
     * @param string $driveId
     * @param string|null $folder
     * @param string|null $itemId
     * @return bool
     * @throws Exception
     */
    public function deleteFile(string $driveId, ?string $folder = null, ?string $itemId = null): bool
    {
        $url = $this->getFolderBaseUrl($driveId, $folder, $itemId);

        try {
            $this->apiConnector->request('DELETE', $url);
            return true;
        } catch (Exception $exception) {
            return false;
        }
    }
}
