<?php
/**
 * Settings
 *
 * @license GPLv3
 *
 * @since       1.0.0
 * @package     Qubus CMS
 * @author      Joshua Parker <josh@joshuaparker.blog>
 */
use TriTan\Common\Container as c;
use TriTan\Common\Context\HelperContext;
use TriTan\Common\FileSystem;
use Qubus\Hooks\ActionFilterHook;
use Cascade\Cascade;

if (defined('TTCMS_MEMORY_LIMIT')) {
    ini_set('memory_limit', TTCMS_MEMORY_LIMIT);
}

$qudb = $app->qudb;

/**
 * Set the current site based on HTTP_HOST.
 */
try {
    $current_site_id = $qudb->getRow(
        $qudb->prepare(
            "SELECT site_id FROM {$qudb->base_prefix}site WHERE site_domain = ? AND site_path = ?",
            [
                $app->req->server['HTTP_HOST'],
                str_replace('index.php', '', $app->req->server['PHP_SELF'])
            ]
        )
    );
    $site_id = (int) $current_site_id->site_id;
    /**
     * Set site id.
     */
    c::getInstance()->set('site_id', $site_id);
} catch (\PDOException $ex) {
    Cascade::getLogger('error')->error(sprintf('CURRENT_SITEID[%s]: %s', $ex->getCode(), $ex->getMessage()));
}

/**
 * Set table prefix.
 */
$tbl_prefix = $site_id <= 1 ? c::getInstance()->get('config')['prefix'] : c::getInstance()->get('config')['prefix'] . "{$site_id}_";
c::getInstance()->set('tbl_prefix', $tbl_prefix);

/**
 * Site directory.
 */
c::getInstance()->set('sites_dir', BASE_PATH . 'private' . DS . 'sites' . DS);

/**
 * Absolute site path.
 */
c::getInstance()->set('site_path', c::getInstance()->get('sites_dir') . $site_id . DS);

/**
 * Cache path.
 */
c::getInstance()->set('cache_path', c::getInstance()->get('site_path') . 'files' . DS . 'cache' . DS);

/**
 * Themes directory.
 */
c::getInstance()->set('theme_dir', c::getInstance()->get('site_path') . 'themes' . DS);

/**
 * Set helper context container.
 */
$helper = new TriTan\Common\Context\HelperContext();
c::getInstance()->set('context', $helper);

/**
 * Set meta data container.
 */
$meta = new TriTan\Common\MetaData($qudb, new HelperContext());
c::getInstance()->set('meta', $meta);

/**
 * Set usermeta data container.
 */
$usermeta = new \TriTan\Common\User\UserMetaData($meta, new TriTan\Common\Utils(ActionFilterHook::getInstance()));
c::getInstance()->set('usermeta', $usermeta);

/**
 * Set postmeta data container.
 */
$postmeta = new TriTan\Common\Post\PostMetaData($meta, new TriTan\Common\Utils(ActionFilterHook::getInstance()));
c::getInstance()->set('postmeta', $postmeta);

/**
 * Set option data container.
 */
$option = new \TriTan\Common\Options\Options(
    new TriTan\Common\Options\OptionsMapper(
        $qudb,
        new HelperContext()
    )
);
c::getInstance()->set('option', $option);

/**
 * Require a functions file
 *
 * A functions file may include any dependency injections
 * or preliminary functions for your application.
 */
require(APP_PATH . 'functions.php');

ActionFilterHook::getInstance()->{'doAction'}('update_user_init');
ActionFilterHook::getInstance()->{'doAction'}('update_post_init');

/**
 * Fires before the site's theme is loaded.
 *
 * @since 1.0.0
 */
ActionFilterHook::getInstance()->{'doAction'}('before_setup_theme');

/**
 * The name of the site's specific theme.
 */
c::getInstance()->set('active_theme', c::getInstance()->get('option')->{'read'}('current_site_theme'));

/**
 * Absolute themes path.
 */
c::getInstance()->set('theme_path', c::getInstance()->get('theme_dir') . c::getInstance()->get('active_theme') . DS);

/**
 * Sets up the Fenom global variable.
 */
$app->inst->singleton('fenom', function () {
    $fenom = new Fenom(new Fenom\Provider(c::getInstance()->get('theme_path') . 'views' . DS));
    $fenom->setCompileDir(c::getInstance()->get('cache_path'));
    c::getInstance()->get('option')->{'read'}('site_cache') == 0 ? $fenom->setOptions(Fenom::DISABLE_CACHE) : '';
    return $fenom;
});

if ((new FileSystem(ActionFilterHook::getInstance()))->exists(c::getInstance()->get('theme_path') . 'views' . DS, false)) {
    $templates = [
      'main' => APP_PATH . 'views' . DS,
      'theme' => c::getInstance()->get('theme_path') . 'views' . DS,
      'plugin' => TTCMS_PLUGIN_DIR
    ];
} else {
    $templates = [
      'main' => APP_PATH . 'views' . DS,
      'plugin' => TTCMS_PLUGIN_DIR
    ];
}

/**
 * Sets up the Foil global variable.
 */
$app->inst->singleton('foil', function () use ($app, $templates) {
    $engine = Foil\engine([
        'folders' => $templates,
        'autoescape' => false
    ]);
    $engine->useData(['app' => $app, 'current_user_id' => get_current_user_id()]);
    return $engine;
});

/**
 * Autoload Sitewide Must-Use plugins
 *
 * Must-Use are snippets of code that must be
 * loaded every time each site is loaded.
 */
foreach (ttcms_get_mu_plugins() as $mu_plugin) {
    include_once($mu_plugin);
    /**
     * Fires once a single must-use plugin is loaded.
     *
     * @since 1.0.0
     * @param $string $mu_plugin Full path to the plugin's main file.
     */
    ActionFilterHook::getInstance()->{'doAction'}('mu_plugin_loaded', $mu_plugin);
}
unset($mu_plugin);

/**
 * Fires once all must-use plugins have loaded.
 *
 * @since 1.0.0
 */
ActionFilterHook::getInstance()->{'doAction'}('muplugins_loaded');

/**
 * Loads activated plugins.
 */
if (!empty(load_activated_plugins())) {
    foreach (load_activated_plugins() as $plugin) {
        include_once($plugin);
        /**
         * Fires once a single activated plugin has loaded.
         *
         * @since 1.0.0
         * @param $string $plugin Full path to the plugin's main file.
         */
        ActionFilterHook::getInstance()->{'doAction'}('plugin_loaded', $plugin);
    }
    unset($plugin);
}

/**
 * Fires once activated plugins have loaded.
 *
 * @since 1.0.0
 */
ActionFilterHook::getInstance()->{'doAction'}('plugins_loaded');

/**
 * Include the routers needed
 *
 * Lazy load the routers. A router is loaded
 * only when it is needed.
 */
include(APP_PATH . 'routers.php');

/**
 * Autoload specific site dropins.
 *
 * Dropins are just site specific snippets of code to include
 * without the hassle of creating a full fledge
 * plugin.
 */
foreach (ttcms_get_site_dropins() as $site_dropin) {
    include_once($site_dropin);
    /**
     * Fires once a single site dropin is loaded.
     *
     * @since 1.0.0
     * @param $string $site_dropin Full path to the site dropin's main file.
     */
    ActionFilterHook::getInstance()->{'doAction'}('site_dropin_loaded', $site_dropin);
}
unset($site_dropin);

/**
 * Fires once all site dropins have loaded.
 *
 * @since 1.0.0
 */
ActionFilterHook::getInstance()->{'doAction'}('sitedropins_loaded');

/**
 * Autoload theme function file if it exist.
 */
if ((new FileSystem(ActionFilterHook::getInstance()))->exists(c::getInstance()->get('theme_path') . 'functions.php', false)) {
    $theme_function = c::getInstance()->get('theme_path') . 'functions.php';
    include($theme_function);
}
unset($theme_function);

/**
 * Autoload specific site Theme Routers if they exist.
 */
foreach (ttcms_get_theme_routers() as $theme_router) {
    include($theme_router);
    /**
     * Fires once a single theme router is loaded.
     *
     * @since 1.0.0
     * @param $string $theme_router Full path to the theme's router file.
     */
    ActionFilterHook::getInstance()->{'doAction'}('theme_router_loaded', $theme_router);
}
unset($theme_router);

/**
 * Set the timezone for the application.
 */
date_default_timezone_set(get_user_timezone());

/**
 * Fires after the site's theme is loaded.
 *
 * @since 1.0.0
 */
ActionFilterHook::getInstance()->{'doAction'}('after_setup_theme');

/**
 * Fires after Qubus CMS has finished loading but before any headers are sent.
 *
 * @since 1.0.0
 */
ActionFilterHook::getInstance()->{'doAction'}('init');

/**
 * This hook is fired once Qubus CMS , all plugins, and the theme are fully loaded and instantiated.
 *
 * @since 1.0.0
 */
ActionFilterHook::getInstance()->{'doAction'}('ttcms_loaded');
