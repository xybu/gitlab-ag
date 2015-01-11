<?php

require_once 'ga-base.php';
require_once 'ga-delegate.php';

class GitLab_CallbackHook extends Base{
	
	protected $QUEUE_PATH;
	protected $gradebook_db = null;
	protected $delegate_record = null;
	protected $delegate_db = null;
	protected $response = null;
	
	function __construct() {
		$this->QUEUE_PATH = APP_ABS_PATH . '/ga-data/queue';
		// All GitLab hook events are sent via HTTP POST.
		if (!$this->IsHttpPost() || !isset($_GET['key'])) {
			$this->JSON_OutputError('invalid-request', 'Invalid Request.', '403 Forbidden');
		}
		
		$raw = @file_get_contents('php://input');
		file_put_contents(APP_ABS_PATH . '/ga-data/last_callback.log', $raw);
		
		$this->response = json_decode($raw, true);
		$this->delegate_db = new Delegate();
		$this->delegate_record = $this->delegate_db->FindDelegate($this->response['project_id'], $_GET['key']);
		
		if ($this->delegate_record == null) {
			$this->JSON_OutputError('invalid-key', 'The delegate key is invalid.', '401 Unauthorized');
		}
		
		$this->RouteEvent();
	}
	
	function RouteEvent() {
		$project_id = $this->response['project_id'];
		
		if ($this->delegate_record['type'] == 'get_repo') {
			// callback from get_repo delegate, next step is to pass the assignment
			// to grader_queue
			if ($this->response['result'] == 'needs_grading') {
				// check if there is "*-test" repository under root user's namespace
				$project_name = explode(':', $this->delegate_record['data']['repository']['url'])[1];
				$project_name = substr($project_name, 0, strlen($project_name) - 4);
				
				if (strpos($project_name, GITLAB_ADMIN_USER . '/') !== 0 && is_dir(APP_ARCHIVE_PATH . '/' . GITLAB_ADMIN_USER . '/' . $this->delegate_record['data']['repository']['name'] . '-test')) {
					$delegate_key = $this->GetRandStr(32);
					
					if (!is_dir($this->QUEUE_PATH)) mkdir($this->QUEUE_PATH);
					if (!is_file(APP_ABS_PATH . '/ga-data/ga-grader_queue.cfg')) {
						file_put_contents(APP_ABS_PATH . '/ga-data/ga-grader_queue.cfg', json_encode([
							'delegate_callback' => APP_HOOK_URL,
							'temp_path' => APP_TEMP_PATH,
						]));
					}
					// a lock is necessary to prevent daemon from accessing the file during the 
					// writing process
					file_put_contents($this->QUEUE_PATH . '/' . $this->delegate_record['data']['user_id'] . '_' . $this->delegate_record['data']['repository']['name'] . '_' . time() . '.json', json_encode([
						'project_id' => $project_id,
						'project_name' => $project_name,
						'delegate_key' => $delegate_key,
						'merge_dir' => [
							APP_ARCHIVE_PATH . '/' . $project_name,
							APP_ARCHIVE_PATH . '/' . GITLAB_ADMIN_USER . '/' . $this->delegate_record['data']['repository']['name'] . '-test'
						]
					]), LOCK_EX);
					$this->delegate_db->AddNewDelegate($project_id, $delegate_key, 'grader_queue', $this->delegate_record['data']);
					if (!file_exists(APP_ABS_PATH . '/ga-data/ga-grader_queue.pid'))
						exec(getcwd() . '/delegates/ga-grader_queue.py start > /dev/null &');
				}
			}
			
			$this->delegate_db->DeleteDelegate($project_id, $_GET['key']);
			
			header('HTTP/1.1 202 Accepted');
			
		} else if ($this->delegate_record['type'] == 'grader_queue') {
			// callback from grader_queue, should record the grade result
			preg_match("/<summary ?.*>(.*)<\/summary>/", $this->response['grade_data'], $matches);
			$grade_data_ini = $matches[1];
			$grade_data_parsed = parse_ini_string($grade_data_ini);
			$grade = intval($grade_data_parsed['grade_total']);
			require_once 'ga-gradebook.php';
			$grade_book = new GradeBook();
			$grade_book->AddNewRecord($this->response['project_id'], $this->response['project_name'], $grade, $this->response['grade_data'], $this->response['grade_log']);
			$this->delegate_db->DeleteDelegate($this->response['project_id'], $_GET['key']);
			header('HTTP/1.1 201 Created');
		}
		
		exit();
	}
	
}
