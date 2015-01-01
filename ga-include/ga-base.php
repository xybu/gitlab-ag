<?php
/**
 * The base class to derive all controller classes.
 * It includes the set of functions to construct a controller.
 * 
 * @author Xiangyu Bu <xybu92@live.com>
 */

abstract class Base {
	
	const CHAR_SET = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
	
	/**
	* Return the HTTP method string.
	* @returns One of {'get', 'post', 'head', 'put', etc.}
	*/
	public function GetHttpMethod() {
		return strtolower($_SERVER['REQUEST_METHOD']);
	}
	
	/**
	 * A simple function that returns whether the current request is
	 * HTTP GET.
	 * @returns true if the request is HTTP GET; false otherwise.
	 */
	public function IsHttpGet() {
		return $this->GetHttpMethod() == 'get';
	}
	
	public function IsHttpPost() {
		return $this->GetHttpMethod() == 'post';
	}
	
	/**
	 * Generate a random string of specified length.
	 */
	public function GetRandStr($len) {
		return substr(str_shuffle(self::CHAR_SET . str_shuffle(self::CHAR_SET . self::CHAR_SET)), 0, $len);
	}
	
	/**
	 * Encrypt the data with SHA-256 algorithm.
	 */
	public function SHA_Encrypt($data) {
		return hash('sha256', $data);
	}
	
	/**
	 * Encode the $str by AES-256 algorithm with $key.
	 */
	public function AES_Encrypt($str, $key) {
		return openssl_encrypt($str, 'AES-256-ECB', $key);
	}
	
	/**
	 * Decode the $str with AES-256 algorithm and $key.
	 */
	public function AES_Decrypt($str, $key) {
		$trial = openssl_decrypt($str, 'AES-256-ECB', $key);
		if (!$trial) return null;
		return $trial;
	}
	
	public function JSON_OutputError($title, $desc, $http_error_code = '400 Bad Request') {
		header("HTTP/1.1 $http_error_code");
		header("Content-Type: application/json");
		header("Cache-Control: no-cache, must-revalidate");
		$str = json_encode(array('error' => $title, 'desc' => $desc));
		header("Content-Length: " . strlen($str));
		echo $str;
		exit();
	}
	
}