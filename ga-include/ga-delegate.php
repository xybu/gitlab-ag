<?php

require_once 'lib/medoo.php';

class Delegate {
	
	const DELEGATE_DB_NAME = 'ga-delegates.db';
	
	protected $delegate_db;
	
	function __construct() {
		$this->delegate_db = new medoo([
			'database_type' => 'sqlite',
			'database_file' => dirname(__FILE__) . '/../ga-data/' . self::DELEGATE_DB_NAME
		]);
		
		$this->delegate_db->query('CREATE TABLE IF NOT EXISTS delegates (delegate_key TEXT PRIMARY KEY, project_id INT, type TEXT, data TEXT, date_created TEXT);');
	}
	
	function AddNewDelegate($project_id, $key, $type, $data) {
		$this->delegate_db->insert('delegates', [
			'delegate_key' => $key,
			'project_id' => $project_id,
			'type' => $type,
			'data' => json_encode($data),
			'date_created' => date('c')
		]);
	}
	
	function FindDelegate($project_id, $key) {
		$row = $this->delegate_db->select(
			'delegates', 
			['delegate_key', 'type', 'status', 'data', 'date_created'], 
			['AND' => ['delegate_key[=]' => $key, 'project_id[=]' => $project_id]]
		);
		
		if (is_array($row) && count($row) == 1) {
			$row = $row[0];
			$row['data'] = json_decode($row['data'], true);
			return $row;
		}
		return null;
	}
	
	function DeleteDelegate($project_id, $key) {
		$this->delegate_db->delete('delegates', [
			'AND' => [
				'project_id[=]' => $project_id,
				'delegate_key[=]' => $key
			]
		]);
	}
	
}