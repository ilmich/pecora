<?php

/**
 * file_place_contents source file
 *
 * This file contains the code for the file_place_contents function
 *
 */

error_reporting(E_ALL);

/**
 * A function that writes content to a file
 *
 * @param string $filename the file to be written
 * @param string $data the data to be written to the file
 * @return integer the number of bytes written or FALSE on failure
 */
function file_place_contents($filename, $data){
	if(false === $handle = @fopen($filename, "wb")) return false;

	if(false === $bytes = @fwrite($handle, $data)) return false;

	if(!fclose($handle)) return false;
	
	return $bytes;
}
?>