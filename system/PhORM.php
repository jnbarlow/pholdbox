<?php
namespace system;
require_once("MDB2.php");

/*
 * Created on Dec 29, 2010
 * John Barlow
 * 
 * PhORM - PholdBox ORM
 *  
 *  The only requirement for PhORM is that every table that is managed by PhORM
 *  have an "id" field that is an auto increment.  This limitation should fit within 90% 
 *  of the applications written in the framework.  I know this could require your tables 
 *  to be a bit larger than they need to be, but it greatly simplifies the ORM logic.  
 *  Besides, if you want to do something more complex (and horribly inefficient) like 
 *  using UUID's for ID fields, or remove my one extra column you need for PhORM, go ahead...
 *  roll your own.  I'm not stopping you :).
 *
 * 	ORM definitions are configured within the ORM array.
 *	
 *	$ORM - dsn - new dsn if not using default system dsn.  Specifying an invalid DSN
 				WILL make your page hang.
 *		   tableName - Database Table
 *		   columns - Array of column names.
 *		   types - Array of column types.
 *		   values - Associative array of values.
 *		   relationships - defines linked items - OnetoMany, ManytoMany
 *			
 *		   ex: "relationships"=>array(array("name" => "Widgets",     - name of link
									  		"type" => "onetomany",   - type of relationship
						   					"object" => "Widget"     - IOC object for relationship
						   				   )
						   		     )
 * 
 */
 //TODO: set up links logic
 class PhORM extends PholdBoxBaseObj
 {
 	/**
 	 * @property array ORM This describes the database you wish to use for the object.
 	 * 
 	 * 	protected $ORM = array("tableName"=>"test",
	 *					   "dsn"=>"",
	 *					   "columns"=>array("id", "name", "title"),
	 *					   "types"=>array("int(1)", "varchar(25)", "varchar(25)"),
	 *					   "values"=>array());
 	 * 
 	 */
 	protected $ORM = array();
 	
 	/**
 	 * @property object db Database Object
 	 */
 	protected $db = null;
 	
 	function __construct()
 	{
 		//go through relationships, add related objects to the IOC array
 		if(isset($this->ORM["relationships"]))
 		{
 			foreach($this->ORM["relationships"] as $rel)
 			{
 				if(isset($rel["object"])){
 					array_push($this->IOC, $rel["object"]);
 				}
 			}
 		}	
 		
 		parent::__construct();
 		$current_dsn="";
 		if(array_key_exists("dsn", $this->ORM) && $this->ORM["dsn"] != "")
 		{
 			$current_dsn = $this->ORM["dsn"];
 		}
 		else
 		{
 			$current_dsn = $this->SYSTEM["dsn"]["default"]; 
 		}
 		
 		$this->db = \MDB2::connect($this->SYSTEM["dsn"][$current_dsn]["connection_string"]);
 		
 		if(\PEAR::isError($this->db))
 		{
 			die($this->db->getMessage());
 		}	
 	}
 	
 	/**
 		Name: setValue
 		
 		Does: Sets value of key into the VO
 		
 		Returns: Nothing
 	*/
 	public function setValue($key, $value)
 	{
 		$this->ORM["values"][$key] = $value;
 	}
 	
 	/**
 		Name: getValue
 		
 		Does: Gets value of key into the VO
 		
 		Returns: value
 	*/
 	public function getValue($key)
 	{
 		if(isset($this->ORM["values"][$key]))
 		{ 
 			return $this->ORM["values"][$key];
 		}
 		return '';
 	}
 	
 	/**
 		Name: __call
 		
 		Does: This is a php 5 "magic" function that gets called if you call a function that doesn't exist.
 		      The purpose of this is to handle calling "get<value>".  If it doesn't find a key in the object,
 		      it passes the call up to the parent to be handled.
 		      
 		Returns: db value of object, or whatever the parent decides to return (could be an object)
 	*/
 	public function __call($name, $arguments)
 	{ 				
 		$action = substr($name, 0, 3);
 		$ucProp = substr($name, 3);
 		$prop = lcfirst($ucProp);
 		
 		if($action == "get")
 		{
 			if(in_array($prop, $this->ORM["columns"]))
 			{
 				return $this->getValue($prop);
 			}
 			else if ($this->isRelationship($ucProp))
 			{
 				if(!isset($this->instance[$ucProp . "Array"]))
 				{
 					$relProcessor = "load" . $this->ORM["relationships"][$ucProp]["type"]; 
 					$this->$relProcessor($ucProp);
 				}
 				
 				return $this->instance[$ucProp . "Array"]; 				
 			}
 			else
 			{
 				return parent::__call($name, $arguments);
 			}
 		} 		
 		else if($action == "set")
 		{
 			if(in_array($prop, $this->ORM["columns"]))
 			{
 				$this->setValue($prop, $arguments[0]);
 			}
 			else
 			{
 				parent::__call($name, $arguments);
 			}
 		} 		
 	}
 	
 	/**
 	 * Loads a OneToMany relationship
 	 * 
 	 * @param string $name Relationship name
 	 */
 	protected function loadOneToMany($name)
 	{
 		$rel = $this->ORM["relationships"][$name];
 		$obj = $this->instance[$rel["object"]];
 		$setFcn = "set" . ucfirst($rel["linkColumn"]);
 		
 		$obj->$setFcn($this->getId());
 		$this->instance[$name . "Array"] = $obj->load();
 	}
 	
 	/**
 	 * isRelationship
 	 * 
 	 * Checks to see if a given name is a defined relationship
 	 * 
 	 * @param string $name relationship name
 	 * @returns bool 
 	 */
 	protected function isRelationship($name)
 	{
 		$retVal = false;
 		if(isset($this->ORM["relationships"]) && isset($this->ORM["relationships"][$name]))
 		{
 			$retVal = true; 			
 		}
 		return $retVal;
 	}
 	
 	/**
 	 * Name: getPhormTable
 	 * Does: returns the table this object is associated with.
 	 */
 	public function getPhORMTable()
 	{
 		return $this->ORM["tableName"];
 	}

	/**
		Name: generateSelect()
		
		Does: Generates a select statement based on the values in the DB object.  The where clause is 
		      generated by ANDing the values of the object together.
		
		Returns: SQL select statement (string)
	*/ 
 	protected function generateSelect()
 	{
 		$sql = "select ";
 		$first = true; 
 		$wFirst = true;
 		$where = "";
 		foreach($this->ORM["columns"] as $column)
 		{
 			if(!$first)
 			{
 			 	$sql = $sql . ", ";
 			}
 			$sql = $sql . $column;
 			$first=false;
 			if(array_key_exists($column, $this->ORM["values"]) && $this->ORM["values"][$column] != '')
 			{
 				if(!$wFirst)
 				{
 					$where = $where . " and ";
 				}
 				$where = $where . $column . "='" . str_replace("'", "''", $this->ORM["values"][$column]) . "'";
 				$wFirst = false;
 			}
 		}
 		$sql = $sql . " from " . $this->ORM["tableName"];
 		if($where != '')
 		{
 			$sql = $sql . " where " . $where;
 		}
 		
 		return $sql;
 	}
 	
 	/**
 	 * query
 	 * 
 	 * This is a wrapper to the underlying PEAR db query function so that you can have custom queries in objects.
 	 * The queries should be in functions named "qMyCustomQueryName".  This is where you put the custom SQL and call
 	 * $this->query($sql);
 	 * 
 	 * @param string $sql SQL string to execute
 	 * @return Object DB object with the result of the query
 	 */
 	public function query($sql)
 	{
 		$result = $this->db->query($sql);
 		
 		// Always check that result is not an error
		if (\PEAR::isError($result)) {
		    die($result->getMessage());
		}
		
		return $result;
 	}
 	
 	/**
 	 * load
 	 * 
 	 * This function loads one object if an ID is supplied, otherwise it will do a
 	 * bulk search across the db and return an array of objects
 	 * 
 	 * @return array Array of matching objects if in bulk mode.
 	 */
 	public function load()
 	{
 		//capture debug timing
		if((isset($this->SYSTEM["debug"]) && $this->SYSTEM["debug"]))
		{
			$startTime = microtime();
		}
		
 		$bulk = false;
 		$returnArray = array();
 		
 		if($this->getId() == '')
 		{
 			$bulk = true;
 		}
 		
 		$sql = $this->generateSelect();	
 		
 		$result = $this->db->query($sql);
 		
 		// Always check that result is not an error
		if (\PEAR::isError($result)) {
			if($this->ORM["tableName"] == "pholdbox"){
				throw new \Exception($result->getMessage());
			}
		    die($result->getMessage());
		}
		
		//load object
		if($result->numRows() == 1)
		{
			$row = $result->fetchRow();
	
			$resultCols = $result->getColumnNames();
			foreach($this->ORM["columns"] as $column)
			{
				$this->setValue($column, $row[$resultCols[strtolower($column)]]);
			}
			array_push($returnArray, $this);
 		}
 		//load objects
 		else if($result->numRows() > 1)
 		{
 			$class= get_class($this);
 			$row = $result->fetchRow();
 			while($row != null){
 				
				$obj = new $class;
 				
				$resultCols = $result->getColumnNames();
				foreach($this->ORM["columns"] as $column)
				{
					$obj->setValue($column, $row[$resultCols[strtolower($column)]]);
				}
				array_push($returnArray, $obj);
				$row = $result->fetchRow();
 			}
 		}
 		else
 		{
 			foreach($this->ORM["columns"] as $column)
 			{
 				if($this->getValue($column) == null)
 				{
 					$this->setValue($column, "");	
 				}
 			}
 			
 		} 	
 		//capture debug output
		if((isset($this->SYSTEM["debug"]) && $this->SYSTEM["debug"]))
		{
			$this->pushDebugStack(get_class($this) .  ".load()", "Function", microtime() - $startTime);
		}
 		return $returnArray;	
 	}
 	
 	/**
 	 * clear
 	 * clears an object to be reused
 	 */
 	public function clear()
 	{
 		foreach($this->ORM["columns"] as $column)
 		{
 			$this->setValue($column, "");	
 		}
 	}
 	
 	protected function generateUpdate()
 	{
 		$sql = "update ". $this->ORM["tableName"] . " set ";
 		$first = true;
 		
 		foreach($this->ORM["columns"] as $column)
 		{	
 			if($column != "id")
 			{
 				//sanity check
 				if(!array_key_exists($column, $this->ORM["values"]))
 				{
 					print(get_class($this) . " - Missing value: $column");
 					exit;
 				}
 				
	 			if(!$first)
	 			{
	 			 	$sql = $sql . ", ";
	 			}
	 			$sql = $sql . $column . " = '" . str_replace("'", "''", $this->ORM["values"][$column]) . "'";
	 			$first=false;
 			}
 			
 		}
 		
 		$sql = $sql . " where id = " . $this->ORM["values"]["id"] . ";";
 		
 		return $sql;
 	}
 	
 	protected function generateInsert()
 	{
 		$sql = "insert into ". $this->ORM["tableName"];
 		$first = true;
 		$colNames = " (";
 		$values = " values (";
 		foreach($this->ORM["columns"] as $column)
 		{	
 			if($column != "id")
 			{
 				//sanity check
 				if(!array_key_exists($column, $this->ORM["values"]))
 				{
 					print(get_class($this) . " - Missing value: $column");
 					exit;
 				}
 				
	 			if(!$first)
	 			{
	 			 	$colNames = $colNames . ", ";
	 			 	$values = $values . ", ";
	 			}
	 			
	 			//$colNames = $colNames . "'" . $column . "'";
	 			$colNames = $colNames . $column;
	 			$values = $values . "'" . str_replace("'", "''", $this->ORM["values"][$column]) . "'";
	 			$first=false;
 			}
 			
 		}
 		$colNames = $colNames . ")";
 		$values = $values . ")";
 		$sql = $sql . $colNames . $values . ";";
 		
 		return $sql;
 	}
 	
 	//TODO: Finish this
 	protected function generateBulkInsert($tempTableKey)
 	{
 		$target = '';
 		$sql = '';
 		
 		if($tempTableKey != null)
 		{
 			$sql .= $this->generateTempTableSQL($tempTableKey);
 			$target = $tempTableKey;
 		}
 		else
 		{
 			$target = $this->ORM["tableName"];
 		}
 		
 		$sql .= "insert into ". $target;
 		$first = true;
 		$colNames = " (";
 		foreach($this->ORM["columns"] as $column)
 		{	
 			if($column != "id" || $tempTableKey != null)
 			{
 				//sanity check
 				if(!array_key_exists($column, $this->ORM["values"]))
 				{
 					print(get_class($this) . " - Missing value: $column");
 					exit;
 				}
 				
	 			if(!$first)
	 			{
	 			 	$colNames = $colNames . ", ";
	 			}
	 			
	 			$colNames = $colNames . $column;
	 			$first=false;
 			}
 			
 		}
 		$colNames = $colNames . ")";
 		$sql = $sql . $colNames;
 		
 		return $sql;
 	}
 	
 	/**
 	 * Name: generateTempTableSQL
 	 * Does: Generates the temp table creation SQL for whatever DB server you are using
 	 * @param string tempTableKey - name of temporary table.
 	 * @return string SQL to create temp table.
 	 */
 	protected function generateTempTableSQL($tempTableKey)
 	{	
 		$sql = "";
 		//TODO: do some fancy switching here based on db type
 		
 		//MYSQL
 		$sql = "CREATE TEMPORARY TABLE ". $tempTableKey . " (";
    	$count = 0;
    	
    	foreach($this->ORM["columns"] as $column)
    	{
    		if($count != 0)
    		{ 
    			$sql .= ", ";
    		}
    		$sql .= $column . " " . $this->ORM["types"][$count];
    		$count++;
    	}
    	$sql .= ");";
   
 		return $sql;	
 	}
 	
 	/**
 	 * Name: generateBulkSelect
 	 * Does: creates the select statements needed for bulk inserting
 	 */
 	protected function generateBulkSelect($tempTableKey)
 	{
 		$sql = "Select ";
 		$first = true;
 		foreach($this->ORM["columns"] as $column)
 		{	
 			if($column != "id" || $tempTableKey != null)
 			{
 				//sanity check
 				if(!array_key_exists($column, $this->ORM["values"]))
 				{
 					print(get_class($this) . " - Missing value: $column");
 					exit;
 				}
 				
	 			if(!$first)
	 			{
	 			 	$sql = $sql . ", ";
	 			}
	 			
	 			$sql = $sql . "'" . $this->ORM["values"][$column] . "'";
	 			$first=false;
 			}
 			
 		}
 		//$sql = $sql . ";";
 		
 		return $sql;
 	}
 	
 	/**
 	 * Name: Save
 	 * 
 	 * Does: Saves the object based on the values.  If an ID is given, it will update instead 
 	 * of insert.
 	 */
 	public function save()
 	{
 		//capture debug timing
		if((isset($this->SYSTEM["debug"]) && $this->SYSTEM["debug"]))
		{
			$startTime = microtime();
		}
		
 		$sql = "";
 		if(array_key_exists("id", $this->ORM["values"]) && $this->ORM["values"]["id"] != "")
 		{
 			//if the id is defined, update
 			$sql = $this->generateUpdate();
 		}
 		//else, if the id is not defined, insert.
 		else
 		{
 			$sql = $this->generateInsert();
 		}
 		
 		$result = $this->db->exec($sql);
 		
 		// Always check that result is not an error
		if (\PEAR::isError($result)) {
		    die($result->getMessage());
		}
		//capture debug output
		if((isset($this->SYSTEM["debug"]) && $this->SYSTEM["debug"]))
		{
			$this->pushDebugStack(get_class($this) .  ".save()", "Function", microtime() - $startTime);
		}
		$this->setId($this->db->lastInsertID());
 		return $result;
 	}
 	
 	/**
 	 * Name: Delete
 	 * 
 	 * Does: removes record from db based on the values in the object
 	 */
 	public function delete()
 	{
 		//capture debug timing
		if((isset($this->SYSTEM["debug"]) && $this->SYSTEM["debug"]))
		{
			$startTime = microtime();
		}
		
 		$sql = "delete from ". $this->ORM["tableName"];
 		//sanity check
 		
		if(!array_key_exists("id", $this->ORM["values"]) || $this->ORM["values"]["id"] == "")
		{
			print(get_class($this) . " - id is undefined");
			exit;
		}
 		$sql = $sql . " where id='". str_replace("'", "''", $this->ORM["values"]["id"]) ."'";
 		
 		$result = $this->db->exec($sql);
 		
 		// Always check that result is not an error
		if (\PEAR::isError($result)) {
		    die($result->getMessage());
		}
		$this->clear();
		
		//capture debug output
		if((isset($this->SYSTEM["debug"]) && $this->SYSTEM["debug"]))
		{
			$this->pushDebugStack(get_class($this) .  ".save()", "Function", microtime() - $startTime);
		}
 	}
 	
 	
 	//TODO: create getlist? Function
 	
 	//TODO: create cascade Save Function? (make it queue up queries and send as one)
 	
 	/**
 	 * bulkSave
 	 * 
 	 * Creates bulk insert/update queries to save arrays of like objects
 	 * 
 	 * @param array Model objects to save
 	 */
 	public function bulkSave($items)
 	{
 		$insertSQL = "";
 		$updateSQL = "";
 		$insertCount = 0;
 		$updateCount = 0;
 		$result = array();
 		$table = "";
 		$tempTableKey = "TempTableKey";
 		
 		foreach($items as $item)
 		{
 			//check to make sure the collection is all of the same type
 			if($table == '')
 			{
 				$table = $item->getPhORMTable();
 			}
 			else if($table != $item->getPhORMTable())
 			{
 				die("PhORM Error: Object array in bulkSave not of the same type.");
 			}
 			
	 		if($item->getId() != "")
	 		{
	 			$updateSQL .= $this->generateBulkSaveInsert($updateCount, $item, $tempTableKey);
	 			$updateCount++;
	 		}
	 		//else, if the id is not defined, insert.
	 		else
	 		{
	 			$insertSQL .= $this->generateBulkSaveInsert($insertCount, $item);
	 			$insertCount++;
	 		}
	 	}
	    
 		if($insertCount != 0)
 		{
 			$insertSQL .= ";";
	 		$result["insert"] = $this->db->exec($insertSQL);
	 		// Always check that result is not an error
			if (\PEAR::isError($result)) {
			    die($result->getMessage());
			}
		}
		
		if($updateCount != 0)
		{
			$updateSQL .= ";";
			$updateSQL .= $this->generateBulkUpdateJoin($tempTableKey, $table); 
			$result["update"] = $this->db->exec($updateSQL);
	 		
	 		// Always check that result is not an error
			if (\PEAR::isError($result)) {
			    die($result->getMessage());
			}
		}
 		
 	}
 	
 	/**
 	 * generateBulkSaveInsert
 	 * 
 	 * Generates the bulk save insert statement
 	 * 
 	 * @param int $index index of item/query
 	 * @param object $item Item to save
 	 * @param string $tempTableKey tempTableName to use for update/joins
 	 * 
 	 * @return string Sql statement
 	 */
 	protected function generateBulkSaveInsert($index, $item, $tempTableKey = null)
 	{
 		$sql = '';
 		if($index == 0)
		{
			$sql = $item->generateBulkInsert($tempTableKey) . " ";
		}
		else
		{
			$sql = $sql . "UNION ALL ";
		}

		$sql = $sql . $item->generateBulkSelect($tempTableKey) . " ";
		return $sql;
 	}
 	
 	/**
 	 * generateBulkUpdateJoin
 	 * 
 	 * Generates the bulk update join statement
 	 * 	 
 	 * @param string $tempTableKey tempTableName to use for update/joins
 	 * @param string $itemTable table that the object belongs to 
 	 * @return string Sql statement
 	 */
 	protected function generateBulkUpdateJoin($tempTableKey, $itemTable)
 	{
 		$first = true;
 		$sql = "UPDATE " . $itemTable . " oldTable ";
 		$sql .= "INNER JOIN " . $tempTableKey . " newTable ";
 		$sql .= "   ON oldTable.id = newTable.id";
 		$sql .= "   SET ";
 		foreach($this->ORM["columns"] as $column)
 		{
 			if(!$first)
 			{
 				$sql .= ", ";
 			}
 			if(isset($this->ORM["values"][$column]))
 			{
 				$sql .= "oldTable." . $column . " = " . "newTable." . $column;
 				$first = false;
 			}
 		}
 		$sql .= ";DROP TABLE " . $tempTableKey . ";";
 		return $sql;
 	}
 }
?>
