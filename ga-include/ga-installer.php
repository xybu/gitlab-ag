<?php
/**
 * The installer control of gitlab-ag.
 * Activated when the file /ga-data/ga-config.php does not exist, and is 
 * disabled otherwise.
 * 
 * @author Xiangyu Bu <xybu92@live.com>
 */

require_once 'ga-base.php';
require_once 'ga-view.php';

/**
 * Security Concerns
 * Root password is the knowledge factor of authentication, and mtime of 
 * ga-config.php is the ownership factor. If the attacker figures out root 
 * password either from brute force or from data traffic, then he / she can 
 * mess around with the system, but GitLab admin creds are not leaked.
 * If the attacker gets the content of ga-config.php, it is encrypted.
 * If the attacker gets both the root password AND the original ga-config.php
 * file, then everything is subject to leakage.
 */

/**
 * Installer class checks HTTP GET and POST methods, each with
 * only one state. When HTTP GET is detected, it renders the installation
 * form; when HTTP POST is found, it collects the form data to
 * generate /ga-data/ga-config.php.
 */
class Installer extends Base {
	
	protected $view = null;
	
	/**
	 * Construct the object and route to the correct path.
	 */
	public function __construct() {
		$this->view = new View();
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
		$this->view->ShowHtmlHeader('Install gitlab-ag');
		$this->view->ShowBreadcrumb(array(
			array('href' => '/', 'name' => 'Home', 'active' => false),
			array('href' => null, 'name' => 'Install', 'active' => true),
		));
		$path = getcwd() . "/ga-data/ga-config.php";
		if (file_exists($path)) {
			// the config file already exists, must refuse the request
			// actually this check is redundant
			$this->view->ShowCallout('danger', 'Error', '<code>ga-data/ga-config.php</code> already exists. Delete it to unlock installer.');
		} else {
			// TODO: make sure all $val is quote escaped.
			try {
				$timestamp = time();
				$config_file_data = "<?php\n/**\n * Generated configuration file\n * DO NOT MODIFY.\n */\n\n";
				$root_password_raw = $_POST['APP_ROOT_PASS'];
				$root_password_sha = $this->SHA_Encrypt(base64_encode($root_password_raw));
				$root_password_hash = password_hash($root_password_sha, PASSWORD_DEFAULT);
				$api_token_raw = $_POST['APP_API_TOKEN'];
				$api_token_sha = $this->SHA_Encrypt(base64_encode($api_token_raw));
				$api_token_hash = password_hash($api_token_sha, PASSWORD_DEFAULT);
				foreach ($_POST as $key => $val) {
					if (empty($val)) throw new InvalidArgumentException($key . ' is unset.');
					if ($key == 'APP_ROOT_PASS') {
						$val = $root_password_hash;
					} else if ($key == 'APP_API_TOKEN') {
						$val = $api_token_hash;
					} else if ($key == 'GITLAB_PRIVATE_TOKEN') {
						$val = $this->AES_Encrypt($val, $$api_token_sha . $timestamp);
					}
					if (substr($key, strlen($key) - 5) == '_PASS') {
						$val = $this->AES_Encrypt($val, $root_password_sha . $timestamp);
					}
					$config_file_data .= "define('". $key ."', '" . $this->AES_Encrypt($api_token_raw, $root_password_sha . $timestamp) . "');\n";
				}
				$config_file_data .= "define('GITLAB_PRIVATE_TOKEN_U', '" .  . "');\n";
				if (false === file_put_contents($path, $config_file_data, LOCK_EX))
					throw new Exception("Failed to write to <code>" . $path . "</code>.");
				if (false == chmod($path, 0700)) {
					unlink($path);
					throw new Exception("Failed to chmod of <code>" . $path . "</code> to <code>0700</code>.");
				}
				if (false == touch($path, $timestamp)) {
					unlink($path);
					throw new Exception("Failed to change mtime of <code>" . $path . "</code>");
				}
				$this->view->ShowCallout('success', 'Success', 'Installation is finished. Click <a href="/">here</a> to sign in.');
			} catch (Exception $e) {
				$this->view->ShowCallout('danger', 'Error', $e->GetMessage());
			}
		}
		$this->view->ShowHtmlFooter();
	}
	
	/**
	 * Render the installation form. This is the handler for 
	 * HTTP GET installation request.
	 */
	public function ShowWelcomeScreen() {
		$this->view->ShowHtmlHeader('Install gitlab-ag');
		$this->view->Render('install.phtml', array(
			'rand_password' => $this->GetRandStr(10),
			'rand_access_token' => $this->GetRandStr(32),
		));
		$this->view->ShowHtmlFooter(array(
			//'//cdnjs.cloudflare.com/ajax/libs/crypto-js/3.1.2/components/sha256-min.js',
			'/ga-assets/installer.js'
		));
	}
	
}
