<?php
/**	This class is part of a PHP framework for web sites by Dr Michael Lopez.
*	The class abstracts details of accessing a database via MySQLi
*	It implements the interface IDatabase
*	Reproduction is permitted for educational purposes
*
*	@author	Dr Michael Lopez
*	@copyright (c) 2015 by Dr Michael Lopez
*	@license creativecommons.org/licenses/by-nc-nd/3.0/nz/ ATTRIBUTION-NONCOMMERCIAL-NO DERIVATIVES
*
*   Usage:
*	1) Query and execute share a common parameter pattern:
*		a) The first parameter is the SQL to run. This may
*		   have place-holder parameters of "?". PDO style
*		   parameters (:name) etc are also allowed, but are
*		   NOT recommended.
*		b) The second parameter is an array of values to match
*		   against the place-holder parameters. if the array has
*		   integer keys, these match the '?' place-holders in
*		   sequence. If it has string keys, the keys are interpreted
*          as named parameters (in PDO style). If this argument is
*		   missing, or an empty array, the unmodified SQL is used.
*		c) The third parameter is optional. If present and set to true,
*		   a two-step process will be used in which the parameterized
*		   query is sent to the server for preparation and then arguments
*		   are sent separately. If omitted, or false, query and execute are
*		   implemented as a one-step process. If an argument list is
*		   supplied, the SQL is modified to reflect the arguments before
*		   sending to the server. This is safe against SQL injection.
*		   There's no need to use a prepared statement if the only motivation
*		   is protection against SQL injection.
*	2) Query returns a result set (an array of associative arrays, one for
*	   each row). Execute returns a count of the number of rows affected,
*
*/
class Database implements IDatabase
{
	// private state data
	private $conn ;
	private $isInTransaction;

	/**	Construct a connection to a database
	*
	*/
	public function __construct ($host, $user, $password, $database, $charset='latin1') {
		$cn = new mysqli($host, $user, $password, $database);
		if ($cn->connect_errno) {
			$this->sqlError('connect');
		}
		$this->conn=$cn;
		$this->isInTransaction=false;
		$this->setCharset($charset);
	}

   /**	Execute SQL
	*
	*	@param	string	$sql 		see getResult function
	*	@param	array	$values		see getResult function
	*	@param	boolean	usePrepared	True to force use of prepared statement
	*	@return integer				Count of the number of rows affected
	*/
    public function execute ($sql, array $values=null, $usePrepared=false){
		if ($usePrepared && $values !==null && is_array($values) && count ($values) > 0) {
			return $this->executePrepared($sql,$values);
		} else {
			$this->getResult($sql, $values);
			return $this->conn->affected_rows;
		}
	}

	/**	Query database for row-set
	*
	*	@param	string	$sql 		see getResult function
	*	@param	array	$values		see getResult function
	*	@param	boolean	usePrepared	True to force use of prepared statement
	*	@return array				A standard row-set [a numerically-indexed array of rows]
	*/
    public function query ($sql, array $values=null, $usePrepared=false) {
		if ($usePrepared && $values !==null && is_array($values) && count ($values) > 0) {
			return $this->queryPrepared($sql,$values);
		} else {
			$result=$this->getResult($sql, $values);
			return $this->getRows($result);
		}
    }

   /**	Get result as row-set
	*
	*	@param	mysqli_result 	A standard mysqli_result
	*	@return array			A standard row-set [a numerically-indexed array of rows]
	*/
	private function getRows ($result) {
		$rows=array();
		while($row = $result->fetch_assoc()){
			$rows[]=$row;
		}
		return $rows;
	}
	/**	Get result from database
	*
	*	@param	string	$sql 			An sql statement that may be parameterised with place-holders
	*	@param	array	$values			(Optional). If present, an array of arguments used to replace the
	*									place-holders in the parameterised SQl statement.
	*	@return mysqli result
	*/
	private function getResult($sql, $values) {
		if ($values !==null && is_array($values) && count ($values) > 0) {
			return $this->queryDirect($sql, $values);
		} elseif ($values !==null && !is_array($values) ) {
			throw new LogicException('Invalid arguments to getResult');
		} else if ($values===null ||  count($values)===0) {
			$result=$this->conn->query ($sql);
			if (!$result) {
				$this->sqlError("query SQL '$sql'");
			}
		} else {
			throw new LogicException('Invalid arguments to call');
		}
		return $result;
	}

	/**	Execute a batch of SQL statements
	*
	*	@param	array	$list 		An array of SQL statements
	*	@return integer				Count of the number of rows affected
	*/
	public function executeBatch ($list){
		$count=0;
		foreach ($list as $sql) {
			$count+=$this->execute($sql);
		}
		return $count;
	}

	/**	Get last insert ID created on connection.
	*
	*	@return	integer	id allocated by database
	*/
	public function getInsertID() {
		return $this->conn->insert_id;
	}

	/**	Close the database connection (Optional)
	*	[Note that the connection will be closed automatically
	*	when the script completes]
	*/
	public function close(){
		if ($this->isInTransaction) {
			throw new DatabaseException('A transaction has been started but not committed');
		}
		$this->conn->close();
	}
	/**	Marks the start of a database transaction
	*
	*	Note that transactions cannot be nested
	*/
	public function beginTransaction(){
		if ($this->isInTransaction) {
			throw new DatabaseException('A transaction has already been started');
		}
		$this->conn->autocommit(false);
		$this->isInTransaction=true;
	}

	/**	Marks the end of a database transaction
	*
	*	All changes since the begin transaction call will be
	*	applied in a single indivisible atomic update
	*/
	public function commitTransaction() {
		if (!$this->isInTransaction) {
			throw new DatabaseException('Cannot commit - not in a transaction');
		}
		$this->conn->commit();
		$this->conn->autocommit(true);
		$this->isInTransaction=false;
	}

	/**	Marks the end of a database transaction
	*
	*	Rolls-back any changes made since the begin transaction call
	*/
	public function rollbackTransaction() {
		if (!$this->isInTransaction) {
			throw new DatabaseException('Cannot rollback - not in a transaction');
		}
		$this->conn->rollback();
		$this->conn->autocommit(true);
		$this->isInTransaction=false;
	}

	/** Sets the current character set of the connection
	*	@param string	character set name (e.g. latin1)
	*/
	public function setCharset($charset) {
		if (!$this->conn->set_charset($charset)) {
			$this->sqlError('set charset');
		}
	}
	/**	Standard MYSQLI prepared statement query
	* 	where all fields are string type
	*
	*	@param 	string	$parameterised sql ... with ? as place-holder
	*	@param	array	$values ... ordered array of strings in same sequence as ?
	*	@return array	rowset
	*/
	private function queryPrepared ($parameterisedSQL,  $arguments) {
		$statement=$this->getPreparedStatement($parameterisedSQL, $arguments);
		$statement->execute();
		$result=$statement->get_result();
		$rows=$this->getRows($result);
		$statement->close();
		return $rows;
	}

	/**	Standard MYSQLI prepared statement execute
	* 	where all fields are string type
	*
	*	@param 	string	$sql ... with ? as placeholder
	*	@param	array	$values ... ordered array of strings in same sequence as ?
	*	@return integer	number of rows affected
	*/
	private function executePrepared ($parameterisedSQL, $arguments) {
		$statement=$this->getPreparedStatement($parameterisedSQL, $arguments);
		$statement->execute();
		$statement->close();
		return $this->conn->affected_rows;
	}

	/**	Standard MYSQLI prepared statement
	*
	*	@param 	string	$sql ... with ? as placeholder
	*	@param	array	$values ... ordered array of strings in same sequence as ?
	*	@return array	statement
	*/
	private function getPreparedStatement ($sql, $values) {
		$statement = $this->conn->prepare ($sql);
		if ($statement===false) {
			$this->sqlError("prepare '$sql'");
		}
		$types = '';
		$arguments=array();
		foreach ($values as $i=>$value) {
			if (!is_int($i)) {
				throw new DatabaseException('Prepared statement must use ? parameters');
			}
			if (is_int($value)) {
				$types.= 'i';
				$arguments[]=$value;
			} elseif (is_float($value)) {
				$types.= 'd';
				$arguments[]=$value;
			} elseif ($value instanceof BinaryField) {
				$types.= 'b';
				$arguments[]=$value->getValue();
			} else {
				$types.= 's';
			}
		}
		$params = array_merge($types, $arguents);
		call_user_func_array(array($statement, 'bind_param'), $params);
		return $statement;
	}
	/**	Substitute arguments for place-holders and run Sql
	*	... arguments with an integer index replace a ? character
	*   ... arguments with a string key (e.g. :name) replace
	*	    matching text
	*	@param $parameterisedSQL string: SQL with placeholders
	*	@param $arguments array: Key-value pairs to substitute
	*	@return	SQL result
	*/
	private function queryDirect($parameterisedSQL, array $arguments) {

		$match=array();
		$replace=array();
		// build substitution list
		foreach ($arguments as $placeholder => $value) {
			$replace[]=$this->sqlValue($value);
			if (is_int($placeholder)) {
				$match[]='/\?/';
			} else {
				$match[]="/$placeholder/";
			}
		}
		$sql=preg_replace($match, $replace, $parameterisedSQL, 1);
		//echo "SQL: $sql<br/>";
		return $this->conn->query ($sql);
	}
	/**	Escapes the string according to the current character set
	*	@param	$fieldValue	string	The value to escape
	*	@return	string	properly escaped value
	*/
	private function escape($fieldValue) {
		return $this->conn->real_escape_string($fieldValue);
	}
	/**	Throws a Database exception
	*	@param	$source string	Operation being attempted
	*/
	private function sqlError($source) {
		throw new DatabaseException('Unable to '.$source.
				', MySQL error ('.
				$this->conn->connect_errno. ') is: '.
				$this->conn->connect_error);
	}
	/**		Get correctly formatted/quoted value for database
	*
	*		@param  mixed:  any supported php value
	*       (all scalar types are supported)
	*       ... also DateTime and BinaryField
	*		... and arrays of above
	*		@return string: properly formatted value for SQL
	*/
	private function sqlValue ($value, $typeHint='s') {
		if (is_null($value)) {
			return 'null';
		} elseif (is_int($value)) {
			return $value;
		} elseif (is_float($value)) {
			return $value;
		} elseif (is_bool($value)) {
			return $value ? 'true':'false';
		} elseif (is_string($value)) {
			$safeValue = $this->escape($value);
			switch ($typeHint) {
				case 'b':
					$hexValue=bin2hex($value);
					return "x'hexValue'";
				case 'd':
					if ($value == (float)$value) {
						return (float)$value;
					} else {
						return "'$safeValue'";
					}
				case 'i':
					if ($value == (int)$value) {
						return (int)$value;
					} else {
						return "'$safeValue'";
					}
				default:
					return "'$safeValue'";
			}
		} elseif (is_array($value)) {
			$inner=array();
			foreach ($value as $cell) {
				$inner[] = sqlValue($cell);
			}
			return implode(', ',$inner);
		} else {
			if ($value instanceof BinaryField) {
				$hexValue=bin2hex($value);
				return "x'{$value->asHex()}'";
			} else if ($value instanceof DateTime) {
				$value=$value->format('Y-m-d H-i-s');
				return "'$value'";
			} else {
				throw new DatabaseException ('Unsupported field type');
			}
		}
	}
}
