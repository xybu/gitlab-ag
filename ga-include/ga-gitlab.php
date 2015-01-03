<?php
/**
 * GitLab controller deals with the data from gitlab-ag to GitLab.
 */

require_once 'ga-base.php';

class GitLab_API extends Base {
	
	public $action = null;
	
	public function __construct() {
		$this->EnsureSignedInRequest();
		
		if (array_key_exists('action', $_GET))
			$this->action = $_GET['action'];
		else if (array_key_exists('action', $_POST))
			$this->action = $_POST['action'];
		
	}
	
	public function HandleRequest() {
		if (empty($this->action))
			$this->session->JSON_OutputError('unspecified-action', 'The request action is not present.', '406 Not Acceptable');
		else if ($this->action == 'add_new_users') {
			$this->AddNewUsers();
		}
		else {
			$this->session->JSON_OutputError('unknown-action', 'The request action is not supported.', '404 Not Found');
		}
	}
	
	public function AddNewUsers() {
		try {
			if (!array_key_exists('CsvData', $_POST))
				throw new Exception('No csv content uploaded.');
			
			$projects_limit = (array_key_exists('ProjectsLimit', $_POST) ? intval($_POST['ProjectsLimit']) : 0);
			$user_can_create_group = array_key_exists('Opt_CanCreateGroup', $_POST) && $_POST['Opt_CanCreateGroup'] == true;
			$user_is_admin = array_key_exists('Opt_IsAdmin', $_POST) && $_POST['Opt_IsAdmin'] == true;
			
			$csv_records = array_map('str_getcsv', explode("\n", $_POST['CsvData']));
			if (count($csv_records) < 1)
				throw new Exception('There is no data record in csv content.');
			
			$user_id_col_key = -1;
			$name_col_key = -1;
			$email_col_key = -1;
			$id_col_key = -1;
			
			// find mappable columns
			foreach ($csv_records[0] as $i => $col_name) {
				if ($col_name == 'ID') $id_col_key = $i;
				else if ($col_name == 'User ID') $user_id_col_key = $i;
				else if ($col_name == 'NAME') $name_col_key = $i;
				else if ($col_name == 'EMAIL') $email_col_key = $i;
			}
			
			// report error if any required column is missing
			$missing_cols = array();
			if ($user_id_col_key == -1) $missing_cols[] = 'User ID';
			if ($name_col_key == -1) $missing_cols[] = 'NAME';
			if ($email_col_key == -1) $missing_cols[] = 'EMAIL';
			if (count($missing_cols) > 0)
				throw new Exception('The following required CSV columns are missing: ' . implode(', ', $missing_cols));
			
			// remove the header
			unset($csv_records[0]);
			
			$i = 0;
			foreach($csv_records as $row) {
				$new_user_data = array(
					'email' => $row[$email_col_key],
					'username' => $row[$user_id_col_key],
					'name' => $row[$name_col_key],
				);
				if ($id_col_key != -1) $new_user_data['extern_uid'] = $row[$id_col_key];
				echo json_encode($new_user_data) . '<br>';
				++$i;
			}
			
		} catch (Exception $e) {
			$this->session->JSON_OutputError('error', $e->GetMessage());
		}
	}
	
}
 
