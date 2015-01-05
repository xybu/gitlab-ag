<?php
/**
 * A SQLite-based logger class
 * 
 * @author Xiangyu Bu <xybu92@live.com>
 */

require_once 'lib/medoo.php';

class Logger {
	
	protected $medoo = null;
	
	public function __construct() {
		$this->medoo = new medoo([
			'database_type' => 'sqlite',
			'database_file' => dirname(__FILE__) . '/../ga-data/ga-syslog.db'
		]);
		$this->medoo->query('CREATE TABLE IF NOT EXISTS log_entries (log_type TEXT, log_detail TEXT, http_request TEXT, http_post TEXT, http_get TEXT, date_created TEXT);');
	}
	
	public function __destruct() {
		
	}
	
	public function AddLog($type, $detail) {
		if (is_array($detail)) $detail = json_encode($detail);
		return $this->medoo->insert('log_entries', [
			'log_type' => $type,
			'log_detail' => $detail,
			'http_request' => json_encode($_SERVER),
			'http_get' => json_encode($_GET),
			'http_post' => json_encode($_POST),
			'date_created' => date('c')
		]);
	}
	
	public function TotalNumberOfLogs() {
		return $this->medoo->select('log_entries,', ['COUNT(id)']);
	}
	
	public function GetLogs($max_id = -1, $count = 20) {
		$where = [
			'ORDER' => 'rowid DESC',
			'LIMIT' => $count
		];
		if ($max_id > 0) $where['rowid[<=]'] = $max_id;
		return $this->medoo->select('log_entries', ['rowid', 'log_type', 'log_detail', 'http_request', 'http_get', 'http_post', 'date_created'], $where);
	}
	
}