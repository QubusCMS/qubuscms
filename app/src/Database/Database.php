<?php
/* ===========================================================================
 * Copyright 2018 Zindex Software
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * ============================================================================ */

namespace TriTan\Database;

use TriTan\Database\SQL\InsertStatement;
use TriTan\Database\SQL\Query as QueryCommand;
use TriTan\Database\SQL\Insert as InsertCommand;
use TriTan\Database\SQL\Update as UpdateCommand;
use TriTan\Interfaces\Database\DatabaseInterface;
use TriTan\Common\Container as c;
use TriTan\Common\Date;
use TriTan\Exception\Exception;
use Cascade\Cascade;
use \PDOException;

/**
 * Whether or not to return as object.
 *
 * @since 1.0.0
 */
define('OBJECT', 'OBJECT');
/**
 * Whether or not to return as an associative array.
 *
 * @since 1.0.0
 */
define('ARRAY_A', 'ARRAY_A');
/**
 * Whether or not to return as a numeric array.
 * @since 1.0.0
 */
define('ARRAY_N', 'ARRAY_N');
/**
 * Whether or not to return as a JSON object.
 * @since 1.0.0
 */
define('_JSON', '_JSON');

final class Database implements DatabaseInterface
{
    /** @var   Connection   Connection instance. */
    protected $connection;

    /** @var    Schema       Schema instance. */
    protected $schema;

    /**
     * PDO configuration.
     *
     * @since 1.0.0
     * @var array
     */
    protected $config = [];

    /**
     * Last result.
     *
     * @since 1.0.0
     * @var string
     */
    public $last_result;

    /**
     * Last insert id.
     *
     * @since 1.0.0
     * @var int
     */
    public $lastInsertId = 0;

    /**
     * Qubus CMS site table prefix.
     *
     * @since 1.0.0
     * @var string
     */
    public $site_prefix = '';

    /**
     * Qubus CMS base table prefix.
     *
     * @since 1.0.0
     * @var string
     */
    public $base_prefix = '';

    /**
     * Qubus CMS table prefix.
     *
     * @since 1.0.0
     * @var string
     */
    public $prefix = '';

    /**
     * Site id.
     *
     * @since 1.0.0
     * @var int
     */
    public $site_id = 0;

    /**
     * List of Qubus CMS site tables.
     *
     * @since 1.0.0
     * @var array
     */
    public $site_tables = [
        'option',
        'plugin',
        'post',
        'postmeta',
        'posttype'
    ];

    /**
     * List if Qubus CMS global tables.
     *
     * @since 1.0.0
     * @var array
     */
    public $global_tables = [
        'permission',
        'role',
        'user',
        'usermeta'
    ];

    /**
     * List of Qubus CMS multisite tables.
     *
     * @since 1.0.0
     * @var array
     */
    public $ms_global_tables = [
        'site'
    ];

    /**
     * Qubus CMS option table.
     *
     * @since 1.0.0
     * @var string
     */
    public $option;

    /**
     * Qubus CMS permission table.
     *
     * @since 1.0.0
     * @var string
     */
    public $permission;

    /**
     * Qubus CMS plugin table.
     *
     * @since 1.0.0
     * @var string
     */
    public $plugin;

    /**
     * Qubus CMS post table.
     *
     * @since 1.0.0
     * @var string
     */
    public $post;

    /**
     * Qubus CMS postmeta table.
     *
     * @since 1.0.0
     * @var string
     */
    public $postmeta;

    /**
     * Qubus CMS posttype table.
     *
     * @since 1.0.0
     * @var string
     */
    public $posttype;

    /**
     * Qubus CMS role table.
     *
     * @since 1.0.0
     * @var string
     */
    public $role;

    /**
     * Qubus CMS site table.
     *
     * @since 1.0.0
     * @var string
     */
    public $site;

    /**
     * Qubus CMS user table.
     *
     * @since 1.0.0
     * @var string
     */
    public $user;

    /**
     * Qubus CMS usermeta table.
     *
     * @since 1.0.0
     * @var string
     */
    public $usermeta;

    /**
     * Constructor
     *
     * @param   Connection $connection Connection instance.
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;

        $this->config = c::getInstance()->get('config');
        $this->site_id = c::getInstance()->get('site_id');

        $this->base_prefix = c::getInstance()->get('config')['prefix'];
        $this->site_prefix = $this->site_id <= 1 ? $this->base_prefix : $this->base_prefix . $this->site_id . '_';
        $this->prefix = $this->site_prefix;
    }

    /**
     * Database connection
     *
     * @return   Connection
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * Returns the query log for this database.
     *
     * @return array
     */
    public function getLog()
    {
        return $this->connection->getLog();
    }

    /**
     * Wrapper for PDO::quote.
     *
     * Taken from StackOverflow: https://stackoverflow.com/a/38049697
     *
     * @param string $str
     * @return string Quoted string.
     */
    public function quote($str)
    {
        if (!is_array($str)) {
            return $this->getConnection()->getPDO()->quote((string) $str);
        } else {
            $str = implode(',', array_map(function ($v) {
                return $this->quote($v);
            }, $str));

            if (empty($str)) {
                return 'NULL';
            }

            return $str;
        }
    }

    /**
     *
     *
     * Taken from StackOverflow: https://stackoverflow.com/a/38049697
     * @param string $query
     * @param array $params
     * @return type
     * @throws Exception
     */
    public function prepare(string $query, $params)
    {
        if (is_null($query)) {
            return;
        }

        if (preg_match_all("/(\?|:)/", $query, $matches) == false) {
            throw new Exception(
                sprintf(
                    'The query argument of %s must have a placeholder.',
                    'Connection::prepare'
                )
            );
        }

        $params = func_get_args();
        array_shift($params);

        $passed_as_array = false;
        if (is_array($params[0]) && count($params) == 1) {
            $passed_as_array = true;
            $params = $params[0];
        }

        foreach ($params as $param) {
            if (!is_scalar($param) && !is_null($param)) {
                throw new PDOException(sprintf('Unsupported value type (%s).', gettype($param)));
            }
        }

        // Count the number of valid placeholders in the query.
        $placeholders = preg_match_all("/(\?|:)/", $query, $matches);
        if (count($params) !== $placeholders) {
            throw new PDOException(
                sprintf(
                    'The query does not contain the correct number of placeholders'
                    . ' (%s) for the number of arguments passed (%s).',
                    $placeholders,
                    count($params)
                )
            );
        }

        $ps = preg_split("/'/is", $query);
        $pieces = [];
        $prev = null;
        foreach ($ps as $p) {
            $lastChar = substr($p, strlen($p) - 1);

            if ($lastChar != "\\") {
                if ($prev === null) {
                    $pieces[] = $p;
                } else {
                    $pieces[] = $prev . "'" . $p;
                    $prev = null;
                }
            } else {
                $prev .= ($prev === null ? '' : "'") . $p;
            }
        }

        $arr = [];
        $indexQuestionMark = -1;
        $matches = [];

        for ($i = 0; $i < count($pieces); $i++) {
            if ($i % 2 !== 0) {
                $arr[] = "'" . $pieces[$i] . "'";
            } else {
                $st = '';
                $s = $pieces[$i];
                while (!empty($s)) {
                    if (preg_match("/(\?|:[A-Z0-9_\-]+)/is", $s, $matches, PREG_OFFSET_CAPTURE)) {
                        $index = $matches[0][1];
                        $st .= substr($s, 0, $index);
                        $key = $matches[0][0];
                        $s = substr($s, $index + strlen($key));

                        if ($key == '?') {
                            $indexQuestionMark++;
                            if (array_key_exists($indexQuestionMark, $params)) {
                                $st .= $this->quote($params[$indexQuestionMark]);
                            } else {
                                throw new PDOException('Wrong params in query at ' . $index);
                            }
                        } else {
                            if (array_key_exists($key, $params)) {
                                $st .= $this->quote($params[$key]);
                            } else {
                                throw new PDOException('Wrong params in query with key ' . $key);
                            }
                        }
                    } else {
                        $st .= $s;
                        $s = null;
                    }
                }
                $arr[] = $st;
            }
        }

        $this->logPreparedStmt(implode('', $arr));

        return implode('', $arr);
    }

    public function queryPrepared($query)
    {
        $sql = $this->getConnection()->getPDO()->query($query);
        $results = $sql->fetchAll();
        $this->last_result = $results;
    }

    /**
     * Retrieve an entire SQL result set from the database (i.e. many rows)
     *
     * Executes an SQL query and returns the entire SQL result.
     *
     * @since 1.0.0
     *
     * @param string|null $query  SQL query.
     * @param string $output Optional. Any of ARRAY_A | ARRAY_N | OBJECT | _JSON constants.
     *                       With one of the first three, return an array of rows indexed from 0 by SQL result row number.
     *                       Each row is an associative array (column => value, ...), a numerically indexed array (0 => value, ...), or an object. ( ->column = value ), respectively.
     *                       With _JSON, return a JSON array string representative of each row requested.
     *                       Duplicate keys are discarded.
     * @return array|object|null Database query results
     */
    public function getResults(string $query = null, $output = OBJECT)
    {
        if ($query) {
            $this->queryPrepared($query);
        } else {
            return null;
        }

        $new_array = [];

        if ($output == OBJECT) {
            return json_decode(json_encode($this->last_result), false);
        } elseif ($output == _JSON) {
            return json_encode($this->last_result); // return as json output
        } elseif ($output == ARRAY_A || $output == ARRAY_N) {
            if ($this->last_result) {
                //$i = 0;
                foreach ((array) $this->last_result as $row) {
                    if ($output == ARRAY_N) {
                        $new_array[] = array_values(get_object_vars($row));
                    } else {
                        $new_array[] = get_object_vars($row);
                    }
                }
            }
            return $new_array;
        }
        return null;
    }

    /**
     * Retrieve one variable from the database.
     *
     * Executes a SQL query and returns the value from the SQL result.
     * If the SQL result contains more than one column and/or more than one
     * row, this function returns the value in the column and row specified.
     * If $query is null, this function returns the value in the specified
     * column and row from the previous SQL result.
     *
     * @since 1.0.0
     *
     * @param string|null $query Optional. SQL query. Defaults to null, use the result from the previous query.
     * @param int         $x     Optional. Column of value to return. Indexed from 0.
     * @param int         $y     Optional. Row of value to return. Indexed from 0.
     * @return string|null Database query result (as string), or null on failure
     */
    public function getVar(string $query = null, int $x = 0, int $y = 0)
    {
        if ($query) {
            $this->queryPrepared($query);
        } else {
            return null;
        }

        // Extract public out of cached results based x,y values
        if (isset($this->last_result[$y])) {
            $values = array_values(get_object_vars($this->last_result[$y]));
        }

        // If there is a value return it else return null
        return (isset($values[$x]) && $values[$x] !== null) ? $values[$x] : null;
    }

    /**
     * Retrieve one column from the database.
     *
     * Executes an SQL query and returns the column from the SQL result.
     * If the SQL result contains more than one column, this function returns the column specified.
     * If $query is null, this function returns the specified column from the previous SQL result.
     *
     * @since 1.0.0
     *
     * @param string|null $query Optional. SQL query. Defaults to previous query.
     * @param int         $x     Optional. Column to return. Indexed from 0.
     * @return array|null Database query result. Array indexed from 0 by SQL result row number.
     */
    public function getCol(string $query = null, int $x = 0)
    {
        $new_array = [];
        if ($query) {
            $this->queryPrepared($query);
        } else {
            return null;
        }

        // Extract the column values
        if (is_array($this->last_result)) {
            $j = count($this->last_result);
            for ($i = 0; $i < $j; $i++) {
                $new_array[$i] = $this->getVar(null, $x, $i);
            }
        }

        return $new_array;
    }

    /**
     * Retrieve one row from the database.
     *
     * Executes an SQL query and returns the row from the SQL result.
     *
     * @since 1.0.0
     *
     * @param string|null $query  SQL query.
     * @param string      $output Optional. The required return type. One of OBJECT, ARRAY_A, or ARRAY_N, which correspond to
     *                            an stdClass object, an associative array, or a numeric array, respectively. Default OBJECT.
     * @param int         $y      Optional. Row to return. Indexed from 0.
     * @return array|object|null|void Database query result in format specified by $output or null on failure
     * @throws PDOException
     */
    public function getRow(string $query = null, $output = OBJECT, int $y = 0)
    {
        if ($query) {
            $this->queryPrepared($query);
        } else {
            return null;
        }

        if (! isset($this->last_result[ $y ])) {
            return null;
        }

        if ($output == OBJECT) {
            // If the output is an object then return object using the row offset..
            return isset($this->last_result[$y]) ? (object) $this->last_result[$y] : null;
        } elseif ($output == ARRAY_A) {
            // If the output is an associative array then return row as such..
            return isset($this->last_result[$y]) ? get_object_vars($this->last_result[$y]) : null;
        } elseif ($output == ARRAY_N) {
            // If the output is an numerical array then return row as such..
            return isset($this->last_result[$y]) ? array_values(get_object_vars($this->last_result[$y])) : null;
        } else {
            // If invalid output type was specified..
            throw new PDOException(" Database::getRow(string query, output type, int offset) -- Output type must be one of: OBJECT, ARRAY_A, ARRAY_N");
        }
    }

    /**
     * Return a Y-m-d H:i:s date format
     *
     * @param string $datetime - An english textual datetime description
     *          now, yesterday, 3 days ago, +1 week
     *          http://php.net/manual/en/function.strtotime.php
     * @return string YYYY-MM-DD HH:II:SS
     */
    public function now($datetime = 'now')
    {
        return (new Date())->{'format'}('Y-m-d H:i:s', $datetime);
    }

    /**
     * Sets the table prefix for Qubus CMS tables.
     *
     * @since 1.0.0
     *
     * @param string $prefix          Alphanumeric name for the new prefix.
     * @param bool   $set_table_names Optional. Whether the table names, e.g. Database::$post, should be updated or not.
     * @return string|Error Old prefix or Error on error
     */
    public function setPrefix($prefix, $set_table_names = true)
    {
        if (is_null($prefix) || empty($prefix)) {
            return new Error('invalid_db_prefix', 'Database prefix cannot be null.');
        }

        if (preg_match('|[^a-z0-9_]|i', $prefix)) {
            return new Error('invalid_db_prefix', 'Invalid database prefix.');
        }

        $old_prefix = '';

        if (isset($this->base_prefix)) {
            $old_prefix = $this->base_prefix;
        }

        $this->base_prefix = $prefix;

        if ($set_table_names) {
            foreach ($this->tables('global') as $table => $prefixed_table) {
                $this->$table = $prefixed_table;
            }

            if (empty($this->site_id)) {
                return $old_prefix;
            }

            $this->site_prefix = $this->getSitePrefix();

            foreach ($this->tables('site') as $table => $prefixed_table) {
                $this->$table = $prefixed_table;
            }
        }
        return $old_prefix;
    }

    /**
     * Sets site id.
     *
     * @since 1.0.0
     *
     * @param int $site_id Site id to use.
     * @return int Previous site id.
     */
    public function setSiteId($site_id)
    {
        $old_site_id = $this->site_id;
        $this->site_id = $site_id;

        $this->site_prefix = $this->getSitePrefix();

        foreach ($this->tables('site') as $table => $prefixed_table) {
            $this->$table = $prefixed_table;
        }

        return $old_site_id;
    }

    /**
     * Gets site prefix.
     *
     * @since 1.0.0
     * @param int $site_id Optional.
     * @return string Site prefix.
     */
    public function getSitePrefix($site_id = null)
    {
        if (null === $site_id) {
            $site_id = $this->site_id;
        }
        $site_id = (int) $site_id;
        if (0 == $site_id || 1 == $site_id) {
            return $this->base_prefix;
        } else {
            return $this->base_prefix . $site_id . '_';
        }
    }

    /**
     * Returns an array of Qubus CMS tables.
     *
     * The scope argument can take one of the following:
     *
     * 'all' - return all the 'global' and 'site' tables.
     * 'site' - returns the site level tables.
     * 'global' - returns global tables.
     * 'ms_global' - returns multisite global tables.
     *
     * @since 1.0.0
     * @param string $scope (Optional) Can be all, site, global or ms_global. Default: all.
     * @param bool $prefix (Optional) Whether to include table prefixes. Default: true.
     * @param int $site_id (Optional) The site_id to prefix. Default: Database::site_id
     * @return string Table names.
     */
    public function tables($scope = 'all', $prefix = true, $site_id = 0)
    {
        $dispatch = [
            'all' => array_merge(
                [
                    $this->global_tables,
                    $this->site_tables
                ],
                [
                    $this->ms_global_tables
                ]
            ),
            'site' => $this->site_tables,
            'global' => array_merge($this->global_tables, $this->ms_global_tables),
            'ms_global' => $this->ms_global_tables
        ];

        $tables = $scope == '' ? $dispatch['all'] : $dispatch[$scope];

        if ($prefix) {
            if (!$site_id) {
                $site_id = $this->site_id;
            }
            $site_prefix = $this->getSitePrefix($site_id);
            $base_prefix = $this->base_prefix;
            foreach ($tables as $k => $table) {
                if (in_array($table, $this->global_tables)) {
                    $tables[$table] = $base_prefix . $table;
                } else {
                    $tables[$table] = $site_prefix . $table;
                }
                unset($tables[$k]);
            }
        }
        return $tables;
    }

    private function logPreparedStmt($log = null)
    {
        if (defined('LOG_PREPARED_STMT') && LOG_PREPARED_STMT === true) {
            Cascade::getLogger('error')->{'error'}(
                sprintf(
                    'SQLQUERY[]: %s',
                    $log
                )
            );
        }
        return false;
    }

    /**
     * Execute a query in order to fetch or to delete records.
     *
     * @param   string|array $tables Table name or an array of tables
     *
     * @return  QueryCommand
     */
    public function from($tables): QueryCommand
    {
        return new QueryCommand($this->connection, $tables);
    }

    /**
     * Insert new records into a table.
     *
     * @param   array $values An array of values.
     *
     * @return  InsertCommand|InsertStatement
     */
    public function insert(array $values): InsertCommand
    {
        return (new InsertCommand($this->connection))->insert($values);
    }

    /**
     * Update records.
     *
     * @param   string $table Table name
     *
     * @return  UpdateCommand
     */
    public function update($table): UpdateCommand
    {
        return new UpdateCommand($this->connection, $table);
    }

    /**
     * The associated schema instance.
     *
     * @return  Schema
     */
    public function schema(): Schema
    {
        if ($this->schema === null) {
            $this->schema = $this->connection->getSchema();
        }

        return $this->schema;
    }

    /**
     * Performs a transaction
     *
     * @param callable $query
     * @param mixed|null $default
     * @return mixed|null
     * @throws \PDOException
     */
    public function transaction(callable $query, $default = null)
    {
        return $this->connection->transaction($query, $this, $default);
    }
}
