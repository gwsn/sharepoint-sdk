<?php declare(strict_types=1);

namespace GWSN\Microsoft\Drive;

use Exception;
use GuzzleHttp\RequestOptions;
use GWSN\Microsoft\ApiConnector;


class FileService
{

    /** @var ApiConnector|null $apiConnector */
    private ?ApiConnector $apiConnector;

    /** @var string $driveId */
    private string $driveId;

    /**  @var FolderService $folderService */
    private FolderService $folderService;

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
        $this->folderService = new FolderService($accessToken, $driveId, $requestTimeout, $verify);
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
     * @return FileService
     */
    public function setApiConnector(?ApiConnector $apiConnector): FileService
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
     * @return FileService
     */
    public function setDriveId(string $driveId): FileService
    {
        $this->driveId = $driveId;
        return $this;
    }

    /**
     * @param string|null $path
     * @param string|null $itemId
     * @return string
     * @throws Exception
     */
    private function getFileBaseUrl(?string $path = null, ?string $itemId = null, ?string $suffix = null): string
    {
        if ($path === null && $itemId === null) {
            throw new \Exception('Microsoft SP Drive Request: Not all the parameters are correctly set. ' . __FUNCTION__, 2211);
        }

        // /drives/{drive-id}/items/{item-id}
        // /drives/{drive-id}/root:/{item-path}
        // https://docs.microsoft.com/en-us/graph/api/driveitem-get?view=graph-rest-1.0&tabs=http
        if ($itemId !== null) {
            return sprintf('/v1.0/drives/%s/items/%s%s', $this->getDriveId(), $itemId, ($suffix ?? ''));
        }
        $path = ltrim($path, '/');
        return sprintf('/v1.0/drives/%s/items/root:/%s%s', $this->getDriveId(), $path, ($suffix !== null ? ':'.$suffix : ''));
    }

    /**
     * Read or Download the content of a file by ItemId
     *
     * @param string|null $path
     * @param string|null $itemId
     * @return string
     * @throws Exception
     */
    public function readFile(?string $path = null, ?string $itemId = null): string
    {
        $url = $this->getFileBaseUrl($path, $itemId, '/content');

        return $this->apiConnector->request('GET', $url);
    }

    /**
     * Use the ItemId to retrieve the URL to stream a file
     *
     * @param string|null $path
     * @param string|null $itemId
     * @return string
     * @throws Exception
     */
    public function requestFileStreamUrl(?string $path = null, ?string $itemId = null): string
    {
        $url = $this->getFileBaseUrl($path, $itemId, '?select=@microsoft.graph.downloadUrl');

        $response = $this->apiConnector->request('GET', $url);

        if (! isset($response['@microsoft.graph.downloadUrl'])) {
            throw new \Exception('Microsoft SP Drive Request: Cannot parse the body of the sharepoint drive request. ' . __FUNCTION__, 2221);
        }

        return $response['@microsoft.graph.downloadUrl'];
    }

    /**
     * @param string|null $path
     * @param string|null $itemId
     * @return array
     * @throws Exception
     */
    public function requestFileMetadata(?string $path = null, ?string $itemId = null): ?array
    {
        $url = $this->getFileBaseUrl($path, $itemId);

        $response = $this->apiConnector->request('GET', $url);

        if (isset($response['error'], $response['error']['code']) && $response['error']['code'] === 'itemNotFound') {
            return null;
        }

        if ( ! isset($response['id'], $response['name'], $response['webUrl'])) {
            throw new \Exception('Microsoft SP Drive Request: Cannot parse the body of the sharepoint drive request. ' . __FUNCTION__, 2221);
        }

        return $response;
    }


    /**
     * @param string|null $path
     * @param string|null $itemId
     * @return bool
     * @throws Exception
     */
    public function checkFileExists(?string $path = null, ?string $itemId = null): bool
    {
        $fileMetaData = $this->requestFileMetadata($path, $itemId);

        if (isset($fileMetaData['folder'])) {
            throw new \Exception('Check for file exists but path is actually a folder', 2231);
        }

        return ($fileMetaData !== null);
    }

    /**
     * @param string|null $path
     * @param string|null $itemId
     * @return int
     * @throws Exception
     */
    public function checkFileLastModified(?string $path = null, ?string $itemId = null): int
    {
        // Will throw exception if file not exists
        $fileMetaData = $this->requestFileMetadata($path, $itemId);

        if ($fileMetaData === null) {
            throw new \Exception('Microsoft SP Drive Request: File not found. ' . __FUNCTION__, 2241);
        }

        if ( ! isset($fileMetaData['lastModifiedDateTime'])) {
            throw new \Exception('Microsoft SP Drive Request: Cannot parse the body of the sharepoint drive request. ' . __FUNCTION__, 2242);
        }

        return (new \DateTime($fileMetaData['lastModifiedDateTime']))->getTimestamp();
    }


    /**
     * @param string|null $path
     * @param string|null $itemId
     * @return string
     * @throws Exception
     */
    public function checkFileMimeType(?string $path = null, ?string $itemId = null): string
    {
        // Will throw exception if file not exists
        $fileMetaData = $this->requestFileMetadata($path, $itemId);

        if ($fileMetaData === null) {
            throw new \Exception('Microsoft SP Drive Request: File not found. ' . __FUNCTION__, 2251);
        }

        if ( ! isset($fileMetaData['file'], $fileMetaData['file']['mimeType'])) {
            throw new \Exception('Microsoft SP Drive Request: Cannot parse the body of the sharepoint drive request. ' . __FUNCTION__, 2252);
        }

        return $fileMetaData['file']['mimeType'];
    }

    /**
     * @param string|null $path
     * @param string|null $itemId
     * @return int
     * @throws Exception
     */
    public function checkFileSize(?string $path = null, ?string $itemId = null): int
    {
        // Will throw exception if file not exists
        $fileMetaData = $this->requestFileMetadata($path, $itemId);

        if ($fileMetaData === null) {
            throw new \Exception('Microsoft SP Drive Request: File not found. ' . __FUNCTION__, 2261);
        }

        if ( ! isset($fileMetaData['size'])) {
            throw new \Exception('Microsoft SP Drive Request: Cannot parse the body of the sharepoint drive request. ' . __FUNCTION__, 2263);
        }

        return $fileMetaData['size'];
    }

    /**
     * @param string $path
     * @param string $content
     * @param string $mimeType
     * @return array|null
     * @throws Exception
     */
    public function writeFile(string $path, string $content, string $mimeType = 'text/plain'): ?array
    {
        $parent = explode('/', $path);
        $fileName = array_pop($parent);

        // Create parent folders if not exists
        $parentFolder = sprintf('/%s', ltrim(implode('/', $parent), '/'));
        if($parentFolder !== '/') {
            $this->folderService->createFolderRecursive($parentFolder);
        }

        $parentFolderMeta = $this->folderService->requestFolderMetadata($parentFolder);
        $parentFolderId = $parentFolderMeta['id'];

        $url = $this->getFileBaseUrl(null, $parentFolderId, sprintf(':/%s:/content', $fileName));

        $response = $this->apiConnector->request('PUT', $url, [], [], $content, [
            RequestOptions::HEADERS => [
                'Content-Type' => $mimeType
            ]
        ]);

        if ($response) {
            return $response;
        }
        return null;
    }

    /**
     * @param string $path
     * @param string $targetDirectory
     * @param string|null $newName
     * @return array
     * @throws Exception
     */
    public function moveFile(string $path, string $targetDirectory, ?string $newName = null): array
    {
        // get current file id,
        $metadata = $this->requestFileMetadata($path);

        if ($metadata === null) {
            throw new \Exception('Microsoft SP Drive Request: File not found. ' . __FUNCTION__, 2271);
        }
        $url = $this->getFileBaseUrl($path, $metadata['id']);

        // get target folder id
        $folderMeta = $this->folderService->requestFolderMetadata($targetDirectory);

        if ($folderMeta === null) {
            // create folders recursive
            $this->folderService->createFolderRecursive($targetDirectory);
            $folderMeta = $this->folderService->requestFolderMetadata($targetDirectory);
        }

        // Build request
        $body = [
            'parentReference' => [
                'id' => $folderMeta['id'],
            ]
        ];

        // add new name to request body when not null
        if ($newName !== null) {
            $body['name'] = $newName;
        }

        $response = $this->apiConnector->request('PATCH', $url, [], [], null, [
            RequestOptions::JSON => $body
        ]);

        return $response;

    }

    /**
     * @param string $path
     * @param string $targetDirectory
     * @param string|null $newName
     * @return bool
     * @throws Exception
     */
    public function copyFile(string $path, string $targetDirectory, ?string $newName = null): bool
    {
        // get current file id,
        $metadata = $this->requestFileMetadata($path);

        if ($metadata === null) {
            throw new \Exception('Microsoft SP Drive Request: File not found. ' . __FUNCTION__, 2281);
        }
        $url = $this->getFileBaseUrl(null, $metadata['id'], '/copy');

        // get target folder id
        $folderMeta = $this->folderService->requestFolderMetadata($targetDirectory);

        if ($folderMeta === null) {
            // create folders recursive
            $this->folderService->createFolderRecursive($targetDirectory);
            $folderMeta = $this->folderService->requestFolderMetadata($targetDirectory);
        }

        // Build request
        $body = [
            'parentReference' => [
                'driveId' => $this->getDriveId(),
                'id' => $folderMeta['id'],
            ]
        ];

        // add new name to request body when not null
        if ($newName !== null) {
            $body['name'] = $newName;
        }

        $result = $this->apiConnector->request('POST', $url, [], [], null, [
            RequestOptions::JSON => $body
        ]);

        if(isset($result['error'], $result['error']['code']) && $result['error']['code'] === 'nameAlreadyExists') {
            throw new Exception('Target file already exists, this is not supported yet.');
        }

        return ($result === '');
    }

    /**
     * @param string|null $path
     * @param string|null $itemId
     * @return bool
     * @throws Exception
     */
    public function deleteFile(?string $path = null, ?string $itemId = null): bool
    {
        $url = $this->getFileBaseUrl($path, $itemId);

        try {
            $this->apiConnector->request('DELETE', $url);
            return true;
        } catch (Exception $exception) {
            return false;
        }
    }
}
