<?php
/**
 * Session controller verifies if the root password is correct, if 
 * the user is signed in, and handles user sign-in action.
 * 
 * @author Xiangyu Bu <xybu92@live.com>
 */

require_once 'ga-base.php';

/** 
 * Security Concerns
 *
 * The session key is hashed by the requester's user-agent string concatenated by the salt.
 * So even if the attacker gets the session data from disk (if PHP stores session in disk), 
 * he or she must know something about the requester.
 * However, the user-agent string can be read from web server's access log.
 * 
 * Besides, the two-key verification is just a partial simulation of two-step verification.
 * There are several ways to compromise the mechanism.
 */

/**
 * Session class gets and sets the user session info.
 */
class Session extends Base {
	
	const SESSION_SALT = 'DEFAULT_SALT';
	const MAX_ATTEMPT_COUNT = 5;
	const MAX_ATTEMPT_INTERVAL = 30; // in minutes
	
	// Knowledge key is actually the SHA-256 hash of the root password.
	public $KnowledgeKey = null;
	
	// Possession key is the mtime of ga-config.php.
	public $PossessionKey = null;
	
	public function __construct() {
		session_start();
		$this->LoadPossessionKey();
	}
	
	public function __destruct() {
	}
	
	public function IsSignedIn() {
		return !empty($this->KnowledgeKey) && !empty($this->PossessionKey);
	}
	
	public function LoadPossessionKey() {
		$this->PosessionKey = filemtime(getcwd() . '/ga-data/ga-config.php');
	}
	
	public function DecryptPassword($data) {
		return $this->AES_Decrypt($data, $this->KnowledgeKey . $this->PossessionKey);
	}
	
	/**
	 * Try authenticating the session for a user. 
	 * If the user cannot be signed in, terminate the session.
	 */
	public function SignInUser() {
		if (isset($_SESSION['SessionKey'])) {
			$this->KnowledgeKey = $this->AES_Decrypt($_SESSION['SessionKey'], base64_encode($_SERVER['HTTP_USER_AGENT']) . self::SESSION_SALT);
		}
		if ($this->KnowledgeKey == null) {
			if ($this->IsHttpGet()) {
				// for HTTP GET, redirect user to homepage
				header('Location: ' . APP_URL);
				exit();
			} else {
				// for HTTP POST, return JSON error
				$session->JSON_OutputError('unauthorized', 'You must sign in the perform the operation.', '401 Unauthorized');
			}
		}
	}
	
	public function SignOutUser() {
		unset($_SESSION['SessionKey']);
	}
	
	/**
	 * Try authenticating the request for an API (by access token).
	 * If the requester cannot be verified, terminate the session.
	 */
	public function SignInAPI() {
		throw new Exception("Unimplemented");
	}
	
	public function IncrementFailureCounter() {
		if (!isset($_SESSION['FailCount']))
			$_SESSION['FailCount'] = 0;
		$_SESSION['FailCount'] += 1;
		$_SESSION['FailTimestamp'] = time();
	}
	
	public function VoidFailureCounter() {
		unset($_SESSION['FailCount']);
		unset($_SESSION['FailTimestamp']);
	}
	
	public function TestSignIn() {
		// if (!$this->IsHttpPost()) return false;
		// if ($_POST['action'] != 'sign_in') return false;
		if (isset($_SESSION['FailTimestamp']) && time() - $_SESSION['FailTimestamp'] > self::MAX_ATTEMPT_INTERVAL * 60000)
			$this->VoidFailureCounter();
		
		if (isset($_SESSION['FailCount']) && $_SESSION['FailCount'] >= self::MAX_ATTEMPT_COUNT ||
		    !array_key_exists('password', $_POST) || empty($_POST['password'])) {
			$this->IncrementFailureCounter();
			return false;
		}
		
		$pass_try = $_POST['password'];
		$pass_try_sha = $this->SHA_Encrypt(base64_encode($pass_try));
		$pass_verif = $this->AES_Decrypt(APP_ROOT_PASS, $pass_try_sha . $this->PossessionKey);
		if ($pass_verif == null || !password_verify($pass_try_sha, $pass_verif)) {
			$this->IncrementFailureCounter();
			return false;
		}
		
		$this->KnowledgeKey = $pass_try_sha;
		$_SESSION['SessionKey'] = $this->AES_Encrypt($pass_try_sha, base64_encode($_SERVER['HTTP_USER_AGENT']) . self::SESSION_SALT);
		$this->VoidFailureCounter();
		return true;
	}
	
	public function GetSessionKey() {
		return $this->AES_Decrypt($_SESSION['SessionKey'], base64_encode($_SERVER['HTTP_USER_AGENT']) . self::SESSION_SALT);
	}
	
	public function ShowSignInPage() {
		require_once 'ga-view.php';
		$view = new View();
		$view->ShowHtmlHeader('Sign In');
		$view->Render('signin.phtml');
		$view->ShowHtmlFooter(array(
			//'//cdnjs.cloudflare.com/ajax/libs/crypto-js/3.1.2/components/sha256-min.js',
			'/ga-assets/sign_in.js'
		));
	}
}
