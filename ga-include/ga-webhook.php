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
		
		$raw = @file_get_contents('php://input');
		file_put_contents(APP_ABS_PATH . '/ga-hook/logs/webhook.log', $raw);
		
		$this->data = json_decode($raw, true);
		
		if (!$this->IsHttpPost() || !isset($_GET['key'])) {
			$this->JSON_OutputError('invalid-request', 'Invalid Request.', '403 Forbidden');
		}
		
		$this->db = new Database();
		$this->RouteEvent();
	}
	
	function __destruct() {
	}
	
	function KillSession() {
		$this->JSON_OutputError('invalid-key', 'The hook key is invalid. Event has been logged.', '401 Unauthorized');
	}
	
	function RouteEvent() {
		if ($this->data == null || !is_array($this->data)) {
			$this->JSON_OutputError('invalid-data', 'The hook data is invalid.', '401 Unauthorized');
		}
		
		if ($this->EnsureKeysExist(['before', 'after', 'commits', 'total_commits_count'], $this->data)) {
			// this event is push event
			if (!$this->db->VerifyWebHookKey($this->data['project_id'], $_GET['key']))
				$this->KillSession();
			
			require_once 'ga-delegate.php';
			
			$delegate = new Delegate();
			$delegate_key = $this->GetRandStr(32);
			$delegate->AddNewDelegate($this->data['project_id'], $delegate_key, 'get_repo', $this->data);
			
			$file_data = [
				'delegate_callback' => APP_HOOK_URL,
				'delegate_key' => $delegate_key,
				'archive_root_path' => APP_ARCHIVE_PATH,
				'gitlab_url' => GITLAB_URL,
				'gitlab_admin_user' => GITLAB_ADMIN_USER,
				'gitlab_admin_pass' => GITLAB_ADMIN_PASS,
				'push_event' => $this->data
			];
			$file_name = $this->data['project_id'] . '_' . $delegate_key . '.json';
			
			if (!is_dir(getcwd() . '/pushes')) mkdir(getcwd() . '/pushes', 0770);
			
			file_put_contents(getcwd() . '/pushes/' . $file_name, json_encode($file_data));
			
			exec(getcwd() . '/delegates/ga-get_repo.py "' . $file_name . '" > /dev/null &');
			
			header('HTTP/1.1 202 Accepted');
			exit();
			
		} else if (array_key_exists('ref', $this->data) && strpos($this->data['ref'], 'refs/tags/') == 0) {
			// this event is tag event
			if (!$this->db->VerifyWebHookKey($this->data['project_id'], $_GET['key']))
				$this->KillSession();
			
		} else if (array_key_exists('object_kind', $this->data) && $this->data['object_kind'] == 'issue') {
			// this event is issue event
			if (!$this->db->VerifyWebHookKey($this->data['object_attributes']['project_id'], $_GET['key']))
				$this->KillSession();
			
		} else if (array_key_exists('object_kind', $this->data) && $this->data['object_kind'] == 'merge_request') {
			// this event is merge request event
			if (!$this->db->VerifyWebHookKey($this->data['object_attributes']['project_id'], $_GET['key']))
				$this->KillSession();
			
		}
	}
	
	private function EnsureKeysExist($keys, $array) {
		foreach ($keys as $item)
			if (!array_key_exists($item, $array)) return false;
		return true;
	}
	
}
