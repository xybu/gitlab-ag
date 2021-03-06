<?php
/**
 * GitLab system_hook controller. 
 * Since GitLab admin area has built-in log feature, this hook
 * is only used as a tester of installation params so far.
 * 
 * @author Xiangyu Bu <xybu92@live.com>
 */

require_once 'ga-base.php';
require_once 'ga-db.php';
require_once 'ga-logger.php';
require_once 'ga-http.php';

class GitLab_SystemHook extends Base {
	
	protected $logger = null;
	protected $raw = null;
	protected $data = null;
	
	public function __construct() {
		
		$this->logger = new Logger();
		$this->raw = @file_get_contents('php://input');
		
		// All GitLab hook events are sent via HTTP POST.
		if (!$this->IsHttpPost()) {
			$this->logger->addLog('system_invalid_request', $this->raw);
			$this->JSON_OutputError('wrong-method', 'HTTP Method not allowed.', '405 Method Not Allowed');
		}
		
		if (!isset($_GET['key']) || GITLAB_HOOK_KEY != $_GET['key']) {
			$this->logger->addLog('system_invalid_request', $this->raw);
			$this->JSON_OutputError('invalid-key', 'The hook key is invalid. Event has been logged.', '401 Unauthorized');
		}
		
		$this->data = json_decode($this->raw, true);
		
		switch ($this->data['event_name']) {
			case 'project_create':
				$this->AddWebHookToProject($this->data);
				$this->logger->addLog($this->data['event_name'], $this->raw);
				break;
			case 'project_destroy':
				$this->DeleteWebHookFromProject($this->data);
			case 'user_add_to_team':
			case 'user_remove_from_team':
			case 'user_create':
			case 'user_destroy':
			case 'key_create':
			case 'key_destroy':
				$this->logger->addLog($this->data['event_name'], $this->raw);
				break;
			default:
				$this->JSON_OutputError('undefined-event', 'The event received is not defined.', '406 Not Acceptable');
		}
		
	}
	
	function AddWebHookToProject($event_args) {
		$db = new Database();
		if (!$db->ProjectHasWebHook($event_args['project_id'])) {
			try {
				$rnd_hook_key = $this->GetRandStr(32);
				$cli = new HttpClient(GITLAB_URL . '/api/v3/projects/' . $event_args['project_id'] . '/hooks?private_token=' . GITLAB_PRIVATE_TOKEN);
				$response = $cli->Post(['url' => APP_HOOK_URL . '/webhook/' . $rnd_hook_key ]);
				if ($response->StatusCode < 200 || $response->StatusCode > 299)
					throw new Exception('Cannot talk to GitLab API: HTTP ' . $response->StatusCode . ' ' . $response->StatusText . '.\n' . $response->Content);
				$db->AddWebHookKey($event_args['project_id'], $rnd_hook_key);
				
				//$handle = popen(APP_ABS_PATH . '/ga-hook/delegates/ga-post.py', 'w');
				//$data = json_encode([
				//	'http_url' => GITLAB_URL . '/api/v3/projects/' . $event_args['project_id'] . '/hooks?private_token=' . GITLAB_PRIVATE_TOKEN,
				//	'data' => ['url' => APP_HOOK_URL . '/webhook/' . $rnd_hook_key ],
				//	'delay' => 3
				//]);
				//fwrite($handle, $data);
				//pclose($handle);
				
			} catch (Exception $e) {
				if (isset($event_args['path_with_namespace'])) $project = $event_args['path_with_namespace'];
				else $project = $event_args['project_path'];
				$this->logger->addLog('add_webhook_failure', 'Failed to add webhook to project ' . $project . ' (' . $event_args['project_id'] . '). ' . $e->GetMessage());
			}
		}
	}
	
	function DeleteWebHookFromProject($event_args) {
		$db = new Database();
		$db->DeleteWebHookKeys($event_args['project_id']);
	}
	
}
