<?php

namespace skillaug\components;

use Exception;
use PDO;

class QueryBuilder implements QueryBuilderInterface {

	public $configs = [
		'host' => 'localhost',
		'port' => '3306',
		'username' => 'root',
		'password' => '',
	];
	/**
	 * @var \PDO
	 */
	public $pdo;

	protected $select = null;

	protected $table = null;

	protected $join = [];

	protected $where = null;

	protected $groupBy = null;

	protected $having = null;

	protected $orderBy = null;

	protected $limit = null;

	protected $offset = null;

	protected $params = [];

    protected $conditionJoinParams = [];

    protected $conditionParams = [];

	public function init() {

		// set default PDO Attributes config
		if(empty($this->configs['attributes'])) {

			$this->configs['attributes'] = [];

			if(empty($this->configs['attributes'][PDO::ATTR_DEFAULT_FETCH_MODE])) {
				$this->configs['attributes'][PDO::ATTR_DEFAULT_FETCH_MODE] = PDO::FETCH_ASSOC;
			}

			if(empty($this->configs['attributes'][PDO::ATTR_ERRMODE])) {
				$this->configs['attributes'][PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;
			}
		}

		//init PDO
		$pdo = new PDO( "mysql:host={$this->configs['host']};port={$this->configs['port']};dbname={$this->configs['database']}", $this->configs['username'], $this->configs['password'] );

		// set PDO Attributes
		foreach($this->configs['attributes'] as $attrKey => $attrValue) {
			$pdo->setAttribute( $attrKey, $attrValue);
		}

		$this->pdo = $pdo;
		return $this;
	}

	public function configs ($args = []) {
		if(isset($args['host']))
			$this->configs['host'] = $args['host'];

		if(isset($args['port']))
			$this->configs['port'] = $args['port'];

		if(isset($args['username']))
			$this->configs['username'] = $args['username'];

		if(isset($args['password']))
			$this->configs['password'] = $args['password'];

		if(isset($args['database']))
			$this->configs['database'] = $args['database'];

		if(isset($args['attributes']))
			$this->configs['attributes'] = $args['attributes'];

		return $this;
	}

	public function query( $sql, array $params = [] )
	{
		$stmt = $this->pdo->prepare( $sql );
		$stmt->execute($params);

		return $stmt->fetchAll();
	}

	public function transaction($transaction_callable, $err_callback = null) {
		$this->pdo->beginTransaction();

		try{

			if(is_callable($transaction_callable)) {
				call_user_func($transaction_callable);
			}

			//We've got this far without an exception, so commit the changes.
			$this->pdo->commit();

		}
			//Our catch block will handle any exceptions that are thrown.
		catch(Exception $e){

			if(is_callable($err_callback)) {
				call_user_func($err_callback, $e);
			}

			//Rollback the transaction.
			$this->pdo->rollBack();
		}
	}

	public function select( $fields = null ) {
		if( is_array( $fields ) )
			$fields = implode( ',', $fields );

		if( is_string( $fields ) ) {
			$this->setSelect( $fields );
		}

		return $this;
	}

	public function from( $table ) {
		$this->setFrom( $table );

		return $this;
	}

	public function table( $table ) {
		return $this->from( $table );
	}

	public function innerJoin( $table, array $on, $where = [] ) {
		$this->setJoin( 'INNER JOIN', $table, $on, $where );

		return $this;
	}

	public function leftJoin( $table, array $on, $where = [] ) {
		$this->setJoin( 'LEFT JOIN', $table, $on, $where );

		return $this;
	}

	public function rightJoin( $table, array $on, $where = [] ) {
		$this->setJoin( 'RIGHT JOIN', $table, $on, $where );

		return $this;
	}

	public function fullJoin( $table, array $on, $where = [] ) {
		$this->setJoin( 'FULL OUTER JOIN', $table, $on, $where );

		return $this;
	}

	public function where( $conditions ) {
		$this->setWhere( $this->handleWhere( $conditions ) );

		return $this;
	}

	public function orWhere( array $conditions ) {
		$this->setWhere( ' OR ' . $this->handleWhere( $conditions ) );

		return $this;
	}

	public function andWhere( array $conditions ) {
		$this->setWhere( ' AND ' . $this->handleWhere( $conditions ) );

		return $this;
	}

	public function orderBy( $field, $sortType = null ) {

		if(is_array($field)) {
			foreach($field as $fieldKey => $fieldItem) {
				$this->setOrderBy( $fieldKey, $fieldItem );
			}
		} else {
			$this->setOrderBy( $field, $sortType );
		}

		return $this;
	}

	public function limit( $limit ) {
		$this->setLimit( (int) $limit );

		return $this;
	}

	public function offset( $offset ) {
		$this->setOffset( $offset );

		return $this;
	}

	public function getQuery() {
		return $this->parseSelectQuery();
	}

	public function one() {
	    $params = $this->mergeParams();
		$this->setLimit(1);

		$stmt = $this->pdo->prepare( $this->parseSelectQuery() );
		$stmt->execute($params);

		return $stmt->fetch();
	}

	public function all() {
	    $params = $this->mergeParams();
        $stmt = $this->pdo->prepare( $this->parseSelectQuery() );
		$stmt->execute($params);

		return $stmt->fetchAll();
	}

	public function dumpQuery() {
	    $result = [];
	    $keys = ['select','table','join','where','groupBy','having','orderBy','limit','offset','params','conditionJoinParams','conditionParams'];
		foreach($keys as $key) {
		    if(!empty($this->$key)) {
                $result[$key] = $this->$key;
            }
        }

        return $result;
	}

	public function explain() {
	    $params = $this->mergeParams();
		$stmt = $this->pdo->prepare( 'explain '. $this->parseSelectQuery() );
		$stmt->execute($params);
		return $stmt->fetchAll();
	}

	public function insert( array $params = [] ) {
		return $this->insertAll( [$params] );
	}

	public function insertGetLastId( array $params = [] ) {
		if($this->insertAll( [$params] )) {
			return $this->pdo->lastInsertId();
		}

		return false;
	}

	public function insertAll( array $params = [] ) {
		$this->params = $params;

		$stmt = $this->pdo->prepare( $this->parseInsertQuery() );

		$insert_values = [];
		foreach( $params as $param ) {
			$insert_values = array_merge( $insert_values, array_values( $param ) );
		}

		return $stmt->execute( $insert_values );
	}

	public function update( array $params = []) {
        $this->params    = $params;
        $conditionParams = $this->conditionParams;

        $stmt = $this->pdo->prepare( $this->parseUpdateQuery() );

        return $stmt->execute( array_merge(array_values($params), $conditionParams) );
	}

	public function delete() {
	    $params = $this->mergeParams();
		$stmt = $this->pdo->prepare( $this->parseDeleteQuery() );
		$stmt->execute($params);
        return $stmt->rowCount();
	}


	protected function setSelect( string $data ) {
		$this->select = $data;
	}

	protected function setFrom( $data ) {
		if( is_array( $data ) ) {
			foreach( $data as $tblName => $item ) {
				if(is_int($tblName)) {
					$table[] = $item;
				} else {
					$table[] = "{$tblName} {$item}";
				}
			}

			$this->table = implode(', ',$table);

		} else {
			$this->table = $data;
		}
	}

	protected function setJoin( $type, $data, $on, $where = [] ) {
		if( is_array( $data ) ) {
			foreach( $data as $tblName => $item ) {
				$table = "{$tblName} {$item}";
			}
		} else {
			$table = $data;
		}
		$this->join[] = "{$type} {$table} ON " . $this->handleJoin( $on ) . ( ! empty( $where ) ? " AND " . $this->handleWhere( $where ) : null );
	}

	protected function setWhere( string $data ) {
		$this->where .= $data;
	}

	protected function setOrderBy( $field, $sortType ) {
		$this->orderBy .= ( null !== $this->orderBy ? ',' : null ) . "{$field} {$sortType}";
	}

	protected function setLimit( $limit ) {
		$this->limit = $limit;
	}

	protected function setOffset( $offset ) {
		$this->offset = $offset;
	}

	protected function selectBase() {
		return [
			'SELECT'     => (!empty($this->select) ? $this->select : '*'),
			'FROM'       => $this->table,
			'JOIN'       => $this->join,
			'WHERE'      => $this->where,
			'GROUP BY'   => $this->groupBy,
			'HAVING'     => $this->having,
			'ORDER BY'   => $this->orderBy,
			'LIMIT'      => $this->limit,
			'OFFSET'     => $this->offset,
		];
	}

	protected function parseSelectQuery() {
		$query = $this->selectBase();
		$this->resetQuery();

		$query_str = '';
		foreach( $query as $stmt => $stmtItem ) {
			if($stmt === 'JOIN') {
				foreach( $stmtItem as $item ) {
					$query_str .= "{$item}\n";
				}
			} elseif( is_array( $stmtItem ) ) {
				foreach( $stmtItem as $item ) {
					$query_str .= "{$stmt} {$item}\n";
				}
			} elseif( ! empty( $stmtItem ) ) {
				$query_str .= "{$stmt} {$stmtItem}\n";
			}
		}

		return $query_str;
	}

	protected function parseInsertQuery() {

		$dataFields = [];
		foreach( $this->params[0] as $key => $param ) {
			$dataFields[] = $key;
		}

		$question_marks = [];
		foreach( $this->params as $d ) {
			$question_marks[] = '(' . $this->piq_placeholders( '?', sizeof( $d ) ) . ')';
		}

		$sql = "INSERT INTO {$this->table} (" . implode( ",", $dataFields ) . ") VALUES " . implode( ',', $question_marks );

		$this->resetQuery();

		return $sql;
	}

	protected function parseUpdateQuery() {

		$dataFields = [];
		foreach( $this->params as $key => $param ) {
			$dataFields[] = "{$key}=?";
		}

		$sql = "UPDATE {$this->table} SET " . implode( ",", $dataFields ) . " WHERE {$this->where}";

		$this->resetQuery();

		return $sql;
	}

	protected function parseDeleteQuery() {

		$sql = "DELETE FROM {$this->table} WHERE {$this->where}";

		$this->resetQuery();

		return $sql;
	}

	protected function resetQuery() {
		$this->select    = null;
		$this->table     = null;
		$this->join      = [];
		$this->where     = null;
		$this->groupBy   = null;
		$this->having    = null;
		$this->orderBy   = null;
		$this->limit     = null;
		$this->offset    = null;
		$this->params    = [];
        $this->conditionParams     = [];
        $this->conditionJoinParams = [];
	}

	protected function handleWhere( $data ) {

	    if(is_array($data)) {
            return $this->multiCondition( $data );
        }

        return $data;
	}

	protected function handleJoin( array $data ) {
		$result = [];
		if( count( $data ) === 3 ) {
			$result[] = $data[1]; //left
			$result[] = $data[0]; //operation
			$result[] = $data[2];  //right
		} elseif( count( $data ) === 1 ) {
			foreach( $data as $field => $value ) {
				$result[] = $field; //left
				$result[] = '='; //operation
				$result[] = $value;  //right
			}
		}

		return implode( ' ', $result );
	}

	protected function singleCondition( array $data ) {
		$result = [];
        if(isset($data[3])) {

            if(strtolower($data[0]) !== 'between') {
                throw new \Exception('first value of condition must be "between"');
            }

            $result[] = $data[1]; //left
            $result[] = $data[0]; //between
            $result[] = '?'; //value 1
            $result[] = 'AND'; //and
            $result[] = '?'; //value 2

            $this->conditionParams[] = $data[2];
            $this->conditionParams[] = $data[3];

        } elseif( isset($data[2]) ) {
			$result[] = $data[1]; //left
			$result[] = $data[0]; //operation
			$result[] = '?';  //right

            $this->conditionParams[] = $data[2];

        } else {
			foreach( $data as $field => $value ) {
				$isIn   = is_array( $value );
				$dataIn = [];
				if( $isIn ) {
					foreach( $value as $item ) {
						$dataIn[] = '?';
                        $this->conditionParams[] = $item;
					}
				} else {
                    $this->conditionParams[] = $value;
                }

				$dataIn = '(' . implode( ',', $dataIn ) . ')';

				$result[] = $field; //left
				$result[] = ( $isIn ? 'IN' : '=' ); //operation
				$result[] = ( $isIn ? $dataIn : '?' );  //right
			}
		}

		return implode( ' ', $result );
	}

	protected function multiCondition( array $data, $operation = null ) {
		$result = [];
		if( isset( $data[0] ) && is_string( $data[0] ) && is_array( $data[1] ) ) { // $data is multi conditions
			foreach( $data as $key => $item ) {
				if( $key === 0 ) {
					continue;
				} // is 'AND' or 'OR'
				if( ( isset( $item[0] ) && is_string( $item[0] ) && is_array( $item[1] ) ) ) { // $item is multi conditions
					$result[] = ( $key > 1 ? strtoupper( $data[0] ) : null ) . ' (' . $this->multiCondition( $item, $data[0] ) . ')';
				} else {
					$result[] = ( $key > 1 ? strtoupper( $data[0] ) : null ) . ' ' . $this->singleCondition( $item );

				}
			}
		} elseif( isset( $data[0] ) && is_array( $data[0] ) ) {
			foreach( $data as $key => $item ) {
                $result[] = ( $key > 0 ? 'AND' : null ) . ' (' . $this->multiCondition( $item, $data[0] ) . ')';
            }
        } else {
            $result[] = $this->singleCondition( $data );
        }

		return implode( ' ', $result );
	}

	private function piq_placeholders( $text, $count = 0, $separator = "," ) {
		$result = array ();
		if( $count > 0 ) {
			for( $x = 0; $x < $count; $x ++ ) {
				$result[] = $text;
			}
		}

		return implode( $separator, $result );
	}

    protected function mergeParams () {
        return array_merge($this->conditionJoinParams, $this->conditionParams, $this->params);
    }
}