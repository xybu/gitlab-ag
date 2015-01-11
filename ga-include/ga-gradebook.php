<?php

require_once 'lib/medoo.php';

class GradeBook {
	
	const DATABASE_NAME = 'ga-gradebook.db';
	
	protected $db;
	
	function __construct() {
		$this->db = new medoo([
			'database_type' => 'sqlite',
			'database_file' => dirname(__FILE__) . '/../ga-data/' . self::DATABASE_NAME
		]);
		
		$this->db->query('CREATE TABLE IF NOT EXISTS grades (project_id INT PRIMARY KEY, project_name TEXT, user_id INT, username TEXT, grade INT, grade_data TEXT, grade_log TEXT, date_created TEXT);');
	}
	
	function AddNewRecord($project_id, $project_name, $user_id, $username, $grade, $grade_data = '', $grade_log = '') {
		$data = [
			'project_id' => $project_id,
			'project_name' => $project_name,
			'user_id' => $user_id,
			'username' => $username,
			'grade' => $grade,
			'grade_data' => '',
			'grade_log' => $grade_log,
			'date_created' => date('c')
		];
		if (is_array($grade_data)) $grade_data = json_encode($grade_data);
		if (is_string($grade_data)) $data['grade_data'] = $grade_data;
		
		$this->db->insert('grades', $data);
	}
	
	function FindRecord($username = null, $project = null) {
		$where = [];
		if ($username != null) $where['username[~]'] = $this->ToSqlWildcard($username);
		if ($project != null) $where['project[~]'] = $this->ToSqlWildcard($project);
		if (count($where) > 1) $where = ['AND' => $where];
			
		return $this->db->select('grades', '*', $where);
	}
	
	function DeleteRecord($username = null, $project = null) {
		$where = [];
		if ($username != null) $where['username[~]'] = $this->ToSqlWildcard($username);
		if ($project != null) $where['project[~]'] = $this->ToSqlWildcard($project);
		if (count($where) > 1) $where = ['AND' => $where];
			
		$this->db->delete('grades', $where);
	}
	
	function ToSqlWildcard($str) {
		$str = str_replace('*', '%', $str);
		$str = str_replace('?', '_', $str);
		return $str;
	}
}
