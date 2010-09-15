<?php

/**
 * desanitize source file
 *
 * This file contains the code for the desanitize function
 *
 */

error_reporting(E_ALL);

/**
 * A function that desanitizes data
 *
 * @param string $entry the data to be desanitized
 * @return string desanitized data
 */
function desanitize($entry){
	return preg_replace(array('/\\x1a2/', '/\\x1a0/'), array(P_VUL, P_DLM), $entry);
}
?>