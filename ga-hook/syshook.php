<?php
/**
 * System Hook for GitLab.
 * 
 * @author Xiangyu Bu <xybu92@live.com>
 */

// If the system is not set up yet, deny all requests.
if (!file_exists(getcwd() . "/../ga-data/ga-config.php"))
{
	header('HTTP/1.1 503 Service Unavailable');
	exit();
}

require_once dirname(__FILE__) . '/../ga-data/ga-config.php';
require_once dirname(__FILE__) . '/../ga-include/ga-syshook.php';

new GltLab_SystemHook();
