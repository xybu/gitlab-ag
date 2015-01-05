<?php
/**
 * Admin panel controller deals with the rendering of admin panel of course.
 * 
 * @author Xiangyu Bu <xybu92@live.com>
 */

require_once 'ga-base.php';

class ControlPanel extends Base {
	
	function __construct() {
		$this->EnsureSignedInRequest();
		
		if ($this->session->IsHttpGet()) {
			if (!isset($_GET['action'])) {
				// HTTP GET with no specific action
				$this->ShowControlPanel();
			} else {
				$action = $_GET['action'];
				switch ($action) {
					case 'sign_out':
						$this->session->SignOutUser();
						header('Location: ' . APP_URL);
						exit();
					case 'heartbeat':
						// heartbeating request
						header('HTTP/1.1 200 OK');
						exit();
					case 'get_log':
						$this->FetchLogs();
						exit();
				}
			}
		} else {
			
		}
	}
	
	function FetchLogs() {
		require_once 'ga-logger.php';
		
		$max_id = intval($_GET['max_id']);
		$count = intval($_GET['count']);
		if ($count == 0) return;
		$logger = new Logger();
		$rows = $logger->GetLogs($max_id, $count);
		if ($rows != false) {
			foreach($rows as &$val) {
				$val['log_detail'] = json_decode($val['log_detail'], true);
				$val['http_request'] = json_decode($val['http_request'], true);
				$val['http_post'] = json_decode($val['http_post'], true);
				$val['http_get'] = json_decode($val['http_get'], true);
			}
			$this->JSON_OutputData($rows);
		} else {
			echo json_encode([]);
		}
	}
	
	function ShowControlPanel() {
		require_once 'ga-view.php';
		
		$view = new View();
		$view->ShowHtmlHeader('Control Panel', true);
		$view->Render('admincp.phtml', [
			'GitLab_PrivateToken' => GITLAB_PRIVATE_TOKEN
		]);
		$view->ShowHtmlFooter(['/ga-assets/admincp.js']);
	}

}
