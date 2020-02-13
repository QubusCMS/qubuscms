<?php
use TriTan\Common\Container as c;
use TriTan\Common\FileSystem;
use TriTan\Common\Context\HelperContext;
use TriTan\Common\Plugin\PluginActivate;
use TriTan\Common\Plugin\PluginActivateMapper;
use TriTan\Common\Plugin\PluginDeactivate;
use TriTan\Common\Plugin\PluginDeactivateMapper;
use TriTan\Common\Plugin\PluginLoad;
use TriTan\Common\Plugin\PluginGetActivated;
use TriTan\Common\Plugin\PluginGetActivatedMapper;
use TriTan\Common\Plugin\PluginIsActivated;
use TriTan\Common\Plugin\PluginIsActivatedMapper;
use Qubus\Hooks\ActionFilterHook;
use Qubus\Exception\Http\Client\NotFoundException;
use Qubus\Exception\IO\IOException;

/**
 * Qubus CMS Hooks Helper & Wrapper
 *
 * @license GPLv3
 *
 * @since 1.0.0
 * @package Qubus CMS
 * @author Joshua Parker <josh@joshuaparker.blog>
 */

/**
 * Attempts activation of a plugin.
 *
 * Wrapper function for PluginActivate::activate() and
 * activates plugin based on $_GET['id'].
 *
 * @file app/functions/hook.php
 *
 * @access private
 * @since 1.0.0
 * @param string $id ID of the plugin to be activated.
 * @return mixed Activates plugin if it exists.
 */
function activate_plugin($id)
{
    $qudb = app()->qudb;

    return (new PluginActivate(
        new PluginActivateMapper($qudb)
    ))->activate($id);
}

/**
 * Attempts deactivation of a plugin.
 *
 * Wrapper function for PluginDeactivate::deactivate() and
 * deactivates plugin based on $_GET['id'].
 *
 * @file app/functions/hook.php
 *
 * @access private
 * @since 1.0.0
 * @param string $id ID of the plugin to be deactivated.
 * @return mixed Deactivates plugin if it exists and is active.
 */
function deactivate_plugin($id)
{
    $qudb = app()->qudb;

    return (new PluginDeactivate(
        new PluginDeactivateMapper($qudb)
    ))->deactivate($id);
}

/**
 * Attempts to load active plugins.
 *
 * Wrapper function for PluginLoad::load() and
 * loads all activated plugins for inclusion.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 * @param string $plugins_dir Loads plugins from specified folder
 * @return mixed
 */
function load_activated_plugins($plugins_dir = '')
{
    $qudb = app()->qudb;

    if (null == $plugins_dir) {
        $plugins_dir = ActionFilterHook::getInstance()->applyFilter('plugins_directory', TTCMS_PLUGIN_DIR);
    }
    return (new PluginLoad(
        new PluginGetActivated(
            new PluginGetActivatedMapper($qudb)
        ),
        new PluginDeactivate(
            new PluginDeactivateMapper($qudb)
        ),
        new HelperContext()
    ))->load($plugins_dir);
}

/**
 * Checks if a particular plugin is activated.
 *
 * Wrapper function for PluginIsActivated::isActivated() and
 * checks if a particular plugin is activated
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 * @param string $plugin Name of plugin file.
 * @return bool False if plugin is not activated and true if it is activated.
 */
function is_plugin_activated($plugin)
{
    $qudb = app()->qudb;

    return (new PluginIsActivated(
        new PluginIsActivatedMapper($qudb)
    ))->isActivated($plugin);
}

/**
 * Mark a function as deprecated and inform when it has been used.
 *
 * There is a hook deprecated_function_run that will be called that can be used
 * to get the backtrace up to what file and function called the deprecated
 * function.
 *
 * Default behavior is to trigger a user error if `APP_ENV` is set to `DEV`.
 *
 * This function is to be used in every function that is deprecated.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 * @param string $function_name The function that was called.
 * @param string $release       The release of Qubus CMS that deprecated the function.
 * @param string $replacement   Optional. The function that should have been called. Default null.
 */
function _deprecated_function($function_name, $release, $replacement = null)
{
    /**
     * Fires when a deprecated function is called.
     *
     * @since 1.0.0
     * @param string $function_name The function that was called.
     * @param string $replacement   The function that should have been called.
     * @param string $release       The release of Qubus CMS that deprecated the function.
     */
    ActionFilterHook::getInstance()->doAction('deprecated_function_run', $function_name, $replacement, $release);

    /**
     * Filter whether to trigger an error for deprecated functions.
     *
     * @since 1.0.0
     * @param bool $trigger
     *            Whether to trigger the error for deprecated functions. Default true.
     */
    if (APP_ENV == 'DEV' && ActionFilterHook::getInstance()->applyFilter('deprecated_function_trigger_error', true)) {
        if (function_exists('t__')) {
            if (!is_null($replacement)) {
                _trigger_error(
                    sprintf(
                        t__(
                            '%1$s() is <strong>deprecated</strong> since release %2$s! Use %3$s() instead. <br />',
                            'tritan-cms'
                        ),
                        $function_name,
                        $release,
                        $replacement
                    ),
                    E_USER_DEPRECATED
                );
            } else {
                _trigger_error(
                    sprintf(
                        t__(
                            '%1$s() is <strong>deprecated</strong> since release %2$s with no alternative available. <br />',
                            'tritan-cms'
                        ),
                        $function_name,
                        $release
                    ),
                    E_USER_DEPRECATED
                );
            }
        } else {
            if (!is_null($replacement)) {
                _trigger_error(
                    sprintf(
                        '%1$s() is <strong>deprecated</strong> since release %2$s! Use %3$s() instead. <br />',
                        $function_name,
                        $release,
                        $replacement
                    ),
                    E_USER_DEPRECATED
                );
            } else {
                _trigger_error(
                    sprintf(
                        '%1$s() is <strong>deprecated</strong> since release %2$s with no alternative available. <br />',
                        $function_name,
                        $release
                    ),
                    E_USER_DEPRECATED
                );
            }
        }
    }
}

/**
 * Mark a class as deprecated and inform when it has been used.
 *
 * There is a hook deprecated_class_run that will be called that can be used
 * to get the backtrace up to what file, function/class called the deprecated
 * class.
 *
 * Default behavior is to trigger a user error if `APP_ENV` is set to `DEV`.
 *
 * This function is to be used in every class that is deprecated.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 * @param string $class_name  The class that was called.
 * @param string $release     The release of Qubus CMS that deprecated the class.
 * @param string $replacement Optional. The class that should have been called. Default null.
 */
function _deprecated_class($class_name, $release, $replacement = null)
{
    /**
     * Fires when a deprecated class is called.
     *
     * @since 1.0.0
     * @param string $class_name  The class that was called.
     * @param string $replacement The class that should have been called.
     * @param string $release     The release of Qubus CMS that deprecated the class.
     */
    ActionFilterHook::getInstance()->doAction('deprecated_class_run', $class_name, $replacement, $release);

    /**
     * Filter whether to trigger an error for deprecated classes.
     *
     * @since 1.0.0
     * @param bool $trigger
     *            Whether to trigger the error for deprecated classes. Default true.
     */
    if (APP_ENV == 'DEV' && ActionFilterHook::getInstance()->applyFilter('deprecated_class_trigger_error', true)) {
        if (function_exists('t__')) {
            if (!is_null($replacement)) {
                _trigger_error(
                    sprintf(
                        t__(
                            '%1$s() is <strong>deprecated</strong> since release %2$s! Use %3$s instead. <br />',
                            'tritan-cms'
                        ),
                        $class_name,
                        $release,
                        $replacement
                    ),
                    E_USER_DEPRECATED
                );
            } else {
                _trigger_error(
                    sprintf(
                        t__(
                            '%1$s() is <strong>deprecated</strong> since release %2$s with no alternative available. <br />',
                            'tritan-cms'
                        ),
                        $class_name,
                        $release
                    ),
                    E_USER_DEPRECATED
                );
            }
        } else {
            if (!is_null($replacement)) {
                _trigger_error(
                    sprintf(
                        '%1$s() is <strong>deprecated</strong> since release %2$s! Use %3$s instead. <br />',
                        $class_name,
                        $release,
                        $replacement
                    ),
                    E_USER_DEPRECATED
                );
            } else {
                _trigger_error(
                    sprintf(
                        '%1$s() is <strong>deprecated</strong> since release %2$s with no alternative available. <br />',
                        $class_name,
                        $release
                    ),
                    E_USER_DEPRECATED
                );
            }
        }
    }
}

/**
 * Mark a class's method as deprecated and inform when it has been used.
 *
 * There is a hook deprecated_class_method_run that will be called that can be used
 * to get the backtrace up to what file, function/class called the deprecated
 * method.
 *
 * Default behavior is to trigger a user error if `APP_ENV` is set to `DEV`.
 *
 * This function is to be used in every class's method that is deprecated.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 * @param string $method_name The class method that was called.
 * @param string $release     The release of Qubus CMS that deprecated the class's method.
 * @param string $replacement Optional. The class method that should have been called. Default null.
 */
function _deprecated_class_method($method_name, $release, $replacement = null)
{
    /**
     * Fires when a deprecated class method is called.
     *
     * @since 1.0.0
     * @param string $method_name The class's method that was called.
     * @param string $replacement The class method that should have been called.
     * @param string $release     The release of Qubus CMS that deprecated the class's method.
     */
    ActionFilterHook::getInstance()->doAction('deprecated_class_method_run', $method_name, $replacement, $release);

    /**
     * Filter whether to trigger an error for deprecated class methods.
     *
     * @since 1.0.0
     * @param bool $trigger Whether to trigger the error for deprecated class methods.
     *                      Default true.
     */
    if (APP_ENV == 'DEV' && ActionFilterHook::getInstance()->applyFilter('deprecated_class_method_trigger_error', true)) {
        if (function_exists('t__')) {
            if (!is_null($replacement)) {
                _trigger_error(
                    sprintf(
                        t__(
                            '%1$s() is <strong>deprecated</strong> since release %2$s! Use %3$s() instead. <br />',
                            'tritan-cms'
                        ),
                        $method_name,
                        $release,
                        $replacement
                    ),
                    E_USER_DEPRECATED
                );
            } else {
                _trigger_error(
                    sprintf(
                        t__(
                            '%1$s() is <strong>deprecated</strong> since release %2$s with no alternative available. <br />',
                            'tritan-cms'
                        ),
                        $method_name,
                        $release
                    ),
                    E_USER_DEPRECATED
                );
            }
        } else {
            if (!is_null($replacement)) {
                _trigger_error(
                    sprintf(
                        '%1$s() is <strong>deprecated</strong> since release %2$s! Use %3$s() instead. <br />',
                        $method_name,
                        $release,
                        $replacement
                    ),
                    E_USER_DEPRECATED
                );
            } else {
                _trigger_error(
                    sprintf(
                        '%1$s() is <strong>deprecated</strong> since release %2$s with no alternative available. <br />',
                        $method_name,
                        $release
                    ),
                    E_USER_DEPRECATED
                );
            }
        }
    }
}

/**
 * Mark a function argument as deprecated and inform when it has been used.
 *
 * This function is to be used whenever a deprecated function argument is used.
 * Before this function is called, the argument must be checked for whether it was
 * used by comparing it to its default value or evaluating whether it is empty.
 *
 * There is a hook `deprecated_argument_run` that will be called that can be used
 * to get the backtrace up to what file and function used the deprecated
 * argument.
 *
 * Default behavior is to trigger a user error if `APP_ENV` is set to `DEV`.
 *
 * Example Usage:
 *
 *      if ( ! empty( $deprecated ) ) {
 *          _deprecated_argument( __FUNCTION__, '1.0.0' );
 *      }
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 * @param string $function_name The function that was called.
 * @param string $release       The release of Qubus CMS that deprecated the argument used.
 * @param string $message       Optional. A message regarding the change. Default null.
 */
function _deprecated_argument($function_name, $release, $message = null)
{
    /**
     * Fires when a deprecated argument is called.
     *
     * @since 1.0.0
     * @param string $function_name The function that was called.
     * @param string $message       A message regarding the change.
     * @param string $release       The release of Qubus CMS that deprecated the argument used.
     */
    ActionFilterHook::getInstance()->doAction('deprecated_argument_run', $function_name, $message, $release);
    /**
     * Filter whether to trigger an error for deprecated arguments.
     *
     * @since 1.0.0
     * @param bool $trigger Whether to trigger the error for deprecated arguments.
     *                      Default true.
     */
    if (APP_ENV == 'DEV' && ActionFilterHook::getInstance()->applyFilter('deprecated_argument_trigger_error', true)) {
        if (function_exists('t__')) {
            if (!is_null($message)) {
                _trigger_error(
                    sprintf(
                        t__(
                            '%1$s() was called with an argument that is <strong>deprecated</strong> since release %2$s! %3$s. <br />',
                            'tritan-cms'
                        ),
                        $function_name,
                        $release,
                        $message
                    ),
                    E_USER_DEPRECATED
                );
            } else {
                _trigger_error(
                    sprintf(
                        t__(
                            '%1$s() was called with an argument that is <strong>deprecated</strong> since release %2$s with no alternative available. <br />',
                            'tritan-cms'
                        ),
                        $function_name,
                        $release
                    ),
                    E_USER_DEPRECATED
                );
            }
        } else {
            if (!is_null($message)) {
                _trigger_error(
                    sprintf(
                        '%1$s() was called with an argument that is <strong>deprecated</strong> since release %2$s! %3$s. <br />',
                        $function_name,
                        $release,
                        $message
                    ),
                    E_USER_DEPRECATED
                );
            } else {
                _trigger_error(
                    sprintf(
                        '%1$s() was called with an argument that is <strong>deprecated</strong> since release %2$s with no alternative available. <br />',
                        $function_name,
                        $release
                    ),
                    E_USER_DEPRECATED
                );
            }
        }
    }
}

/**
 * Marks a deprecated action or filter hook as deprecated and throws a notice.
 *
 * Default behavior is to trigger a user error if `APP_ENV` is set to `DEV`.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 * @param string $hook        The hook that was used.
 * @param string $release     The release of Qubus CMS that deprecated the hook.
 * @param string $replacement Optional. The hook that should have been used.
 * @param string $message     Optional. A message regarding the change.
 */
function _deprecated_hook($hook, $release, $replacement = null, $message = null)
{
    /**
     * Fires when a deprecated hook is called.
     *
     * @since 1.0.0
     * @param string $hook        The hook that was called.
     * @param string $replacement The hook that should be used as a replacement.
     * @param string $release     The release of Qubus CMS that deprecated the argument used.
     * @param string $message     A message regarding the change.
     */
    ActionFilterHook::getInstance()->doAction('deprecated_hook_run', $hook, $replacement, $release, $message);

    /**
     * Filters whether to trigger deprecated hook errors.
     *
     * @since 1.0.0
     * @param bool $trigger Whether to trigger deprecated hook errors. Requires
     *                      `APP_DEV` to be defined DEV.
     */
    if (APP_ENV == 'DEV' && ActionFilterHook::getInstance()->applyFilter('deprecated_hook_trigger_error', true)) {
        $message = empty($message) ? '' : ' ' . $message;
        if (!is_null($replacement)) {
            _trigger_error(
                sprintf(
                    __(
                        '%1$s is <strong>deprecated</strong> since release %2$s! Use %3$s instead.'
                    ),
                    $hook,
                    $release,
                    $replacement
                ) . $message,
                E_USER_DEPRECATED
            );
        } else {
            _trigger_error(
                sprintf(
                    __(
                        '%1$s is <strong>deprecated</strong> since release %2$s with no alternative available.'
                    ),
                    $hook,
                    $release
                ) . $message,
                E_USER_DEPRECATED
            );
        }
    }
}

/**
 * Mark something as being incorrectly called.
 *
 * There is a hook incorrectly_called_run that will be called that can be used
 * to get the backtrace up to what file and function called the deprecated
 * function.
 *
 * Default behavior is to trigger a user error if `APP_ENV` is set to `DEV`.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 * @param string $function_name The function that was called.
 * @param string $message       A message explaining what has been done incorrectly.
 * @param string $release       The release of Qubus CMS where the message was added.
 */
function _incorrectly_called($function_name, $message, $release)
{
    /**
     * Fires when the given function is being used incorrectly.
     *
     * @since 1.0.0
     * @param string $function_name The function that was called.
     * @param string $message       A message explaining what has been done incorrectly.
     * @param string $release       The release of Qubus CMS where the message was added.
     */
    ActionFilterHook::getInstance()->doAction('incorrectly_called_run', $function_name, $message, $release);

    /**
     * Filter whether to trigger an error for _incorrectly_called() calls.
     *
     * @since 1.0.0
     * @param bool $trigger Whether to trigger the error for _incorrectly_called() calls.
     *                      Default true.
     */
    if (APP_ENV == 'DEV' && ActionFilterHook::getInstance()->applyFilter('incorrectly_called_trigger_error', true)) {
        if (function_exists('t__')) {
            $release = is_null($release) ? '' : sprintf(
                t__(
                    '(This message was added in release %s.) <br /><br />',
                    'tritan-cms'
                ),
                $release
            );
            /* translators: %s: Codex URL */
            $message .= ' ' . sprintf(
                t__(
                    'Please see <a href="%s">Debugging in Qubus CMS</a> for more information.',
                    'tritan-cms'
                ),
                'https://learn.tritancms.com/start.html#debugging'
            );
            _trigger_error(
                sprintf(
                    t__(
                        '%1$s() was called <strong>incorrectly</strong>. %2$s %3$s <br />',
                        'tritan-cms'
                    ),
                    $function_name,
                    $message,
                    $release
                )
            );
        } else {
            $release = is_null($release) ? '' : sprintf('(This message was added in release %s.) <br /><br />', $release);
            $message .= sprintf(
                ' Please see <a href="%s">Debugging in Qubus CMS</a> for more information.',
                'https://learn.tritancms.com/start.html#debugging'
            );
            _trigger_error(
                sprintf(
                    '%1$s() was called <strong>incorrectly</strong>. %2$s %3$s <br />',
                    $function_name,
                    $message,
                    $release
                )
            );
        }
    }
}

/**
 * Prints copyright in the admin footer.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 */
function ttcms_admin_copyright_footer()
{
    $copyright = '<!--  Copyright Line -->' . "\n";
    $copyright .= '<strong>&#169; ' . t__('Copyright 2019', 'tritan-cms') . ' | ' . t__('Powered by', 'tritan-cms') . ' <a href="//www.qubuscms.com/">' . t__('Qubus CMS', 'tritan-cms') . '</a></strong>' . "\n";
    $copyright .= '<!--  End Copyright Line -->' . "\n";

    return ActionFilterHook::getInstance()->applyFilter('admin_copyright_footer', $copyright);
}

/**
 * An action called to add the plugin's link
 * to the menu structure.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 * @uses ActionFilterHook::getInstance()->doAction() Calls 'admin_menu' hook.
 */
ActionFilterHook::getInstance()->doAction('admin_menu');

/**
 * Fires the admin_head action.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 */
function admin_head()
{
    /**
     * Registers & enqueues a stylesheet to be printed in backend head section.
     *
     * @since 1.0.0
     */
    ActionFilterHook::getInstance()->doAction('enqueue_admin_css');
    /**
     * Fires in head section of all admin screens.
     *
     * @since 1.0.0
     */
    ActionFilterHook::getInstance()->doAction('ttcms_admin_head');
}

/**
 * Fires the ttcms_head action.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 */
function ttcms_head()
{
    /**
     * Registers & enqueues a stylesheet to be printed in frontend head section.
     *
     * @since 1.0.0
     */
    ActionFilterHook::getInstance()->doAction('enqueue_css');
    /**
     * Prints scripts and/or data in the head of the front end.
     *
     * @since 1.0.0
     */
    ActionFilterHook::getInstance()->doAction('ttcms_head');
}

/**
 * Fires the admin_footer action via backend.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 */
function admin_footer()
{
    /**
     * Registers & enqueues javascript to be printed in backend footer section.
     *
     * @since 1.0.0
     */
    ActionFilterHook::getInstance()->doAction('enqueue_admin_js');
    /**
     * Prints scripts and/or data before the ending body tag of the backend.
     *
     * @since 1.0.0
     */
    ActionFilterHook::getInstance()->doAction('ttcms_admin_footer');
}

/**
 * Fires the ttcms_footer action via the admin.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 */
function ttcms_footer()
{
    /**
     * Registers & enqueues javascript to be printed in frontend footer section.
     *
     * @since 1.0.0
     */
    ActionFilterHook::getInstance()->doAction('enqueue_js');
    /**
     * Prints scripts and/or data before the ending body tag
     * of the front end.
     *
     * @since 1.0.0
     */
    ActionFilterHook::getInstance()->doAction('ttcms_footer');
}

/**
 * Fires the ttcms_release action.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 */
function ttcms_release()
{
    /**
     * Prints Qubus CMS release information.
     *
     * @since 1.0.0
     */
    ActionFilterHook::getInstance()->doAction('ttcms_release');
}

/**
 * Fires the admin_top_widgets action.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 */
function admin_top_widgets()
{
    /**
     * Prints widgets at the top portion of the admin.
     *
     * @since 1.0.0
     */
    ActionFilterHook::getInstance()->doAction('admin_top_widgets');
}

/**
 * Large logo. Filterable.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 * @return string
 */
function get_logo_large()
{
    $logo = '<strong>' . t__('Qubus', 'tritan-cms') . '</strong>' . t__('CMS', 'tritan-cms');
    return ActionFilterHook::getInstance()->applyFilter('logo_large', $logo);
}

/**
 * Mini logo. Filterable.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 * @return string
 */
function get_logo_mini()
{
    $logo = '<strong>' . t__('Qub', 'tritan-cms') . '</strong>' . t__('us', 'tritan-cms');
    return ActionFilterHook::getInstance()->applyFilter('logo_mini', $logo);
}

/**
 * Checks data to make sure it is a valid request.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 * @param mixed $data
 */
function ttcms_validation_check($data)
{
    if ($data['m6qIHt4Z5evV'] != '' || !empty($data['m6qIHt4Z5evV'])) {
        app()->res->_format('json', 422);
        exit();
    }

    if ($data['YgexGyklrgi1'] != '' || !empty($data['YgexGyklrgi1'])) {
        app()->res->_format('json', 422);
        exit();
    }
}

/**
 * Retrieve name of the current theme.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 * @return string Theme name.
 */
function get_theme()
{
    /**
     * Filters the name of the current theme.
     *
     * @since 1.0.0
     * @param string $theme Current theme's directory name.
     */
    return ActionFilterHook::getInstance()->applyFilter('theme', c::getInstance()->get('option')->read('current_site_theme'));
}

/**
 * Retrieve theme directory URI.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 * @uses ActionFilterHook::getInstance()->applyFilter() Calls 'theme_directory_uri' filter.
 * @return string Qubus CMS theme directory uri.
 */
function get_theme_directory_uri()
{
    if (!get_theme()) {
        return false;
    }
    $theme = str_replace('%2F', '/', rawurlencode(get_theme()));
    $theme_root_uri = get_theme_url();
    $theme_dir_uri = $theme_root_uri . $theme . '/';
    return ActionFilterHook::getInstance()->applyFilter('theme_directory_uri', $theme_dir_uri, $theme, $theme_root_uri);
}

/**
 * Retrieve javascript directory uri.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 * @uses ActionFilterHook::getInstance()->applyFilter() Calls 'javascript_directory_uri' filter.
 * @return string Qubus CMS javascript url.
 */
function get_javascript_directory_uri()
{
    if (!get_theme()) {
        return false;
    }
    $theme = str_replace('%2F', '/', rawurlencode(get_theme()));
    $javascript_root_uri = get_theme_url();
    $javascript_dir_uri = $javascript_root_uri . $theme . '/assets/js/';
    return ActionFilterHook::getInstance()->applyFilter('javascript_directory_uri', $javascript_dir_uri, $theme, $javascript_root_uri);
}

/**
 * Retrieve less directory uri.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 * @uses ActionFilterHook::getInstance()->applyFilter() Calls 'less_directory_uri' filter.
 * @return string Qubus CMS less url.
 */
function get_less_directory_uri()
{
    if (!get_theme()) {
        return false;
    }
    $theme = str_replace('%2F', '/', rawurlencode(get_theme()));
    $less_root_uri = get_theme_url();
    $less_dir_uri = $less_root_uri . $theme . '/assets/less/';
    return ActionFilterHook::getInstance()->applyFilter('less_directory_uri', $less_dir_uri, $theme, $less_root_uri);
}

/**
 * Retrieve css directory uri.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 * @uses ActionFilterHook::getInstance()->applyFilter() Calls 'css_directory_uri' filter.
 * @return string Qubus CMS css url.
 */
function get_css_directory_uri()
{
    if (!get_theme()) {
        return false;
    }
    $theme = str_replace('%2F', '/', rawurlencode(get_theme()));
    $css_root_uri = get_theme_url();
    $css_dir_uri = $css_root_uri . $theme . '/assets/css/';
    return ActionFilterHook::getInstance()->applyFilter('css_directory_uri', $css_dir_uri, $theme, $css_root_uri);
}

/**
 * Retrieve image directory uri.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 * @uses ActionFilterHook::getInstance()->applyFilter() Calls 'image_directory_uri' filter.
 * @return string Qubus CMS image url.
 */
function get_image_directory_uri()
{
    if (!get_theme()) {
        return false;
    }
    $theme = str_replace('%2F', '/', rawurlencode(get_theme()));
    $image_root_uri = get_theme_url();
    $image_dir_uri = $image_root_uri . $theme . '/assets/images/';
    return ActionFilterHook::getInstance()->applyFilter('image_directory_uri', $image_dir_uri, $theme, $image_root_uri);
}

/**
 * Frontend portal footer powered by and release.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 * @uses ActionFilterHook::getInstance()->applyFilter() Calls 'met_footer_release' filter.
 * @return mixed.
 */
function get_footer_release()
{
    $release = t__('Powered by Qubus CMS v', 'tritan-cms') . CURRENT_RELEASE;
    return ActionFilterHook::getInstance()->applyFilter('footer_release', $release);
}

/**
 * Retrieve the avatar `<img>` tag for user.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 * @param string $email User's email address.
 * @param int $s        Height and width of the avatar image file in pixels. Default 80.
 * @param string $class Class to add to `<img>` element.
 * @return string `<img>` tag for user's avatar or default otherwise.
 */
function get_user_avatar($email, $s = 80, $class = '')
{
    $email_hash = md5(strtolower(_trim($email)));

    if (is_ssl()) {
        $url = 'https://secure.gravatar.com/avatar/' . $email_hash . "?s=200";
    } else {
        $url = 'http://www.gravatar.com/avatar/' . $email_hash . "?s=200";
    }

    $resource_check = 'https://www.gravatar.com/avatar/205e460b479e2e5b48aec07710c08d50?f=y';

    if (get_http_response_code($resource_check) !== (int) 200) {
        $static_image_url = site_url('static/assets/img/avatar.png?s=200');
        $avatarsize = getimagesize($static_image_url);
        $avatar = '<img src="' . site_url('static/assets/img/avatar.png') . '" ' . ttcms()->obj['image']->resize($avatarsize[1], $avatarsize[1], $s) . ' class="' . $class . '" alt="' . $email . '" />';
    } else {
        $avatarsize = getimagesize($url);
        $avatar = '<img src="' . $url . '" ' . ttcms()->obj['image']->resize($avatarsize[1], $avatarsize[1], $s) . ' class="' . $class . '" alt="' . $email . '" />';
    }

    return ActionFilterHook::getInstance()->applyFilter('user_avatar', $avatar, $email, $s, $class);
}

/**
 * Retrieves the avatar url.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 * @param string $email Email address of user.
 * @return string The url of the avatar that was found, or default if not found.
 */
function get_user_avatar_url($email)
{
    $email_hash = md5(strtolower(_trim($email)));

    if (is_ssl()) {
        $url = 'https://secure.gravatar.com/avatar/' . $email_hash;
    } else {
        $url = 'http://www.gravatar.com/avatar/' . $email_hash;
    }

    $resource_check = 'https://www.gravatar.com/avatar/205e460b479e2e5b48aec07710c08d50?f=y';

    if (get_http_response_code($resource_check) !== (int) 200) {
        $avatar = site_url('static/assets/img/avatar.png');
    } else {
        $avatar = $url;
    }

    return ActionFilterHook::getInstance()->applyFilter('user_avatar_url', $avatar, $email);
}

function ttcms_get_nocache_headers()
{
    $headers = [
        'Expires' => 'Sun, 01 Jan 2014 04:00:00 GMT',
        'Cache-Control' => 'no-cache, no-store, must-revalidate, max-age=0',
        'Pragma' => 'no-cache'
    ];

    /**
     * Filters the cache-controlling headers
     *
     * @since 1.0.0
     * @param array $headers {
     *      Header names and field values.
     *
     *      @type string $Expires       Expires header.
     *      @type string $Cache-Control Cache-Control header.
     * }
     */
    $headers = (array) ActionFilterHook::getInstance()->applyFilter('nocache_headers', $headers);
    $headers['Last-Modified'] = false;
    return $headers;
}

function nocache_headers()
{
    $headers = ttcms_get_nocache_headers();

    unset($headers['Last-Modified']);

    if (function_exists('header_remove')) {
        @header_remove('Last-Modified');
    }


    foreach ($headers as $name => $field_value) {
        header("{$name}: {$field_value}");
    }
}

/**
 * Upload image button.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 */
function ttcms_upload_image()
{
    $elfinder = '<link rel="stylesheet" type="text/css" href="//cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css">
            <link href="vendor/studio-42/elfinder/css/elfinder.full.css" type="text/css" rel="stylesheet" />
            <link href="vendor/studio-42/elfinder/css/theme.css" type="text/css" rel="stylesheet" />
            <script src="vendor/studio-42/elfinder/js/elfinder.full.js" type="text/javascript"></script>
            <script src="//cdnjs.cloudflare.com/ajax/libs/fancybox/2.1.7/js/jquery.fancybox.min.js" type="text/javascript"></script>
            <script>
                $(document).ready(function () {

                    $("#remove_image").hide();
                    $("#set_image").show();

                    $("#set_image").click(function (e) {
                        var elfinder = $("#elfinder").elfinder({
                            url: "' . admin_url('connector/') . '",
                            resizable: false,
                            onlyMimes: ["image"],
                            uiOptions: {
                                // toolbar configuration
                                toolbar: [
                                    ["reload"],
                                    ["open", "download", "getfile"],
                                    ["duplicate", "rename", "edit", "resize"],
                                    ["quicklook", "info"],
                                    ["search"],
                                    ["view", "sort"]
                                ]
                            },
                            getfile: {
                                onlyURL: true,
                                multiple: false,
                                folders: false,
                                oncomplete: "destroy"
                            },
                            handlers: {
                                dblclick: function (event, elfinderInstance) {
                                    fileInfo = elfinderInstance.file(event.data.file);

                                    if (fileInfo.mime != "directory") {
                                        var imgURL = elfinderInstance.url(event.data.file);
                                        $("#upload_image").val(imgURL);

                                        var imgPath = "<img src=\'"+imgURL+"\' id=\"append-image\" style=\"width:260px;height:auto;background-size:contain;margin-bottom:.9em;background-repeat:no-repeat\"/>";
                                        $("#elfinder_image").append(imgPath); //add the image to a div so you can see the selected images

                                        $("#remove_image").show();
                                        $("#set_image").hide();

                                        elfinderInstance.destroy();
                                        return false; // stop elfinder
                                    };
                                },
                                destroy: function () {
                                    elfinder.dialog("close");

                                }
                            }
                        }).dialog({
                            title: "filemanager",
                            resizable: true,
                            width: 920,
                            height: 500
                        });
                        $("#remove_image").click(function () {

                            $("#upload_image").val("");
                            $("#elfinder_image").find("#append-image").remove(); //remove image from div when user clicks remove image button.

                            $("#remove_image").hide();
                            $("#set_image").show();

                            return false;
                        });
                    });
                });
            </script>';
    return ActionFilterHook::getInstance()->applyFilter('ttcms_upload_image', $elfinder);
}

/**
 * Compares release values.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 * @param string $current Current installed release.
 * @param string $latest The latest Qubus CMS release.
 * @param string $operator Operand use to compare current and latest release values.
 * @return bool
 */
function compare_releases($current, $latest, $operator = '>'): bool
{
    $php_function = version_compare($current, $latest, $operator);
    /**
     * Filters the comparison between two releases.
     *
     * @since 1.0.0
     * @param $php_function PHP function for comparing two release values.
     */
    $release = ActionFilterHook::getInstance()->applyFilter('compare_releases', $php_function);

    if ($release) {
        return (bool) $latest;
    } else {
        return false;
    }
}

/**
 * Retrieves a response code from the header
 * of a given resource.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 * @param string $url URL of resource/website.
 * @return int HTTP response code.
 */
function get_http_response_code($url)
{
    $headers = @get_headers($url);
    $status = substr($headers[0], 9, 3);
    /**
     * Filters the http response code.
     *
     * @since 1.0.0
     * @param int $status The http response code from external resource.
     */
    return ActionFilterHook::getInstance()->applyFilter('http_response_code', (int) $status);
}

/**
 * Plugin success message when plugin is activated successfully.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 * @param string $plugin_name The name of the plugin that was just activated.
 */
function ttcms_plugin_activate_message($plugin_name)
{
    $success = ttcms()->obj['flash']->success(t__('Plugin <strong>activated</strong>.', 'tritan-cms'));
    /**
     * Filter the default plugin success activation message.
     *
     * @since 1.0.0
     * @param string $success The success activation message.
     * @param string $plugin_name The name of the plugin that was just activated.
     */
    return ActionFilterHook::getInstance()->applyFilter('ttcms_plugin_activate_message', $success, $plugin_name);
}

/**
 * Plugin success message when plugin is deactivated successfully.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 * @param string $plugin_name The name of the plugin that was just deactivated.
 */
function ttcms_plugin_deactivate_message($plugin_name)
{
    $success = ttcms()->obj['flash']->success(t__('Plugin <strong>deactivated</strong>.', 'tritan-cms'));
    /**
     * Filter the default plugin success deactivation message.
     *
     * @since 1.0.0
     * @param string $success The success deactivation message.
     * @param string $plugin_name The name of the plugin that was just deactivated.
     */
    return ActionFilterHook::getInstance()->applyFilter('ttcms_plugin_deactivate_message', $success, $plugin_name);
}

/**
 * Shows an error message when system is in DEV mode.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 */
function ttcms_dev_mode()
{
    if (APP_ENV === 'DEV') {
        echo '<div class="alert dismissable alert-danger center sticky">' . esc_html__(
            'Your system is currently in DEV mode. Please remember to set your system back to PROD mode after testing. When PROD mode is set, this warning message will disappear.'
        ) . '</div>';
    }
}

/**
 * Returns full base url of MU Plugins.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 * @return string MU Plugin base url.
 */
function get_mu_plugin_url()
{
    $url = site_url('mu-plugins/');
    return ActionFilterHook::getInstance()->applyFilter('the_mu_plugin_url', $url);
}

/**
 * Returns full base url of Plugins.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 * @return string Plugin base url.
 */
function get_plugin_url()
{
    $url = site_url('plugins/');
    return ActionFilterHook::getInstance()->applyFilter('the_plugin_url', $url);
}

/**
 * Retrieves a URL within the plugins or mu-plugins directory.
 *
 * Defaults to the plugins directory URL if no arguments are supplied.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 * @param  string $path   Optional. Extra path appended to the end of the URL, including
 *                        the relative directory if $plugin is supplied. Default empty.
 * @param  string $plugin Optional. A full path to a file inside a plugin or mu-plugin.
 *                        The URL will be relative to its directory. Default empty.
 *                        Typically this is done by passing `__FILE__` as the argument.
 * @return string Plugins URL link with optional paths appended.
 */
function plugins_url($path = '', $plugin = '')
{
    $_path = ttcms()->obj['file']->normalizePath($path);
    $_plugin = ttcms()->obj['file']->normalizePath($plugin);
    $mu_plugin_dir = ttcms()->obj['file']->normalizePath(TTCMS_MU_PLUGIN_DIR);

    if (!empty($_plugin) && 0 === strpos($_plugin, $mu_plugin_dir)) {
        $url = get_mu_plugin_url();
    } else {
        $url = get_plugin_url();
    }

    $url = set_url_scheme($url);

    if (!empty($_plugin) && is_string($_plugin)) {
        $folder = plugin_basename(dirname($_plugin));
        if ('.' != $folder) {
            $url .= ltrim($folder, '/');
        }
    }

    if ($_path && is_string($_path)) {
        $url .= '/' . ltrim($_path, '/');
    }

    /**
     * Filters the URL to the plugins or mu-plugins directory.
     *
     * @since 1.0.0
     * @param string $url       The complete URL to the plugins directory including scheme and path.
     * @param string $_path     Path relative to the URL to the plugins directory. Blank string
     *                          if no path is specified.
     * @param string $_plugin   The plugin file path to be relative to. Blank string if no plugin
     *                          is specified.
     */
    return ActionFilterHook::getInstance()->applyFilter('plugins_url', $url, $_path, $_plugin);
}

/**
 * Get the URL directory path (with trailing slash) for the plugin __FILE__ passed in.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 * @param string $file The filename of the plugin (__FILE__).
 * @return string the URL path of the directory that contains the plugin.
 */
function plugin_dir_url($file)
{
    $url = add_trailing_slash(plugins_url('', $file));
    return ActionFilterHook::getInstance()->applyFilter('plugin_dir_url', $url, $file);
}

/**
 * Returns full base url of a site's theme.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 * @return string Site's theme base url.
 */
function get_theme_url()
{
    $site_id = c::getInstance()->get('site_id');
    $url = site_url('private/sites/' . $site_id . '/themes/');
    return ActionFilterHook::getInstance()->applyFilter("the_theme_url_site_{$site_id}", $url);
}

/**
 * Retrieves a URL within a site's theme directory.
 *
 * Defaults to the site's theme directory URL if no arguments are supplied.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 * @param  string $path   Optional. Extra path appended to the end of the URL, including
 *                        the relative directory if $theme is supplied. Default empty.
 * @param  string $theme  Optional. A full path to a file inside a theme.
 *                        The URL will be relative to its directory. Default empty.
 *                        Typically this is done by passing `__FILE__` as the argument.
 * @return string Site's theme URL link with optional paths appended.
 */
function themes_url($path = '', $theme = '')
{
    $site_id = c::getInstance()->get('site_id');
    $_path = ttcms()->obj['file']->normalizePath($path);
    $_theme = ttcms()->obj['file']->normalizePath($theme);

    $url = get_theme_url();
    $url = set_url_scheme($url);

    if (!empty($_theme) && is_string($_theme)) {
        $folder = basename(dirname($_theme));
        if ('.' != $folder) {
            $url .= ltrim($folder, '/');
        }
    }

    if ($_path && is_string($_path)) {
        $url .= '/' . ltrim($_path, '/');
    }

    /**
     * Filters the URL to a site's theme directory.
     *
     * @since 1.0.0
     * @param string $url       The complete URL to a site's theme directory including scheme and path.
     * @param string $_path     Path relative to the URL to a site's theme directory. Blank string
     *                          if no path is specified.
     * @param string $_theme    A site's theme file path to be relative to. Blank string if no site's theme
     *                          is specified.
     */
    return ActionFilterHook::getInstance()->applyFilter("themes_url_site_{$site_id}", $url, $_path, $_theme);
}

/**
 * Returns full base url of a site's private url.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 * @return string Site's private base url.
 */
function get_private_site_url($path = '')
{
    $site_id = c::getInstance()->get('site_id');
    $url = site_url('private/sites/' . $site_id . '/' . $path);
    return ActionFilterHook::getInstance()->applyFilter("private_site_url_{$site_id}", $url);
}

/**
 * Returns full base url of a site's private upload url.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 * @return string Site's private upload base url.
 */
function get_private_site_upload_url($path = '')
{
    $site_id = c::getInstance()->get('site_id');
    $url = get_private_site_url('uploads/' . $path);
    return ActionFilterHook::getInstance()->applyFilter("private_site_upload_url_{$site_id}", $url);
}

/**
 * Searches for plain email addresses in given $string and
 * encodes them (by default) with the help of ttcms_encode_email_str().
 *
 * Regular expression is based on based on John Gruber's Markdown.
 * http://daringfireball.net/projects/markdown/
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 * @param string $string Text with email addresses to encode
 * @return string $string Given text with encoded email addresses
 */
function ttcms_encode_email($string)
{
    // abort if $string doesn't contain a @-sign
    if (ActionFilterHook::getInstance()->applyFilter('encode_email_at_sign_check', true)) {
        if (strpos($string, '@') === false) {
            return $string;
        }
    }

    // override encoding function with the 'encode_email_method' filter
    $method = ActionFilterHook::getInstance()->applyFilter('encode_email_method', 'ttcms_encode_email_str');

    // override regex pattern with the 'encode_email_regexp' filter
    $regexp = ActionFilterHook::getInstance()->applyFilter('encode_email_regexp', '{
			(?:mailto:)?
			(?:
				[-!#$%&*+/=?^_`.{|}~\w\x80-\xFF]+
			|
				".*?"
			)
			\@
			(?:
				[-a-z0-9\x80-\xFF]+(\.[-a-z0-9\x80-\xFF]+)*\.[a-z]+
			|
				\[[\d.a-fA-F:]+\]
			)
		}xi');

    return preg_replace_callback(
        $regexp,
        function ($matches) use ($method) {
            return $method($matches[0]);
        },
        $string
    );
}

/**
 * Encodes each character of the given string as either a decimal
 * or hexadecimal entity, in the hopes of foiling most email address
 * harvesting bots.
 *
 * Based on Michel Fortin's PHP Markdown:
 * http://michelf.com/projects/php-markdown/
 * Which is based on John Gruber's original Markdown:
 * http://daringfireball.net/projects/markdown/
 * Whose code is based on a filter by Matthew Wickline, posted to
 * the BBEdit-Talk with some optimizations by Milian Wolff.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 * @param string $string Text with email addresses to encode
 * @return string $string Given text with encoded email addresses
 */
function ttcms_encode_email_str($string)
{
    $chars = str_split($string);
    $seed = mt_rand(0, (int) abs(crc32($string) / strlen($string)));

    foreach ($chars as $key => $char) {
        $ord = ord($char);

        if ($ord < 128) { // ignore non-ascii chars
            $r = ($seed * (1 + $key)) % 100; // pseudo "random function"

            if ($r > 60 && $char != '@') {
                ; // plain character (not encoded), if not @-sign
            } elseif ($r < 45) {
                $chars[$key] = '&#x' . dechex($ord) . ';'; // hexadecimal
            } else {
                $chars[$key] = '&#' . $ord . ';'; // decimal (ascii)
            }
        }
    }

    return implode('', $chars);
}

/**
 * Renders an editor.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 * @param string $selector HTML ID attribute value for the textarea and TinyMCE. Can only be /[a-z]+/.
 */
function ttcms_editor($selector = null)
{
    ttcms_enqueue_js('default', '//cdn.tinymce.com/4/tinymce.min.js');

    if ($selector == null) {
        $mce_selector = '#post_content';
    } else {
        $mce_selector = $selector;
    }

    /**
     * Filters the default theme for TinyMCE.
     *
     * @since 1.0.0
     * @param string $theme Theme used for TinyMCE.
     */
    $mce_theme = ActionFilterHook::getInstance()->applyFilter('tiny_mce_theme', 'modern');

    $plugins = [
        'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'print', 'preview', 'anchor',
        'searchreplace', 'visualblocks', 'code', 'codesample',
        'insertdatetime', 'media', 'table', 'contextmenu', 'paste', 'pagebreak'
    ];
    /**
     * Filters the list of default TinyMCE plugins.
     *
     * @since 1.0.0
     * @param array $plugins An array of default TinyMCE plugins.
     */
    $mce_plugins = ActionFilterHook::getInstance()->applyFilter('tiny_mce_plugins', $plugins);

    $mce_buttons = [
        'undo',
        'redo',
        'styleselect',
        'bold',
        'italic',
        'alignleft',
        'aligncenter',
        'alignright',
        'alignjustify',
        'bullist',
        'numlist',
        'blockquote',
        'outdent',
        'indent',
        'link',
        'image',
        'media',
        'codesample',
        'preview'
    ];
    /**
     * Filters the first-row list of TinyMCE buttons.
     *
     * @since 1.0.0
     * @param array  $buttons       First-row list of buttons.
     * @param string $mce_selector  Unique editor identifier, e.g. 'textarea'.
     */
    $mce_buttons_1 = ActionFilterHook::getInstance()->applyFilter('tiny_mce_buttons_1', $mce_buttons, $mce_selector);
    /**
     * Filters the second-row list of TinyMCE buttons.
     *
     * @since 1.0.0
     * @param array  $buttons       First-row list of buttons.
     * @param string $mce_selector  Unique editor identifier, e.g. 'textarea'.
     */
    $mce_buttons_2 = ActionFilterHook::getInstance()->applyFilter('tiny_mce_buttons_2', [], $mce_selector);
    /**
     * Filters the third-row list of TinyMCE buttons.
     *
     * @since 1.0.0
     * @param array  $buttons       First-row list of buttons.
     * @param string $mce_selector  Unique editor identifier, e.g. 'textarea'.
     */
    $mce_buttons_3 = ActionFilterHook::getInstance()->applyFilter('tiny_mce_buttons_3', [], $mce_selector);
    /**
     * Filters the default stylesheets.
     *
     * @since 1.0.0
     * @param array  $css           CSS stylesheets to include.
     * @param string $mce_selector  Unique editor identifier, e.g. 'textarea'.
     */
    $mce_css = ActionFilterHook::getInstance()->applyFilter(
        'tiny_mce_css',
        [
            '//fonts.googleapis.com/css?family=Lato:300,300i,400,400i',
            site_url('static/assets/css/tinymce.css')
        ],
        $mce_selector
    );

    /**
     * Fires immediately before TinyMCE is printed.
     *
     * @since 1.0.0
     */
    ActionFilterHook::getInstance()->doAction('before_ttcms_tiny_mce'); ?>
    <script type="text/javascript">
        tinymce.init({
            selector: "<?= $mce_selector; ?>",
            theme: "<?= $mce_theme; ?>",
            browser_spellcheck: true,
            relative_urls: false,
            remove_script_host: false,
            height: 400,
            media_live_embeds: true,
            plugins: ["<?= implode(',', $mce_plugins); ?>"],
            toolbar: "pagebreak",
            pagebreak_separator: "<!--pagebreak-->",
            link_list: [
    <?php
    foreach (tinymce_link_list() as $link) :
            echo "{title: '" . esc_html($link->getTitle()) . "', value: '" . home_url($link->getRelativeUrl()) . "'}," . "\n";
    endforeach; ?>
            ],
            toolbar1: "<?= implode(' ', $mce_buttons_1); ?>",
            toolbar2: "<?= implode(' ', $mce_buttons_2); ?>",
            toolbar3: "<?= implode(' ', $mce_buttons_3); ?>",
            autosave_ask_before_unload: true,
            content_css: [<?= '"' . implode('", "', $mce_css) . '"'; ?>],
            file_picker_callback: elFinderBrowser
        });
        function elFinderBrowser(callback, value, meta) {
            tinymce.activeEditor.windowManager.open({
                file: "<?= admin_url('elfinder/'); ?>",
                title: "elFinder 2.1",
                width: 900,
                height: 600,
                resizable: "yes"
            }, {
                oninsert: function (file) {
                    // Provide file and text for the link dialog
                    if (meta.filetype == "file") {
                        //callback("mypage.html", {text: "My text"});
                        callback(file.url);
                    }

                    // Provide image and alt text for the image dialog
                    if (meta.filetype == "image") {
                        //callback("myimage.jpg", {alt: "My alt text"});
                        callback(file.url, {alt: file.name});
                    }

                    // Provide alternative source and posted for the media dialog
                    if (meta.filetype == "media") {
                        //callback("movie.mp4", {source2: "alt.ogg", poster: "image.jpg"});
                        callback(file.url, {alt: file.name});
                    }
                }
            });
            return false;
        }
        ;
    </script>
    <?php
    /**
     * Fires immediately after TinyMCE is printed.
     *
     * @since 1.0.0
     */
    ActionFilterHook::getInstance()->doAction('after_ttcms_tiny_mce');
}

/**
 * Returns an optimized image for use.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 * @param string $image Original image file.
 * @return string Optimized image file.
 */
function ttcms_optimized_image_upload($image)
{
    if ($image === '') {
        return null;
    }
    $site_id = c::getInstance()->get('site_id');
    $raw_filename = str_replace(site_url(), '', $image);
    $new_filename = str_replace(
        get_private_site_upload_url(),
        'private/sites/' . $site_id . '/uploads/__optimized__/',
        $image
    );
    if (!file_exists($new_filename)) {
        _ttcms_image_optimizer($raw_filename, $new_filename);
    }
    return ActionFilterHook::getInstance()->applyFilter('optimized_image', $new_filename, $image, $raw_filename);
}

/**
 * Post router function.
 *
 * @file app/functions/hook.php
 *
 * @access private
 * @since 1.0.0
 */
function _ttcms_post_router()
{
    $app = \Liten\Liten::getInstance();
    $router = $app->config('routers_dir') . 'post.router.php';
    if (!ActionFilterHook::getInstance()->hasFilter('post_router')) {
        require($router);
    }
    return ActionFilterHook::getInstance()->applyFilter('post_router', $router);
}

/**
 * Adds missing files to site's cache directory.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 */
function add_files_cache_directory()
{
    $dir = c::getInstance()->get('cache_path');

    try {
        /**
         * Re-creates the cache directory with proper permissions.
         */
        ttcms()->obj['file']->mkdir($dir);
    } catch (IOException $e) {
        Cascade::getLogger('error')->error(
            sprintf(
                'IOSTATE[%s]: Forbidden: %s',
                $e->getCode(),
                $e->getMessage()
            ),
            [
                'Hook Function' => 'add_files_cache_directory'
            ]
        );
    }

    $key = _ttcms_random_lib()->generateString(25, '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ');

    if (!ttcms()->obj['file']->exists($dir . '.htaccess', false)) {
        $content = "# BEGIN Privatization" . "\n";
        $content .= "# This .htaccess file ensures that other people cannot download your files." . "\n";
        $content .= "<IfModule mod_rewrite.c>" . "\n";
        $content .= "RewriteEngine On" . "\n";
        $content .= "RewriteCond %{QUERY_STRING} !key=$key" . "\n";
        $content .= "RewriteRule (.*) - [F]" . "\n";
        $content .= "</IfModule>" . "\n";
        $content .= "# END Privatization";
        file_put_contents($dir . '.htaccess', $content);
    }

    if (!ttcms()->obj['file']->exists($dir . '.gitignore', false)) {
        $content = "*" . "\n";
        $content .= "*/" . "\n";
        $content .= "!.gitignore";
        file_put_contents($dir . '.gitignore', $content);
    }
}

/**
 * Loads javascript for backend dashboard.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 */
function admin_dashboard_js()
{
    ttcms_enqueue_js('default', site_url('static/assets/js/pages/dashboard.js'));
}

function ttcms_charset($charset = null)
{
    $select = '<select class="form-control select2" name="charset" style="width: 100%;" required>
        <option value="">&nbsp;</option>
        <option value="UTF-32"' . selected($charset, 'UTF-32', false) . '>UTF-32</option>
        <option value="UTF-16"' . selected($charset, 'UTF-16', false) . '>UTF-16</option>
        <option value="UTF-7"' . selected($charset, 'UTF-7', false) . '>UTF-7</option>
        <option value="UTF-8"' . selected($charset, 'UTF-8', false) . '>UTF-8</option>
        <option value="ASCII"' . selected($charset, 'ASCII', false) . '>ASCII</option>
        <option value="ISO-8859-1"' . selected($charset, 'ISO-8859-1', false) . '>ISO-8859-1</option>
        <option value="ISO-8859-2"' . selected($charset, 'ISO-8859-2', false) . '>ISO-8859-2</option>
        <option value="ISO-8859-3"' . selected($charset, 'ISO-8859-3', false) . '>ISO-8859-3</option>
        <option value="ISO-8859-4"' . selected($charset, 'ISO-8859-4', false) . '>ISO-8859-4</option>
        <option value="ISO-8859-5"' . selected($charset, 'ISO-8859-5', false) . '>ISO-8859-5</option>
        <option value="ISO-8859-6"' . selected($charset, 'ISO-8859-6', false) . '>ISO-8859-6</option>
        <option value="ISO-8859-7"' . selected($charset, 'ISO-8859-7', false) . '>ISO-8859-7</option>
        <option value="ISO-8859-8"' . selected($charset, 'ISO-8859-8', false) . '>ISO-8859-8</option>
        <option value="ISO-8859-9"' . selected($charset, 'ISO-8859-9', false) . '>ISO-8859-9</option>
        <option value="ISO-8859-10"' . selected($charset, 'ISO-8859-10', false) . '>ISO-8859-10</option>
        <option value="ISO-8859-13"' . selected($charset, 'ISO-8859-13', false) . '>ISO-8859-13</option>
        <option value="ISO-8859-14"' . selected($charset, 'ISO-8859-14', false) . '>ISO-8859-14</option>
        <option value="ISO-8859-15"' . selected($charset, 'ISO-8859-15', false) . '>ISO-8859-15</option>
        <option value="ISO-8859-16"' . selected($charset, 'ISO-8859-16', false) . '>ISO-8859-16</option>
        <option value="Windows-1251"' . selected($charset, 'Windows-1251', false) . '>Windows-1251</option>
        <option value="Windows-1252"' . selected($charset, 'Windows-1252', false) . '>Windows-1252</option>
        <option value="SJIS-mac"' . selected($charset, 'SJIS-mac', false) . '>SJIS-mac</option>
        <option value="UTF-8-Mobile#DOCOMO"' . selected($charset, 'UTF-8-Mobile#DOCOMO', false) . '>UTF-8-Mobile#DOCOMO</option>
        <option value="UTF-8-Mobile#KDDI-A"' . selected($charset, 'UTF-8-Mobile#KDDI-A', false) . '>UTF-8-Mobile#KDDI-A</option>
        <option value="UTF-8-Mobile#KDDI-B"' . selected($charset, 'UTF-8-Mobile#KDDI-B', false) . '>UTF-8-Mobile#KDDI-B</option>
        <option value="UTF-8-Mobile#SOFTBANK"' . selected($charset, 'UTF-8-Mobile#SOFTBANK', false) . '>UTF-8-Mobile#SOFTBANK</option>
        <option value="ISO-2022-JP-MOBILE#KDDI"' . selected($charset, 'ISO-2022-JP-MOBILE#KDDI', false) . '>ISO-2022-JP-MOBILE#KDDI</option>
        <option value="GB18030"' . selected($charset, 'GB18030', false) . '>GB18030</option>
        </select>';
    return ActionFilterHook::getInstance()->applyFilter('charset', $select, $charset);
}

/**
 * Sanitize meta value.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 * @param string $meta_key       Meta key.
 * @param mixed  $meta_value     Meta value to sanitize.
 * @param string $array_type    Type of object the meta is registered to.
 * @param string $array_subtype Optional. The subtype of the object type.
 * @return mixed Sanitized $meta_value.
 */
function sanitize_meta($meta_key, $meta_value, $array_type, $array_subtype = '')
{
    if (!empty($array_subtype) && ActionFilterHook::getInstance()->hasFilter("sanitize_{$array_type}_meta_{$meta_key}_for_{$array_subtype}")) {
        /**
         * Filters the sanitization of a specific meta key of a specific meta type and subtype.
         *
         * The dynamic portions of the hook name, `$array_type`, `$meta_key`,
         * and `$array_subtype`, refer to the metadata object type (post, user or site),
         * the meta key value, and the object subtype respectively.
         *
         * @since 1.0.0
         * @param mixed  $meta_value     Meta value to sanitize.
         * @param string $meta_key       Meta key.
         * @param string $array_type    Object type.
         * @param string $array_subtype Object subtype.
         */
        return ActionFilterHook::getInstance()->applyFilter(
            "sanitize_{$array_type}_meta_{$meta_key}_for_{$array_subtype}",
            $meta_value,
            $meta_key,
            $array_type,
            $array_subtype
        );
    }

    /**
     * Filters the sanitization of a specific meta key of a specific meta type.
     *
     * The dynamic portions of the hook name, `$array_type` and `$meta_key`,
     * refer to the metadata object type (post, user or site), the meta key
     * value respectively.
     *
     * @since 1.0.0
     * @param mixed  $meta_value      Meta value to sanitize.
     * @param string $meta_key        Meta key.
     * @param string $array_type     Object type.
     */
    return ActionFilterHook::getInstance()->applyFilter(
        "sanitize_{$array_type}_meta_{$meta_key}",
        $meta_value,
        $meta_key,
        $array_type
    );
}

/**
 * Default actions and filters.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 */
ActionFilterHook::getInstance()->addAction('ttcms_admin_head', 'head_release_meta', 5);
//ActionFilterHook::getInstance()->addAction('ttcms_admin_footer', 'admin_dashboard_js', 5);
ActionFilterHook::getInstance()->addAction('ttcms_head', 'head_release_meta', 5);
ActionFilterHook::getInstance()->addAction('ttcms_release', 'foot_release', 5);
ActionFilterHook::getInstance()->addAction('activated_plugin', 'ttcms_plugin_activate_message', 5);
ActionFilterHook::getInstance()->addAction('deactivated_plugin', 'ttcms_plugin_deactivate_message', 5);
ActionFilterHook::getInstance()->addAction('login_form_top', 'ttcms_login_form_show_message', 5);
ActionFilterHook::getInstance()->addAction('admin_notices', 'ttcms_dev_mode', 5);
ActionFilterHook::getInstance()->addAction('save_site', 'new_site_schema', 5, 3);
ActionFilterHook::getInstance()->addAction('save_site', 'create_site_directories', 5, 3);
ActionFilterHook::getInstance()->addAction('deleted_site', 'delete_site_user_meta', 5, 2);
ActionFilterHook::getInstance()->addAction('deleted_site', 'delete_site_tables', 5, 2);
ActionFilterHook::getInstance()->addAction('deleted_site', 'delete_site_directories', 5, 2);
ActionFilterHook::getInstance()->addAction('init', 'update_main_site', 5);
ActionFilterHook::getInstance()->addAction('reset_password_route', 'send_reset_password_email', 5, 2);
ActionFilterHook::getInstance()->addAction('password_change_email', 'send_password_change_email', 5, 3);
ActionFilterHook::getInstance()->addAction('email_change_email', 'send_email_change_email', 5, 2);
ActionFilterHook::getInstance()->addAction('before_router_login', 'update_main_site', 5);
ActionFilterHook::getInstance()->addAction('before_router_login', 'does_site_exist', 6);
ActionFilterHook::getInstance()->addAction('ttcms_login', 'generate_encryption_node', 5);
ActionFilterHook::getInstance()->addAction('enqueue_ttcms_editor', 'ttcms_editor', 5);
ActionFilterHook::getInstance()->addAction('flush_cache', 'add_files_cache_directory', 5);
ActionFilterHook::getInstance()->addAction('flush_cache', 'populate_usermeta_cache', 5);
ActionFilterHook::getInstance()->addAction('update_user_init', 'populate_usermeta_cache', 5);
ActionFilterHook::getInstance()->addAction('flush_cache', 'populate_postmeta_cache', 5);
ActionFilterHook::getInstance()->addAction('update_post_init', 'populate_postmeta_cache', 5);
ActionFilterHook::getInstance()->addAction('flush_cache', 'populate_options_cache', 5);
ActionFilterHook::getInstance()->addAction('maintenance_mode', 'ttcms_maintenance_mode', 1);
ActionFilterHook::getInstance()->addAction('post_create_view', 'ttcms_post_create_view', 5, 3);
ActionFilterHook::getInstance()->addAction('post_update_view', 'ttcms_post_update_view', 5, 3);
ActionFilterHook::getInstance()->addFilter('the_content', [new \TriTan\Common\Parsecode(), 'autop']);
ActionFilterHook::getInstance()->addFilter('the_content', [new \TriTan\Common\Parsecode(), 'unAutop']);
ActionFilterHook::getInstance()->addFilter('the_content', [new \TriTan\Common\Parsecode(), 'doParsecode'], 5);
ActionFilterHook::getInstance()->addFilter('the_content', 'ttcms_encode_email', EAE_FILTER_PRIORITY);
ActionFilterHook::getInstance()->addFilter('ttcms_authenticate_user', 'ttcms_authenticate', 5, 3);
ActionFilterHook::getInstance()->addFilter('ttcms_auth_cookie', 'ttcms_set_auth_cookie', 5, 2);
ActionFilterHook::getInstance()->addFilter('pre_user_login', '_trim', 5);
ActionFilterHook::getInstance()->addFilter('pre_user_email', '_trim', 5);
ActionFilterHook::getInstance()->addFilter('reassign_posts', 'reassign_posts', 5, 2);
ActionFilterHook::getInstance()->addFilter('reassign_sites', 'reassign_sites', 5, 2);
