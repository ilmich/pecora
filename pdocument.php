<?php

/**
 * Implementation of Pecora Document object
 * 
 */

class PDocument {
	
	/**
	 * @var Pecora	internal intance of database
	 */
	protected $dbHandle;
	/**
	 * @var array	array of fields/value
	 */
	protected $_data;
	/**
	 * @var string	id
	 */
	protected $_id;
	
	public function __construct($dbHandle) {
		if (is_null($dbHandle) || !($dbHandle instanceof Pecora)) {
			throw new Exception("dbHandle must be a valid instance of Pecora database");
		}	
		$this->dbHandle = $dbHandle;
		$this->_data = array();
	}
	
	public function getId() {
		return $this->_id;
	}
	
	public function setId($id) {
		$this->_id=$id;
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
		
		$this->_id=$id;
		if ($this->dbHandle->dbExists()) {		
			$data = $this->dbHandle->getRow($id,false);
			if ($data) {
				$this->_data = $data[$id];
			}
		}
	}
	
	public function save($id=null) {
		
		$ids = $this->_id;
		if (!is_null($id)) {
			$ids = $id;
		}
		
		if (!is_string($ids)) {
			throw new Exception("id must be a valid string");
		}
		
		$this->dbHandle->insertRow(array($ids => $this->toArray()),false);
	}
	
}