<?php
/**
 * Main page of gitlab-ag system. All the exit() entries are either
 * described in comment or explicitly called here.
 * 
 * @author Xiangyu Bu <xybu92@live.com>
 */

$START_TIME = microtime();

// If the system is not set up yet, run the installer.
if (!file_exists(getcwd() . "/ga-data/ga-config.php"))
{
	// Pass control to Installer and exit.
	require_once "ga-include/ga-installer.php";
	$installer = new Installer();
	exit();
}

// If this fails, a fatal error E_COMPIL_ERROR will occur. 
// It cannot be handled by user code. So after installation, test
// before use.
require_once "ga-data/ga-config.php";
require_once "ga-include/ga-session.php";

$session = new Session();

// If there is no specific action and the requester is a guest, show
// HTML sign in page and exit.
if (!isset($_REQUEST['action'])) {
	$session->ShowSignInPage();
	exit();
} else {
	// if there is a desired action, either the requester wants to sign in 
	// or the requester has signed in. Check this.
	
	$post_action = $_POST['action'];
	
	if (substr($post_action, 0, 3) == 'api') {
		$session->SignInAPI();
		
		//TODO: Route the action to specific API handler.
		
	} else if ($post_action == 'sign_in') {
		// handles a sign in request. If fails, return HTTP 400; otherwise HTTP 200.
		
		if (!$session->TestSignIn())
			$session->JSON_OutputError('wrong-password', 'The password you typed is incorrect.');
		else {
			header('HTTP/1.1 200 OK');
			exit();
		}
		
	} else {
		// authenticate the request, if failed, HTTP 401 with JSON error and exit (!GET), or
		// redirecting to home page without action (GET).
		$session->SignInUser();
		
		//TODO: ROute the action to specific API handler.
		$get_action = $_GET['action'];
		
		var_dump($_GET);
		var_dump($_POST);
	}
	
}
