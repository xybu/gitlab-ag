<?php
/**
 * View controller for gitlab-ag. Mostly used by other controllers
 * to render HTML views. Not a controller than dispatches and routes requests.
 * 
 * @author Xiangyu Bu <xybu92@live.com>
 */

class View {
	
	public function __construct() {
	}
	
	public function __destruct() {
	}
	
	/**
	 * Render the HTML header.
	 */
	public function ShowHtmlHeader($page_title, $is_user = false) {
		require __DIR__ . '/../ga-views/header.phtml';
	}
	
	
	/**
	 * (Cited from ga-views/breadcrumb.phtml) Print page breadcrumb.
	 * Requires variable $breadcrumb to be set, and it is assumed to be an array of structure
	 * array('href' => YOUR_URL, 'active' => true, 'name' => 'ITEM_NAME').
	 * YOUR_URL must NOT contain unescaped double quote chars, but can be NULL or empty string.
	 * All keys above must exist. No error checking.
	 */
	public function ShowBreadcrumb($breadcrumb) {
		require __DIR__ . '/../ga-views/breadcrumb.phtml';
	}
	
	/**
	 * Print a callout.
	 * @param $type: one of {'default, 'info', 'success', 'warning', 'danger'}
	 * @param $title: title of callout panel.
	 * @param $content: content of the callout panel. HTML enabled.
	 */
	public function ShowCallout($type, $title, $content) {
		require __DIR__ . '/../ga-views/callout.phtml';
	}
	
	/**
	 * Render the specified PHP template.
	 * @param $template_name: the file name under ga-views dir.
	 * @param $params: an associative array that will be realized
	 *                 to separate variables before rendering.
	 */
	public function Render($template_name, $params = null) {
		if ($params != null) {
			foreach ($params as $key => $val)
				$$key = $val;
		}
		require __DIR__ . '/../ga-views/' . $template_name;
	}
	
	/**
	 * @param $script_files: an array of string URIs of javascript files.
	 *                       If present each will append a <script> tag.
	 */
	public function ShowHtmlFooter($script_files = null) {
		require __DIR__ . '/../ga-views/footer.phtml';
	}
	
}
