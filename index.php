<?php
/**
 * Main router handles 4 types of requests: app installation * {GET, POST} and
 * sign-in * {GET, POST}. When ga-config.php is not present, the request is 
 * app installation.
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

if ($session->IsHttpGet()) {
	// Render sign-in page
	$session->ShowSignInPage();
	exit();
} else {
	if (!$session->TestSignIn())
		$session->JSON_OutputError('wrong-password', 'The password you typed is incorrect.');
	else {
		header('HTTP/1.1 200 OK');
		exit();
	}
}
