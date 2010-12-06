<?php

/**
 * Polarizer source file
 *
 * This file contains the code for the Polarizer class
 */

error_reporting(E_ALL);

/**
 * The Polarizer class takes an array and decomposes its key=>value pairs into a serialized key string and a serialized value string. It also recombines said strings into the original array.
 */
class Polarizer {	
	
	/**
	 * Defines the field separation string for polarized data
	 *
	 * @access private
	 */
	const P_FSEP = "\x1e";	// Field Separator
	
	/**
	 * Defines the section separation string for polarized data
	 *
	 * @access private
	 */
	const P_SSEP = "\x1f";	// Section Separator
	
	/**
     * A private variable that holds the serialized value(s)
     *
     * @access private
     * @var string
     */
	var $values;

	/**
	 * The constructor determines the procedure to follow on how to parse the serialized input strings dependent on one or two parameters being passed to it. If one parameter is passed then it assumes a serialized array and will thus split the serialized array string into two strings of keys and values respectively. If two parameters are passed then the constructor assumes that it is being given serialized keys and serialized values and therefore recombines them into a serialized array.
	 *
	 * @param mixed $keys array or serialized key(s)
	 * @param string $values serialized value(s)
	 */
	public function __construct($keys){
		// Split the array into polarized strings
		if(is_array($keys)){			
			$this->values = serialize($keys);			
		// Join two polarized strings
		}else{
			$this->values = unserialize($keys);			
		}
	}
	
	/**
	 * A method that returns serialized values from a serialized array
	 *
	 * @return string serialized value(s) or FALSE on failure
	 */
	public function getValues(){
		return $this->values;
	}

	/**
	 * A method that returns an array from serialized keys and serialized values
	 * 
	 * @deprecated 
	 * @return array array or FALSE on failure
	 */
	public function getArr(){
		return $this->values;
	}

	/**
 	* A function that sanitizes data
 	*
 	* @param string $entry the data to be sanitized
 	* @return string sanitized data
 	*/
	public static function sanitize($entry){	
		return base64_encode($entry);
	}

	/**
	 * A function that desanitizes data
	 *
	 * @param string $entry the data to be desanitized
	 * @return string desanitized data
	 */
	
	public static function desanitize($entry){
		return base64_decode($entry);		
	}

}