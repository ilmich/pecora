<?php

/**
 * Mutex source file
 *
 * This file contains the code for the Mutex class
 */

error_reporting(E_ALL);

/**
 * The Mutex class creates a temporary directory in order to ensure that exclusive locks are acquired whenever a file is accessed.
 */

class Mutex {
	/**
     * URI of the directory to be locked
     *
     * @access private
     * @var string
     */
	var $dirname;

	/**
	 * The constructor sets up all the parameters to create the lock (always infinite).
	 *
	 * @param string $dirname file to be locked
	 */
	function Mutex($dirname){
		/**
		 * Parameter passing error handling
		 */

		if(!is_string($dirname))
			trigger_error('[Mutex.php] &lt; ' . __LINE__ . ' &gt;', E_USER_ERROR);

		/**
		 * Code section
		 */

		// Append a '.lck' extension to filename for the locking mechanism
		$this->dirname = $dirname . '.lck';
	}

	/**
	 * A method that sets the lock on a file
	 *
	 * @param integer $polling specifies the sleep time (seconds) for the lock to wait in order to reacquire the lock if it fails.
	 * @return boolean TRUE on success or crash on failure
	 */
	function acquireLock($polling = 1){
		/**
		 * Parameter passing error handling
		 */

		$fp=false;		
		if(!is_int($polling) || $polling < 1) $polling = 1;

		$retry = intval((ini_get('max_execution_time')/$polling));
		
		if ($retry == 0) {
			$retry = 10;
		}		
		/**
		 * Code section
		 */
		
		// Create the directory and hang in the case of a preexisting lock
		while(!($fp = @mkdir($this->dirname)) && $retry-->0) {			
			sleep($polling);	
		}
		

		// Successful lock
		return $fp;
	}

	/**
	 * A method that releases the lock on a file
	 *
	 * @return boolean TRUE on success FALSE on failure
	 */
	function releaseLock(){
		/**
		 * Code section
		 */

		// Delete the directory with the extension '.lck'
		if(!@rmdir($this->dirname))
			return !trigger_error('[Mutex.php] &lt; ' . __LINE__ . ' &gt;', E_USER_WARNING);
		// Successful release
		return true;
	}
}
?>