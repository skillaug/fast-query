<?php

namespace skillaug\components;

use Exception;
use PDO;

class QueryBuilder implements QueryBuilderInterface {

	public $configs = [];
	/**
	 * @var \PDO
	 */
	public $pdo;

    protected $parent;

	protected $select = null;

	protected $selectOption = null;

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

    public function __construct($configs = []) {
        if(!empty($configs)) {
            $this->configs($configs);
        }
    }

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
		$pdo = @new PDO( "mysql:host={$this->configs['host']};port={$this->configs['port']};dbname={$this->configs['database']}", $this->configs['username'], $this->configs['password'] );

		// set PDO Attributes
		foreach($this->configs['attributes'] as $attrKey => $attrValue) {
			$pdo->setAttribute( $attrKey, $attrValue);
		}

		$this->pdo = $pdo;
        $this->configs = null;

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

	public function subQuery()
	{
        $subQuery         = new QueryBuilder();
        $subQuery->parent = $this;
        $subQuery->pdo    = $this->pdo;

		return $subQuery;
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

	public function select( $columns, $option = null ) {
		if( is_array( $columns ) ) {
            $columns = $this->parseArraySelect($columns);
        }
		if( is_string( $columns ) ) {
            $this->select = $columns;
        }
		if(!empty($option)) {
            $this->selectOption = $option;
        }

		return $this;
	}

	public function from( $tables ) {
        $this->table = $this->handleFrom( $tables );

		return $this;
	}

	public function table( $tables ) {
		return $this->from( $tables );
	}

	public function join( $table, $on, $conditions = [] ) {
		$this->setJoin( 'JOIN', $table, $on, $conditions );

		return $this;
	}

	public function innerJoin( $table, $on, $conditions = [] ) {
		$this->setJoin( 'INNER JOIN', $table, $on, $conditions );

		return $this;
	}

	public function leftJoin( $table, $on, $conditions = [] ) {
		$this->setJoin( 'LEFT JOIN', $table, $on, $conditions );

		return $this;
	}

	public function rightJoin( $table, $on, $conditions = [] ) {
		$this->setJoin( 'RIGHT JOIN', $table, $on, $conditions );

		return $this;
	}

	public function fullJoin( $table, $on, $conditions = [] ) {
		$this->setJoin( 'FULL OUTER JOIN', $table, $on, $conditions );

		return $this;
	}

	public function where( $conditions ) {
        if(null !== $this->where) {
            throw new Exception('The QueryBuilder::where() method just called once for each query');
        }

		$this->setWhere( $this->handleWhere( $conditions ) );

		return $this;
	}

	public function orWhere( $conditions ) {
		$this->setWhere( ' OR ' . $this->handleWhere( $conditions ) );

		return $this;
	}

	public function andWhere( $conditions ) {
		$this->setWhere( ' AND ' . $this->handleWhere( $conditions ) );

		return $this;
	}

	public function orderBy( $columns, $sortType = null ) {

		if(is_array($columns)) {
			foreach($columns as $fieldKey => $fieldItem) {
				$this->setOrderBy( $fieldKey, $fieldItem );
			}
		} else {
			$this->setOrderBy( $columns, $sortType );
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

	public function selectQuery() {
        if(! empty($this->parent)) {
            $this->parent->params = array_merge($this->parent->params, $this->params);
            $this->parent->conditionParams = array_merge($this->parent->conditionParams, $this->conditionParams);
            $this->parent->conditionJoinParams = array_merge($this->parent->conditionJoinParams, $this->conditionJoinParams);
        }

        return $this->parseSelectQuery();
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


	protected function parseArraySelect( array $data ) {
        $result = [];

        foreach($data as $maybeAlias => $column) {
            if(is_string($maybeAlias)) {
                if($column instanceof \Closure) {
                    $result[] = '(' . $column() . ") AS {$maybeAlias}";
                } else {
                    $result[] = "{$column} AS {$maybeAlias}";
                }
            } else {
                $result[] = $column;
            }
        }
		return implode(',', $result);
	}

	protected function handleFrom( $data ) {
        if(empty($data)) {
            throw new Exception('Table (From) value can not be empty');
        }

		if( is_array( $data ) ) {
			foreach( $data as $tblName => $item ) {
				if(is_int($tblName)) {
					$tables[] = $item;
				} else {
                    if($item instanceof \Closure) {
                        $tables[] = '(' . $item() . ") AS {$tblName}";
                    } else {
                        $tables[] = "{$tblName} {$item}";
                    }
				}
			}

            return implode(', ',$tables);

		} elseif(is_string($data)) {
			return $data;
		} else {
		    throw new Exception('Table (From) value must be string or array');
        }
	}

	protected function setJoin( $type, $table, $on, $conditions = [] ) {
        $table = $this->handleFrom($table);

		$this->join[] = "{$type} {$table} ON " . $this->handleOnConditions( $on ) . ( ! empty( $conditions ) ? " AND " . $this->handleWhere( $conditions ) : null );
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
		$query_str = '';
		foreach( $this->selectBase() as $stmt => $stmtItem ) {
			if($stmt === 'JOIN') {
				foreach( $stmtItem as $item ) {
					$query_str .= "{$item}\n";
				}
			} elseif ($stmt === 'SELECT') {
                $query_str .= "{$stmt}".($this->selectOption ? " {$this->selectOption}" : null)." {$stmtItem}\n";
            } elseif( is_array( $stmtItem ) ) {
				foreach( $stmtItem as $item ) {
					$query_str .= "{$stmt} {$item}\n";
				}
			} elseif( ! empty( $stmtItem ) ) {
				$query_str .= "{$stmt} {$stmtItem}\n";
			}
		}
        $this->resetQuery();

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
        $this->select              = null;
        $this->selectOption        = null;
        $this->table               = null;
        $this->join                = [];
        $this->where               = null;
        $this->groupBy             = null;
        $this->having              = null;
        $this->orderBy             = null;
        $this->limit               = null;
        $this->offset              = null;
        $this->params              = [];
        $this->conditionParams     = [];
        $this->conditionJoinParams = [];
	}

	protected function handleWhere( $conditions ) {
        return $this->multiCondition( $conditions );
	}

	protected function handleOnConditions( $conditions ) {
        return $this->multiCondition( $conditions, true, 'raw' );
	}

	protected function singleCondition( $data, $conditionMode ) {

        if($conditionMode === 'raw') {
            return $this->singleConditionRaw($data);
        } else {
            return $this->singleConditionQuestionMark($data);
        }
	}

	protected function singleConditionQuestionMark( $data ) {
		$result = [];

        $isArray = is_array($data);

        $count = 0;
		if($isArray) {
            $count = count($data);
        }

		if(!$isArray) {
            $result[] = $data;
        } elseif( $count === 3 && isset($data[0]) ) {
            $isIn   = is_array( $data[2] ) || is_callable($data[2]);
            $isNull   = is_null( $data[2] );
            if( $isIn ) {
                $inVal = $this->handleInOperation($data[2]);
            } else {
                $this->conditionParams[] = $data[2];
            }

            $result[] = $data[1] . ' ' . $data[0] . ' ' . ( $isIn ? $inVal : ($isNull ? 'NULL' : '?') );  //right
        } elseif($count === 4 && isset($data[3])) {

            if(!in_array(strtoupper($data[0]), ['BETWEEN', 'NOT BETWEEN'])) {
                throw new \Exception('first value of condition must be "BETWEEN" or "NOT BETWEEN"');
            }
            $result[] = $data[1]. ' ' . $data[0]. ' ? AND ?'; //value 2

            $this->conditionParams[] = $data[2];
            $this->conditionParams[] = $data[3];

        } else {
			if(isset($data[0])) { //exists
                $result[] = strtoupper($data[0]) . ' ' . ($data[1] instanceof \Closure ? '(' . call_user_func($data[1]) . ')' : $this->multiCondition( $data[1] ));
            } else {
                $items = [];
                foreach( $data as $field => $value ) {
                    $isIn   = is_array( $value ) || $value instanceof \Closure;
                    $isNull   = is_null( $value );

                    if( $isIn ) {
                        $inVal = $this->handleInOperation($value);
                    } else {
                        $this->conditionParams[] = $value;
                    }

                    $items[] = $field.' '.( $isIn ? 'IN' : ($isNull ? 'IS' : '=') ).' '.( $isIn ? $inVal : ($isNull ? 'NULL' : '?') );
                }

                $result[] = implode( ' AND ', $items );
            }
		}

		return implode( ' ', $result );
	}

	protected function singleConditionRaw( $data ) {
		$result = [];

		if(!is_array($data)) {
            $result[] = $data;
        } elseif(isset($data[3])) {

            if(strtolower($data[0]) !== 'between') {
                throw new \Exception('first value of condition must be "between"');
            }

            $result[] = $data[1]; //left
            $result[] = $data[0]; //between
            $result[] = $data[2]; //value 1
            $result[] = 'AND'; //and
            $result[] = $data[3]; //value 2

        } elseif( isset($data[2]) ) {
			$result[] = $data[1]; //left
			$result[] = $data[0]; //operation

            $isIn   = is_array( $data[2] ) || is_callable($data[2]);
            if( $isIn ) {
                $inVal = $this->handleInOperation($data[2], 'raw');
            }

            $result[] = ( $isIn ? $inVal : $data[2] );  //right
        } else {
			if(isset($data[0])) {
                $result[] = strtoupper($data[0]); // 'exists' operation
                $result[] = $data[1] instanceof \Closure ? '(' . call_user_func($data[1]) . ')' : $this->multiCondition( $data[1] ); //operation
            } else {
                foreach( $data as $field => $value ) {
                    $isIn   = is_array( $value ) || $value instanceof \Closure;
                    if( $isIn ) {
                        $inVal = $this->handleInOperation($value, 'raw');
                    }

                    $result[] = $field; //left
                    $result[] = ( $isIn ? 'IN' : '=' ); //operation
                    $result[] = ( $isIn ? $inVal : $value );  //right
                }
            }
		}

		return implode( ' ', $result );
	}

	protected function handleInOperation($value, $conditionMode = null) {

        $isRaw = ($conditionMode === 'raw');

	    if($value instanceof \Closure) {
            $result = call_user_func($value);
        } else {
            $dataIn = [];

            foreach( (array) $value as $item ) {

                if($isRaw) {
                    $dataIn[] = $item;
                } else {
                    $dataIn[] = '?';
                    $this->conditionParams[] = $item;
                }
            }

            $result = implode( ',', $dataIn );
        }

	    return '(' . $result . ')';
    }

	protected function multiCondition( $data, $isRoot = true, $conditionMode = null) {
        $isGroup = true && ! $isRoot;
        $result  = [];

        $is_single = function() use ( &$result, $data, &$isGroup, $conditionMode ) {
            $result[] = $this->singleCondition( $data, $conditionMode );
            $isGroup  = false;
        };

        if(!is_array($data)) {
            $is_single();
        } elseif( isset( $data[0] ) && is_string( $data[0] ) && in_array( trim(strtoupper($data[0])), ['AND', 'OR'] ) ) { // $data is multi conditions
			foreach( $data as $key => $item ) {
				if( $key === 0 ) {  // is 'AND' or 'OR'
					continue;
				}
                $result[] = ( $key > 1 ? strtoupper( $data[0] ) . ' ' : null ) . $this->multiCondition( $item, false, $conditionMode );
            }
		} elseif( isset( $data[0] ) && is_array( $data[0] ) ) {
			foreach( $data as $key => $item ) {
                $result[] = ( $key > 0 ? 'AND ' : null ) . $this->multiCondition( $item, false, $conditionMode );
            }
        } else {
            $is_single();
        }

		return ($isGroup ? '(' : null) . implode( ' ', $result ) . ($isGroup ? ')' : null);
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