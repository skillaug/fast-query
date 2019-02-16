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

	public $error;

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

    private $logEnable = false;

    private $logInstance = null;

    private $logLevel = LOG_WARNING;


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

	    $dsn  = "mysql:host={$this->configs['host']};";
	    $dsn .= isset($this->configs['database']) ? "dbname={$this->configs['database']};" : null;
	    $dsn .= isset($this->configs['charset']) ? "charset={$this->configs['charset']};" : 'charset=utf8;';
	    $dsn .= isset($this->configs['port']) ? "port={$this->configs['port']};" : 'port=3306;';

		$pdo = new PDO( $dsn, $this->configs['username'], $this->configs['password'] );

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

		if(isset($args['log.enable']))
            $this->logEnable = $args['log.enable'];

		if(isset($args['log.level']))
            $this->logLevel = $args['log.level'];

		if(isset($args['log.instance']))
            $this->logInstance = $args['log.instance'];

		return $this;
	}

	public function queryAll( string $sql, $params = [] )
	{
		if(!is_array($params)) {
			$params = [$params];
		}

		$stmt = $this->pdo->prepare( $sql );

		if($stmt === false) {
            $this->error = $this->pdo->errorInfo();

            if($this->logEnable && $this->logLevel >= LOG_WARNING) {
                $this->log($sql,  ['params' => $params, 'execute_result' => null, 'errorInfo' => $this->error]);
            }
        } else {
            $result = $stmt->execute($params);

            if($result) {
                if($this->logEnable && $this->logLevel >= LOG_DEBUG) {
                    $this->log($sql,  ['params' => $params, 'execute_result' => $result]);
                }

                return $stmt->fetchAll();
            } else {
                $this->error = $stmt->errorInfo();

                if($this->logEnable && $this->logLevel >= LOG_WARNING) {
                    $this->log($sql,  ['params' => $params, 'execute_result' => $result, 'errorInfo' => $this->error]);
                }
            }
        }

        return [];
	}

	public function queryOne( string $sql, $params = [] )
	{
		if(!is_array($params)) {
			$params = [$params];
		}

		$stmt = $this->pdo->prepare( $sql );

        if($stmt === false) {
            $this->error = $this->pdo->errorInfo();

            if($this->logEnable && $this->logLevel >= LOG_WARNING) {
                $this->log($sql,  ['params' => $params, 'execute_result' => null, 'errorInfo' => $this->error]);
            }
        } else {
            $result = $stmt->execute($params);

            if($result) {
                if($this->logEnable && $this->logLevel >= LOG_DEBUG) {
                    $this->log($sql,  ['params' => $params, 'execute_result' => $result]);
                }

                return $stmt->fetch();
            } else {
                $this->error = $stmt->errorInfo();

                if($this->logEnable && $this->logLevel >= LOG_WARNING) {
                    $this->log($sql,  ['params' => $params, 'execute_result' => $result, 'errorInfo' => $this->error]);
                }
            }
        }

        return false;
	}

	public function execute( string $sql, $params = [] )
	{
		if(!is_array($params)) {
			$params = [$params];
		}

        $stmt = $this->pdo->prepare( $sql );

        if($stmt === false) {
            $this->error = $this->pdo->errorInfo();

            if($this->logEnable && $this->logLevel >= LOG_WARNING) {
                $this->log($sql,  ['params' => $params, 'execute_result' => null, 'errorInfo' => $this->error]);
            }
        } else {
            $result = $stmt->execute($params);

            if($result) {
                if($this->logEnable && $this->logLevel >= LOG_DEBUG) {
                    $this->log($sql,  ['params' => $params, 'execute_result' => $result]);
                }

                return true;
            } else {
                $this->error = $stmt->errorInfo();

                if($this->logEnable && $this->logLevel >= LOG_WARNING) {
                    $this->log($sql,  ['params' => $params, 'execute_result' => $result, 'errorInfo' => $this->error]);
                }

                throw new Exception($this->error[2], $this->error[0]);
            }
        }

        return false;
	}

	public function subQuery()
	{
        $subQuery         = new QueryBuilder();
        $subQuery->pdo    = $this->pdo;
        $subQuery->parent = $this;

        return $subQuery;
	}

	public function newInstance()
	{
		$instance         = new QueryBuilder();
		$instance->pdo    = $this->pdo;

		return $instance;
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
			//Rollback the transaction.
			$this->pdo->rollBack();

            if(is_callable($err_callback)) {
                call_user_func($err_callback, $e);
            }
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
		$this->setWhere( ' OR (' . $this->handleWhere( $conditions ) .')' );

		return $this;
	}

	public function andWhere( $conditions ) {
		$this->setWhere( ' AND (' . $this->handleWhere( $conditions ) .')' );

		return $this;
	}

	public function groupBy( string $column ) {
		$this->groupBy = $column;

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

	protected function getQuery() {
		return $this->parseSelectQuery();
	}

	public function one() {
        $this->setLimit( 1 );

        $params = $this->mergeParams();

        return $this->queryOne($this->parseSelectQuery(), $params);
	}

	public function all() {

        $params = $this->mergeParams();

		return $this->queryAll($this->parseSelectQuery(), $params);
	}

	public function dumpQuery() {
	    $result = [];
	    $keys = ['select','table','join','where','groupBy','having','orderBy','limit','offset','params','conditionJoinParams','conditionParams'];
		foreach($keys as $key) {
		    if(!empty($this->$key)) {
                $result[$key] = $this->$key;
            }
        }
        $this->resetQuery();

        return $result;
	}

    protected function selectQuery() {
        if(! empty($this->parent)) {
            $this->parent->params = array_merge($this->parent->params, $this->params);
            $this->parent->conditionParams = array_merge($this->parent->conditionParams, $this->conditionParams);
            $this->parent->conditionJoinParams = array_merge($this->parent->conditionJoinParams, $this->conditionJoinParams);
        }

        return $this->parseSelectQuery();
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
		if(empty($params)) {
			throw new Exception(sprintf(
				'Parameter $params passes into QueryBuilder::insertAll() is invalid, empty %s is given. It must be not empty array.',
				gettype($params)
			));
		}

		$this->params = $params;

		$insert_values = [];
		foreach( $params as $param ) {
			$insert_values = array_merge( $insert_values, array_values( $param ) );
		}

        return $this->execute($this->parseInsertQuery(), $insert_values);
	}

	public function update( array $params = []) {
		if(empty($params)) {
			throw new Exception(sprintf(
				'Parameter $params passes into QueryBuilder::update() is invalid, empty %s is given. It must be not empty array.',
				gettype($params)
			));
		}

        $this->params    = $params;
        $conditionParams = $this->conditionParams;

		return $this->execute( $this->parseUpdateQuery(), array_merge(array_values($params), $conditionParams) );
	}

	public function delete() {
        $params = $this->mergeParams();
        $sql    = $this->parseDeleteQuery();
        $stmt   = $this->pdo->prepare( $sql );

        if($stmt === false) {
            $this->error = $this->pdo->errorInfo();

            if($this->logEnable && $this->logLevel >= LOG_WARNING) {
                $this->log($sql,  ['params' => $params, 'execute_result' => null, 'errorInfo' => $this->error]);
            }
        } else {
            $result = $stmt->execute( $params );

            if( $result ) {

                if($this->logEnable && $this->logLevel >= LOG_DEBUG) {
                    $this->log($sql,  ['params' => $params, 'execute_result' => $result]);
                }

                return $stmt->rowCount();
            } else {
                $this->error = $stmt->errorInfo();

                if($this->logEnable && $this->logLevel >= LOG_WARNING) {
                    $this->log($sql,  ['params' => $params, 'execute_result' => $result, 'errorInfo' => $this->error]);
                }

                throw new Exception( $this->error[2], $this->error[0] );
            }
        }

        return false;
	}


	protected function parseArraySelect( array $data ) {
        $result = [];

        foreach($data as $maybeAlias => $column) {
            if(is_string($maybeAlias)) {
                if($column instanceof $this) {
                    $result[] = '(' . call_user_func([$column, 'selectQuery']) . ") AS {$maybeAlias}";
                } else {
                    $result[] = $this->handleFieldName($maybeAlias).' AS '.$column;
                }
            } else {
                $result[] = $this->handleFieldName($column);
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
                    if($item instanceof $this) {
                        $tables[] = '(' . call_user_func([$item, 'selectQuery']) . ") AS {$tblName}";
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
		$this->orderBy .= ( null !== $this->orderBy ? ',' : null ) . $this->handleFieldName($field)." {$sortType}";
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
			$dataFields[] = "`{$key}`";
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
			$dataFields[] = "`{$key}`=?";
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
        $this->error               = null;
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

	protected function handleOnAndConditions( $conditions ) {
		return $this->multiCondition( $conditions, true, null, 'conditionJoinParams' );
	}

	protected function handleOnConditions( $conditions ) {
        return $this->multiCondition( $conditions, true, 'raw' );
	}

	protected function singleCondition( $data, $conditionMode, $holderProperty = 'conditionParams' ) {

        if($conditionMode === 'raw') {
            return $this->singleConditionRaw($data);
        } else {
            return $this->singleConditionQuestionMark($data, $holderProperty);
        }
	}

	protected function singleConditionQuestionMark( $data, $holderProperty ) {
		$result = [];

        $isArray = is_array($data);

        $count = 0;
		if($isArray) {
            $count = count($data);
        }

		if(!$isArray) {
            $result[] = $data;
        } elseif( $count === 3 && isset($data[0]) ) {
            $isIn   = (is_array( $data[2] ) || $data[2] instanceof $this);
            $isNull   = is_null( $data[2] );
            if( $isIn ) {
                $inVal = $this->handleInOperation($data[2], null, $holderProperty);
            } elseif ( !$isNull ) {
                $this->{$holderProperty}[] = $data[2];
            }

            $result[] = $this->handleFieldName($data[1]) . ' ' . $data[0] . ' ' . ( $isIn ? $inVal : ($isNull ? 'NULL' : '?') );  //right
        } elseif($count === 4 && isset($data[3])) {

            if(!in_array(strtoupper($data[0]), ['BETWEEN', 'NOT BETWEEN'])) {
                throw new \Exception('first value of condition must be "BETWEEN" or "NOT BETWEEN"');
            }
            $result[] = $this->handleFieldName($data[1]). ' ' . $data[0]. ' ? AND ?'; //value 2

            $this->{$holderProperty}[] = $data[2];
            $this->{$holderProperty}[] = $data[3];

        } else {
			if(isset($data[0])) { //exists
                $result[] = strtoupper($data[0]) . ' ' . ($data[1] instanceof $this ? '(' . call_user_func([$data[1], 'selectQuery']) . ')' : $this->multiCondition( $data[1] ));
            } else {
                $items = [];
                foreach( $data as $field => $value ) {
                    $isIn   = (is_array( $value ) || $value instanceof $this);
                    $isNull   = is_null( $value );

                    if( $isIn ) {
                        $inVal = $this->handleInOperation($value, null, $holderProperty);
                    } elseif(!$isNull) {
                        $this->{$holderProperty}[] = $value;
                    }

                    $items[] = $this->handleFieldName($field).' '.( $isIn ? 'IN' : ($isNull ? 'IS' : '=') ).' '.( $isIn ? $inVal : ($isNull ? 'NULL' : '?') );
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
                $result[] = $data[1] instanceof $this ? '(' . call_user_func([$data[1], 'selectQuery']) . ')' : $this->multiCondition( $data[1] ); //operation
            } else {
                foreach( $data as $field => $value ) {
                    $isIn   = is_array( $value ) || $value instanceof $this;
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

	protected function handleInOperation($value, $conditionMode = null, $holderProperty = 'conditionParams') {

        $isRaw = ($conditionMode === 'raw');

	    if($value instanceof $this) {
            $result = call_user_func([$value, 'selectQuery']);
        } else {
            $dataIn = [];

            foreach( (array) $value as $item ) {

                if($isRaw) {
                    $dataIn[] = $item;
                } else {
                    $dataIn[] = '?';
                    $this->{$holderProperty}[] = $item;
                }
            }

            $result = implode( ',', $dataIn );
        }

	    return '(' . $result . ')';
    }

	protected function multiCondition( $data, $isRoot = true, $conditionMode = null, $holderProperty = 'conditionParams') {
        $isGroup = true && ! $isRoot;
        $result  = [];

        $is_single = function() use ( &$result, &$isGroup, $data, $conditionMode, $holderProperty ) {
            $result[] = $this->singleCondition( $data, $conditionMode, $holderProperty );
            $isGroup  = false;
        };

        if(!is_array($data)) {
            $is_single();
        } elseif( isset( $data[0] ) && is_string( $data[0] ) && in_array( trim(strtoupper($data[0])), ['AND', 'OR'] ) ) { // $data is multi conditions
			foreach( $data as $key => $item ) {
				if( $key === 0 ) {  // is 'AND' or 'OR'
					continue;
				}
                $result[] = ( $key > 1 ? strtoupper( $data[0] ) . ' ' : null ) . $this->multiCondition( $item, false, $conditionMode, $holderProperty );
            }
		} elseif( isset( $data[0] ) && is_array( $data[0] ) ) {
			foreach( $data as $key => $item ) {
                $result[] = ( $key > 0 ? 'AND ' : null ) . $this->multiCondition( $item, false, $conditionMode, $holderProperty );
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

    protected function handleFieldName ($fieldName) {
	    $temp = explode('.', $fieldName);

	    if (count($temp) === 2) {
		    return $temp[0].'.`'.$temp[1].'`';
	    } else {
		    return "`{$temp[0]}`";
	    }
    }

	public static function esc_like(string $string) {
		return static::esc($string, '\x25\x5F');
	}

	public static function esc_sql_like(string $string) {
		return static::esc($string, '\x00\x0A\x0D\x1A\x22\x25\x27\x5C\x5F');
	}

	public static function esc_sql(string $string) {
		return static::esc($string, '\x00\x0A\x0D\x1A\x22\x27\x5C');
	}

	public static function esc(string $string, $charlist) {
		if (function_exists('mb_ereg_replace'))
		{
			return mb_ereg_replace('['.$charlist.']', '\\\0', $string);
		} else {
			return preg_replace('~['.$charlist.']~u', '\\\$0', $string);
		}
	}

	private function log($content, $args) {
        if($this->logInstance !== null) {
            $this->logInstance->write($content, $args);
        }
    }
}