<?php
/**
 * gitlab-ag database controller.
 */

require_once 'lib/medoo.php';
 
class Database {
	
	const DATABASE_FILENAME = 'ga-webhook.db';
	
	protected $medoo = null;
	
	function __construct() {
		$this->medoo = new medoo([
			'database_type' => 'sqlite',
			'database_file' => dirname(__FILE__) . '/../ga-data/' . self::DATABASE_FILENAME
		]);
		
		// create tables if missing
		$this->medoo->query('CREATE TABLE IF NOT EXISTS webhook_keys (project_id INT PRIMARY KEY, hook_key TEXT, date_created TEXT);');
	}
	
	function __destruct() {
	}
	
	function AddWebHookKey($project_id, $hook_key) {
		//if ($this->VerifyWebHookKey($project_id, $hook_key))
		//	return false;		
		return $this->medoo->insert('webhook_keys', [
			'project_id' => $project_id,
			'hook_key' => hash('sha256', $hook_key),
			'date_created' => date('c')
		]);
	}
	
	function VerifyWebHookKey($project_id, $hook_key) {
		$rows = $this->medoo->select('webhook_keys', 'hook_key', [
			'AND' => [
				'project_id[=]' => $project_id,
				'hook_key[=]' => hash('sha256', $hook_key)
			]
		]);
		if (is_array($rows) && count($rows) > 0) return true;
		return false;
	}
	
	function ProjectHasWebHook($project_id) {
		$rows = $this->medoo->select('webhook_keys', 'hook_key', [
			'project_id[=]' => $project_id
		]);
		if (is_array($rows) && count($rows) > 0) return true;
		return false;
	}
	
	/**
	 * Delete all keys for a project, and return the number of keys deleted.
	 */
	function DeleteWebHookKeys($project_id) {
		return $this->medoo->delete('webhook_keys', ['project_id[=]' => $project_id]);
	}
	
}
 