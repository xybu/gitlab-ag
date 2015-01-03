<?php
/**
 * Admin panel controller deals with the rendering of admin panel of course.
 * 
 * @author Xiangyu Bu <xybu92@live.com>
 */

require_once 'ga-base.php';

class ControlPanel extends Base {
	
	public function __construct() {
		$this->EnsureSignedInRequest();
		
		if ($this->session->IsHttpGet()) {
			if (!isset($_GET['action'])) {
				// HTTP GET with no specific action
				$this->ShowControlPanel();
			} else {
				$action = $_GET['action'];
				if ($action == 'sign_out') {
					$this->session->SignOutUser();
					header('Location: ' . APP_URL);
					exit();
				} else if ($action == 'heartbeat') {
					// heartbeating request
					header('HTTP/1.1 200 OK');
					exit();
				}
			}
		} else {
			
		}
	}
	
	public function ShowControlPanel() {
		require_once 'ga-view.php';
		$view = new View();
		$view->ShowHtmlHeader('Control Panel', true);
		$view->Render('admincp.phtml', array(
			'GitLab_PrivateToken' => $this->AES_Decrypt(GITLAB_PRIVATE_TOKEN_U, $this->session->GetSessionKey() . $this->session->PosessionKey)
		));
		$view->ShowHtmlFooter(array('/ga-assets/admincp.js'));
	}

}
