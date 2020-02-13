<?php
use TriTan\Csrf\Config;
use TriTan\Database\Database;
use TriTan\Database\Connection;

/**
 * Config
 *
 * @license GPLv3
 *
 * @since       1.0.0
 * @package     Qubus CMS
 * @author      Joshua Parker <josh@joshuaparker.blog>
 */

// Initial Installation Info!
$system = [];
$system['release'] = '{release}';
$system['installed'] = '{datenow}';

/**
 * If set to PROD, errors will be generated in the logs
 * directory (static/tmp/logs/*.txt). If set to DEV, then
 * errors will be displayed on the screen. For security
 * reasons, when made live to the world, this should be
 * set to PROD.
 */
defined('APP_ENV') or define('APP_ENV', 'PROD');

/**
 * Application path.
 */
defined('APP_PATH') or define('APP_PATH', BASE_PATH . 'app' . DS);

/**
 * Must Use Plugins Path.
 */
defined('TTCMS_MU_PLUGIN_DIR') or define('TTCMS_MU_PLUGIN_DIR', BASE_PATH . 'mu-plugins' . DS);

/**
 * Plugins path.
 */
defined('TTCMS_PLUGIN_DIR') or define('TTCMS_PLUGIN_DIR', BASE_PATH . 'plugins' . DS);

/**
 * Set for low ram cache.
 */
defined('TTCMS_FILE_CACHE_LOW_RAM') or define('TTCMS_FILE_CACHE_LOW_RAM', '');

/**
 * Email encoding filter priority.
 */
defined('EAE_FILTER_PRIORITY') or define('EAE_FILTER_PRIORITY', 1000);

/**
 * Instantiate a Liten application
 *
 * You can update
 */
$app = new \Liten\Liten(
    [
    'cookies.lifetime' => '86400',
    'cookies.path'          => '/',
    'cookies.domain'        => '{url}',
    'cookies.secret.key' => '{cookies_secret_key}',
    'private.savepath' => BASE_PATH . 'private' . DS,
    'nodeq.savepath' => BASE_PATH . 'private' . DS . 'nodeq' . DS,
    'cookies.savepath' => BASE_PATH . 'private' . DS . 'cookies' . DS,
    'file.savepath' => BASE_PATH . 'private' . DS . 'files' . DS
    ]
);

/**
 * NodeQ NoSQL details.
 */
defined('TTCMS_NODEQ_PATH') or define('TTCMS_NODEQ_PATH', $app->config('nodeq.savepath'));

/**
 * Log prepared statements for debugging.
 *
 * Log located at private/sites/{site_id}/files/logs/*.txt
 */
defined('LOG_PREPARED_STMT') or define('LOG_PREPARED_STMT', false);

/**
 * Database details
 */
$config = [
    'driver'    => '{driver}',
    'charset'   => '{charset}',
    'collation' => '{collation}',
    'host' => '{hostname}',
    'name' => '{database}',
    'user' => '{username}',
    'pass' => '{password}',
    'port' => '{port}',
    'prefix' => '{prefix}', // Don't change unless you know what you are doing.
    'opts' => [
        \PDO::ATTR_PERSISTENT           => false,
        \PDO::ATTR_ERRMODE              => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE   => \PDO::FETCH_OBJ,
        \PDO::ATTR_EMULATE_PREPARES     => false,
        \PDO::MYSQL_ATTR_INIT_COMMAND   => "SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'"
    ]
];
TriTan\Common\Container::getInstance()->set('config', $config);
$app->inst->singleton('qudb', function () use ($config) {
    $connection = new Connection(
        $config['driver'] . ':host=' . $config['host'] . ';dbname=' . $config['name'],
        $config['user'],
        $config['pass']
    );
    $connection->options($config['opts']);
    TriTan\Common\Container::getInstance()->set('connect', $connection);
    return new Database($connection);
});

/**
 * Set here a random salt which can be used along with a random string to
 * generate the CSRF token.
 */
Config::$SALT = '{nonce_salt}';
/**
 * Set the cookie path ( to which the cookies are saved ).
 *
 * If you run this on the root domain, then set `/`. Otherwise if used on a
 * subdirectory, then enter the directory name
 * (e.g /my-custom-site/ for http://example.com/my-custom-site/)
 */
Config::$COOKIE_PATH = $app->config('cookies.path');
/**
 * Cookies host: enter your host (domain name).
 */
Config::$COOKIE_DOMAIN = $app->config('cookies.domain');
/**
 * Set CSRF token lifetime.
 */
Config::$CSRF_EXPIRE = 3600; // 1 hr

/**
 * Main site
 */
defined('TTCMS_MAINSITE') or define('TTCMS_MAINSITE', '{url}'); //i.e. localhost:8888
defined('TTCMS_MAINSITE_PATH') or define('TTCMS_MAINSITE_PATH', '{path}'); //i.e. /qubus/

/* ************************************************* *
 * Do not edit anything from this point on.          *
 * ************************************************* */
require_once(BASE_PATH . 'settings.php');

/**
 * Run the Liten application
 *
 * This method should be called last. This executes the Liten application
 * and returns the HTTP response to the HTTP client.
 */
$app->run();
