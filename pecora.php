<?php

/**
 * Pecora source file
 *
 * This file contains the code for the Pecora class
 */

error_reporting(E_ALL);

/**
 * Class required to split arrays into keys and values for storage into database
 *
 * @access private
 */

require_once('polarizer.php');

/**
 * Class required to ensure file locking for atomicity of data
 *
 * @access private
 */

require_once('mutex.php');

/**
 * The Pecora class contains all the methods necessary for manipulation of the flat file database.
 */

class Pecora{

	/**
	* Pecora version
 	*
 	* @access private
 	* @var string
 	*/
	const VERSION="3.0";
	
	/**
	* Pecora internal engine version
 	*
 	* @access private
 	* @var string
 	*/
	const ENGINE_VERSION="2.0";
	
	
	/**
	 * An AND mask used to manipulate binary data
 	*
 	* @access private
 	*/

	const M_PMASK = 0x7fffffff;
	
	/**
     * URI of a table
     *
     * @access private
	 * @var string
     */
	var $table = null;

	/**
     * URI of a table's structural file
     *
     * @access private
	 * @var string
     */
	var $struct = null;

	/**
     * The mutex mechanism variable
     *
     * @access private
     * @var Mutex
     */
	var $mutex = null;

	/**
	 * The constructor sets the table and structural file uris and their existence
	 *
	 * @param string $cwd a uri denoting the directory where the database tables are to be created/stored
	 * @param string $table the name of the table
	 * @param string $struct the name of the structural file
	 */
	public function __construct($cwd, $table){
		$this->struct = $cwd . DIRECTORY_SEPARATOR . $table . '_ts.php';
		$this->table = $cwd . DIRECTORY_SEPARATOR . $table . '.php';
	}

	/**
	 * A method that returns the table of interest
	 *
	 * @param boolean $path return table path or just the table name (name returned by default)
	 * @return string
	 */
	public function table($path = null){
		if(isset($path)) 
			return $this->table;
		
		return substr(basename($this->table), 0, -4);
	}

	/**
	 * A method that returns the structure of interest
	 *
	 * @param boolean $path return structure path or just the structure name (name returned by default)
	 * @return string
	 */
	public function struct($path = null){
		if(isset($path)) 
			return $this->struct;
		
		return substr(basename($this->struct), 0, -4);
	}

	/**
	 * A method that locks a table
	 *
	 * @param integer $polling specifies the sleep time (seconds) for the lock to wait in order to reacquire the lock if it fails.
	 * @return boolean TRUE on success FALSE on failure
	 */
	public function lock($polling = 1){
		// For atomicity we have to lock the table
		$mutex = new Mutex($this->table);

		if(!$mutex->acquireLock($polling))
			return false;
			
		$this->mutex = $mutex;

		return true;
	}

	/**
	 * A method that releases the lock on a table
	 *
	 * @return boolean TRUE on success FALSE on failure
	 */
	public function release(){
		if(!$this->mutex->releaseLock())
			return false;

		$this->mutex = null;
		
		return true;
	}
	
	/**
	 * A method that retrieves rows from a table.
	 *
	 * @param string $search regular expression search pattern or case-sensitive row label
	 * @param boolean $preg whether to use parameter $search as a regular expression or a case-sensitive string
	 * @return array table row(s) on success or FALSE on failure
	 */
	public function getRow($search, $preg = true){
		// Parameters
		if(!is_bool($preg)) $preg = false;
		
		if ($preg) {
			if (!is_string($search)) {
				throw new Exception("Regular expression must be a string");
			}			
		}else {
			if(!is_string($search) && !is_int($search) && !is_array($search))
				throw new Exception("Not valid search keys, ".gettype($search)." passed");

			if (!is_array($search)) {
				$search = array($search);
			}
		}		
		
		// Code
		if(false === $tableStruct = @file_get_contents($this->struct,null,null,14))
			return !trigger_error('Struct file '.$this->struct(true).' not found or not readable', E_USER_WARNING);			

		$tableStruct = explode(Polarizer::P_SSEP, $tableStruct);
		$tableStruct[0] = explode(Polarizer::P_FSEP, $tableStruct[0]);		
		$tableStruct[1] = unpack('N*', Polarizer::desanitize($tableStruct[1]));
		$tableStruct[2] = unpack('N*', Polarizer::desanitize($tableStruct[2]));			
				
		$ret = array();
		if($preg){			
			$tableStruct[0] = array_map('unserialize', $tableStruct[0]);
			foreach($tableStruct[0] as $key => $rowLabel){
				$key++;
				if(preg_match($search, $rowLabel)){
					if(false === $values = file_get_contents($this->table, null,null,14+$tableStruct[1][$key], $tableStruct[2][$key]))
						throw new Exception("Unable to load row ".$rowLabel." at offset ".$tableStruct[1][$key]." with lenght ".$tableStruct[2][$key]);
					$polarizer = new Polarizer($tableStruct[3], $values); //substr($values, 0, -2));
					if(false === $polarizer = $polarizer->getArr())
						throw new Exception("Unable to deserialize row ".$rowLabel);
					$ret[$rowLabel] = $polarizer;
				}
			}
		}else{
			foreach ($search as $find) {
				$key = serialize($find);
				if(false !== $key = array_search($key, $tableStruct[0])){									
					$key++;
					if(false === $values = file_get_contents($this->table, null,null,14+$tableStruct[1][$key], $tableStruct[2][$key]))
						throw new Exception("Unable to load row ".$rowLabel." at offset ".reset(unpack('N', $tableStruct[1][$key] . $tableStruct[1][$key + 1] . $tableStruct[1][$key + 2] . $tableStruct[1][$key + 3]))
																			." with lenght ".reset(unpack('N', $tableStruct[2][$key] . $tableStruct[2][$key + 1] . $tableStruct[2][$key + 2] . $tableStruct[2][$key + 3])));
					$polarizer = new Polarizer($tableStruct[3], $values); //substr($values, 0, -2));
					if(false === $polarizer = $polarizer->getArr())
						throw new Exception("Unable to deserialize row ".$rowLabel);
					$ret[$find] = $polarizer;
				}
			}
		}
		
		if(empty($ret)) 
			return false;
			
		return $ret;
	}
	
	/**
	 * A method retrieves all rows in a table
	 *
	 * @return array an array of tabular rows or FALSE on failure
	 */
	public function query(){
		// Code
		if(false === $tableStruct = file_get_contents($this->struct,null,null,14))
			return !trigger_error('Struct file '.$this->struct(true).' not found or not readable', E_USER_WARNING);
		
		if(false === $rows = file_get_contents($this->table,null,null,14))
			return !trigger_error('Table file '.$this->table(true).' not found or not readable', E_USER_WARNING);

		$tableStruct = explode(Polarizer::P_SSEP, $tableStruct);		
		$columns = $tableStruct[3];
		$tableStruct[0] = explode(Polarizer::P_FSEP, $tableStruct[0]);				
		$tableStruct[0] = array_map('unserialize', $tableStruct[0]);
		
		$tableStruct[1] = unpack('N*', Polarizer::desanitize($tableStruct[1]));
		$tableStruct[2] = unpack('N*', Polarizer::desanitize($tableStruct[2]));		
		
		$modStruct = array();
		foreach($tableStruct[0] as $key => $value){
			$key++;
			$polarizer = new Polarizer($tableStruct[3], substr($rows, $tableStruct[1][$key], $tableStruct[2][$key]));
			if(false === $polarizer = $polarizer->getArr())
				throw new Exception("Unable to deserialize row ".$value);
			$modStruct[$value] = $polarizer;
		}
		return $modStruct;
	}
	
	/**
	 * A method that returns the historical number of entries and the unique number of entries within the table
	 *
	 * @return array an array whose first value is the historical count and the second value is the unique count
	 */
	public function entries(){
		// Code		
		if(false === $tableStruct = file_get_contents($this->struct,null,null,14))
			return !trigger_error('Struct file '.$this->struct(true).' not found or not readable', E_USER_WARNING);
		
		$tableStruct = explode(Polarizer::P_SSEP, $tableStruct);
		
		$tableStruct[4] = unpack('N*', base64_decode($tableStruct[4]));
		
		return array(
			'history' => $tableStruct[4][2] + $tableStruct[4][3], 
			'unique' => $tableStruct[4][2]
		);
	}
	
/**
	 * A method that returns the engine version number and the creation time of the table
	 *
	 * @return array an array whose first value is the version number and the second value is the creation time of the table
	 */
	public function info(){
		// Code		
		if(false === $tableStruct = file_get_contents($this->struct,null,null,14))
			return !trigger_error('Struct file '.$this->struct(true).' not found or not readable', E_USER_WARNING);
		
		$tableStruct = explode(Polarizer::P_SSEP, $tableStruct);		
		$tableStruct[5] = explode(Polarizer::P_FSEP,$tableStruct[5]);
		
		return array(
			'version' => $tableStruct[5][0], 
			'ctime' => $tableStruct[5][1]
		);
	}
	
	/**
	 * A method that inserts rows into a table (if the table does not exist it attempts to create it)
	 *
	 * @param array $data an array of tabular rows to be inserted
	 * @param boolean $atomic whether or not file modifications should be atomic
	 * @return boolean TRUE on success FALSE on failure
	 */
	public function insertRow($data, $atomic = true){
		// Parameters
		if(!is_array($data) || empty($data))
			throw new Exception("Invalid or empty data");
		if(!is_bool($atomic)) $atomic = true;

		// Atomicity
		if($atomic)
			if(!is_object($this->mutex))
				throw new Exception("Lock not yet acquired");

		// Code
		$structOut = null;
		$tableOut = '';
		$offset = 0;
		$length = 0;

		if(false === $structOut = @file_get_contents($this->struct,NULL,NULL,14)){
			$length = reset($data);
			
			if(!is_array($length) || empty($length))
				throw new Exception("Invalid or empty data");
				
			$offset = key($data);

			if(!ctype_print($offset))
				throw new Exception("Invalid or empty data");
			
			$length = new Polarizer($length);
			$tableOut .= $length->getValues() . Polarizer::P_SSEP;
			$length = $length->getKeys();
			
			$structOut = strlen($tableOut);

			$structOut = serialize($offset)  . Polarizer::P_SSEP . //elenco chiavi separato da P_FSEP
											Polarizer::sanitize("\x00\x00\x00\x00") . Polarizer::P_SSEP . //offset scritto in intero da 4 byte
											Polarizer::sanitize(pack('N', $structOut & self::M_PMASK)) . Polarizer::P_SSEP . //lunghezza scritta in intero da 4 byte
											$length . Polarizer::P_SSEP . //elenco chiavi
												Polarizer::sanitize(pack('N*', ($structOut) & self::M_PMASK) . //lunghezza del file
												"\x00\x00\x00\x01". //numero di chiavi uniche
												"\x00\x00\x00\x00").Polarizer::P_SSEP . //numero di storia
												self::ENGINE_VERSION.Polarizer::P_FSEP.time(true).Polarizer::P_SSEP;
			unset($data[$offset]);
		}

		$structOut = explode(Polarizer::P_SSEP, $structOut);
		$structOut[0] = explode(Polarizer::P_FSEP, $structOut[0]);
		
		$structOut[1] = Polarizer::desanitize($structOut[1]);
		$structOut[2] = Polarizer::desanitize($structOut[2]);
		
		$structOut[4] = unpack('N*', Polarizer::desanitize($structOut[4]));
		
		foreach($data as $rowLabel => $rowData){
			// Parameters still to be checked
			if(!is_array($rowData) || empty($rowData) || !ctype_print($rowLabel))
				throw new Exception("Invalid or empty data");
			
			$rowLabel = serialize($rowLabel);
			$polarizer = new Polarizer($rowData);
			$polarizer = $polarizer->getValues() . Polarizer::P_SSEP;
			$length = strlen($polarizer);
			
			if(false !== $key = array_search($rowLabel, $structOut[0])){
				$structOut[4][3]++;
			}else{
				$key = $structOut[4][2];
				$structOut[4][2]++;
				$structOut[0][] = $rowLabel;
			}
			$key *= 4;
			$temp = pack('N', $structOut[4][1] & self::M_PMASK);
			$structOut[1][$key] = $temp[0];
			$structOut[1][$key + 1] = $temp[1];
			$structOut[1][$key + 2] = $temp[2];
			$structOut[1][$key + 3] = $temp[3];
			$temp = pack('N', $length & self::M_PMASK);
			$structOut[2][$key] = $temp[0];
			$structOut[2][$key + 1] = $temp[1];
			$structOut[2][$key + 2] = $temp[2];
			$structOut[2][$key + 3] = $temp[3];
			$structOut[4][1] += $length;
			$tableOut .= $polarizer;
		}
		
		$structOut[0] = implode(Polarizer::P_FSEP, $structOut[0]);
		
		$structOut[1] = Polarizer::sanitize($structOut[1]);
		$structOut[2] = Polarizer::sanitize($structOut[2]);
		$structOut[4] = Polarizer::sanitize(pack('N*', $structOut[4][1] & self::M_PMASK, $structOut[4][2] & self::M_PMASK, $structOut[4][3] & self::M_PMASK));
		
		$structOut = '<?php die();?>' . implode(Polarizer::P_SSEP, $structOut);

		if(false === file_put_contents($this->struct, $structOut))
			throw new Exception('Unable to write struct file');
		
		if (!file_exists($this->table)) {
			$tableOut = '<?php die();?>' . $tableOut;
		}
		
		if(false === file_put_contents($this->table, $tableOut,FILE_APPEND))
			throw new Exception('Unable to write table file');			

		return true;
	}
	
	/**
	 * A method that deletes rows within a table based on a search
	 *
	 * @param string $search regular expression search pattern or case-sensitive row label
	 * @param boolean $preg whether to use parameter $row as a regular expression or a case-sensitive string
	 * @param boolean $atomic whether or not file modifications should be atomic
	 * @return boolean TRUE on success FALSE on failure
	 */
	public function deleteRow($search, $preg = true, $atomic = true){
		// Parameters
		if(!is_bool($preg)) $preg = false;		
		
		if ($preg) {
			if (!is_string($search)) {
				throw new Exception("Regular expression must be a string");
			}			
		}else {
			if(!is_string($search) && !is_int($search) && !is_array($search))
				throw new Exception("Not valid search keys, ".gettype($search)." passed");

			if (!is_array($search)) {
				$search = array($search);
			}
		}		
				
		if(!is_bool($atomic)) $atomic = true;

		// Atomicity
		if($atomic)
			if(!is_object($this->mutex))
				throw new Exception("Lock not yet acquired");

		// Code
		if(false === $tableStruct = file_get_contents($this->struct,null,null,14))
			return !trigger_error('Struct file '.$this->struct(true).' not found or not readable', E_USER_WARNING);		
		$tableStruct = explode(Polarizer::P_SSEP, $tableStruct);
		$tableStruct[0] = explode(Polarizer::P_FSEP, $tableStruct[0]);
		$tableStruct[1] = Polarizer::desanitize($tableStruct[1]);
		$tableStruct[2] = Polarizer::desanitize($tableStruct[2]);
		$tableStruct[4] = unpack('N*', Polarizer::desanitize($tableStruct[4]));
		
		if($preg){
			foreach($tableStruct[0] as $key => $value){
				if(preg_match($search, unserialize($value))){
					unset($tableStruct[0][$key]);
					$key *= 4;
					$tableStruct[1] = substr_replace($tableStruct[1], '', $key, 4);
					$tableStruct[2] = substr_replace($tableStruct[2], '', $key, 4);
					$tableStruct[4][3]++;
					$tableStruct[4][2]--;
				}
			}
		}else{
			foreach ($search as $row) {
				$key = serialize($row);
				if(false !== $key = array_search($key, $tableStruct[0])){
					unset($tableStruct[0][$key]);
					$key *= 4;
					$tableStruct[1] = substr_replace($tableStruct[1], '', $key, 4);
					$tableStruct[2] = substr_replace($tableStruct[2], '', $key, 4);
					$tableStruct[4][3]++;
					$tableStruct[4][2]--;
				}
			}
		}

		if(empty($tableStruct[0])){
			if(!unlink($this->table) || !unlink($this->struct))
				return !trigger_error('Unable to delete empty struct file '.$this->struct(true), E_USER_WARNING);
		}else{
			$tableStruct[0] = implode(Polarizer::P_FSEP, $tableStruct[0]);
			$tableStruct[1] = Polarizer::sanitize($tableStruct[1]);
			$tableStruct[2] = Polarizer::sanitize($tableStruct[2]);
			$tableStruct[4] = Polarizer::sanitize(pack('N*', $tableStruct[4][1] & self::M_PMASK, $tableStruct[4][2] & self::M_PMASK, $tableStruct[4][3] & self::M_PMASK));
			
			$tableStruct = '<?php die();?>' . implode(Polarizer::P_SSEP, $tableStruct);

			if(false === file_put_contents($this->struct, $tableStruct))
				throw new Exception('Unable to write struct file');
		}
		return true;
	}
	
	/**
	 * A method that refreshes a table by removing its row history
	 *
	 * @param boolean $atomic whether or not file modifications should be atomic
	 * @return boolean TRUE on success FALSE on failure
	 */
	public function refresh($atomic = true){
		// Parameters
		if(!is_bool($atomic)) $atomic = true;

		// Atomicity
		if($atomic)
			if(!is_object($this->mutex))
				throw new Exception("Lock not yet acquired");

		// Code
		if(false === $rows = file_get_contents($this->table,null,null,14))
			return !trigger_error('Table file '.$this->table(true).' not found or not readable', E_USER_WARNING);
		if(false === $tableStruct = file_get_contents($this->struct,null,null,14))
			return !trigger_error('Struct file '.$this->struct(true).' not found or not readable', E_USER_WARNING);
		
		$tableStruct = explode(Polarizer::P_SSEP, $tableStruct);
		$tableStruct[1] = unpack('N*', Polarizer::desanitize($tableStruct[1]));
		$tableStruct[2] = unpack('N*', Polarizer::desanitize($tableStruct[2]));
		$tableStruct[4] = unpack('N*', Polarizer::desanitize($tableStruct[4]));
		
		$tableOut = '<?php die();?>';
		
		$offset = 0;
		foreach($tableStruct[2] as $key => $value){
			$tableOut .= substr($rows, $tableStruct[1][$key], $value);
			$tableStruct[1][$key] = pack('N', $offset & self::M_PMASK);
			$tableStruct[2][$key] = pack('N', $value & self::M_PMASK);
			$offset += $value;
		}	
		
		$tableStruct[1] = Polarizer::sanitize(implode('', $tableStruct[1]));
		$tableStruct[2] = Polarizer::sanitize(implode('', $tableStruct[2]));
		
		$tableStruct[4] = Polarizer::sanitize(pack('N*', $offset & self::M_PMASK, $tableStruct[4][2] & self::M_PMASK) . "\x00\x00\x00\x00");
		
		$tableStruct = '<?php die();?>' . implode(Polarizer::P_SSEP, $tableStruct);
		
		if(false === file_put_contents($this->struct, $tableStruct))
			throw new Exception('Unable to write struct file');
		if(false === file_put_contents($this->table, $tableOut))
			throw new Exception('Unable to write table file');

		return true;
	}	
}