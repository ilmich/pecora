<?php

/**
 * file_cull_contents source file
 *
 * This file contains the code for the file_cull_contents function
 *
 */

error_reporting(E_ALL);

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
function file_cull_contents($filename, $offset = 0, $bytes = null, $whence = SEEK_SET, $data = null){
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
?>