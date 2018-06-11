<?php

	class DB {
		
		private $DB_HOST = "localhost";
		private $DB_USER = "root";
		private $DB_PWD = "062030";
		private $DB_NAME = "favorite";
		private $conn;
		private static $_instance;
		
		public function __construct() {
			$this->conn = mysqli_connect($this->DB_HOST, $this->DB_USER, $this->DB_PWD, $this->DB_NAME);
			$this->conn -> set_charset("utf8");
			$this->conn -> query("SET time_zone = '+8:00'");
			$this->conn -> query("SET character_set_results = 'utf8', character_set_client = 'utf8', character_set_connection = 'utf8', character_set_database = 'utf8', character_set_server = 'utf8'");
		}
		
		public static function getInstance() {
			if(!isset(self::$_instance)) { 
				$c=__CLASS__; 
				self::$_instance=new $c; 
			} 
			return self::$_instance; 
		}
		
		//阻止用户复制对象实例 
		public function __clone() { 
			trigger_error('Clone is not allowed' ,E_USER_ERROR); 
		} 
		
		public function getConn() {
			return $this->conn;
		}
		
		public function close() {
			mysqli_close($this->conn); 
		}
		
		public function query($sql) {
			$result = mysqli_query($this->conn,$sql);
			$array = array();
			while($row = $result->fetch_assoc())
				$array[] = $row;
			return $array;
		}
		
		public function insert($table,$data) {
			$sql = "insert into ".$table."(";
			$column = "";
			$values = "";
			foreach($data as $key => $value) {
				$column = $column.$key.",";
				if(gettype($value) == 'string' ) {
					$values = $values."'".$value."',";
				} else {
					$values = $values.$value.",";
				}
			}
			$column = substr($column,0,-1);
			$values = substr($values,0,-1);
			$sql = $sql.$column.") values(".$values.");";
			$result = $this->conn->query($sql);
			return $result;
		}
		
		public function update($table,$data,$where) {
			$sql = "update ".$table." set ";
			foreach($data as $key => $value) {
				if(gettype($value) == 'string' ) {
					$sql = $sql.$key."='".$value."',";
				} else {
					$sql = $sql.$key."=".$value.",";
				}
			}
			$sql = substr($sql,0,-1);
			$sql = $sql." where ".$where;
			$result = $this->conn->query($sql);
			return $result;
		}
		
	}

?>