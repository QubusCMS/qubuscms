<?php

$uri = new TriTan\Common\Uri(Qubus\Hooks\ActionFilterHook::getInstance());

/**
 * This file is used for lazy loading of the routers
 * and modules when called.
 */

if (strpos($uri->getPathInfo('/rest'), "/rest") === 0) {
    require($app->config('routers_dir') . 'rest.router.php');
} elseif (strpos($uri->getPathInfo('/admin/user'), "/admin/user") === 0) {
    require($app->config('routers_dir') . 'user.router.php');
} elseif (strpos($uri->getPathInfo('/admin/site'), "/admin/site") === 0) {
    require($app->config('routers_dir') . 'site.router.php');
} elseif (strpos($uri->getPathInfo('/admin'), "/admin") === 0) {
    require($app->config('routers_dir') . 'admin.router.php');
    _ttcms_post_router();
} elseif (strpos($uri->getPathInfo('/login'), "/login") === 0) {
    require($app->config('routers_dir') . 'auth.router.php');
} elseif (strpos($uri->getPathInfo('/reset-password'), "/reset-password") === 0) {
    require($app->config('routers_dir') . 'auth.router.php');
} elseif (strpos($uri->getPathInfo('/logout'), "/logout") === 0) {
    require($app->config('routers_dir') . 'auth.router.php');
} elseif (strpos($uri->getPathInfo('/cronjob'), "/cronjob") === 0) {
    require($app->config('routers_dir') . 'cron.router.php');
} else {
    require($app->config('routers_dir') . 'index.router.php');
    // default routes
}
