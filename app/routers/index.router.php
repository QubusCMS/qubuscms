<?php
use TriTan\Common\Uri;
use TriTan\Csrf\Nonce;
use Qubus\Hooks\ActionFilterHook;

$qudb = app()->qudb;

ActionFilterHook::getInstance()->{'doAction'}('maintenance_mode', $app);

/**
 * If the requested page does not exist,
 * return a 404.
 */
$app->setError(function () use ($app) {
    $app->res->_format('json', 404);
});
