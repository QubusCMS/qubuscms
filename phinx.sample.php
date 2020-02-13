<?php
/**
 * Phinx config file.
 *
 * @license GPLv3
 *
 * @since 1.0.0
 * @package Qubus CMS
 * @author Joshua Parker <josh@joshuaparker.blog>
 */

date_default_timezone_set('UTC');

/**
 * If you are installing on a development server such
 * as WAMP, MAMP, XAMPP or AMPPS, you might need to
 * set DB_HOST to 127.0.0.1 instead of localhost.
 */
defined('DB_HOST') or define('DB_HOST', ''); // MySQL server host.
defined('DB_NAME') or define('DB_NAME', ''); // Database name.
defined('DB_USER') or define('DB_USER', ''); // Database username.
defined('DB_PASS') or define('DB_PASS', ''); // Database password.
defined('DB_PORT') or define('DB_PORT', 3306); // Database port.

return [
    "paths" => [
        "migrations" => "app/migrations/mysql" //If using database other than mysql, change mysql to sql
    ],
    "environments" => [
        "default_migration_table" => "qub_migrations", //If you change table_prefix, make sure to change it here as well for consistency.
        "default_database" => "qubus1",
        "qubus1" => [
            "adapter" => "mysql",
            "host" => DB_HOST,
            "name" => DB_NAME,
            "user" => DB_USER,
            "pass" => DB_PASS,
            "table_prefix" => 'qub_',
            "charset" => 'utf8mb4',
            "collation" => 'utf8mb4_unicode_ci',
            "port" => DB_PORT,
            'strict' => false,
            'mainsite' => [
                'url' => '', //i.e. localhost:8888
                'path' => '' //i.e. /qubus/
            ],
            //'unix_socket' => "/Applications/MAMP/tmp/mysql/mysql.sock"
        ],
        /*"qubus2" => [
            "adapter" => "mysql",
            "host" => DB_HOST,
            "name" => DB_NAME,
            "user" => DB_USER,
            "pass" => DB_PASS,
            "table_prefix" => 'qub_1_',
            "exclude" => [
                'permission' => 'permission',
                'role' => 'role',
                'site' => 'site',
                'user' => 'user',
                'usermeta' => 'usermeta'
            ],
            "charset" => 'utf8mb4',
            "collation" => 'utf8mb4_unicode_ci',
            "port" => DB_PORT,
            'strict' => false,
            //'unix_socket' => "/Applications/MAMP/tmp/mysql/mysql.sock"
        ]*/
    ]
];
