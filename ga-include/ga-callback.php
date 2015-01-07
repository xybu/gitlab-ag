<?php

require_once 'ga-base.php';
require_once 'ga-delegate.php';

class GitLab_CallbackHook extends Base{
	
	protected $gradebook_db = null;
	protected $delegate_record = null;
	protected $delegate_db = null;
	protected $response = null;
	
	function __construct() {
		// All GitLab hook events are sent via HTTP POST.
		//if (!$this->IsHttpPost() || !isset($_GET['key'])) {
		//	$this->JSON_OutputError('invalid-request', 'Invalid Request.', '403 Forbidden');
		//}
		
		$raw = @file_get_contents('php://input');
		//file_put_contents(APP_ABS_PATH . '/ga-hook/logs/callback.log', $raw);
		
		$this->response = json_decode($raw, true);
		$this->delegate_db = new Delegate();
		$this->delegate_record = $this->delegate_db->FindDelegate($this->response['project_id'], $_GET['key']);
		
		if ($this->delegate_record == null) {
			$this->JSON_OutputError('invalid-key', 'The delegate key is invalid.', '401 Unauthorized');
		}
		
		$this->RouteEvent();
	}
	
	function RouteEvent() {
		$project_id = $this->delegate_record['data']['project_id'];
		
		if ($this->delegate_record['type'] == 'get_repo') {
			// callback from get_repo delegate, next step is to pass the assignment
			// to grader_queue
			if ($this->response['result'] == 'needs_grading') {
				// check if there is "*-test" repository under root user's namespace
				$project_name = explode(':', $this->delegate_record['data']['repository']['url'])[1];
				
				if (strpos($project_name, GITLAB_ADMIN_USER . '/') !== 0 && is_dir(APP_ARCHIVE_PATH . '/' . GITLAB_ADMIN_USER . '/' . $this->delegate_record['data']['repository']['name'] . '-test')) {
					$delegate_key = $this->GetRandStr(32);
					
					if (!is_dir(getcwd() . '/queue')) mkdir(getcwd() . '/queue');
					
					file_put_contents(getcwd() . '/queue/' . $this->delegate_record['data']['user_id'] . '_' . $this->delegate_record['data']['repository']['name'] . '_' . time() . '.json', json_encode([
						'project_id' => $project_id,
						'project_name' => $project_name,
						'delegate_key' => $delegate_key,
						'merge_dir' => [
							APP_ARCHIVE_PATH . '/' . $project_name,
							APP_ARCHIVE_PATH . '/' . GITLAB_ADMIN_USER . '/' . $this->delegate_record['data']['repository']['name'] . '-test'
						]
					]));
					$this->delegate_db->AddNewDelegate($project_id, $delegate_key, 'grader_queue', $this->delegate_record['data']);
					$this->delegate_db->DeleteDelegate($project_id, $_GET['key']);
					
					$fds = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
					$stdin_data = ['delegate_callback' => APP_HOOK_URL, 'temp_path' => APP_TEMP_PATH];
					$proc = proc_open(getcwd() . '/delegates/ga-grader_queue.py start', $fds, $pipes);
					if (is_resource($proc)) {
						fwrite($pipes[0], json_encode($stdin_data));
						fclose($pipes[0]);
						fclose($pipes[1]);
						fclose($pipes[2]);
						proc_close($proc);
					}
				}
			}
			
			header('HTTP/1.1 202 Accepted');
			
		} else if ($this->delegate_record['type'] == 'grader_queue') {
			// callback from grader_queue, should record the grade result
			require_once 'ga-gradebook.php';
			$grade_book = new GradeBook();
			$grade_book->AddNewRecord($this->response['project_id'], $this->response['project_name'], $this->response['grade'], json_decode($this->response['grade_data'], true), $this->response['grade_log']);
			$this->delegate_db->DeleteDelegateByKey($this->response['project_id'], $_GET['key']);
			header('HTTP/1.1 201 Created');
		}
		
		exit();
	}
	
}
