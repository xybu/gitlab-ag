<?php
/**
 * A simple HTTP Client implemented by cURL.
 * Might work incorrectly if there is proxy layer in between.
 * 
 * @author Xiangyu Bu <xybu92@live.com>
 */

class HttpClient {
	
	const DEFAULT_UA_STR = 'gitlab-ag Hook Handler';
	
	private $url = null;
	private $curl = null;
	
	public function __construct($url) {
		$this->curl = curl_init();
		$this->url = $url;
		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($this->curl, CURLOPT_HEADER, true);
		curl_setopt($this->curl, CURLOPT_USERAGENT, self::DEFAULT_UA_STR);
	}
	
	public function __destruct() {
		curl_close($this->curl);
	}
	
	public function SetOpt($options) {
		curl_setopt_array($this->curl, $options);
	}
	
	public function SetUserAgent($str) {
		return curl_setopt($this->curl, CURLOPT_USERAGENT, $str);
	}
	
	public function SetConnectionTimeOut($num) {
		return curl_setopt($this->curl, CURLOPT_CONNECTTIMEOUT, $num);
	}
	
	public function SetTimeOut($num) {
		return curl_setopt($this->curl, CURLOPT_TIMEOUT, $num);
	}
	
	public function Get($data = null) {
		$query_string = '';
		if ($data != null)
			$query_string = '?' . http_build_query($data);
		curl_setopt($this->curl, CURLOPT_URL, $this->url . $query_string);
		$result = curl_exec($this->curl);
		if ($result === false) {
			throw new HttpRequestException(curl_error($this->curl), curl_errno($this->curl));
		}
		return new HttpResponseMessage($this->curl, $result);
	}
	
	public function Post($data = null) {
		curl_setopt($this->curl, CURLOPT_URL, $this->url);
		curl_setopt($this->curl, CURLOPT_POST, true);
		if ($data != null)
			curl_setopt($this->curl, CURLOPT_POSTFIELDS, http_build_query($data));
		$result = curl_exec($this->curl);
		if ($result === false) {
			throw new HttpRequestException(curl_error($this->curl), curl_errno($this->curl));
		}
		return new HttpResponseMessage($this->curl, $result);
	}
	
	public function Put($data = null) {
		$query_string = '';
		if ($data != null)
			$query_string = '?' . http_build_query($data);
		curl_setopt($this->curl, CURLOPT_URL, $this->url . $query_string);
		curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, 'PUT');
		$result = curl_exec($this->curl);
		if ($result === false) {
			throw new HttpRequestException(curl_error($this->curl), curl_errno($this->curl));
		}
		return new HttpResponseMessage($this->curl, $result);
	}
	
}

class HttpResponseMessage {
	public $HttpVersion = '';
	public $StatusCode = -1;
	public $StatusText = '';
	public $EffectiveUrl = '';
	public $HeaderText = '';
	public $Content = '';
	public $HeaderSize = 0;
	public $TransferTime = '';
	private $_headers = [];
	
	function __construct($curl, $response) {
		$this->TransferTime = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		$this->HeaderSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
		$this->EffectiveUrl = curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);
		$this->HeaderText = substr($response, 0, $this->HeaderSize);
		$this->Content = substr($response, $this->HeaderSize);
		$h = explode("\n", $this->HeaderText);
		$s = explode(' ', $h[0], 3);
		$this->HttpVersion = $s[0];
		$this->StatusCode = intval($s[1]);
		$this->StatusText = trim($s[2]);
		unset($h[0]);
		foreach($h as $item) {
			$item = trim($item);
			if ($item == '') continue;
			$s = explode(': ', $item, 2);
			$this->_headers[$s[0]] = $s[1];
		}
	}
	
}

class HttpRequestException extends Exception {
}
