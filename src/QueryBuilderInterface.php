<?php

namespace skillaug\components;

interface QueryBuilderInterface {

    public function __construct($configs = []);

    public function init();

	public function queryAll( string $sql, $params = [] );

	public function queryOne( string $sql, $params = [] );

	public function execute( string $sql, $params = [] );

    public function subQuery();

	public function newInstance();

	public function transaction($transaction_callable, $err_callback = null);

    /**
     * Sets the SELECT part of the query.
     * @param string|array $columns the columns to be selected.
     * Columns can be specified in either a string (e.g. "id, name") or an array (e.g. ['id', 'name']).
     * Columns can be prefixed with table names (e.g. "user.id") and/or contain column aliases (e.g. "user.id AS user_id").
     * A DB expression may also be passed in form of an sub-query.
     *
     * @param string $option [optional] that should be appended to the 'SELECT' keyword. For example, the option 'DISTINCT' can be used.
     *
     * @return $this the query object itself
     * @since 1.0.4
     */
    public function select( $columns, $option = null );

    /**
     * Sets the FROM part, INTO part or UPDATE part of the query, it depend on what's kind of your query (SELECT, INSERT or UPDATE)
     * This method is alias of the table() method, please refer to [[table()]] for details
     * @param string|array $tables the table(s) to be selected from, insert into or update.
     *
     * @see table()
     *
     * @return $this the query object itself
     */
	public function from( $tables );

    /**
     * Sets the FROM part, INTO part or UPDATE part of the query, it depend on what's kind of your query (SELECT, INSERT or UPDATE)
     *
     * Here are some examples:
     *
     * ```php
     * // SELECT * FROM user u, profile;
     * $query = $db->table(['u' => 'user', 'profile']);
     * //or
     * $query = $db->table('user u, profile');
     *
     * // SELECT * FROM (SELECT * FROM user WHERE active = 1) AS active_users;
     * $subQuery = $db->subQuery()->table('user')->where(['active' => 1]);
     *
     * $db->table(['active_users' => $subQuery]);
     * ```
     *
     * @param string|array $tables the table(s) to be selected from, insert into or update. This can be either a string (e.g. `'user'`)
     * or an array (e.g. `['user', 'profile']`) specifying one or several table names.
     * Table names can contain schema prefixes (e.g. `'public.user'`) and/or table aliases (e.g. `'user u'`).
     *
     * When the tables are specified as an array, you may also use the array keys as the table aliases
     * (if a table does not need alias, do not use a string key).
     *
     * Use a Query object to represent a sub-query. In this case, the corresponding array key will be used
     * as the alias for the sub-query.
     *
     *
     * @return $this the query object itself
     */
    public function table( $tables );

    /**
     * Appends an JOIN part to the query.
     *
     * Here are some examples:
     *
     * ```php
     * // SELECT * FROM user JOIN profile p ON user.id = p.user_id;
     * $query = $db->table('user')->join(['profile' => 'p'], ['user.id' => 'p.user_id']);
     * //or
     * $query = $db->table('user')->join('profile p', 'user.id = p.user_id');
     *
     *
     * // SELECT * FROM user JOIN (SELECT post_title FROM posts) AS p ON p.author_id = user.id;
     * $subQuery = $db->subQuery()->select('post_title')->table('posts');
     *
     * $query = $db->table('user')->join(['p' => $subQuery], 'p.author_id = user.id');
     * ```
     *
     * @param string|array $table the table to be joined.
     * This parameter specify with the same rules like $tables parameter of the table() method, @see table() on how to specify this parameter.
     *
     * @param string|array $on the join condition that should appear in the ON part.
     * This can be specified in either a string (e.g. `'p.author_id = user.id'`)
     * or an array (e.g. `['user.id' => 'p.user_id']`, `[['user.id' => 'p.user_id'], ['user.other_id' => 'p.user_other_id']]`).
     *
     * @param string|array $conditions the additional conditions that should appear in the AND part of join clause
     *
     * @return $this the query object itself
     */
	public function join( $table, $on, $conditions = [] );

    /**
     * Appends an INNER JOIN part to the query. it similar to join() method @see join()
     * @param string|array $table the table to be joined.
     * @param string|array $on the join condition that should appear in the ON part.
     * @param string|array $conditions the additional conditions that should appear in the AND part of join clause
     *
     * @return $this the query object itself
     */
	public function innerJoin( $table, $on, $conditions = [] );

    /**
     * Appends an LEFT JOIN part to the query. it similar to join() method @see join()
     * @param string|array $table the table to be joined.
     * @param string|array $on the join condition that should appear in the ON part.
     * @param string|array $conditions the additional conditions that should appear in the AND part of join clause
     *
     * @return $this the query object itself
     */
	public function leftJoin( $table, $on, $conditions = [] );

    /**
     * Appends an RIGHT JOIN part to the query. it similar to join() method @see join()
     * @param string|array $table the table to be joined.
     * @param string|array $on the join condition that should appear in the ON part.
     * @param string|array $conditions the additional conditions that should appear in the AND part of join clause
     *
     * @return $this the query object itself
     */
	public function rightJoin( $table, $on, $conditions = [] );

    /**
     * Appends an FULL OUTER JOIN part to the query. it similar to join() method @see join()
     * @param string|array $table the table to be joined.
     * @param string|array $on the join condition that should appear in the ON part.
     * @param string|array $conditions the additional conditions that should appear in the AND part of join clause
     *
     * @return $this the query object itself
     */
	public function fullJoin( $table, $on, $conditions = [] );

    /**
     * Sets the WHERE part of the query.
     *
     * The `$condition` specified as an array can be in one of the following two formats:
     *
     * - hash format: `['column1' => value1, 'column2' => value2, ...]`
     * - operator format: `[operator, operand1, operand2], [operator, operand1, operand2], ...`
     *
     * A condition in hash format represents the following SQL expression in general:
     * `column1=value1 AND column2=value2 AND ...`. In case when a value is an array,
     * an `IN` expression will be generated. And if a value is `null`, `IS NULL` will be used
     * in the generated expression. Below are some examples:
     *
     * - `['type' => 1, 'status' => 2]` generates `(type = 1) AND (status = 2)`.
     * - `['id' => [1, 2, 3], 'status' => 2]` generates `(id IN (1, 2, 3)) AND (status = 2)`.
     * - `['status' => null]` generates `status IS NULL`.
     *
     * A condition in operator format generates the SQL expression according to the specified operator, which
     * can be one of the following:
     *
     * - **and**: the operands should be concatenated together using `AND`. For example,
     *   `['and', 'id=1', 'id=2']` will generate `id=1 AND id=2`. If an operand is an array,
     *   it will be converted into a string using the rules described here. For example,
     *   `['and', 'type=1', ['or', 'id=1', 'id=2']]` will generate `type=1 AND (id=1 OR id=2)`.
     *   The method will *not* do any quoting or escaping.
     *
     * - **or**: similar to the `and` operator except that the operands are concatenated using `OR`. For example,
     *   `['or', ['type' => [7, 8, 9]], ['id' => [1, 2, 3]]]` will generate `(type IN (7, 8, 9) OR (id IN (1, 2, 3)))`.
     *
     * - **not**: this will take only one operand and build the negation of it by prefixing the query string with `NOT`.
     *   For example `['not', ['attribute' => null]]` will result in the condition `NOT (attribute IS NULL)`.
     *
     * - **between**: operand 1 should be the column name, and operand 2 and 3 should be the
     *   starting and ending values of the range that the column is in.
     *   For example, `['between', 'id', 1, 10]` will generate `id BETWEEN 1 AND 10`.
     *
     * - **not between**: similar to `between` except the `BETWEEN` is replaced with `NOT BETWEEN`
     *   in the generated condition.
     *
     * - **in**: operand 1 should be a column or DB expression, and operand 2 be an array representing
     *   the range of the values that the column or DB expression should be in. For example,
     *   `['in', 'id', [1, 2, 3]]` will generate `id IN (1, 2, 3)`.
     *   The method will properly quote the column name and escape values in the range.
     *
     *   You may also specify a sub-query that is used to get the values for the `IN`-condition:
     *   `['in', 'user_id', $db->subQuery()->select('id')->from('users')->where(['active' => 1])]`
     *
     * - **not in**: similar to the `in` operator except that `IN` is replaced with `NOT IN` in the generated condition.
     *
     * - **exists**: operand 1 is a query object that used to build an `EXISTS` condition. For example
     *   `['exists', $db->subQuery()->select('id')->from('users')->where(['active' => 1])]` will result in the following SQL expression:
     *   `EXISTS (SELECT "id" FROM "users" WHERE "active"=1)`.
     *
     * - **not exists**: similar to the `exists` operator except that `EXISTS` is replaced with `NOT EXISTS` in the generated condition.
     *
     * - Additionally you can specify arbitrary operators as follows: A condition of `['>=', 'id', 10]` will result in the
     *   following SQL expression: `id >= 10`.
     *
     * **Note that this method will override any existing WHERE condition. You might want to use [[andWhere()]] or [[orWhere()]] instead.**
     *
     * @param string|array $condition the conditions that should be put in the WHERE part.
     *
     * @return $this the query object itself
     */
	public function where( $condition );

    /**
     * Appends conditions to WHERE part and concatenated using `OR` to the query. it similar to where() method @see where()

     * @param string|array $condition the conditions that should be put in the WHERE part. Please refer to [[where()]]
     * on how to specify this parameter.
     *
     * @return $this the query object itself
     */
	public function orWhere( $condition );

    /**
     * Appends conditions to WHERE part and concatenated using `AND` to the query. it similar to where() method @see where()

     * @param string|array $condition the conditions that should be put in the WHERE part. Please refer to [[where()]]
     * on how to specify this parameter.
     *
     * @return $this the query object itself
     */
	public function andWhere( $condition );

	public function groupBy( string $column );

	/**
     * Sets the ORDER BY part of the query.
     *
     * @param string|array $columns the columns (and the directions) to be ordered by.
     * Columns can be specified in either a string (e.g. "id ASC, name DESC") or an array
     * (e.g. `['id' => SORT_ASC, 'name' => SORT_DESC]`).
     *
     * @param null $sortType @deprecated
     *
     * @return $this the query object itself
     */
	public function orderBy( $columns, $sortType = null );

    /**
     * Sets the LIMIT part of the query.
     * @param int|null $limit the limit. Use null or negative value to disable limit.
     * @return $this the query object itself
     */
	public function limit( $limit );

    /**
     * Sets the OFFSET part of the query.
     * @param int|null $offset the offset. Use null or negative value to disable offset.
     * @return $this the query object itself
     */
	public function offset( $offset );

    /**
     * Execute and return results of the query has built
     * this method also set the LIMIT clause to 1
     *
     * @return object|mixed the results depends on fetch mode of the PDO configs, return object by default, return false on failure
     */
    public function one();

    /**
     * Execute and return results of the query has built
     *
     * @return array
     */
	public function all();

    /**
     * Dump the query params has pass into the query for debugs
     *
     * @return array
     */
	public function dumpQuery();

    /**
     * Insert a row into the Database.
     *
     * For example,
     * ```php
     * $db->table('user')->insert([
     *     'name' => 'Sam',
     *     'age' => 30,
     * ]);
     * ```
     * @param array $params the binding parameters to be inserted
     *
     * @return bool
     */
	public function insert( array $params = [] );

    /**
     * Insert a row into the Database. Similar to insert() method, except that this method return the last insert id on successful
     *
     * @param array $params the binding parameters to be inserted
     *
     * @return bool|int the last insert id, return false on failure
     */
	public function insertGetLastId( array $params = [] );

    /**
     * Insert row(s) into the Database
     *
     * For example,
     * ```php
     * $db->table('user')->insert([
     *  [
     *     'name' => 'Sam',
     *     'age' => 30,
     *  ],
     *  [
     *     'name' => 'Josh',
     *     'age' => 40,
     *  ]
     * ]);
     * ```
     *
     * @param array $params list of the binding parameters to be inserted
     *
     * @return bool
     */
	public function insertAll( array $params = [] );

    /**
     * Update an existing row in the Database
     *
     * For example,
     *
     * ```php
     * $db->table('user')->where(['id' => 1])->update([
     *     'name' => 'Sam',
     *     'age' => 30,
     * ]);
     * ```
     *
     * @param array $params the binding parameters to be updated
     *
     * @return bool
     */
	public function update( array $params = [] );

    /**
     * Delete existing row(s) in the Database
     *
     * For example,
     *
     * ```php
     * $db->table('user')->where(['id' => 1])->delete();
     * ```
     *
     * @return int the number of row(s) affected
     */
	public function delete();
}