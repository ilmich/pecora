<?php

/**
 * sanitize source file
 *
 * This file contains the code for the sanitize function
 *
 */

error_reporting(E_ALL);

/**
 * A function that sanitizes data
 *
 * @param string $entry the data to be sanitized
 * @return string sanitized data
 */
function sanitize($entry){
	return preg_replace(array('/\\x1a/', '/\\x2a\\x2f/'), array(P_SUB, P_HAZ), $entry);
}
?>