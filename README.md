# sharepoint-sdk for the Sharepoint Graph API
Sharepoint SDK to use Sharepoint as filestorage.

For the Flysystem adapter (Symfony and Laravel) see the flysystem package: [gwsn/flysystem-sharepoint-adapter](https://github.com/gwsn/flysystem-sharepoint-adapter)

## Installation
You can install the package via composer:

``` bash
composer require gwsn/sharepoint-sdk
```

## First configuration to start usage

You need to request a new clientId and clientSecret for a new application on Azure.

1. Go to `Azure portal` https://portal.azure.com
2. Go to `Azure Active Directory`
3. Go to `App registrations`
4. Click on `new Registration` and follow the wizard.  
   (give it a name like mine is 'gwsn-sharepoint-connector' and make a decision on the supported accounts, single tenant should be enough but this depends on your organisation)
5. When created the application is created write down the following details
6. 'Application (client) id', this will be your `$clientId`
7. 'Directory (tenant) id', this will be your `$tenantId`
8. Then we go in the menu to the `API permissions` to set the permissions that are required
9. The click on `Add a permission` and add the following permissions:  
   Microsoft Graph:
    - Files.ReadWrite.All
    - Sites.ReadWrite.All
    - User.Read
10. Click on the `Grant admin consent for ...Company...`
11. Go in the menu to `Certificates & secrets`
12. Click on `new client secret`
13. Give it a description and expiry date and the value will be your `$clientSecret`
14. The last parameter will be the sharepoint 'slug', this is part of the url of the sharepoint site what you want to use and creation of sharepoint site is out of scope of this readme.  
    When you sharepoint url is like `https://{tenant}.sharepoint.com/sites/{site-slug}/Shared%20Documents/Forms/AllItems.aspx`  
    You need to set the `$sharepointSite` as `{site-slug}`

    Example:
    - Sharepoint site url: `https://GWSN.sharepoint.com/sites/gwsn-documents-store/Shared%20Documents/Forms/AllItems.aspx`
    - Sharepoint site variable:  `$sharepointSite = 'gwsn-documents-store'`

## Basic usage with the flysystem adapter (preferred way!)
``` php
use GWSN\FlysystemSharepoint\FlysystemSharepointAdapter;
use GWSN\FlysystemSharepoint\SharepointConnector;
use League\Flysystem\Filesystem;

$tenantId = 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx';
$clientId = 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx';
$clientSecret = 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';
$sharepointSite = 'your-path-to-your-site';

$connector = new SharepointConnector($tenantId, $clientId, $clientSecret, $sharepointSite);

$prefix = '/test'; // optional
$adapter = new FlysystemSharepointAdapter($connector, $prefix);


$flysystem = new Filesystem($adapter);
```

## Basic needs to be able to use the folder|drive|file service
``` php

use GWSN\Microsoft\Authentication\AuthenticationService;
use GWSN\Microsoft\Drive\DriveService;
use GWSN\Microsoft\Drive\FileService;
use GWSN\Microsoft\Drive\FolderService;
use GWSN\Microsoft\Sharepoint\SharepointService;

// Not needed if you use a framework with dependency injection !
require dirname(dirname(__DIR__)) . '/vendor/autoload.php';

$tenantId = 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx';
$clientId = 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx';
$clientSecret = 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';

$sharepointSite = 'your-path-to-your-site';

// Login into MS oauth and fetch new access token
// In real application please save the access token and use it until it expires
$authService = new AuthenticationService();
$accessToken = $authService->getAccessToken($tenantId, $clientId, $clientSecret);
```

## Usage for managing for Sharepoint drives
  
include the basic usage and add the following code
``` php
    // Initialize the drive
    $spDrive = new DriveService($accessToken);
    $driveId = $spDrive->requestDriveId($siteId);
    $spDrive->setDriveId($driveId);
    
     // Check if Resource exists
    try {
        $result = $spDrive->checkResourceExists('/test');
        var_dump($result);
        $result = $spDrive->checkResourceExists('/testDoc.docx');
        var_dump($result);
    } catch (\Exception $exception) {
        var_dump($exception->getMessage());
    }

    // move file /test.txt to folder /test and rename it to testje.txt
    $result = $spFileService->moveFile('/test.txt', '/test', 'testje.txt');
    
    // check if it still exists
    $result = $spDrive->checkResourceExists('/test.txt');
    var_dump($result);
    
    // check if new file exists
    $result = $spDrive->checkResourceExists('/test/testje.txt');
    var_dump($result);
    
```

## Usage for managing Sharepoint folders

include the basic usage and add the following code
``` php
try {
    // Initialize the drive
    $spDrive = new DriveService($accessToken);
    $driveId = $spDrive->requestDriveId($siteId);
    $spDrive->setDriveId($driveId);
   
    // Create the folderService
    $spFolderService = new FolderService($accessToken, $driveId);
        
    // Get files from sharepoint folder
    $listRootDirResult = $spFolderService->requestFolderItems('/');

    // Check if Folder exists
    $spFolderService->checkFolderExists('/test');
    
    // Get files from sharepoint sub folder
    $listRootDirResult = $spFolderService->requestFolderItems('/test');
    
    // Get Folder from sharepoint
    $spFolderService->createFolderRecursive('/dummy/test');

    // Delete Folder from sharepoint we just created
    $spFolderService->deleteFolder('/dummy/test');
    $spFolderService->deleteFolder('/dummy');
    
    // Check if Folder exists while its a file
    try {
        $result = $spFolderService->checkFolderExists('/testDoc.docx');
        var_dump($result);
    } catch (\Exception $exception) {
        var_dump($exception->getMessage());
    }
    
} catch (\Exception $exception) {
    var_dump($exception->getMessage());
}
```

## Usage for files in Sharepoint drives
  
include the basic usage and add the following code
``` php
    // FileService
    $spFileService = new FileService($accessToken, $driveId);

    // write file to directory
    $result = $spFileService->writeFile('/test.txt', 'testContent');
    var_dump(isset($result['id']));

    // read file from directory
    $content = $spFileService->readFile('/test.txt');
    var_dump(($content === 'testContent'));

    // write file to directory
    $result = $spFileService->writeFile('/test/docje.txt', 'testContent');
    var_dump(isset($result['id']));

    // read file from directory
    $content = $spFileService->readFile('/test/docje.txt');
    var_dump(($content === 'testContent'));
   

    // move file
    $result = $spFileService->moveFile('/test.txt', '/test', 'testje.txt');
    $result = $spDrive->checkResourceExists('/test.txt');
    var_dump($result);
    $result = $spDrive->checkResourceExists('/test/testje.txt');
    var_dump($result);

    // copy file
    $result = $spFileService->copyFile('/test/testje.txt', '/', 'toBeDeleted.txt');
    var_dump($result);
    // copy dir
    $result = $spFileService->copyFile('/test', '/test2');
    var_dump($result);


    // delete file from directory
    $content = $spFileService->deleteFile('/test/testje.txt');
    var_dump($content);
    $content = $spFileService->deleteFile('/test/docje.txt');
    var_dump($content);
    $content = $spFileService->deleteFile('/toBeDeleted.txt');
    var_dump($content);
    $content = $spFileService->deleteFile('/test.txt');
    var_dump($content);

```


## Testing

``` bash
$ composer run-script test
```

## Security

If you discover any security related issues, please email info@gwsn.nl instead of using the issue tracker.


## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
