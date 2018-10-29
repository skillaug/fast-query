<?php

namespace skillaug\components;

interface QueryBuilderInterface {

	public function select( $fields );

	public function from( $table );

	public function table( $table );

	public function innerJoin( $table, array $on, $where = [] );

	public function leftJoin( $table, array $on, $where = [] );

	public function rightJoin( $table, array $on, $where = [] );

	public function fullJoin( $table, array $on, $where = [] );

	public function where( array $data );

	public function orWhere( array $data );

	public function andWhere( array $data );

	public function orderBy( $field, $sortType );

	public function limit( $limit );

	public function offset( $offset );

	public function one();

	public function all();

	public function dumpQuery();

	public function explain();

	public function insert( array $params = [] );

	public function insertGetLastId( array $params = [] );

	public function insertAll( array $params = [] );

	public function update( array $params = [] );

	public function updateAll( array $params = [] );

	public function delete();
}