#Backblaze B2 PHP API Wrapper 
Forked by triblytree, originally by [Dan Rovito](https://www.danrovito.com) and [Aidhan Dossel](https://aidhan.net/)

This is a PHP wrapper for the [Backblaze B2](https://www.backblaze.com/b2/cloud-storage.html) API.

This wrapper is in alpha and should not be used on production sites.

##Usage
1. Clone the repository

```
git clone https://github.com/triblytree/b2-api.git
```

2. Include b2_api.php when required

```php
include "/path/to/b2_api.php"; // Include the API wrapper
```

OR

For you folks that use Composer

Add the following to your 'repositories' in your composer.json

```
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/triblytree/b2-api.git"
    }
]
```

And add the following to 'required' in your composer.json

```
"triblytree/b2-api": "dev-master"
```

And run

```
composer update
```

####Requirements
- PHP 5.3.3+ (works on 7.0)
- php-curl
- php-json
- php-mbstring
If you're using Composer, it should get all the dependencies figured out but you still need to have the PHP extensions installed.

###Sample code
You need to pass your Account ID and Application key from your B2 account to authorize access:

```php
$b2 = new b2_api("ACCOUNTID", "APPLICATIONKEY");
```

After authorizing, you can access the returned data if you need to on the $b2 object:
- $b2->authorizationToken
- $b2->apiUrl
- $b2->downloadUrl

##Calls

Currently only the following API calls are supported, see the examples directory for full examples or see [B2 API](https://www.backblaze.com/b2/docs/) for more information about each call.

#### b2_create_bucket
```php
$b2->b2_create_bucket($bucketName, $bucketType)

$bucketName // The new bucket's name. 6 char min, 50 char max, letters, digits, - and _ are allowed
$bucketType // Type to create the bucket as, either allPublic or allPrivate
```

#### b2_delete_bucket
```php
$b2->b2_delete_bucket($bucketId)

$bucketId // The ID of the bucket you want to delete
```

#### b2_list_buckets
```php
$b2->b2_list_buckets()

```

#### b2_get_bucket_id
```php
$b2->b2_get_bucket_id($bucketName)

$bucketName // The name of the bucket you want to get the ID of
```

#### b2_delete_file_version
```php
$b2->b2_delete_file_version($fileId, $fileName)

$fileId // The ID of the file you want to delete
$fileName // The file name of the file you want to delete (including any folder you may haved put it in)
```

#### b2_get_download_authorization
```php
$b2->b2_get_download_authorization($bucketId, $fileNamePrefix, $validDurationInSeconds = 3600);

$bucketId // The ID of the bucket containing the file(s) you wish to authorize the downloading of
$fileNamePrefix // The name or prefix of the file(s) you wish to authorize the downloading of
$validDurationInSeconds // The number of seconds this downloa authorization will be valid for. Default: 3600 (1 hour)
```
```

#### b2_download_file_by_id
```php
$b2->b2_download_file_by_id($fileId)

$fileId // The ID of the file you wish to download
```

#### b2_download_file_by_id_url
```php
$b2->b2_download_file_by_id_url($fileId)

$fileId // The ID of the file you wish to get the download URL of
```

#### b2_download_file_by_name
```php
$b2->b2_download_file_by_name($bucketName, $fileName, [$authorizationToken]);

$bucketName // The name of the bucket you wish to download from
$fileName // The name of the file you wish to download
$authorizationToken // The authorization token retrieved from a b2_get_download_authorization() call. Optional.
```

#### b2_download_file_by_name_url
```php
$b2->b2_download_file_by_name_url($bucketName, $fileName, [$authorizationToken]);

$bucketName // The name of the bucket you wish to download from
$fileName // The name of the file you wish to get the download URL of
$authorizationToken // The authorization token retrieved from a b2_get_download_authorization() call. Optional.
```

#### b2_get_file_info
```php
$b2->b2_get_file_info($fileId)

$fileId // The ID of the file you wish to recieve the info of
```

#### b2_hide_file
```php
$b2->b2_hide_file($bucketId, $fileName)

$bucketId // The ID of the bucket containing the file you wish to hide
$fileName // The name of the file you wish to hide
```

#### b2_list_file_names
```php
$b2->b2_list_file_names($bucketId, [$options])

$bucketId // The ID of the bucket containing the files you wish to list

$options = array( // None of these options are required but may be used
    "maxFileCount" => "", // The maxiumum amount of file names to list in a call
    "startFileName" => "" // If the specified file name exists, it's the first listed
);
```

#### b2_list_file_versions
```php
$b2->b2_list_file_versions($bucketId, [$options])

$bucketId // The ID of the bucket containing the files you wish to list

$options = array( // None of these options are required but may be used
    "max_count" => "", // The maxiumum amount of file names to list in a call
    "start_id" => "", // If the specified file ID exists, it's the first listed
    "start_name" => "" // If the specified file name exists, it's the first listed
);
```

#### b2_update_bucket
```php
$b2->b2_update_bucket($bucketId, $bucketType)

$bucketId // The ID of the bucket you want to update
$bucketType // Type to change to, either allPublic or allPrivate
```

#### upload_file
A helper function which handles all the aspects of file uploading, including large file uploads.
Note: there are also upload_regular_file() and upload_large_file() methods you could use if you needed.
```php
$b2->upload_file($bucketId, $filePath, [$fileName], [$size], [$contentType])

$bucketId // The ID of the bucket you want to upload to
$filePath // The path to the file you wish to upload
$fileName // Optional. The name for the file when stored on B2 (can include folder). Defaults to basename of $filePath.
$size // Optional. The size of the file we're uploading. Defaults to filesize($filePath)
$contentType // Optional. The content type of the file we're uploading. Large files only. Defaults to 'b2/x-auto' (B2 will automatically determine content type once large file is complete).
```

#### b2_get_upload_url
Note: you can use $b2->upload_file() method to more easily handle file uploading.
```php
$b2->b2_get_upload_url($bucketId)

$bucketId // The ID of the bucket you want to upload to

// Returns
An object containing the uploadUrl and authorizationToken you'll use in $b2->b2_$b2->upload_file()
```

#### b2_upload_file
Note: you can use $b2->upload_file() method to more easily handle file uploading.
```php
$b2->b2_upload_file($uploadUrl, $authorizationToken, $filePath, [$fileName])

$uploadUrl // Upload URL, obtained from the b2_get_upload_url call
$authorizationToken // The Authorization Token, obtained from the b2_get_upload_url call (this is NOT the authorization token [$b2->authorizationToken] we store when first authenticating/initializing the class)
$filePath // The path to the file you wish to upload
$fileName // Optional. The name for the file when stored on B2 (can include folder). Defaults to basename of $filePath.
```

#### b2_start_large_file
Note: you can use $b2->upload_file() method to more easily handle file uploading.
```php
$b2->b2_start_large_file($bucketId, [$fileName], [$contentType])

$bucketId // The ID of the bucket you want to upload to
$fileName // Optional. The name for the file when stored on B2 (can include folder). Defaults to basename of $filePath.
$contentType // Optional. The content type of the file we're uploading. Large files only. Defaults to 'b2/x-auto' (B2 will automatically determine content type once large file is complete).

// Returns
An object containing the fileId you'll use in $b2->b2_get_upload_part_url()
```

#### b2_get_upload_part_url
Note: you can use $b2->upload_file() method to more easily handle file uploading.
```php
$b2->b2_get_upload_url($fileId)

$fileId // The file ID you retrieved via a $b2->b2_start_large_file() call

// Returns
An object containing the uploadUrl and authorizationToken you'll use in $b2->b2_upload_part()
```

#### b2_upload_part
Note: you can use $b2->upload_file() method to more easily handle file uploading.
```php
$b2->b2_upload_part($uploadUrl, $authorizationToken, $partNumber, $body, [$sha])

$uploadUrl // Upload URL, obtained from the b2_get_upload_part_url call
$authorizationToken // The Authorization Token, obtained from the b2_get_upload_part_url call (this is NOT the authorization token [$b2->authorizationToken] we store when first authenticating/initializing the class)
$partNumber // The part number we're uploading (starting with 1).                            
$body // The actual body part we're uploading (ex: substr($body_full,0,1000)).
$sha // Optional. The sha of the body part. Don't need to pass, just used internally for small speed improvement. Defaults to sha1($body).
```

#### b2_finish_large_file
Note: you can use $b2->upload_file() method to more easily handle file uploading.
```php
$b2->b2_finish_large_file($fileId, $shaArray)

$fileId // The file ID you retrieved via a $b2->b2_start_large_file() call
$shaArray // An array of the sha 1 values you sent to the $b2->b2_upload_part() calls
```
