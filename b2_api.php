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
    	public $appKey, $acccountId, $authorizationToken, $apiUrl, $downloadUrl, $buckets;
    	
    	// Construct
        public function __construct($acccountId, $appKey)
        {
            $this->accountId = $acccountId; 
            $this->appKey = $appKey;
            $this->b2_authorize_account();
        }

		// Base function for further calls
		function b2_call($call_url, $headers, $data = NULL, $json_result = true) 
		{
			$error = NULL;
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
			}
			curl_close($session); // Clean up
	
			if(!$error) {
				// Parse JSON
				if($json_result) {
					$json = json_decode($http_result);
					if(json_last_error() == JSON_ERROR_NONE || json_last_error() == 0) // Check if the response is JSON
					{
						if(!empty($json->status)) $error = $json->status.": ".$json->message;
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
            	throw new Exception($error);
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
			define('B2_'.$this->accountId.'_authorizationToken',$this->authorizationToken);
			define('B2_'.$this->accountId.'_apiUrl',$this->apiUrl);
			define('B2_'.$this->accountId.'_downloadUrl',$this->downloadUrl);
        }

        // Cancel large file
        // Part of the large files API, not functional at time of writing
        public function b2_cancel_large_file()
        {

        }

        // Create bucket
        public function b2_create_bucket($bucketName, $bucketType)
        {
            $call_url    = $this->apiUrl."/b2api/v1/b2_create_bucket";
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
            $fileId    = $fileId; // The ID of the file you want to delete
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

        // Finish large file
        // Part of the large files API, not functional at time of writing
        public function b2_finish_large_file()
        {

        }

        // Get file info
        public function b2_get_file_info($fileId)
        {
            $call_url   = $this->apiUrl."/b2api/v1/b2_get_file_info";
            $fileId    = $fileId; // The ID of the file you wish to recieve the info of

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

        // Start large file
        // Part of the large files API, not functional at time of writing
        public function b2_start_large_file()
        {

        }

        // List update bucket
        public function b2_update_bucket($bucketId, $bucketType)
        {
            $call_url    = $this->apiUrl."/b2api/v1/b2_update_bucket";
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

        // List upload file
        public function b2_upload_file($uploadUrl, $filePath)
        {
            $call_url   = $uploadUrl; // Upload URL, obtained from the b2_get_upload_url call
            $filePath  = $filePath; // The path to the file you wish to upload

            $handle = fopen($filePath, 'r');
            $read_file = fread($handle, filesize($filePath));

            $fileName = basename($filePath);
            $file_type = mime_content_type($filePath);
            $file_hash = sha1_file($filePath);

            // Add headers
            $headers = array(
                "Authorization: {$this->authorizationToken}",
                "X-Bz-File-Name: {$fileName}",
                "Content-Type: {$file_type}",
                "X-Bz-Content-Sha1: {$file_hash}"
            );

            $result = $this->b2_call($call_url, $headers, $read_file);
            return $result; // Return the result
        }

        // Upload part
        // Part of the large files API, not functional at time of writing
        public function b2_upload_part()
        {

        }
    }
