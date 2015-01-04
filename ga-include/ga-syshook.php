<?php
/**
 * GitLab system_hook controller. 
 * Since GitLab admin area has built-in log feature, this hook
 * is only used as a tester of installation params so far.
 * 
 * @author Xiangyu Bu <xybu92@live.com>
 */

require_once 'ga-base.php';
require_once 'ga-logger.php';

class GltLab_SystemHook extends Base {
	
	protected $logger = null;
	protected $raw = null;
	protected $data = null;
	
	public function __construct() {
		
		// All GitLab hook events are sent via HTTP POST.
		if (!$this->IsHttpPost()) {
			$this->JSON_OutputError('wrong-method', 'HTTP Method not allowed.', '405 Method Not Allowed');
		}
		
		if (GITLAB_HOOK_KEY != $_GET['key']) {
			$this->JSON_OutputError('invalid-key', 'The hook key is invalid. Event has been logged.', '401 Unauthorized');
		}
		
		$this->logger = new Logger();
		$this->raw = @file_get_contents('php://input');
		$this->data = json_decode($this->raw, true);
		
		switch ($this->data['event_name']) {
			case 'project_create':
			case 'project_destroy':
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
	
}