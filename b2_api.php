<?php

/**
 * Backblaze B2 API wrapper for PHP
 *
 * @author Aidhan Dossel
 * @copyright aidhan.net
 * @version dev-master
 *
 */

class b2_api
{
	public $appKey, $acccountId, $authorizationToken, $apiUrl, $downloadUrl, $buckets, $recommendedPartSize;
    
	/**
     * Lower limit for using large files upload support. More information:
     * https://www.backblaze.com/b2/docs/large_files.html. Default: 3 GB
     * Files larger than this value will be uploaded in multiple parts.
     *
     * @var int
     */
    //public $largeFileLimit = 3000000000; // 3GB
    //public $largeFileLimit = 500000000; // 500MB
    public $largeFileLimit = 50000000; // 50MB
	
	// Construct
	public function __construct($acccountId, $appKey)
	{
		$this->accountId = $acccountId; 
		$this->appKey = $appKey;
		$this->b2_authorize_account();
	}

	// Base function for further calls
	function b2_call($call_url, $headers, $data = NULL, $json_result = true,$try = 1) 
	{
		$error = NULL;
		$error_code = 0;
		$session = curl_init();
		curl_setopt($session, CURLOPT_URL, $call_url);

		if(!empty($data)) // Check if POST data exists
		{
			if(is_array($data)) // Check if the data is an array
			{
				$data = json_encode($data); // Encode the data as JSON
			}

			curl_setopt($session, CURLOPT_POST, true); // Make the request a POST
			curl_setopt($session, CURLOPT_POSTFIELDS, $data); // Add the data to the request
		}

		else
		{
			curl_setopt($session, CURLOPT_HTTPGET, true); // Make the request a GET
		}
		
		curl_setopt($session, CURLOPT_HTTPHEADER, $headers); // Include the headers
		curl_setopt($session, CURLOPT_RETURNTRANSFER, true);  // Receive server response
		curl_setopt($session, CURLOPT_SSL_VERIFYPEER, 0);
		//curl_setopt($session, CURLOPT_SSL_VERIFYHOST, 0); // Don't need I don't think
		if(!$http_result = curl_exec($session)) { // Execute the request
			// Error
			$error = "Curl error: ".curl_error($session)." (".curl_errno($session).")";
			$error_code = curl_errno($session);
		}
		curl_close($session); // Clean up

		if(!$error) {
			// Parse JSON
			if($json_result) {
				$json = json_decode($http_result);
				if(json_last_error() == JSON_ERROR_NONE || json_last_error() == 0) // Check if the response is JSON
				{
					if(!empty($json->status)) {
						$error = $json->status.": ".$json->message.", full result: ".$http_result;
						$error_code = $json->status;
					}
				}
				else
				{
					$error = ($http_result ? $http_result : "Unknown error");
				}
			}
			// No JSON - return result
			else $json = $http_result; 
		}
		
		// Error
		if($error) {
			// Re-try up to 3 times, B2 has all sort of issues, failure is built into how it works
			if($try < 3) {
				$try++;
				$this->b2_call($call_url,$headers,$data,$json_result,$try);
			}
			else throw new Exception($error,$error_code);
		}
		// Return
		else {
			return $json;	
		}
	}
	
	// Account authorization
	public function b2_authorize_account()
	{
		// Already authorized
		if($this->authorizationToken) return;
		
		// Already authorized globally - used just to save time on re-authorizing if already did on this page
		if(defined('B2_'.$this->accountId.'_authorizationToken')) {
			$this->authorizationToken = constant('B2_'.$this->accountId.'_authorizationToken');
			$this->apiUrl = constant('B2_'.$this->accountId.'_apiUrl');
			$this->downloadUrl = constant('B2_'.$this->accountId.'_downloadUrl');
			$this->recommendedPartSize = constant('B2_'.$this->accountId.'_recommendedPartSize');
			return;
		}
		
		$call_url = "https://api.backblaze.com/b2api/v1/b2_authorize_account";
		$credentials = base64_encode($this->accountId.":".$this->appKey);

		// Add headers
		$headers = array(
			"Accept: application/json",
			"Authorization: Basic {$credentials}"
		);

		// Call
		$result = $this->b2_call($call_url, $headers);
		
		// Store values
		$this->authorizationToken = $result->authorizationToken;
		$this->apiUrl = $result->apiUrl;
		$this->downloadUrl = $result->downloadUrl;
		$this->recommendedPartSize = $result->recommendedPartSize;
		define('B2_'.$this->accountId.'_authorizationToken',$this->authorizationToken);
		define('B2_'.$this->accountId.'_apiUrl',$this->apiUrl);
		define('B2_'.$this->accountId.'_downloadUrl',$this->downloadUrl);
		define('B2_'.$this->accountId.'_recommendedPartSize',$this->recommendedPartSize);
	}

	// Cancel large file
	// Part of the large files API, not functional at time of writing
	public function b2_cancel_large_file()
	{

	}

	// Create bucket
	public function b2_create_bucket($bucketName, $bucketType)
	{
		$call_url	= $this->apiUrl."/b2api/v1/b2_create_bucket";
		$this->accountId  = $this->accountId; // Obtained from your B2 account page
		$bucketName = $bucketName; // The new bucket's name. 6 char min, 50 char max, letters, digits, - and _ are allowed
		$bucketType = $bucketType; // Type to change to, either allPublic or allPrivate

		// Add POST fields
		$data = array(
			"accountId"  => $this->accountId,
			"bucketName" => $bucketName,
			"bucketType" => $bucketType
		);

		// Add headers
		$headers = array(
			"Authorization: {$this->authorizationToken}"
		);

		$result = $this->b2_call($call_url, $headers, $data);
		return $result; // Return the result
	}

	// Delete bucket
	public function b2_delete_bucket($bucketId)
	{
		$call_url   = $this->apiUrl."/b2api/v1/b2_delete_bucket";
		$bucketId  = $bucketId; // The ID of the bucket you want to delete

		// Add POST fields
		$data = array(
			"accountId" => $this->accountId,
			"bucketId"  => $bucketId
		);

		// Add headers
		$headers = array(
			"Authorization: {$this->authorizationToken}"
		);

		$result = $this->b2_call($call_url, $headers, $data);
		return $result; // Return the result
	}

	// List buckets
	public function b2_list_buckets()
	{
		$call_url   = $this->apiUrl."/b2api/v1/b2_list_buckets";

		// Add POST fields
		$data = array(
			"accountId" => $this->accountId
		);

		// Add headers
		$headers = array(
			"Authorization: {$this->authorizationToken}"
		);

		$result = $this->b2_call($call_url, $headers, $data);
		
		// Store
		if(!empty($result->buckets)) $this->buckets = $result->buckets;	
		else $this->buckets = (object) array();
		
		return $result; // Return the result
	}
	
	/**
	 * Returns the ID of the bucket corresponding to the given bucket name.
	 */
	public function b2_get_bucket_id($bucketName) {
		if(!isset($this->buckets)) $this->b2_list_buckets();
		if($this->buckets) {
			foreach($this->buckets as $bucket) {
				if($bucket->bucketName == $bucketName) return $bucket->bucketId;	
			}
		}
	}

	// Delete file version
	public function b2_delete_file_version($fileId, $fileName)
	{
		$call_url   = $this->apiUrl."/b2api/v1/b2_delete_file_version";
		$fileId	= $fileId; // The ID of the file you want to delete
		$fileName  = $fileName; // The file name of the file you want to delete

		// Add POST fields
		$data = array(
			"fileId"   => $fileId, 
			"fileName" => $fileName
		);

		// Add headers
		$headers = array(
			"Authorization: {$this->authorizationToken}"
		);

		$result = $this->b2_call($call_url, $headers, $data);
		return $result; // Return the result
	}

	// Get download authorization by bucket / file name
	public function b2_get_download_authorization($bucketId, $fileNamePrefix, $validDurationInSeconds = 3600)
	{
		$call_url = $this->apiUrl."/b2api/v1/b2_get_download_authorization";

		// Add POST fields
		$data = array(
			"bucketId"  => $bucketId,
			"fileNamePrefix" => $fileNamePrefix,
			"validDurationInSeconds" => $validDurationInSeconds
		);
		
		// Add headers
		$headers = array(
			"Authorization: {$this->authorizationToken}"
		);

		$result = $this->b2_call($call_url, $headers, $data);
		return $result; // Return the result
	}

	/**
	 * Download file by ID 
	 *
	 * @param string $fileId The ID of the file you wish to download
	 * @return string The contents of the file.
	 */
	public function b2_download_file_by_id($fileId)
	{
		$call_url = $this->b2_download_file_by_id_url($fileId);

		// Add headers
		$headers = array(
			"Authorization: {$this->authorizationToken}"
		);

		$result = $this->b2_call($call_url, $headers, NULL, false);
		return $result; // Return the result
	}

	/**
	 * Gets download URL of file by ID 
	 *
	 * @param string $fileId The ID of the file you wish to get the download URL of
	 * @return string The URL you can use to download the file.
	 */
	public function b2_download_file_by_id_url($fileId)
	{
		return $this->downloadUrl."/b2api/v1/b2_download_file_by_id?fileId=".$fileId;
	}

	/**
	 * Download file by name 
	 *
	 * @param string $bucketName The name of the bucket you wish to download from
	 * @param string $fileName The name of the file you wish to download
	 * @param string $authorizationToken The authorization token retrieved from a b2_get_download_authorization() call. Optional.
	 * @return string The contents of the file.
	 */
	public function b2_download_file_by_name($bucketName, $fileName, $authorizationToken = NULL)
	{
		$call_url = $this->b2_download_file_by_name_url($bucketName,$fileName,$authorizationToken);

		// Add headers
		$headers = array(
			"Authorization: {$this->authorizationToken}"
		);

		$result = $this->b2_call($call_url, $headers, NULL, false);
		return $result; // Return the result
	}

	/**
	 * Gets download URL of file by name 
	 *
	 * @param string $bucketName The name of the bucket you wish to download from
	 * @param string $fileName The name of the file you wish to get the download URL of
	 * @param string $authorizationToken The authorization token retrieved from a b2_get_download_authorization() call. Optional.
	 * @return string The URL you can use to download the file.
	 */
	public function b2_download_file_by_name_url($bucketName, $fileName, $authorizationToken = NULL)
	{
		return $this->downloadUrl."/file/".$bucketName."/".$fileName.($authorizationToken ? "?Authorization=".$authorizationToken : "");
	}

	// Get file info
	public function b2_get_file_info($fileId)
	{
		$call_url   = $this->apiUrl."/b2api/v1/b2_get_file_info";
		$fileId	= $fileId; // The ID of the file you wish to recieve the info of

		// Add POST fields
		$data = array(
			"fileId" => $fileId
		);

		// Add headers
		$headers = array(
			"Authorization: {$this->authorizationToken}"
		);

		$result = $this->b2_call($call_url, $headers, $data);
		return $result; // Return the result
	}

	// Hide file
	public function b2_hide_file($bucketId, $fileName)
	{
		$call_url   = $this->apiUrl."/b2api/v1/b2_hide_file";
		$bucketId  = $bucketId; // The ID of the bucket containing the file you wish to hide
		$fileName  = $fileName; // The name of the file you wish to hide

		// Add POST fields
		$data = array(
			"bucketId" => $bucketId,
			"fileName" => $fileName
		);

		// Add headers
		$headers = array(
			"Authorization: {$this->authorizationToken}"
		);

		$result = $this->b2_call($call_url, $headers, $data);
		return $result; // Return the result
	}

	/**
	 * List file names
	 *
	 * Options
	 * - maxFileCount - The maxiumum amount of file names to list in a call
	 * - startFileName - If the specified file name exists, it's the first listed
	 *
	 * @param int $bucketId The id of the bucket you want to list files in.
	 * @param array $options An array of options (startFileName and maxFileCount) to pass. Default = NULL
	 * @return object An object containg array of matching file names
	 */
	public function b2_list_file_names($bucketId, $options = NULL)
	{
		if(!$bucketId) return;
		$call_url   = $this->apiUrl."/b2api/v1/b2_list_file_names";

		// Add POST fields
		$data = $options;
		$data['bucketId'] = $bucketId;

		// Add headers
		$headers = array(
			"Authorization: {$this->authorizationToken}"
		);

		$result = $this->b2_call($call_url, $headers, $data);
		return $result; // Return the result
	}

	/**
	 * List file versions
	 *
	 * Options
	 * - maxFileCount - The maxiumum amount of file names to list in a call
	 * - startFileId - If the specified file ID exists, it's the first listed
	 * - startFileName - If the specified file name exists, it's the first listed
	 * 
	 * @param int $bucketId The ID of the bucket containing the files you wish to list
	 * @param array $options An array of options (startFileName, startFileId, and maxFileCount) to pass. Default = NULL
	 * @return object An object containg array of matching file names
	 */
	public function b2_list_file_versions($bucketId, $options = NULL)
	{
		$call_url   = $this->apiUrl."/b2api/v1/b2_list_file_versions";

		// Add POST fields
		$data = $options;
		$data['bucketId'] = $bucketId;

		// Add headers
		$headers = array(
			"Authorization: {$this->authorizationToken}"
		);

		$result = $this->b2_call($call_url, $headers, $data);
		return $result; // Return the result
	}

	// List parts
	// Part of the large files API, not functional at time of writing
	public function b2_list_parts()
	{

	}

	// List unfinished large files
	// Part of the large files API, not functional at time of writing
	public function b2_list_unfinished_large_files()
	{

	}

	// Update bucket
	public function b2_update_bucket($bucketId, $bucketType)
	{
		$call_url	= $this->apiUrl."/b2api/v1/b2_update_bucket";
		$this->accountId  = $this->accountId; // Obtained from your B2 account page
		$bucketId   = $bucketId; // The ID of the bucket you want to update
		$bucketType = $bucketType; // Type to change to, either allPublic or allPrivate

		// Add POST fields
		$data = array(
			"accountId"  => $this->accountId,
			"bucketId"   => $bucketId,
			"bucketType" => $bucketType
		);

		// Add headers
		$headers = array(
			"Authorization: {$this->authorizationToken}"
		);

		$result = $this->b2_call($call_url, $headers, $data);
		return $result; // Return the result
	}
	
	/**
	 * Helper for uploading files (large or small)
	 */
	public function upload_file($bucketId,$filePath,$fileName = NULL,$size = NULL,$contentType = 'b2/x-auto') {
		// Size
		if(!$size) $size = filesize($filePath);
		
		// Large
		if($size > $this->largeFileLimit) {
			$result = $this->upload_large_file($bucketId,$filePath,$fileName,$size,$contentType);
		}
		// Default
		else {
			$result = $this->upload_regular_file($bucketId,$filePath,$fileName);
		}
		
		// Return
		return $result;
	}
	
	/**
	 * Helper for uploading large files.
	 * Probably easier to just call $b2->upload_file() and let it handle this.
	 */
	public function upload_large_file($bucketId,$filePath,$fileName = NULL,$size = NULL,$contentType = 'b2/x-auto') {
		// Size
		if(!$size) $size = filesize($filePath);
		
		// File name
		if(!$fileName) $fileName = basename($filePath);
		
		// Large file upload
		$result = $this->b2_start_large_file($bucketId,$fileName,$contentType);
		if(!empty($result->fileId)) {
			// File ID
			$fileId = $result->fileId;
			
			// Part size - maximum equal to $this->largeFileLimit
			$partSize = $this->recommendedPartSize;
			if($partSize > $this->largeFileLimit) $partSize = $this->largeFileLimit;
			
			// Parts
			$partsCount = ceil($size / $partSize);
			
			// Body
			$body = file_get_contents($filePath);
			
			// Upload parts
			$shaArray = [];
			for($i = 1; $i <= $partsCount; $i++) {
				// Part bytes
				$bytesSent = ($i - 1) * $partSize;
				$bytesLeft = $size - $bytesSent;
				$partSize = ($bytesLeft > $partSize) ? $partSize : $bytesLeft;
				
				// Body part
				$body_part = substr($body,$bytesSent,$partSize);
				
				// SHA - need when finishing upload, pass to b2_upload_part() as well to save a smidge of timme
				$sha = sha1($body_part);
				$shaArray[] = $sha;
		
				// Loop - https://www.backblaze.com/blog/b2-503-500-server-error/
				$finished = 0;
				while($finished == 0) {
					try {
						// Retrieve the URL that we should be uploading to.
						$result = $this->b2_get_upload_part_url($fileId);
						
						// Upload part
						$r = $this->b2_upload_part($result->uploadUrl,$result->authorizationToken,$i,$body_part,$sha);
				
						// Finished
						$finished = 1;
					}
					catch(Exception $e) {
						// Non-500 error - continue throwing error (if 500, we retry)
						if(!in_array($e->getCode(),array(500,503))) {
							$finished = 1;
							throw $e;
						}
					}
				}
			}
			
			// Finish upload of large file
			$result = $this->b2_finish_large_file($fileId,$shaArray);
			
			return $result;
		}
		else throw new Exception("Error creationg large file upload");
	}
	
	/**
	 * Helper for uploading regular (non-large) files.
	 * Probably easier to just call $b2->upload_file() and let it handle this.
	 */
	public function upload_regular_file($bucketId,$filePath,$fileName = NULL) {
		// File name
		if(!$fileName) $fileName = basename($filePath);
		
		// Loop - https://www.backblaze.com/blog/b2-503-500-server-error/
		$finished = 0;
		while($finished == 0) {
			// Upload URL
			$result = $this->b2_get_upload_url($bucketId);
			if(!empty($result->uploadUrl)) {
				try {
					// Upload
					$result = $this->b2_upload_file($result->uploadUrl,$result->authorizationToken,$filePath,$fileName);
					return $result;
					
					// Finished
					$finished = 1;
				}
				catch(Exception $e) {
					// Non-500 error - continue throwing error (if 500, we retry)
					if(!in_array($e->getCode(),array(500,503))) {
						$finished = 1;
						throw $e;
					}
				}
			}
			else {
				$finished = 1;
				throw new Exception("Error creationg file upload URL");
			}
		}
	}

	// Get upload URL
	public function b2_get_upload_url($bucketId)
	{
		$call_url   = $this->apiUrl."/b2api/v1/b2_get_upload_url";
		$bucketId  = $bucketId; // The ID of the bucket you want to upload to

		// Add POST fields
		$data = array(
			"bucketId" => $bucketId
		);

		// Add headers
		$headers = array(
			"Authorization: {$this->authorizationToken}"
		);

		$result = $this->b2_call($call_url, $headers, $data);
		return $result; // Return the result
	}

	// Upload file
	public function b2_upload_file($uploadUrl, $authorizationToken, $filePath, $fileName = NULL)
	{
		$call_url = $uploadUrl; // Upload URL, obtained from the b2_get_upload_url call
		$authorizationToken = $authorizationToken; // The Authorization Token, obtained from the b2_get_upload_url call (NOT $this->authorizationToken stored in this class via b2_authorize_account() call)
		$filePath = $filePath; // The path to the file you wish to upload

		$handle = fopen($filePath, 'r');
		$read_file = fread($handle, filesize($filePath));

		if(!$fileName) $fileName = basename($filePath);
		$file_type = mime_content_type($filePath);
		$sha = sha1_file($filePath);

		// Add headers
		$headers = array(
			"Authorization: {$authorizationToken}",
			"X-Bz-File-Name: ".rawurlencode($fileName),
			"Content-Type: {$file_type}",
			"X-Bz-Content-Sha1: {$sha}"
		);

		$result = $this->b2_call($call_url, $headers, $read_file);
		return $result; // Return the result
	}

	// Get start large file id
	public function b2_start_large_file($bucketId,$fileName,$contentType = 'b2/x-auto')
	{
		$call_url = $this->apiUrl."/b2api/v1/b2_start_large_file";
		
		// Add POST fields
		$data = array(
			"bucketId" => $bucketId,
			"fileName" => $fileName,
			"contentType" => $contentType
		);

		// Add headers
		$headers = array(
			"Authorization: {$this->authorizationToken}"
		);

		$result = $this->b2_call($call_url, $headers, $data);
		return $result; // Return the result
	}

	// Get upload part URL
	public function b2_get_upload_part_url($fileId)
	{
		$call_url = $this->apiUrl."/b2api/v1/b2_get_upload_part_url";

		// Add POST fields
		$data = array(
			"fileId" => $fileId
		);

		// Add headers
		$headers = array(
			"Authorization: {$this->authorizationToken}"
		);

		$result = $this->b2_call($call_url, $headers, $data);
		return $result; // Return the result
	}

	// Uploads part of a file
	public function b2_upload_part($uploadUrl, $authorizationToken, $partNumber, $body, $sha = NULL)
	{
		$call_url = $uploadUrl;

		$contentLength = mb_strlen($body,'8bit');
		if(!$sha) $sha = sha1($body);

		// Add headers
		$headers = array(
			"Authorization: {$authorizationToken}",
			"X-Bz-Part-Number: {$partNumber}",
			"Content-Length: {$contentLength}",
			"X-Bz-Content-Sha1: {$sha}"
		);

		$result = $this->b2_call($call_url, $headers, $body);
		return $result; // Return the result
	}
	
	// Finishes large upload
	public function b2_finish_large_file($fileId,$shaArray) {
		$call_url = $this->apiUrl."/b2api/v1/b2_finish_large_file";

		// Add POST fields
		$data = array(
			"fileId" => $fileId,
			"partSha1Array" => $shaArray
		);

		// Add headers
		$headers = array(
			"Authorization: {$this->authorizationToken}"
		);

		$result = $this->b2_call($call_url, $headers, $data);
		return $result; // Return the result
	}
}