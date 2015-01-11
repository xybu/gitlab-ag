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
					// writing process; prepending time() to make the queue FIFO
					file_put_contents($this->QUEUE_PATH . '/' . time() . '_' . $this->delegate_record['data']['user_id'] . '_' . $this->delegate_record['data']['repository']['name'] . '.json', json_encode([
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
			$grade = 0;
			$user_info = ['username' => '', 'email' => ''];
			$user_id = $this->delegate_record['data']['user_id'];
			
			// the dumbest way to parse <summary>
			$summary_start = strpos($this->response['grade_data'], '<summary>') + 9;
			$summary_end = strpos($this->response['grade_data'], '</summary>', $summary_start);
			$summary = substr($this->response['grade_data'], $summary_start, $summary_end - $summary_start);
			$summary_parsed = parse_ini_string($summary);
			if ($summary !== false && is_array($summary_parsed) && array_key_exists('grade_total', $summary_parsed))
				$grade = intval($summary_parsed['grade_total']);
			
			// try to resolve username and email
			try {
				$user_cache_path = APP_ABS_PATH . '/ga-data/cache/user_' . $user_id . '.json';
				if (!file_exists($user_cache_path)) {
					if (!is_dir(APP_ABS_PATH . '/ga-data/cache'))
						mkdir(APP_ABS_PATH . '/ga-data/cache');
					require_once 'ga-http.php';
					$cli = new HttpClient(GITLAB_URL . '/api/v3/users/' . $user_id);
					$response = $cli->Get(['private_token' => GITLAB_PRIVATE_TOKEN]);
					if ($response->StatusCode < 200 || $response->StatusCode > 300) 
						throw new Exception('Failed to get user data.');
					
					file_put_contents($user_cache_path, $response->Content, LOCK_EX);
					$user_info = json_decode($response->Content, true);
				} else {
					$user_info = json_decode(file_get_contents($user_cache_path), true);
				}
			} catch (Exception $e) {
				// failed to resolve username
				syslog(LOG_INFO, $e->GetMessage());
			}
			
			require_once 'ga-gradebook.php';
			$grade_book = new GradeBook();
			$grade_book->AddNewRecord($this->response['project_id'], $this->response['project_name'], $user_id, $user_info['username'], $grade, $this->response['grade_data'], $this->response['grade_log']);
			$this->delegate_db->DeleteDelegate($this->response['project_id'], $_GET['key']);
			
			if (strpos($user_info['email'], '@') !== false) {
				// send email notification
				require_once 'lib/mail.php';
				$push_info = $this->delegate_record['data'];
				$grade_data = $this->response['grade_data'];
				require_once APP_ABS_PATH . '/ga-views/grading_result.phtml';
				$mail = new Mail();
				$mail->addTo($user_info['email'], $user_info['name']);
				$mail->setFrom(APP_FROM_EMAIL, APP_FROM_EMAIL_NAME);
				$mail->setSubject('Grading Result');
				$mail->setMessage($content);
				$mail->send();
			}
			
			header('HTTP/1.1 201 Created');
		}
		
		exit();
	}
	
}
