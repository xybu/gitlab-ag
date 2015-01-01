<?php
/**
 * Homepage of gitlab-ag control panel.
 * 
 * @author Xiangyu Bu <xybu92@live.com>
 */

$START_TIME = microtime();

// If the control panel is not set up yet, run the installer.
if (!file_exists(getcwd() . "/ga-data/ga-config.php"))
{
	// Pass control to Installer and exit.
	require_once "ga-include/ga-installer.php";
	$installer = new Installer();
	exit();
}
