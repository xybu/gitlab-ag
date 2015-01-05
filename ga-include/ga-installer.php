<?php
/**
 * The installer control of gitlab-ag.
 * Activated when the file /ga-data/ga-config.php does not exist, and is 
 * disabled otherwise.
 * 
 * @author Xiangyu Bu <xybu92@live.com>
 */

require_once 'ga-base.php';

/**
 * Security Concerns
 * GitLab stores most credentials we need as plain-text. This makes any encryption 
 * futile.
 */

/**
 * Installer class checks HTTP GET and POST methods, each with
 * only one state. When HTTP GET is detected, it renders the installation
 * form; when HTTP POST is found, it collects the form data to
 * generate /ga-data/ga-config.php.
 */
class Installer extends Base {
	
	/**
	 * Construct the object and route to the correct path.
	 */
	public function __construct() {		
		if ($this->IsHttpGet()) {
			$this->ShowWelcomeScreen();
		} else {
			$this->Install();
		}
	}
	
	/**
	 * Free the content.
	 */
	public function __destruct() {
	}
	
	public function Install() {
		$path = getcwd() . "/ga-data/ga-config.php";
		if (file_exists($path)) {
			// the config file already exists, must refuse the request
			// actually this check is redundant
			$this->JSON_OutputError('installer-locked', 'Install cannot proceed because <code>ga-data/ga-config.php</code> already exists. Delete it to unlock installer.', '403 Forbidden');
		} else {
			// TODO: make sure all $val is quote escaped.
			try {
				//$timestamp = time();
				$config_file_data = "<?php\n/**\n * Generated configuration file\n * DO NOT MODIFY.\n */\n\n";
				$root_password_raw = $_POST['APP_ROOT_PASS'];
				$root_password_sha = $this->SHA_Encrypt(base64_encode($root_password_raw));
				$root_password_hash = password_hash($root_password_sha, PASSWORD_DEFAULT);
				// no need to encrypt. Students WILL see it from GitLab.
				//$api_token_raw = $_POST['APP_API_TOKEN'];
				//$api_token_sha = $this->SHA_Encrypt(base64_encode($api_token_raw));
				//$api_token_hash = password_hash($api_token_sha, PASSWORD_DEFAULT);
				foreach ($_POST as $key => $val) {
					if (empty($val)) throw new InvalidArgumentException($key . ' is unset.');
					if ($key == 'APP_ROOT_PASS') {
						$val = $root_password_hash;
					}// else if ($key == 'APP_API_TOKEN') {
					//	$val = $api_token_hash;
					//} else if ($key == 'GITLAB_PRIVATE_TOKEN') {
					//	$val = $this->AES_Encrypt($val, $api_token_sha . $timestamp);
					//}
					//if (substr($key, strlen($key) - 4) == 'PASS') {
					//	$val = $this->AES_Encrypt($val, $root_password_sha . $timestamp);
					//}
					$config_file_data .= "define('". $key ."', '" . $val . "');\n";
				}
				//$config_file_data .= "define('GITLAB_PRIVATE_TOKEN_U', '" . $this->AES_Encrypt($_POST['GITLAB_PRIVATE_TOKEN'], $root_password_sha . $timestamp) . "');\n";
				if (false === file_put_contents($path, $config_file_data, LOCK_EX))
					throw new Exception("Failed to write to <code>" . $path . "</code>.");
				if (false == chmod($path, 0700)) {
					unlink($path);
					throw new Exception("Failed to chmod of <code>" . $path . "</code> to <code>0700</code>.");
				}
				//if (false == touch($path, $timestamp)) {
				//	unlink($path);
				//	throw new Exception("Failed to change mtime of <code>" . $path . "</code>");
				//}
				$this->JSON_OutputData(array('response' => 'success'));
			} catch (Exception $e) {
				$this->JSON_OutputError('Error', $e->GetMessage());
			}
		}
		$this->view->ShowHtmlFooter();
	}
	
	/**
	 * Render the installation form. This is the handler for 
	 * HTTP GET installation request.
	 */
	public function ShowWelcomeScreen() {
		require_once 'ga-view.php';
		$view = new View();
		$view->ShowHtmlHeader('Install gitlab-ag');
		$view->Render('install.phtml', array(
			'rand_password' => $this->GetRandStr(10),
			'rand_access_token' => $this->GetRandStr(32),
			'rand_hook_key' => $this->GetRandStr(32)
		));
		$view->ShowHtmlFooter(array(
			//'//cdnjs.cloudflare.com/ajax/libs/crypto-js/3.1.2/components/sha256-min.js',
			'/ga-assets/installer.js'
		));
	}
	
}
