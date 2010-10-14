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
 	* Defines the delimeter character used to sanitize and separate polarized data
 	*
 	* @access private
 	*/
	const P_DLM = "\x1a";	// ASCII 26

	/**
	 * Defines the string that causes vulnerability issues
	 *
	 * @access private
	 */
	const P_VUL = "\x2a\x2f";	// Refers to multi-line comment */
	
	/**
	 * Defines the substitution string for the delimeter character
	 *
	 * @access private
	 */
	const P_SUB = "\x1a0";	// Substitute for ASCII char 26
	
	/**
	 * Defines the substitution string for sanitizing polarized data
	 *
	 * @access private
	 */
	const P_HAZ = "\x1a2";	// Substitute for multi-line
	
	/**
	 * Defines the field separation string for polarized data
	 *
	 * @access private
	 */
	const P_FSEP = "\x1a1";	// Field Separator
	
	/**
	 * Defines the section separation string for polarized data
	 *
	 * @access private
	 */
	const P_SSEP = "\x1a3";	// Section Separator
		
	/**
     * A private variable that holds the array
     *
     * @access private
     * @var array
     */
	var $arr;
	
	/**
     * A private variable that holds the serialized key(s)
     *
     * @access private
     * @var string
     */
	var $keys;
	
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
	public function __construct($keys, $values = null){
		// Split the array into polarized strings
		if(is_array($keys)){
			$this->arr = $keys;
			$this->keys = '';
			$this->values = '';
			foreach($keys as $k => $v){
				$this->keys .= self::sanitize(serialize($k)) .self::P_FSEP;
				$this->values .= self::sanitize(serialize($v)) . self::P_FSEP;
			}
		// Join two polarized strings
		}else{
			$this->keys = $keys;
			$this->values = $values;
			$limit = '0';
			$output = ':{';
			while(false !== $temp = strpos($keys, self::P_FSEP)){
				$limit = bcadd($limit, '1');
				$output .= substr($keys, 0, $temp);
				$keys = substr($keys, $temp + 2);
				$temp = strpos($values, self::P_FSEP);
				$output .= substr($values, 0, $temp);
				$values = substr($values, $temp + 2);
			}
			$output .= '}';
			if(false === $this->arr = unserialize(self::desanitize('a:' . $limit . $output))){
				$this->arr = false;
				$this->keys = false;
				$this->values = false;
			}
		}
	}

	/**
	 * A method that returns serialized keys from a serialized array
	 *
	 * @return string serialized key(s) or FALSE on failure
	 */
	public function getKeys(){
		return $this->keys;
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
	 * @return array array or FALSE on failure
	 */
	public function getArr(){
		return $this->arr;
	}

	/**
 	* A function that sanitizes data
 	*
 	* @param string $entry the data to be sanitized
 	* @return string sanitized data
 	*/
	public static function sanitize($entry){
		return preg_replace(array('/\\x1a/', '/\\x2a\\x2f/'), array(self::P_SUB, self::P_HAZ), $entry);
	}

	/**
	 * A function that desanitizes data
	 *
	 * @param string $entry the data to be desanitized
	 * @return string desanitized data
	 */
	
	public static function desanitize($entry){	
		return preg_replace(array('/\\x1a2/', '/\\x1a0/'), array(self::P_VUL, self::P_DLM), $entry);
	}

}
?>