<?php
/**
 * GitLab webhook controller.
 * 
 * @author Xiangyu Bu <xybu92@live.com>
 */

require_once 'ga-base.php';
require_once 'ga-db.php';


class GitLab_WebHook extends Base {
	
	protected $db;
	protected $data;
	
	function __construct() {
		
		// All GitLab hook events are sent via HTTP POST.
		if (!$this->IsHttpPost()) {
			$this->JSON_OutputError('wrong-method', 'HTTP Method not allowed.', '405 Method Not Allowed');
		}
		
		$this->db = new Database();
		
		if (!isset($_GET['key']) || !$this->db->VerifyWebHookKey($_GET['key'])) {
			$this->JSON_OutputError('invalid-key', 'The hook key is invalid. Event has been logged.', '401 Unauthorized');
		}
		
		$raw = @file_get_contents('php://input');
		$this->data = json_decode($raw);
		$this->RouteEvent();
	}
	
	function __destruct() {
	}
	
	function RouteEvent() {
		if ($this->data == null || !is_array($this->data)) {
			$this->JSON_OutputError('invalid-data', 'The hook data is invalid.', '401 Unauthorized');
		}
		
		//if (array_key_exists('before', $this->data))
	}
	
	private function EnsureKeysExist($keys, $array) {
		foreach ($keys as $item)
			if (!array_key_exists($item, $array)) return false;
		return true;
	}
	
}
