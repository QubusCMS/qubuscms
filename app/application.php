<?php
use TriTan\Common\FileSystem;
use Qubus\Exception\IO\IOException;
use Cascade\Cascade;

$hook = Qubus\Hooks\ActionFilterHook::getInstance();
$helper = new TriTan\Common\Context\HelperContext();

/**
 * Bootstrap for the application
 *
 * @license GPLv3
 *
 * @since       1.0.0
 * @package     Qubus CMS
 * @author      Joshua Parker <josh@joshuaparker.blog>
 */
try {
    /**
     * Creates a cookies directory with proper permissions.
     */
    (new FileSystem($hook))->mkdir(app()->config('cookies.savepath'));
} catch (IOException $e) {
    Cascade::getLogger('error')->error(sprintf('IOSTATE[%s]: Forbidden: %s', $e->getCode(), $e->getMessage()));
}

try {
    /**
     * Creates a file directory with proper permissions.
     */
    (new FileSystem($hook))->mkdir(app()->config('file.savepath'));
} catch (IOException $e) {
    Cascade::getLogger('error')->error(sprintf('IOSTATE[%s]: Forbidden: %s', $e->getCode(), $e->getMessage()));
}

/**
 * Error log setting
 */
ttcms_set_environment();

/**
 * Loads the default textdomain.
 *
 * @since 1.0.0
 */
load_default_textdomain('tritan-cms', BASE_PATH . 'languages' . DS);
