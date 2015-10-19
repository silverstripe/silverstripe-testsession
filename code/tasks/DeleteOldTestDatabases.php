<?php 

class DeleteOldTestDatabases extends BuildTask {
	
	protected $description = 'Delete test databases created by testsesson module';
	
	public function run($request){
		$dry = $request->getVar('dry') !== '0'; 
		if($dry) echo 'DRY run, add ?dry=0 to run for realsies<br/><br/>';
		
		$rows = DB::query('show databases;');
		$numDeleted = 0;
		foreach($rows as $row){
			$db = $row['Database'];
			echo 'Database: '.$db;
			
			if($this->isTempDb($db)){
				echo ' ... TEMP DB FOUND';
				if(!$dry){
					DB::query('DROP DATABASE '.$db.';');
					echo ' ... DELETED';
					$numDeleted++;
				}
			}
			
			echo '<br/>';
		}
		
		echo '<br/>Done, deleted '.$numDeleted.' temporary database(s)';
	}
	
	
	/**
	 * @see framework/dev/SapphireTest.php::create_temp_db()
	 * @return boolean
	 */
	protected function isTempDb($name){
		$prefix = defined('SS_DATABASE_PREFIX') ? SS_DATABASE_PREFIX : 'ss_';
		$dbNameStartsWith = strtolower(sprintf('%stmpdb', $prefix));
		
		return strpos($name, $dbNameStartsWith) === 0;
	}
	
}