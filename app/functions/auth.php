<?php
use TriTan\Common\Container as c;
use TriTan\Common\Acl\RoleRepository;
use TriTan\Common\Acl\RoleMapper;
use TriTan\Common\User\UserRepository;
use TriTan\Common\User\UserMapper;
use TriTan\Common\User\UserPermissionRepository;
use TriTan\Common\User\UserPermissionMapper;
use TriTan\Common\User\UserRoleRepository;
use TriTan\Common\User\UserRoleMapper;
use TriTan\Common\Context\HelperContext;
use TriTan\Common\Password\PasswordCheck;
use TriTan\Common\Password\PasswordSetMapper;
use TriTan\Common\Password\PasswordHash;
use Qubus\Hooks\ActionFilterHook;
use Qubus\Exception\Data\TypeException;
use Qubus\Exception\Http\Client\UnauthorizedException;
use Qubus\Exception\Http\Client\NotFoundException;
use Cascade\Cascade;

/**
 * Qubus CMS Auth Helper
 *
 * @since 1.0.0
 * @package Qubus CMS
 * @author Joshua Parker <josh@joshuaparker.blog>
 */

/**
 * Checks if current user has specified permission or not.
 *
 * @file app/functions/auth.php
 *
 * @since 1.0.0
 * @param string $perm Permission to check for.
 * @return bool Return true if permission matches or false otherwise.
 */
function current_user_can($perm): bool
{
    $current_user = ttcms_get_current_user();
    if ($current_user == false || empty($current_user)) {
        return false;
    }

    $qudb = app()->qudb;

    $acl = new UserPermissionRepository(new UserPermissionMapper($qudb, new HelperContext()));

    if ($acl->has($perm) && is_user_logged_in()) {
        return true;
    }
    return false;
}

/**
 * Checks if current user has specified role or not.
 *
 * @file app/functions/auth.php
 *
 * @since 1.0.0
 * @param string $role The role to check for.
 * @return bool True if user has role, false otherwise.
 */
function current_user_has_role(string $role): bool
{
    $current_user = ttcms_get_current_user();
    if ($current_user == false || empty($current_user)) {
        return false;
    }

    $qudb = app()->qudb;

    return (
        new UserRoleRepository(
            new UserRoleMapper(
                $qudb
            )
        )
    )->has($role);
}

/**
 * Returns the values of a requested role.
 *
 * @file app/functions/auth.php
 *
 * @since 1.0.0
 * @param int $role The id of the role to check for.
 * @return array Returned values of the role.
 */
function get_role_by_id($role = 0)
{
    $qudb = app()->qudb;

    try {
        $repo = (
          new RoleRepository(
              new RoleMapper(
                  $qudb,
                  new HelperContext()
              )
          )
      )->findById((int) $role);
    } catch (\PDOException $ex) {
        Cascade::getLogger('error')->error(
            sprintf(
                'SQLSTATE[%s]: %s',
                $ex->getCode(),
                $ex->getMessage()
            ),
            [
                'Auth Function' => 'get_role_by_id'
            ]
        );
    } catch (TypeException $ex) {
        Cascade::getLogger('error')->error(
            sprintf(
                'SQLSTATE[%s]: %s',
                $ex->getCode(),
                $ex->getMessage()
            ),
            [
                'Auth Function' => 'get_role_by_id'
            ]
        );
    }

    $data = [];
    $data['role'] = [
        'role_id' => $repo->getId(),
        'role_name' => $repo->getName(),
        'role_key' => $repo->getKey(),
        'role_permission' => $repo->getPermission()
    ];

    return ActionFilterHook::getInstance()->applyFilter('role_by_id', $data, $role);
}

/**
 * Retrieve user info by user_id.
 *
 * @file app/functions/auth.php
 *
 * @since 1.0.0
 * @param mixed $user_id User's id.
 * @return User|false User object on success, false on failure.
 */
function get_userdata($user_id)
{
    return get_user_by('id', $user_id);
}

/**
 * Checks if a visitor is logged in or not.
 *
 * @file app/functions/auth.php
 *
 * @since 1.0.0
 * @return bool
 */
function is_user_logged_in(): bool
{
    $user = get_user_by('id', get_current_user_id());
    return false != $user && ttcms()->obj['app']->cookies->verifySecureCookie('TTCMS_COOKIENAME');
}

/**
 * Checks if logged in user can access menu, tab, or screen.
 *
 * @file app/functions/auth.php
 *
 * @since 1.0.0
 * @param string $perm Permission to check for.
 * @return string HTML style.
 */
function ae($perm)
{
    if (!current_user_can($perm)) {
        return ' style="display:none !important;"';
    }
}

/**
 * Retrieve user info by a given field from the user's table.
 *
 * @file app/functions/auth.php
 *
 * @since 1.0.0
 * @param string $field The field to retrieve the user with.
 * @param int|string $value A value for $field (id, login or email).
 * @return User|false User object on success, false otherwise.
 */
function get_user_by($field, $value)
{
    $qudb = app()->qudb;

    try {
        $userdata = (
          new UserRepository(
              new UserMapper(
                  $qudb,
                  new HelperContext()
              )
          ))
          ->findBy($field, $value);
    } catch (\PDOException $ex) {
        Cascade::getLogger('error')->error(
            sprintf(
                'SQLSTATE[%s]: %s',
                $ex->getCode(),
                $ex->getMessage()
            ),
            [
            'Auth Function' => 'get_user_by'
        ]
        );
    } catch (TypeException $ex) {
        Cascade::getLogger('error')->error(
            sprintf(
                'SQLSTATE[%s]: %s',
                $ex->getCode(),
                $ex->getMessage()
            ),
            [
            'Auth Function' => 'get_user_by'
        ]
        );
    }

    if (!$userdata) {
        return false;
    }

    return $userdata;
}

/**
 * Logs a user in after the login information has checked out.
 *
 * @file app/functions/auth.php
 *
 * @since 1.0.0
 * @param string $login User's username or email address.
 * @param string $password User's password.
 * @param string $rememberme Whether to remember the user.
 */
function ttcms_authenticate($login, $password, $rememberme)
{
    $qudb = app()->qudb;

    $sql = "SELECT *"
            . " FROM {$qudb->base_prefix}user"
            . " WHERE user_login = ?"
            . " OR user_email = ?";

    $user = $qudb->getRow($qudb->prepare($sql, [$login, $login]), ARRAY_A);

    if (false == $user) {
        ttcms()->obj['flash']->error(
            sprintf(
                t__(
                    'Sorry, an account for <strong>%s</strong> does not exist.'
                ),
                $login
            ),
            ttcms()->obj['app']->req->server['HTTP_REFERER']
        );
        return false;
    }

    /**
     * Filters the authentication cookie.
     *
     * @since 1.0.0
     * @param object $_user User data object.
     * @param string $rememberme Whether to remember the user.
     */
    try {
        ActionFilterHook::getInstance()->applyFilter('ttcms_auth_cookie', $user, $rememberme);
    } catch (UnauthorizedException $ex) {
        Cascade::getLogger('error')->error(
            sprintf(
                'AUTHSTATE[%s]: Unauthorized: %s',
                $ex->getCode(),
                $ex->getMessage()
            )
        );
    }

    $redirect_to = ActionFilterHook::getInstance()->applyFilter(
        'authenticate_redirect_to',
        (
            ttcms()->obj['app']->req->post['redirect_to'] != null ? ttcms()->obj['app']->req->post['redirect_to'] : admin_url()
        )
    );

    ttcms()->obj['flash']->success(
        sprintf(
            t__(
                'Login was successful. Welcome <strong>%s</strong> to the dashboard.'
            ),
            get_name(esc_html($user['user_id']))
        ),
        $redirect_to
    );
}

/**
 * Checks a user's login information.
 *
 * @file app/functions/auth.php
 *
 * @since 1.0.0
 * @param string $login User's username or email address.
 * @param string $password User's password.
 * @param string $rememberme Whether to remember the user.
 * @return null|bool Returns credentials if valid, null or false otherwise.
 */
function ttcms_authenticate_user($login, $password, $rememberme)
{
    $qudb = app()->qudb;

    if (isset($login) && !is_string($login)) {
        ttcms()->obj['flash']->error(
            t__(
                '<strong>ERROR</strong>: The username/email is malformed.'
            ),
            ttcms()->obj['app']->req->server['HTTP_REFERER']
        );
        exit();
    }

    if (isset($password) && !is_string($password)) {
        ttcms()->obj['flash']->error(
            t__(
                '<strong>ERROR</strong>: The password is malformed.'
            ),
            ttcms()->obj['app']->req->server['HTTP_REFERER']
        );
        exit();
    }

    if (isset($rememberme) && !is_string($rememberme)) {
        ttcms()->obj['flash']->error(
            t__(
                '<strong>ERROR</strong>: The remember me option is malformed.'
            ),
            ttcms()->obj['app']->req->server['HTTP_REFERER']
        );
        exit();
    }

    if (empty($login) || empty($password)) {
        if (empty($login)) {
            ttcms()->obj['flash']->error(
                t__(
                    '<strong>ERROR</strong>: The username/email field is empty.'
                ),
                ttcms()->obj['app']->req->server['HTTP_REFERER']
            );
        }

        if (empty($password)) {
            ttcms()->obj['flash']->error(
                t__(
                    '<strong>ERROR</strong>: The password field is empty.'
                ),
                ttcms()->obj['app']->req->server['HTTP_REFERER']
            );
        }
        return null;
    }

    if (validate_email($login)) {
        $user = get_user_by('email', $login);

        if (false == $user) {
            ttcms()->obj['flash']->error(
                t__(
                    '<strong>ERROR</strong>: Invalid email address.'
                ),
                ttcms()->obj['app']->req->server['HTTP_REFERER']
            );
            return false;
        }
    } else {
        $user = get_user_by('login', $login);

        if (false == $user) {
            ttcms()->obj['flash']->error(
                t__(
                    '<strong>ERROR</strong>: Invalid username.'
                ),
                ttcms()->obj['app']->req->server['HTTP_REFERER']
            );
            return false;
        }
    }

    $auth = new PasswordCheck(
        new PasswordSetMapper(
            $qudb,
            new PasswordHash(
                ActionFilterHook::getInstance()
            )
        ),
        new PasswordHash(
            ActionFilterHook::getInstance()
        ),
        ActionFilterHook::getInstance()
    );

    if (!$auth->check($password, $user->getPassword(), $user->getId())) {
        ttcms()->obj['flash']->error(
            t__(
                '<strong>ERROR</strong>: The password you entered is incorrect.'
            ),
            ttcms()->obj['app']->req->server['HTTP_REFERER']
        );
        return false;
    }

    /**
     * Filters log in details.
     *
     * @since 1.0.0
     * @param string $login User's username or email address.
     * @param string $password User's password.
     * @param string $rememberme Whether to remember the user.
     */
    $user = ActionFilterHook::getInstance()->applyFilter('ttcms_authenticate_user', $login, $password, $rememberme);

    return $user;
}

/**
 * Sets auth cookie.
 *
 * @file app/functions/auth.php
 *
 * @since 1.0.0
 * @param array $user           User data array.
 * @param string $rememberme    Should user be remembered for a length of time?
 * @throws \\Qubus\\Exception\\Http\\Client\\UnauthorizedException
 */
function ttcms_set_auth_cookie($user, $rememberme = '')
{
    if (!is_array($user)) {
        throw new UnauthorizedException(esc_html__('"$user" should be an array.'), 4011);
    }

    if (isset($rememberme)) {
        /**
         * Ensure the browser will continue to send the cookie until it expires.
         *
         * @since 1.0.0
         */
        $expire = ActionFilterHook::getInstance()->applyFilter(
            'auth_cookie_expiration',
            (c::getInstance()->get('option')->read('cookieexpire') !== '') ?
            c::getInstance()->get('option')->read('cookieexpire') :
            ttcms()->obj['app']->config('cookies.lifetime')
        );
    } else {
        /**
         * Ensure the browser will continue to send the cookie until it expires.
         *
         * @since 1.0.0
         */
        $expire = ActionFilterHook::getInstance()->applyFilter(
            'auth_cookie_expiration',
            (ttcms()->obj['app']->config('cookies.lifetime') !== '') ?
            ttcms()->obj['app']->config('cookies.lifetime') :
            86400
        );
    }

    $auth_cookie = [
        'key' => 'TTCMS_COOKIENAME',
        'user_id' => (int) esc_html($user['user_id']),
        'user_login' => (string) esc_html($user['user_login']),
        'remember' => (isset($rememberme) ? 'yes' : 'no'),
        'exp' => (int) $expire + time()
    ];

    /**
     * Fires immediately before the secure authentication cookie is set.
     *
     * @since 1.0.0
     * @param string $auth_cookie Authentication cookie.
     * @param int    $expire  Duration in seconds the authentication cookie should be valid.
     */
    ActionFilterHook::getInstance()->doAction('set_auth_cookie', $auth_cookie, $expire);

    ttcms()->obj['app']->cookies->setSecureCookie($auth_cookie);
}

/**
 * Removes all cookies associated with authentication.
 *
 * @file app/functions/auth.php
 *
 * @since 1.0.0
 */
function ttcms_clear_auth_cookie()
{
    /**
     * Fires just before the authentication cookies are cleared.
     *
     * @since 1.0.0
     */
    ActionFilterHook::getInstance()->doAction('clear_auth_cookie');

    $vars1 = [];
    parse_str(ttcms()->obj['app']->cookies->get('TTCMS_COOKIENAME'), $vars1);
    /**
     * Checks to see if the cookie is exists on the server.
     * It it exists, we need to delete it.
     */
    $file1 = ttcms()->obj['app']->config('cookies.savepath') . 'cookies.' . $vars1['data'];
    try {
        if (ttcms()->obj['file']->exists($file1)) {
            unlink($file1);
        }
    } catch (NotFoundException $e) {
        Cascade::getLogger('error')->error(
            sprintf(
                'FILESTATE[%s]: File not found: %s',
                $e->getCode(),
                $e->getMessage()
            )
        );
    }

    $vars2 = [];
    parse_str(ttcms()->obj['app']->cookies->get('SWITCH_USERBACK'), $vars2);
    /**
     * Checks to see if the cookie exists on the server.
     * It it exists, we need to delete it.
     */
    $file2 = ttcms()->obj['app']->config('cookies.savepath') . 'cookies.' . $vars2['data'];
    if (ttcms()->obj['file']->exists($file2, false)) {
        @unlink($file2);
    }

    /**
     * After the cookie is removed from the server,
     * we know need to remove it from the browser and
     * redirect the user to the login page.
     */
    ttcms()->obj['app']->cookies->remove('TTCMS_COOKIENAME');
    ttcms()->obj['app']->cookies->remove('SWITCH_USERBACK');
}

/**
 * Shows error messages on login form.
 *
 * @file app/functions/auth.php
 *
 * @since 1.0.0
 */
function ttcms_login_form_show_message()
{
    echo ActionFilterHook::getInstance()->applyFilter('login_form_show_message', ttcms()->obj['flash']->showMessage());
}

/**
 * Retrieves data from a secure cookie.
 *
 * @file app/functions/auth.php
 *
 * @since 1.0.0
 * @param string $key COOKIE key.
 * @return array|false Cookie data or false.
 */
function get_secure_cookie_data($key)
{
    if (ttcms()->obj['app']->cookies->verifySecureCookie($key)) {
        $data = ttcms()->obj['app']->cookies->getSecureCookie($key);
        return $data;
    }
    return false;
}
