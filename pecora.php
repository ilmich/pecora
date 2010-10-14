<?php

/**
 * Pecora source file
 *
 * This file contains the code for the Pecora class
 */

error_reporting(E_ALL);

/**
 * An AND mask used to manipulate binary data
 *
 * @access private
 */

define('M_PMASK', 0x7fffffff);

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
	function getRow($search, $preg = true){
		// Parameters
		if(!is_bool($preg)) $preg = false;
		
		if ($preg) {
			if (!is_string($search)) {
				return !trigger_error('[' . basename(__FILE__) . '] &lt; ' . __LINE__ . ' &gt;', E_USER_WARNING);
			}			
		}else {
			if(!is_string($search) && !is_int($search) && !is_array($search))
				return !trigger_error('[' . basename(__FILE__) . '] &lt; ' . __LINE__ . ' &gt;', E_USER_WARNING);

			if (!is_array($search)) {
				$search = array($search);
			}
		}		
		
		// Code
		if(false === $tableStruct = file_get_contents($this->struct))
			return !trigger_error('[' . basename(__FILE__) . '] &lt; ' . __LINE__ . ' &gt;', E_USER_WARNING);
		$tableStruct = substr($tableStruct, 8, -4);
		$tableStruct = explode(P_SSEP, $tableStruct);
		$tableStruct[0] = explode(P_FSEP, substr($tableStruct[0], 0, -2));
		
		$tableStruct[1] = desanitize($tableStruct[1]);
		$tableStruct[2] = desanitize($tableStruct[2]);

		$ret = array();
		if($preg){
			$tableStruct[0] = array_map('desanitize', $tableStruct[0]);
			$tableStruct[0] = array_map('unserialize', $tableStruct[0]);
			$tableStruct[1] = unpack('N*', $tableStruct[1]);
			$tableStruct[2] = unpack('N*', $tableStruct[2]);
			foreach($tableStruct[0] as $key => $rowLabel){
				$key++;
				if(preg_match($search, $rowLabel)){
					if(false === $values = $this->file_cull_contents($this->table, $tableStruct[1][$key], $tableStruct[2][$key]))
						return !trigger_error('[' . basename(__FILE__) . '] &lt; ' . __LINE__ . ' &gt;', E_USER_WARNING);
					$polarizer = new Polarizer($tableStruct[3], substr($values, 0, -2));
					if(false === $polarizer = $polarizer->getArr())
						return !trigger_error('[' . basename(__FILE__) . '] &lt; ' . __LINE__ . ' &gt;', E_USER_WARNING);
					$ret[$rowLabel] = $polarizer;
				}
			}
		}else{
			foreach ($search as $find) {
				$key = sanitize(serialize($find));
				if(false !== $key = array_search($key, $tableStruct[0])){
					$key *= 4;
					if(false === $values = $this->file_cull_contents($this->table, reset(unpack('N', $tableStruct[1][$key] . $tableStruct[1][$key + 1] . $tableStruct[1][$key + 2] . $tableStruct[1][$key + 3])), reset(unpack('N', $tableStruct[2][$key] . $tableStruct[2][$key + 1] . $tableStruct[2][$key + 2] . $tableStruct[2][$key + 3]))))
						return !trigger_error('[' . basename(__FILE__) . '] &lt; ' . __LINE__ . ' &gt;', E_USER_WARNING);
					$polarizer = new Polarizer($tableStruct[3], substr($values, 0, -2));
					if(false === $polarizer = $polarizer->getArr())
						return !trigger_error('[' . basename(__FILE__) . '] &lt; ' . __LINE__ . ' &gt;', E_USER_WARNING);
					$ret[$find] = $polarizer;
				}
			}
		}
		if(empty($ret)) return false;
		return $ret;
	}
	
	/**
	 * A method retrieves all rows in a table
	 *
	 * @return array an array of tabular rows or FALSE on failure
	 */
	function query(){
		// Code
		if(false === $tableStruct = file_get_contents($this->struct))
			return !trigger_error('[' . basename(__FILE__) . '] &lt; ' . __LINE__ . ' &gt;', E_USER_WARNING);
		$tableStruct = substr($tableStruct, 8, -4);
		if(false === $rows = file_get_contents($this->table))
			return !trigger_error('[' . basename(__FILE__) . '] &lt; ' . __LINE__ . ' &gt;', E_USER_WARNING);

		$tableStruct = explode(P_SSEP, $tableStruct);
		$columns = $tableStruct[3];
		$tableStruct[0] = explode(P_FSEP, substr($tableStruct[0], 0, -2));
		
		$tableStruct[0] = array_map('desanitize', $tableStruct[0]);
		$tableStruct[0] = array_map('unserialize', $tableStruct[0]);
		$tableStruct[1] = unpack('N*', desanitize($tableStruct[1]));
		$tableStruct[2] = unpack('N*', desanitize($tableStruct[2]));

		$modStruct = array();
		foreach($tableStruct[0] as $key => $value){
			$key++;
			$polarizer = new Polarizer($tableStruct[3], substr($rows, $tableStruct[1][$key], $tableStruct[2][$key]));
			if(false === $polarizer = $polarizer->getArr())
				return !trigger_error('[' . basename(__FILE__) . '] &lt; ' . __LINE__ . ' &gt;', E_USER_WARNING);
			$modStruct[$value] = $polarizer;
		}
		return $modStruct;
	}
	
	/**
	 * A method that returns the historical number of entries and the unique number of entries within the table
	 *
	 * @return array an array whose first value is the historical count and the second value is the unique count
	 */
	function entries(){
		// Code		
		if(false === $tableStruct = file_get_contents($this->struct))
			return !trigger_error('[' . basename(__FILE__) . '] &lt; ' . __LINE__ . ' &gt;', E_USER_WARNING);
		$tableStruct = substr($tableStruct, 8, -4);
		$tableStruct = explode(P_SSEP, $tableStruct);
		
		$tableStruct[4] = unpack('N*', desanitize($tableStruct[4]));
		
		return array(
			'history' => $tableStruct[4][2] + $tableStruct[4][3], 
			'unique' => $tableStruct[4][2]
		);
	}

	
	/**
	 * A method that inserts rows into a table (if the table does not exist it attempts to create it)
	 *
	 * @param array $data an array of tabular rows to be inserted
	 * @param boolean $atomic whether or not file modifications should be atomic
	 * @return boolean TRUE on success FALSE on failure
	 */
	function insertRow($data, $atomic = true){
		// Parameters
		if(!is_array($data) || empty($data))
			return !trigger_error('[' . basename(__FILE__) . '] &lt; ' . __LINE__ . ' &gt;', E_USER_WARNING);
		if(!is_bool($atomic)) $atomic = true;

		// Atomicity
		if($atomic)
			if(!is_object($this->mutex))
				return !trigger_error('[' . basename(__FILE__) . '] &lt; ' . __LINE__ . ' &gt;', E_USER_WARNING);

		// Code
		$structOut = null;
		$tableOut = '';
		$offset = 0;
		$length = 0;

		if(false !== $structOut = @file_get_contents($this->struct)){
			$structOut = substr($structOut, 8, -4);
		}else{
			$length = reset($data);
			$offset = key($data);
	
			$length = new Polarizer($length);
			$tableOut .= $length->getValues() . P_SSEP;
			$length = $length->getKeys();
			
			$structOut = strlen($tableOut);
	
			$structOut = sanitize(serialize($offset)) . P_FSEP . P_SSEP . "\x00\x00\x00\x08" . P_SSEP . sanitize(pack('N', $structOut & M_PMASK)) . P_SSEP . $length . P_SSEP . sanitize(pack('N*', (8 + $structOut) & M_PMASK)) . "\x00\x00\x00\x01\x00\x00\x00\x00";
			unset($data[$offset]);
		}

		$structOut = explode(P_SSEP, $structOut);
		$structOut[0] = explode(P_FSEP, substr($structOut[0], 0, -2));
		
		$structOut[1] = desanitize($structOut[1]);
		$structOut[2] = desanitize($structOut[2]);
		
		$structOut[4] = unpack('N*', desanitize($structOut[4]));
		
		foreach($data as $rowLabel => $rowData){
			// Parameters still to be checked
			if(!is_array($rowData) || empty($rowData))
				return !trigger_error('[' . basename(__FILE__) . '] &lt; ' . __LINE__ . ' &gt;', E_USER_WARNING);
			
			$rowLabel = sanitize(serialize($rowLabel));
			$polarizer = new Polarizer($rowData);
			$polarizer = $polarizer->getValues() . P_SSEP;
			$length = strlen($polarizer);
			
			if(false !== $key = array_search($rowLabel, $structOut[0])){
				$structOut[4][3]++;
			}else{
				$key = $structOut[4][2];
				$structOut[4][2]++;
				$structOut[0][] = $rowLabel;
			}
			$key *= 4;
			$temp = pack('N', $structOut[4][1] & M_PMASK);
			$structOut[1][$key] = $temp[0];
			$structOut[1][$key + 1] = $temp[1];
			$structOut[1][$key + 2] = $temp[2];
			$structOut[1][$key + 3] = $temp[3];
			$temp = pack('N', $length & M_PMASK);
			$structOut[2][$key] = $temp[0];
			$structOut[2][$key + 1] = $temp[1];
			$structOut[2][$key + 2] = $temp[2];
			$structOut[2][$key + 3] = $temp[3];
			$structOut[4][1] += $length;
			$tableOut .= $polarizer;
		}
		
		$structOut[0] = implode(P_FSEP, $structOut[0]) . P_FSEP;
		
		$structOut[1] = sanitize($structOut[1]);
		$structOut[2] = sanitize($structOut[2]);
		$structOut[4] = sanitize(pack('N*', $structOut[4][1] & M_PMASK, $structOut[4][2] & M_PMASK, $structOut[4][3] & M_PMASK));

		$structOut = '<?php /*' . implode(P_SSEP, $structOut) . '*/?>';

		if(false === $this->file_place_contents($this->struct, $structOut))
			return !trigger_error('[' . basename(__FILE__) . '] &lt; ' . __LINE__ . ' &gt;', E_USER_WARNING);

		$tableOut .= '*/?>';

		if(false === $this->file_cull_contents($this->table, -4, null, SEEK_END, $tableOut)){
			$tableOut = '<?php /*' . $tableOut;
			if(false === $this->file_place_contents($this->table, $tableOut))
				return !trigger_error('[' . basename(__FILE__) . '] &lt; ' . __LINE__ . ' &gt;', E_USER_WARNING);
		}

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
	function deleteRow($search, $preg = true, $atomic = true){
		// Parameters
		if(!is_bool($preg)) $preg = false;		
		
		if ($preg) {
			if (!is_string($search)) {
				return !trigger_error('[' . basename(__FILE__) . '] &lt; ' . __LINE__ . ' &gt;', E_USER_WARNING);
			}			
		}else {
			if(!is_string($search) && !is_int($search) && !is_array($search))
				return !trigger_error('[' . basename(__FILE__) . '] &lt; ' . __LINE__ . ' &gt;', E_USER_WARNING);

			if (!is_array($search)) {
				$search = array($search);
			}
		}		
				
		if(!is_bool($atomic)) $atomic = true;

		// Atomicity
		if($atomic)
			if(!is_object($this->mutex))
				return !trigger_error('[' . basename(__FILE__) . '] &lt; ' . __LINE__ . ' &gt;', E_USER_WARNING);

		// Code
		if(false === $tableStruct = file_get_contents($this->struct))
			return !trigger_error('[' . basename(__FILE__) . '] &lt; ' . __LINE__ . ' &gt;', E_USER_WARNING);
		$tableStruct = substr($tableStruct, 8, -4);
		$tableStruct = explode(P_SSEP, $tableStruct);
		$tableStruct[0] = explode(P_FSEP, substr($tableStruct[0], 0, -2));
		$tableStruct[1] = desanitize($tableStruct[1]);
		$tableStruct[2] = desanitize($tableStruct[2]);
		$tableStruct[4] = unpack('N*', desanitize($tableStruct[4]));
		
		if($preg){
			foreach($tableStruct[0] as $key => $value){
				if(preg_match($search, unserialize(desanitize($value)))){
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
				$key = sanitize(serialize($row));
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
				return !trigger_error('[' . basename(__FILE__) . '] &lt; ' . __LINE__ . ' &gt;', E_USER_WARNING);
		}else{
			$tableStruct[0] = implode(P_FSEP, $tableStruct[0]) . P_FSEP;
			$tableStruct[1] = sanitize($tableStruct[1]);
			$tableStruct[2] = sanitize($tableStruct[2]);
			$tableStruct[4] = sanitize(pack('N*', $tableStruct[4][1] & M_PMASK, $tableStruct[4][2] & M_PMASK, $tableStruct[4][3] & M_PMASK));
			
			$tableStruct = '<?php /*' . implode(P_SSEP, $tableStruct) . '*/?>';

			if(false === $this->file_place_contents($this->struct, $tableStruct))
				return !trigger_error('[' . basename(__FILE__) . '] &lt; ' . __LINE__ . ' &gt;', E_USER_WARNING);
		}
		return true;
	}
	
	/**
	 * A method that refreshes a table by removing its row history
	 *
	 * @param boolean $atomic whether or not file modifications should be atomic
	 * @return boolean TRUE on success FALSE on failure
	 */
	function refresh($atomic = true){
		// Parameters
		if(!is_bool($atomic)) $atomic = true;

		// Atomicity
		if($atomic)
			if(!is_object($this->mutex))
				return !trigger_error('[' . basename(__FILE__) . '] &lt; ' . __LINE__ . ' &gt;', E_USER_WARNING);

		// Code
		if(false === $rows = file_get_contents($this->table))
			return !trigger_error('[' . basename(__FILE__) . '] &lt; ' . __LINE__ . ' &gt;', E_USER_WARNING);
		if(false === $tableStruct = file_get_contents($this->struct))
			return !trigger_error('[' . basename(__FILE__) . '] &lt; ' . __LINE__ . ' &gt;', E_USER_WARNING);
		$tableStruct = substr($tableStruct, 8, -4);
		$tableStruct = explode(P_SSEP, $tableStruct);
		$tableStruct[1] = unpack('N*', desanitize($tableStruct[1]));
		$tableStruct[2] = unpack('N*', desanitize($tableStruct[2]));
		$tableStruct[4] = unpack('N*', desanitize($tableStruct[4]));
		
		$tableOut = '<?php /*';
		
		$offset = 8;
		foreach($tableStruct[2] as $key => $value){
			$tableOut .= substr($rows, $tableStruct[1][$key], $value);
			$tableStruct[1][$key] = pack('N', $offset & M_PMASK);
			$tableStruct[2][$key] = pack('N', $value & M_PMASK);
			$offset += $value;
		}
		
		$tableOut .= '*/?>';
		
		$tableStruct[1] = sanitize(implode('', $tableStruct[1]));
		$tableStruct[2] = sanitize(implode('', $tableStruct[2]));
		
		$tableStruct[4] = sanitize(pack('N*', $offset & M_PMASK, $tableStruct[4][2] & M_PMASK) . "\x00\x00\x00\x00");
		
		$tableStruct = '<?php /*' . implode(P_SSEP, $tableStruct) . '*/?>';
		
		if(false === $this->file_place_contents($this->struct, $tableStruct))
			return !trigger_error('[' . basename(__FILE__) . '] &lt; ' . __LINE__ . ' &gt;', E_USER_WARNING);
		if(false === $this->file_place_contents($this->table, $tableOut))
			return !trigger_error('[' . basename(__FILE__) . '] &lt; ' . __LINE__ . ' &gt;', E_USER_WARNING);

		return true;
	}
	
	/**
	 * A function that writes content to a file
	 *
	 * @param string $filename the file to be written
	 * @param string $data the data to be written to the file
	 * @return integer the number of bytes written or FALSE on failure
	 */
	private function file_place_contents($filename, $data){
		
		if(false === $handle = @fopen($filename, "wb")) 
			return false;
	
		if(false === $bytes = @fwrite($handle, $data)) 
			return false;
	
		if(!fclose($handle)) 
			return false;
		
		return $bytes;
	}
	
	/**
	 * A function that reads/writes content to a file
	 *
	 * @param string $filename the file to be read/written
	 * @param integer $offset the offset from where to begin the read/write operation
	 * @param integer $bytes the number of bytes to be read
	 * @param integer $whence the location from where to compute offset for fseek
	 * @param string $data the data to be written
	 * @return mixed the number of bytes written, the bytes read, or FALSE on failure
	 */
	private function file_cull_contents($filename, $offset = 0, $bytes = null, $whence = SEEK_SET, $data = null){
		if(!isset($bytes)){
			if(false === $handle = @fopen($filename, 'r+b')) return false;
			if(-1 === fseek($handle, $offset, $whence)) return false;
			if(false === $data = @fwrite($handle, $data)) return false;
			if(!fclose($handle)) return false;
			return $data;
		}else{
			if(false === $handle = @fopen($filename, 'rb')) return false;
			if(-1 === fseek($handle, $offset, $whence)) return false;
			if(false === $data = @fread($handle, $bytes)) return false;
			if(!fclose($handle)) return false;
			return $data;
		}
	}
	
}

?>