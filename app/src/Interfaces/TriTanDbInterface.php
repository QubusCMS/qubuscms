<?php
namespace TriTan\Interfaces;

interface TriTanDbInterface
{
    public function quote($str);

    /**
     *
     *
     * Taken from StackOverflow: https://stackoverflow.com/a/38049697
     * @param string $query
     * @param array $params
     * @return type
     * @throws Exception
     */
    public function prepare(string $query, array $params);

    /**
     * Retrieve an entire SQL result set from the database (i.e. many rows)
     *
     * Executes an SQL query and returns the entire SQL result.
     *
     * @since 1.0.0
     *
     * @param string $query  SQL query.
     * @param string $output Optional. Any of ARRAY_A | ARRAY_N | OBJECT | _JSON constants.
     *                       With one of the first three, return an array of rows indexed from 0 by SQL result row number.
     *                       Each row is an associative array (column => value, ...), a numerically indexed array (0 => value, ...), or an object. ( ->column = value ), respectively.
     *                       With _JSON, return a JSON array string representative of each row requested.
     *                       Duplicate keys are discarded.
     * @return array|object|null Database query results
     */
    public function getResults(string $query = null, $output = OBJECT);

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
    public function getVar(string $query = null, int $x = 0, int $y = 0);

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
     * @return array Database query result. Array indexed from 0 by SQL result row number.
     */
    public function getCol(string $query = null, int $x = 0);

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
     */
    public function getRow(string $query = null, $output = OBJECT, int $y = 0);

    /**
     * Sets the table prefix for Qubus CMS tables.
     *
     * @since 1.0.0
     *
     * @param string $prefix          Alphanumeric name for the new prefix.
     * @param bool   $set_table_names Optional. Whether the table names, e.g. Database::$post, should be updated or not.
     * @return string|Error Old prefix or Error on error
     */
    public function setPrefix($prefix, $set_table_names = true);

    /**
     * Sets site id.
     *
     * @since 1.0.0
     *
     * @param int $site_id Site id to use.
     * @return int Previous site id.
     */
    public function setSiteId($site_id);

    /**
     * Gets site prefix.
     *
     * @since 1.0.0
     * @param int $site_id Optional.
     * @return string Site prefix.
     */
    public function getSitePrefix($site_id = null);

    /**
     * Returns an array of Qubus tables.
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
     * @param int $site_id (Optional) The site_id to prefix. Default: qudb::site_id
     * @return string Table names.
     */
    public function tables($scope = 'all', $prefix = true, $site_id = 0);
}
