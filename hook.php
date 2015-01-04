<?php
/**
 * Routes requests from GitLab to gitlab-ag.
 * 
 * @author Xiangyu Bu <xybu92@live.com>
 */

$START_TIME = microtime();

// If the system is not set up yet, deny all requests.
if (!file_exists(getcwd() . "/ga-data/ga-config.php"))
{
	header('HTTP/1.1 503 Service Unavailable');
	exit();
}

require_once 'ga-data/ga-config.php';
require_once 'ga-include/ga-hook.php';

new Hook();
