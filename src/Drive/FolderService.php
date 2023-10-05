<?php declare(strict_types=1);

namespace GWSN\Microsoft\Drive;

use Exception;
use GuzzleHttp\RequestOptions;
use GWSN\Microsoft\ApiConnector;


class FolderService
{
    /** @var ApiConnector|null $apiConnector */
    private ?ApiConnector $apiConnector;

    /** @var string $driveId */
    private string $driveId;

    /**
     * @param string $accessToken
     * @param int $requestTimeout
     * @param bool $verify
     */
    public function __construct(
        string $accessToken,
        string $driveId,
        int    $requestTimeout = 60,
        bool   $verify = true
    )
    {
        $this->setApiConnector(new ApiConnector($accessToken, $requestTimeout, $verify));
        $this->setDriveId($driveId);
    }

    /**
     * @return ApiConnector|null
     */
    public function getApiConnector(): ?ApiConnector
    {
        return $this->apiConnector;
    }

    /**
     * @param ApiConnector|null $apiConnector
     * @return FolderService
     */
    public function setApiConnector(?ApiConnector $apiConnector): FolderService
    {
        $this->apiConnector = $apiConnector;
        return $this;
    }

    /**
     * @return string
     */
    public function getDriveId(): string
    {
        return $this->driveId;
    }

    /**
     * @param string $driveId
     * @return FolderService
     */
    public function setDriveId(string $driveId): FolderService
    {
        $this->driveId = $driveId;
        return $this;
    }


    /**
     * @param string|null $path
     * @param string|null $itemId
     * @param string|null $suffix
     * @return string
     * @throws Exception
     */
    private function getFolderBaseUrl(?string $path = '/', ?string $itemId = null, ?string $suffix = null): string
    {
        if ($path === null && $itemId === null) {
            throw new \Exception('Microsoft SP Drive Request: Not all the parameters are correctly set. ' . __FUNCTION__, 2311);
        }

        // /drives/{drive-id}/items/{item-id}
        // /drives/{drive-id}/root:/{item-path}
        // https://docs.microsoft.com/en-us/graph/api/driveitem-get?view=graph-rest-1.0&tabs=http
        if ($itemId !== null) {
            return sprintf('/v1.0/drives/%s/items/%s%s', $this->getDriveId(), $itemId, ($suffix ?? ''));
        }

        if ($path === '/' || $path === '') {
            return sprintf('/v1.0/drives/%s/items/root%s', $this->getDriveId(), ($suffix ?? ''));
        }

        $path = ltrim($path, '/');
        return sprintf('/v1.0/drives/%s/items/root:/%s%s', $this->getDriveId(), $path, ($suffix !== null ? ':'.$suffix : ''));
    }

    /**
     * List all items in a specific folder
     *
     * @param string|null $folder
     * @param string|null $itemId
     * @return array
     * @throws Exception
     */
    public function requestFolderItems(?string $folder = '/', ?string $itemId = null): array
    {
        // /sites/{siteId}/drive
        $url = $this->getFolderBaseUrl($folder, $itemId, '/children');

        $exists = $this->checkFolderExists($folder, $itemId);
        if ( ! $exists ) {
            throw new \Exception('Microsoft SP Drive Request: Cannot get folder items for folder that not exists, please create the folder first!. ' . __FUNCTION__, 2321);
        }

        return $this->requestAllItems($url);
    }

    /**
     * Get all items from a url
     *
     * @param string $url
     * @return array
     * @throws Exception
     */
    public function requestAllItems(string $url): ?array
    {
        $response = $this->apiConnector->request('GET', $url);

        if ( ! isset($response['value'])) {
            throw new \Exception('Microsoft SP Drive Request: Cannot parse the body of the sharepoint drive request. ' . __FUNCTION__, 2321);
        }

        $results = $response['value'];

        if (isset($response['@odata.nextLink'])) {
            $results = [
                ...$response['value'],
                ...$this->requestAllItems($response['@odata.nextLink'])
            ];
        }

        return $results;
    }

    /**
     * Read the folder metadata and so check if it exists
     *
     * @param string|null $folder
     * @param string|null $itemId
     * @return array
     * @throws Exception
     */
    public function requestFolderMetadata(?string $folder = null, ?string $itemId = null): ?array
    {
        $url = $this->getFolderBaseUrl($folder, $itemId);

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
     * @param string|null $folder
     * @param string|null $itemId
     * @return bool
     * @throws Exception
     */
    public function checkFolderExists(?string $folder = null, ?string $itemId = null): bool
    {
        $folderMetaData = $this->requestFolderMetadata($folder, $itemId);

        if (isset($folderMetaData['file'])) {
            throw new \Exception('Check for file exists but path is actually a folder', 2231);
        }

        return ($folderMetaData !== null);
    }

    /**
     * @param string|null $folder
     * @param string|null $parentFolderId
     * @return array|null
     * @throws Exception
     */
    public function createFolder(?string $folder = null, ?string $parentFolderId = null): ?array
    {
        if($folder === '/') {
            throw new \Exception('Cannot create the root folder, this already exists', 2351);
        }

        // Explode the path
        $parent = explode( '/', $folder);
        $folderName = array_pop($parent);


        // build url to fetch the parentItemId if not provided
        if($parentFolderId === null) {
            $parentFolderMeta = $this->requestFolderMetadata(sprintf('/%s', ltrim(implode('/', $parent), '/')));
            if($parentFolderMeta === null) {
                throw new \Exception('Parent folder does not exists', 2352);
            }
            $parentFolderId = $parentFolderMeta['id'];
        }

        $url = $this->getFolderBaseUrl(null, $parentFolderId, '/children');

        // Build request
        $body = [
            'name' => $folderName,
            'folder' => new \stdClass()
        ];

        try {
            $response = $this->apiConnector->request('POST', $url, [], [], null, [
                RequestOptions::JSON => $body
            ]);

            return $response;
        } catch (\Exception $exception) {
            return null;
        }
    }

    /**
     * @param string|null $folder
     * @param string|null $itemId
     * @return bool
     * @throws Exception
     */
    public function createFolderRecursive(?string $folder = null): ?array
    {
        $pathParts = explode("/", $folder);

        $buildPath = '';
        $parentFolderId = null;
        $createFolderResponse = null;
        foreach($pathParts as $path) {
            $buildPath = sprintf('%s/%s', $buildPath, $path);
            $folderMeta = $this->requestFolderMetadata($buildPath);

            if($folderMeta !== null) {
                $parentFolderId = $folderMeta['id'];
                continue;
            }

            $createFolderResponse = $this->createFolder($buildPath, $parentFolderId);
            if($createFolderResponse === null || !isset($createFolderResponse['id'])) {
                $errorMessage = (isset($createFolderResponse['error'], $createFolderResponse['error']['message']) ? $createFolderResponse['error']['message'] : '');
                throw new \Exception(sprintf('Cannot create recursive the folder %s, errorMessage: %s', $buildPath, $errorMessage), 2361);
            }

            $parentFolderId = $createFolderResponse['id'];
        }

        return $createFolderResponse;
    }

    /**
     * @param string|null $folder
     * @param string|null $itemId
     * @return bool
     * @throws Exception
     */
    public function deleteFolder(?string $folder = null, ?string $itemId = null): bool
    {
        $url = $this->getFolderBaseUrl($folder, $itemId);

        try {
            $this->apiConnector->request('DELETE', $url);
            return true;
        } catch (Exception $exception) {
            return false;
        }
    }
}
