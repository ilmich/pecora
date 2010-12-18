<?php

/**
 * Implementation of Pecora Document object
 * 
 */

class PDocument {
	
	protected $dbHandle;
	protected $_data;
	protected $id;
	
	public function __construct($dbHandle) {
		if (is_null($dbHandle) || !($dbHandle instanceof Pecora)) {
			throw new Exception("dbHandle must be a valid instance of Pecora database");
		}	
		$this->dbHandle = $dbHandle;
		$this->_data = array();
	}
	
	public function getId() {
		return $this->id;
	}
	
	public function setId($id) {
		$this->id=$id;
	}
	
	public function __set($key,$value) {
		$this->_data[$key] = $value;		
	}
	
	public function __get($key) {
		if(!isset($this->_data[$key])) return null;
		return $this->_data[$key];
	}
	
	public function toArray() {
		return $this->_data;
	}
	
	public function load($id) {
		if (is_null($id) || !is_string($id)) {
			throw new Exception("id must be a valid string");
		}		
		$this->id=$id;
		$data = $this->dbHandle->getRow($id,false);
		if ($data) {
			$this->_data = $data[$id];
		}
	}
	
	public function save($id=null) {
		
		$ids = $this->id;
		if (!is_null($id)) {
			$ids = $id;
		}
		
		if (!is_string($ids)) {
			throw new Exception("id must be a valid string");
		}
		
		$this->dbHandle->insertRow(array($ids => $this->toArray()),false);
	}
	
}