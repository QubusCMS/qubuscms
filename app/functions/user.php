<?php
use TriTan\Common\Date;
use TriTan\Common\Mailer;
use TriTan\Common\User\User;
use TriTan\Common\User\UserRepository;
use TriTan\Common\User\UserMapper;
use TriTan\Common\Acl\RoleRepository;
use TriTan\Common\Acl\RoleMapper;
use TriTan\Common\Acl\PermissionRepository;
use TriTan\Common\Acl\PermissionMapper;
use TriTan\Common\Context\HelperContext;
use TriTan\Common\Password\PasswordHash;
use TriTan\Common\MetaData;
use TriTan\Common\Utils;
use TriTan\NodeQ;
use TriTan\Common\Container as c;
use Qubus\Hooks\ActionFilterHook;
use Qubus\Exception\Exception;
use Qubus\Exception\Data\TypeException;
use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use Defuse\Crypto\Exception\BadFormatException;
use Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException;
use Cascade\Cascade;

/**
 * Qubus CMS User Functions
 *
 * @license GPLv3
 *
 * @since       1.0.0
 * @package     Qubus CMS
 * @author      Joshua Parker <josh@joshuaparker.blog>
 */

/**
 * Used on the Role screen for permissions.
 *
 * @file app/functions/user.php
 *
 * @since 1.0.0
 * @param int $id Role id.
 */
function role_perm(int $id = 0)
{
    $qudb = app()->qudb;

    try {
        $role = (
          new RoleRepository(
              new RoleMapper(
                  $qudb
              )
          )
      )->findById($id);
        $perm = ttcms()->obj['serializer']->unserialize($role->getPermission());

        $sql = (
          new PermissionRepository(
              new PermissionMapper(
                  $qudb
              )
          )
      )->findAll('full');
    } catch (\PDOException $ex) {
        Cascade::getLogger('error')->error(
            sprintf(
                'SQLSTATE[%s]: %s',
                $ex->getCode(),
                $ex->getMessage()
            ),
            [
                'User Function' => 'role_perm'
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
                'User Function' => 'role_perm'
            ]
        );
    }

    foreach ($sql as $r) {
        echo '<tr>
                <td>' . $r['Name'] . '</td>
                <td class="text-center">';
        if (is_array($perm) && in_array($r['Key'], $perm)) {
            echo '<input type="checkbox" class="flat-red" name="role_perm[]" value="' . $r['Key'] . '" checked="checked" />';
        } else {
            echo '<input type="checkbox" class="flat-red" name="role_perm[]" value="' . $r['Key'] . '" />';
        }
        echo '</td>
            </tr>';
    }
}

function user_perm(int $id)
{
    $qudb = app()->qudb;

    $array = [];

    $pp = $qudb->getResults(
        $qudb->prepare(
            "SELECT * FROM {$qudb->base_prefix}user_perms WHERE user_id = ?",
            [
                $id
            ]
        ),
        ARRAY_A
    );

    foreach ($pp as $r) {
        $array[] = $r;
    }

    $userPerm = ttcms()->obj['serializer']->unserialize(esc_html($r['user_perms_permission']));
    /**
     * Select the role(s) of the user who's
     * userID = $id
     */
    $array1 = [];

    $pr = $qudb->getResults(
        $qudb->prepare(
            "SELECT * FROM {$qudb->base_prefix}user_roles WHERE user_id = ?",
            [
                $id
            ]
        ),
        ARRAY_A
    );

    foreach ($pr as $r1) {
        $array1[] = $r1;
    }
    /**
     * Select all the permissions from the role(s)
     * that are connected to the selected user.
     */
    //$array2 = [];
    $role = $qudb->getRow(
        $qudb->prepare(
            "SELECT * FROM {$qudb->base_prefix}role WHERE role_id = ?",
            [
                esc_html($r1['role_id'])
            ]
        ),
        ARRAY_A
    );
    /* foreach ($role as $r2) {
      $array2[] = $r2;
      } */
    $perm = ttcms()->obj['serializer']->unserialize(esc_html($role['role_permission']));
    $permission = $qudb->getResults("SELECT * FROM {$qudb->base_prefix}permission");
    foreach ($permission as $row) {
        echo '
            <tr>
                <td>' . esc_html($row['permission_name']) . '</td>
                <td class="text-center">';
        if (is_array($perm) && in_array(esc_html($row['permission_key']), $perm)) {
            echo '<input type="checkbox" name="user_perms_permission[]" value="' . esc_html($row['permission_key']) . '" checked="checked" disabled="disabled" />';
        } elseif ($userPerm != '' && in_array(esc_html($row['permission_key']), $userPerm)) {
            echo '<input type="checkbox" name="user_perms_permission[]" value="' . esc_html($row['permission_key']) . '" checked="checked" />';
        } else {
            echo '<input type="checkbox" name="user_perms_permission[]" value="' . esc_html($row['permission_key']) . '" />';
        }
        echo '</td>
            </tr>';
    }
}

/**
 * Print a dropdown list of users.
 *
 * @file app/functions/user.php
 *
 * @since 1.0.0
 * @param int $active If working with active record, it will be the user's id.
 * @return array Dropdown list of users.
 */
function get_users_dropdown($active = null)
{
    $qudb = app()->qudb;

    $list_users = $qudb->from($qudb->base_prefix . 'user')
        ->where('user_id')->notIn(function ($query) use ($qudb) {
            $query->from($qudb->base_prefix . 'usermeta')
                  ->distinct()
                  ->where('meta_key')->like("$qudb->prefix%")
                  ->select('user_id');
        })
        ->select()
        ->fetchAssoc()
        ->all();

    foreach ($list_users as $user) {
        echo '<option value="' . (int) esc_html($user['user_id']) . '"' . selected((int) esc_html($user['user_id']), $active, false) . '>' . get_name((int) esc_html($user['user_id'])) . '</option>';
    }
}

/**
 * Get the current user's ID
 *
 * @file app/functions/user.php
 *
 * @since 1.0.0
 * @return int The current user's ID, or 0 if no user is logged in.
 */
function get_current_user_id() : int
{
    if (!ttcms()->obj['app']->cookies->verifySecureCookie('TTCMS_COOKIENAME')) {
        return (int) 0;
    }

    $cookie = get_secure_cookie_data('TTCMS_COOKIENAME');
    if ($cookie->user_id <= 0) {
        return (int) 0;
    }
    return (int) $cookie->user_id;
}

/**
 * Returns object of data for current user.
 *
 * @file app/functions/user.php
 *
 * @since 1.0.0
 * @return User
 */
function ttcms_get_current_user()
{
    $user = get_userdata(get_current_user_id());
    return $user;
}

/**
 * Returns the name of a particular user.
 *
 * Uses `get_name` filter.
 *
 * @file app/functions/user.php
 *
 * @since 1.0.0
 * @param int $id User ID.
 * @return string User's name.
 */
function get_name(int $id, bool $reverse = false)
{
    if ('' == ttcms()->obj['util']->trim($id)) {
        throw new Exception(
            esc_html__(
                'Invalid user ID: empty ID given.'
            ),
            'invalid_id'
        );
    }

    if (!is_numeric($id)) {
        throw new Exception(
            esc_html__(
                'Invalid user id: user id must be numeric.'
            ),
            'invalid_id'
        );
    }

    $name = get_user_by('id', $id);

    if ($reverse) {
        $_name = $name->getFname() . ' ' . $name->getLname();
    } else {
        $_name = $name->getLname() . ', ' . $name->getFname();
    }

    return ActionFilterHook::getInstance()->applyFilter('get_name', $_name);
}

/**
 * Shows selected user's initials instead of
 * his/her's full name.
 *
 * Uses `get_initials` filter.
 *
 * @file app/functions/user.php
 *
 * @since 1.0.0
 * @param int $id       User ID
 * @param int $initials Number of initials to show.
 * @return string User's initials.
 */
function get_initials(int $id, int $initials = 2)
{
    if ('' == ttcms()->obj['util']->trim($id)) {
        throw new Exception(
            esc_html__(
                'Invalid user ID: empty ID given.'
            ),
            'invalid_id'
        );
    }

    if (!is_numeric($id)) {
        throw new Exception(
            esc_html__(
                'Invalid user id: user id must be numeric.'
            ),
            'invalid_id'
        );
    }

    $name = get_user_by('id', $id);

    if ($initials == 2) {
        $_initials = mb_substr($name->getFname(), 0, 1, 'UTF-8') . '. ' . mb_substr($name->getLname(), 0, 1, 'UTF-8') . '.';
    } else {
        $_initials = $name->getLname() . ', ' . mb_substr($name->getFname(), 0, 1, 'UTF-8') . '.';
    }

    return ActionFilterHook::getInstance()->applyFilter('get_initials', $_initials);
}

/**
 * Retrieve requested field from usermeta document based on user's id.
 *
 * @file app/functions/user.php
 *
 * @since 1.0.0
 * @param string $id    User ID.
 * @param string $field Data requested of particular user.
 * @return mixed
 */
function get_user_value(int $id, string $field)
{
    $value = get_user_by('id', $id);
    return $value->{$field};
}

/**
 * Retrieves a list of roles from the roles table.
 *
 * @file app/functions/user.php
 *
 * @since 1.0.0
 * @return array
 */
function get_perm_roles()
{
    $qudb = app()->qudb;

    $query = $qudb->getResults("SELECT * FROM {$qudb->base_prefix}role", ARRAY_A);

    foreach ($query as $row) {
        echo '<option value="' . (int) esc_html($row['role_id']) . '">' . esc_html($row['role_name']) . '</option>' . "\n";
    }
}

/**
 * Checks whether the given username exists.
 *
 * Uses `username_exists` filter.
 *
 * @file app/functions/user.php
 *
 * @since 1.0.0
 * @param string $username Username to check.
 * @return int|false The user's ID on success or false on failure.
 */
function username_exists(string $username)
{
    if ($user = get_user_by('login', $username)) {
        $user_id = (int) $user->getId();
    } else {
        $user_id = false;
    }

    /**
     * Filters whether the given username exists or not.
     *
     * @since 1.0.0
     * @param int|false $user_id    The user's user_id on success or false on failure.
     * @param string    $username   Username to check.
     */
    return ActionFilterHook::getInstance()->applyFilter('username_exists', $user_id, $username);
}

/**
 * Checks whether the given email exists.
 *
 * Uses `email_exists` filter.
 *
 * @file app/functions/user.php
 *
 * @since 1.0.0
 * @param string $email Email to check.
 * @return int|false The user's ID on success or false on failure.
 */
function email_exists(string $email)
{
    if ($user = get_user_by('email', $email)) {
        $user_id = (int) $user->getId();
    } else {
        $user_id = false;
    }

    /**
     * Filters whether the given email exists or not.
     *
     * @since 1.0.0
     * @param int|false $user_id    The user's user_id on success, and false on failure.
     * @param string    $email      Email to check.
     */
    return ActionFilterHook::getInstance()->applyFilter('email_exists', $user_id, $email);
}

/**
 * Checks whether the given username is valid.
 *
 * Uses `validate_username` filter.
 *
 * Example Usage:
 *
 *      if(validate_username('batman')) {
 *          //do something;
 *      }
 *
 * @file app/functions/user.php
 *
 * @since 1.0.0
 * @param string $username Username to check.
 * @return bool Whether given username is valid.
 */
function validate_username(string $username)
{
    $sanitize = (new TriTan\Common\Sanitizer(ActionFilterHook::getInstance()))->username($username, true);
    $valid = \TriTan\Validators::validateUsername($sanitize);

    /**
     * Filters whether the given username is valid or not.
     *
     * @since 1.0.0
     * @param bool   $valid    Whether given username is valid.
     * @param string $username Username to check.
     */
    return ActionFilterHook::getInstance()->applyFilter('validate_username', $valid, $username);
}

/**
 * Validates an email address.
 *
 * Uses `validate_email` filter.
 *
 * Example Usage:
 *
 *      if(validate_email('email@gmail.com')) {
 *          //do something;
 *      }
 *
 * @file app/functions/user.php
 *
 * @since 1.0.0
 * @param string $email Email address to validate.
 * @return bool True if valid, false otherwise.
 */
function validate_email(string $email)
{
    $valid = \TriTan\Validators::validateEmail($email);

    /**
     * Filters whether the given email is valid or not.
     *
     * @since 1.0.0
     * @param bool   $valid Whether given email is valid.
     * @param string $email Email to check.
     */
    return ActionFilterHook::getInstance()->applyFilter('validate_email', $valid, $email);
}

/**
 * Adds label to user's status.
 *
 * @file app/functions/user.php
 *
 * @since 1.0.0
 * @param string $status
 * @return string User's status
 */
function ttcms_user_status_label(string $status)
{
    $label = [
        'A' => 'label-success',
        'I' => 'label-danger'
    ];

    /**
     * Filters the label result.
     *
     * @since 1.0.0
     * @param array $label User's label.
     */
    return ActionFilterHook::getInstance()->applyFilter('user_status_label', $label[$status], $status);
}

/**
 * Retrieve a list of available user roles.
 *
 * @file app/functions/user.php
 *
 * @since 1.0.0
 * @param int $active
 * @return array
 */
function get_user_roles($active = null)
{
    $qudb = app()->qudb;

    $roles = $qudb->getResults("SELECT * FROM {$qudb->base_prefix}role", ARRAY_A);

    foreach ($roles as $role) {
        echo '<option value="' . (string) esc_html($role['role_key']) . '"' . selected((int) $active, (int) esc_html($role['role_id']), false) . '>' . esc_html($role['role_name']) . '</option>';
    }
}

/**
 * Retrieve a list of all users.
 *
 * @file app/functions/user.php
 *
 * @since 1.0.0
 * @param string|null $active
 * @return array
 */
function get_users_list($active = null)
{
    $qudb = app()->qudb;

    try {
        $users = (
          new UserRepository(
              new UserMapper(
                  $qudb,
                  new HelperContext()
              )
          )
      )->findAll();
    } catch (\PDOException $ex) {
        Cascade::getLogger('error')->error(
            sprintf(
                'SQLSTATE[%s]: %s',
                $ex->getCode(),
                $ex->getMessage()
            ),
            [
                'User Function' => 'get_users_list'
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
                'User Function' => 'get_user_list'
            ]
        );
    }

    foreach ($users as $user) {
        echo '<option value="' . (int) $user->getId() . '"' . selected($active, (int) $user->getId(), false) . '>' . get_name((int) $user->getId()) . '</option>';
    }
}

/**
 * Sanitize user meta value.
 *
 * @since 1.0.0
 * @param string $meta_key       Meta key.
 * @param mixed  $meta_value     Meta value to sanitize.
 * @param string $object_subtype Optional. The subtype of the object type.
 * @return mixed Sanitized $meta_value.
 */
function sanitize_user_meta($meta_key, $meta_value, $object_subtype = '')
{
    $qudb = app()->qudb;

    return (
        new MetaData(
            $qudb,
            new HelperContext()
        )
    )->sanitize($meta_key, $meta_value, 'user', $object_subtype);
}

/**
 * Retrieve user meta field for a user.
 *
 * @file app/functions/user.php
 *
 * @since 1.0.0
 * @param int    $user_id User ID.
 * @param string $key     Optional. The meta key to retrieve. By default, returns data for all keys.
 * @param bool   $single  Whether to return a single value.
 * @return array|string Will be an array if $single is false. Will be value of meta data field if $single is true.
 */
function get_user_meta(int $user_id, string $key = '', bool $single = false)
{
    $qudb = app()->qudb;

    return (
        new MetaData(
            $qudb,
            new HelperContext()
        )
    )->read('user', $user_id, $key, $single);
}

/**
 * Get user meta data by meta ID.
 *
 * @file app/functions/user.php
 *
 * @since 1.0.0
 * @param int $mid
 * @return array|bool
 */
function get_user_meta_by_mid(int $mid)
{
    $qudb = app()->qudb;

    return (
        new MetaData(
            $qudb,
            new HelperContext()
        )
    )->readByMid('user', $mid);
}

/**
 * Update user meta field based on user ID.
 *
 * Use the $prev_value parameter to differentiate between meta fields with the
 * same key and user ID.
 *
 * If the meta field for the user does not exist, it will be added.
 *
 * @file app/functions/user.php
 *
 * @since 1.0.0
 * @param int    $user_id    User ID.
 * @param string $meta_key   Metadata key.
 * @param mixed  $meta_value Metadata value.
 * @param mixed  $prev_value Optional. Previous value to check before removing.
 * @return int|bool Meta ID if the key didn't exist, true on successful update, false on failure.
 */
function update_user_meta(int $user_id, string $meta_key, $meta_value, $prev_value = '')
{
    $qudb = app()->qudb;

    return (
        new MetaData(
            $qudb,
            new HelperContext()
        )
    )->update('user', $user_id, $meta_key, $meta_value, $prev_value);
}

/**
 * Update user meta data by meta ID.
 *
 * @file app/functions/user.php
 *
 * @since 1.0.0
 * @param int $mid
 * @param string $meta_key
 * @param mixed $meta_value
 * @return bool
 */
function update_user_meta_by_mid(int $mid, string $meta_key, $meta_value)
{
    $_meta_key = ttcms()->obj['util']->unslash($meta_key);
    $_meta_value = ttcms()->obj['util']->unslash($meta_value);
    $qudb = app()->qudb;
    return (
        new MetaData(
            $qudb,
            new HelperContext()
        )
    )->updateByMid('user', $mid, $_meta_key, $_meta_value);
}

/**
 * Adds meta data to a user.
 *
 * @file app/functions/user.php
 *
 * @since 1.0.0
 * @param int    $user_id    User ID.
 * @param string $meta_key   Metadata name.
 * @param mixed  $meta_value Metadata value.
 * @param bool   $unique     Optional. Whether the same key should not be added. Default false.
 * @return int|false Meta ID on success, false on failure.
 */
function add_user_meta(int $user_id, string $meta_key, $meta_value, bool $unique = false)
{
    $qudb = app()->qudb;

    return (
        new MetaData(
            $qudb,
            new HelperContext()
        )
    )->create('user', $user_id, $meta_key, $meta_value, $unique);
}

/**
 * Remove metadata matching criteria from a user.
 *
 * You can match based on the key, or key and value. Removing based on key and
 * value, will keep from removing duplicate metadata with the same key. It also
 * allows removing all metadata matching key, if needed.
 *
 * @file app/functions/user.php
 *
 * @since 1.0.0
 * @param int    $user_id    User ID
 * @param string $meta_key   Metadata name.
 * @param mixed  $meta_value Optional. Metadata value.
 * @return bool True on success, false on failure.
 */
function delete_user_meta(int $user_id, string $meta_key, $meta_value = '')
{
    $qudb = app()->qudb;

    return (
        new MetaData(
            $qudb,
            new HelperContext()
        )
    )->delete('user', $user_id, $meta_key, $meta_value);
}

/**
 * Delete user meta data by meta ID.
 *
 * @file app/functions/user.php
 *
 * @since 1.0.0
 * @param int $mid
 * @return bool
 */
function delete_user_meta_by_mid(int $mid)
{
    $qudb = app()->qudb;

    return (
        new MetaData(
            $qudb,
            new HelperContext()
        )
    )->deleteByMid('user', $mid);
}

/**
 * Retrieve user option that can be either per Site or global.
 *
 * If the user ID is not given, then the current user will be used instead. If
 * the user ID is given, then the user data will be retrieved. The filter for
 * the result, will also pass the original option name and finally the user data
 * object as the third parameter.
 *
 * The option will first check for the per site name and then the global name.
 *
 * Uses `get_user_option_$option` filter.
 *
 * @file app/functions/user.php
 *
 * @since 1.0.0
 * @param string $option User option name.
 * @param int    $user   Optional. User ID.
 * @return string|false User option value on success or false on failure.
 */
function get_user_option(string $option, int $user = 0)
{
    if (empty($user)) {
        $user = get_current_user_id();
    }

    if (!$user = get_userdata($user)) {
        return false;
    }

    $prefix = app()->qudb->prefix;
    if ($user->hasProp($prefix . $option)) {
        $result = $user->get($prefix . $option);
    } elseif ($user->hasProp($option)) {
        $result = $user->get($option);
    } else {
        return false;
    }

    /**
     * Filters a specific user option value.
     *
     * The dynamic portion of the hook name, `$option`, refers to the user option name.
     *
     * @since 1.0.0
     * @param mixed     $result Value for the user's option.
     * @param string    $option Name of the option being retrieved.
     * @param int       $user   ID of the user whose option is being retrieved.
     */
    return ActionFilterHook::getInstance()->applyFilter("get_user_option_{$option}", $result, $option, $user);
}

/**
 * Update user option with global site capability.
 *
 * User options are just like user metadata except that they have support for
 * global site options. If the 'global' parameter is false, which it is by default
 * it will prepend the TriTan CMS table prefix to the option name.
 *
 * Deletes the user option if $newvalue is empty.
 *
 * @file app/functions/user.php
 *
 * @since 1.0.0
 * @param int    $user_id     User ID.
 * @param string $option_name User option name.
 * @param mixed  $newvalue    User option value.
 * @param bool   $global      Optional. Whether option name is global or site specific.
 *                            Default false (site specific).
 * @return int|bool User meta ID if the option didn't exist, true on successful update,
 *                  false on failure.
 */
function update_user_option(int $user_id, string $option_name, $newvalue, bool $global = false)
{
    $qudb = app()->qudb;

    if (!$global) {
        $option_name = $qudb->prefix . $option_name;
    }

    return update_user_meta($user_id, $option_name, $newvalue);
}

/**
 * Delete user option with global site capability.
 *
 * User options are just like user metadata except that they have support for
 * global site options. If the 'global' parameter is false, which it is by default
 * it will prepend the TriTan CMS table prefix to the option name.
 *
 * @file app/functions/user.php
 *
 * @since 1.0.0
 * @param int    $user_id           User ID
 * @param string $option_name       User option name.
 * @param bool   $global            Optional. Whether option name is global or site specific.
 *                                  Default false (site specific).
 * @return bool True on success or false on failure.
 */
function delete_user_option(int $user_id, string $option_name, bool $global = false)
{
    $qudb = app()->qudb;

    if (!$global) {
        $option_name = $qudb->prefix. $option_name;
    }

    return delete_user_meta($user_id, $option_name);
}

/**
 * Insert a user into the database.
 *
 * Most of the `$userdata` array fields have filters associated with the values. Exceptions are
 * 'user_id', 'user_url', 'user_admin_layout', 'user_admin_sidebar', 'user_admin_skin',
 * 'user_registered' and 'user_modified'. The filters have the prefix 'pre_' followed by
 * the field name. An example using 'user_bio' would have the filter called, 'pre_user_bio' that
 * can be hooked into.
 *
 * @file app/functions/user.php
 *
 * @since 1.0.0
 * @param array|object|User $userdata {
 *     An array, object or User object of user data arguments.
 *
 *     @type int        $user_id               User's ID. If supplied, the user will be updated.
 *     @type string     $user_pass             The plain-text user password.
 *     @type string     $user_login            The user's login username.
 *     @type string     $user_fname            The user's first name.
 *     @type string     $user_lname            The user's last name.
 *     @type string     $user_bio              The user's biographical description.
 *     @type string     $user_email            The user's email address.
 *     @type string     $user_url              The user's url.
 *     @type string     $user_status           The user's status.
 *     @type int        $user_admin_layout     The user's admin layout option.
 *     @type int        $user_admin_sidebar    The user's admin sidebar option
 *     @type string     $user_admin_skin       The user's admin skin option.
 *     @type string     $user_registered       Date the user registered. Format is 'Y-m-d H:i:s'.
 *     @type string     $user_modified         Date the user's account was updated. Format is 'Y-m-d H:i:s'.
 * }
 * @return int|Exception The newly created user's user_id or throws an exception if the user could not
 *                      be created.
 */
function ttcms_insert_user($userdata)
{
    $qudb = app()->qudb;

    if ($userdata instanceof \stdClass) {
        $userdata = get_object_vars($userdata);
    } elseif ($userdata instanceof User) {
        $userdata = $userdata->toArray();
    }

    // Are we updating or creating?
    if (!empty($userdata['user_id'])) {
        $update = true;
        $user_id = (int) $userdata['user_id'];
        $old_user_data = get_userdata($user_id);

        if (!$old_user_data) {
            throw new Exception(
                esc_html__(
                    'Invalid user id.'
                ),
                'invalid_id'
            );
        }

        // hashed in ttcms_update_user(), plaintext if called directly
        $user_pass = !empty($userdata['user_pass']) ? $userdata['user_pass'] : $old_user_data->getPassword();

        /**
         * Create a new user object.
         */
        $user = new User();
        $user->setId($user_id);
        $user->setPassword($user_pass);
    } else {
        $update = false;

        // Hash the password
        $user_pass = (new PasswordHash(ActionFilterHook::getInstance()))->hash($userdata['user_pass']);

        /**
         * Create a new user object.
         */
        $user = new User();
        $user->setPassword($user_pass);
    }

    // Store values to save in user meta.
    $meta = [];

    $raw_user_login = $userdata['user_login'];
    $sanitized_user_login = ttcms()->obj['sanitizer']->username($raw_user_login, true);
    /**
     * Filters a username after it has been sanitized.
     *
     * This filter is called before the user is created or updated.
     *
     * @since 1.0.0
     * @param string $sanitized_user_login Username after it has been sanitized.
     * @param string $raw_user_login The user's login.
     */
    $pre_user_login = ActionFilterHook::getInstance()->applyFilter(
        'pre_user_login',
        (string) $sanitized_user_login,
        (string) $raw_user_login
    );

    //Remove any non-printable chars from the login string to see if we have ended up with an empty username
    $user_login = ttcms()->obj['util']->trim($pre_user_login);

    // user_login must be between 0 and 60 characters.
    if (empty($user_login)) {
        throw new Exception(
            esc_html__(
                'Cannot create a user with an empty login name.'
            ),
            'invalid_login'
        );
    } elseif (mb_strlen($user_login) > 60) {
        throw new Exception(
            esc_html__(
                'Username may not be longer than 60 characters.'
            ),
            'exceeded_limit'
        );
    }

    if (!$update && username_exists($user_login)) {
        throw new Exception(
            esc_html__(
                'Sorry, that username already exists!'
            ),
            'duplicate_login'
        );
    }

    /**
     * Filters the list of blacklisted usernames.
     *
     * @since 1.0.0
     * @param array $usernames Array of blacklisted usernames.
     */
    $illegal_logins = (array) ActionFilterHook::getInstance()->applyFilter('illegal_user_logins', blacklisted_usernames());

    if (in_array(strtolower($user_login), array_map('strtolower', $illegal_logins))) {
        throw new Exception(
            sprintf(
                t__(
                    'Sorry, the username <strong>%s</strong> is not allowed.'
                ),
                $user_login
            ),
            'invalid_username'
        );
    }
    $meta['username'] = $user_login;
    $user->setLogin($user_login);

    $raw_user_url = $userdata['user_url'];
    $sanitized_user_url = ttcms()->obj['sanitizer']->item($raw_user_url);
    /**
     * Filters a user's url after it has been sanitized and before the user is created or updated.
     *
     * @since 1.0.0
     * @param string $sanitized_user_url User url after it has been sanitized.
     * @param string $raw_user_url The user's URL.
     */
    $user_url = ActionFilterHook::getInstance()->applyFilter(
        'pre_user_url',
        (string) $sanitized_user_url,
        (string) $raw_user_url
    );
    $user->setUrl($user_url);

    $raw_user_email = $userdata['user_email'];
    if (!validate_email($raw_user_email)) {
        throw new Exception(
            esc_html__(
                'Sorry, that email address is not valid.'
            ),
            'invalid_email'
        );
    }
    $sanitized_user_email = ttcms()->obj['sanitizer']->item($raw_user_email, 'email');
    /**
     * Filters a user's email before the user is created or updated.
     *
     * @since 1.0.0
     * @param string $sanitized_user_url User email after it has been sanitized
     * @param string $raw_user_email The user's email.
     */
    $user_email = ActionFilterHook::getInstance()->applyFilter(
        'pre_user_email',
        (string) $sanitized_user_email,
        (string) $raw_user_email
    );
    /*
     * If there is no update, just check for `email_exists`. If there is an update,
     * check if current email and new email are the same, or not, and check `email_exists`
     * accordingly.
     */
    if ((
        !$update || (
            !empty($old_user_data)
            && 0 !== strcasecmp(
                $user_email,
                $old_user_data->getEmail()
            )
        )
    ) && email_exists($user_email)) {
        throw new Exception(
            esc_html__(
                'Sorry, that email address is already used.'
            ),
            'duplicate_email'
        );
    }
    $meta['email'] = $user_email;
    $user->setEmail($user_email);

    $raw_user_fname = $userdata['user_fname'];
    $sanitized_user_fname = ttcms()->obj['sanitizer']->item($userdata['user_fname']);
    /**
     * Filters a user's first name before the user is created or updated.
     *
     * @since 1.0.0
     * @param string $sanitized_user_fname User first name after it has been sanitized.
     * @param string $raw_user_fname The user's first name.
     */
    $user_fname = ActionFilterHook::getInstance()->applyFilter(
        'pre_user_fname',
        (string) $sanitized_user_fname,
        (string) $raw_user_fname
    );
    $meta['fname'] = $user_fname;
    $user->setFname($user_fname);

    $raw_user_lname = $userdata['user_lname'];
    $sanitized_user_lname = ttcms()->obj['sanitizer']->item($userdata['user_lname']);
    /**
     * Filters a user's last name before the user is created or updated.
     *
     * @since 1.0.0
     * @param string $sanitized_user_lname User last name after it has been sanitized.
     * @param string $raw_user_lname The user's last name.
     */
    $user_lname = ActionFilterHook::getInstance()->applyFilter(
        'pre_user_lname',
        (string) $sanitized_user_lname,
        (string) $raw_user_lname
    );
    $meta['lname'] = $user_lname;
    $user->setLname($user_lname);

    $raw_user_bio = $userdata['user_bio'];
    $sanitized_user_bio = ttcms()->obj['sanitizer']->item($userdata['user_bio']);
    /**
     * Filters a user's bio before the user is created or updated.
     *
     * @since 1.0.0
     * @param string $sanitized_user_bio User bio after it has been sanitized.
     * @param string $raw_user_bio The user's bio.
     */
    $meta['bio'] = ActionFilterHook::getInstance()->applyFilter(
        'pre_user_bio',
        (string) $sanitized_user_bio,
        (string) $raw_user_bio
    );

    $raw_user_timezone = $userdata['user_timezone'];
    $sanitized_user_timezone = ttcms()->obj['sanitizer']->item($userdata['user_timezone']);
    /**
     * Filters a user's timezone before the user is created or updated.
     *
     * @since 1.0.0
     * @param string $sanitized_user_timezone User timezone after it has been sanitized.
     * @param string $raw_user_timezone The user's timezone.
     */
    $user_timezone = ActionFilterHook::getInstance()->applyFilter(
        'pre_user_timezone',
        (string) $sanitized_user_timezone,
        (string) $raw_user_timezone
    );
    $meta['timezone'] = $user_timezone;
    $user->setTimezone($user_timezone);

    $raw_user_date_format = $userdata['user_date_format'];
    $sanitized_user_date_format = ttcms()->obj['sanitizer']->item($userdata['user_date_format']);
    /**
     * Filters a user's date format before the user is created or updated.
     *
     * @since 1.0.0
     * @param string $sanitized_user_date_format User date format after it has been sanitized.
     * @param string $raw_user_date_format The user's date format.
     */
    $user_date_format = ActionFilterHook::getInstance()->applyFilter(
        'pre_user_date_format',
        (string) $sanitized_user_date_format,
        (string) $raw_user_date_format
    );
    $meta['date_format'] = $user_date_format;
    $user->setDateFormat($user_date_format);

    $raw_user_time_format = $userdata['user_time_format'];
    $sanitized_user_time_format = ttcms()->obj['sanitizer']->item($userdata['user_time_format']);
    /**
     * Filters a user's time format before the user is created or updated.
     *
     * @since 1.0.0
     * @param string $sanitized_user_time_format User time format after it has been sanitized.
     * @param string $raw_user_time_format The user's time format.
     */
    $user_time_format = ActionFilterHook::getInstance()->applyFilter(
        'pre_user_time_format',
        (string) $sanitized_user_time_format,
        (string) $raw_user_time_format
    );
    $meta['time_format'] = $user_time_format;
    $user->setTimeFormat($user_time_format);

    $raw_user_locale = $userdata['user_locale'];
    $sanitized_user_locale = ttcms()->obj['sanitizer']->item($userdata['user_locale']);
    /**
     * Filters a user's locale before the user is created or updated.
     *
     * @since 1.0.0
     * @param string $sanitized_user_locale User locale after it has been sanitized.
     * @param string $raw_user_locale       The user's locale.
     */
    $user_locale = ActionFilterHook::getInstance()->applyFilter(
        'pre_user_locale',
        (string) $sanitized_user_locale,
        (string) $raw_user_locale
    );
    $meta['locale'] = $user_locale;
    $user->setLocale($user_locale);

    $raw_user_status = $userdata['user_status'];
    $sanitized_user_status = ttcms()->obj['sanitizer']->item($userdata['user_status']);
    /**
     * Filters a user's status before the user is created or updated.
     *
     * @since 1.0.0
     * @param string $sanitized_user_status User status after it has been sanitized.
     * @param string $raw_user_status The user's status.
     */
    $meta['status'] = ActionFilterHook::getInstance()->applyFilter(
        'pre_user_status',
        (string) $sanitized_user_status,
        (string) $raw_user_status
    );

    $user_admin_layout = 0;

    $meta['admin_layout'] = (int) $user_admin_layout;

    $user_admin_sidebar = 0;

    $meta['admin_sidebar'] = (int) $user_admin_sidebar;

    $user_admin_skin = 'skin-purple-light';

    $meta['admin_skin'] = (string) $user_admin_skin;

    $user_addedby = (int) get_current_user_id() <= (int) 0 ? (int) 1 : (int) get_current_user_id();

    $user_registered = (string) (new Date())->current('db');

    $user_modified = (string) (new Date())->current('db');

    $compacted = compact(
        'user_login',
        'user_fname',
        'user_lname',
        'user_pass',
        'user_email',
        'user_url',
        'user_timezone',
        'user_date_format',
        'user_time_format',
        'user_locale'
    );
    $data = ttcms()->obj['util']->unslash($compacted);

    /**
     * Filters user data before the record is created or updated.
     *
     * It only includes data in the user's table, not any user metadata.
     *
     * @since 1.0.0
     * @param array    $data {
     *     Values and keys for the user.
     *
     *      @type string $user_login        The user's login.
     *      @type string $user_fname        The user's first name.
     *      @type string $user_lname        The user's last name.
     *      @type string $user_pass         The user's password.
     *      @type string $user_email        The user's email.
     *      @type string $user_url          The user's url.
     *      @type string $user_timezone     The user's timezone.
     *      @type string $user_date_format  The user's date format.
     *      @type string $user_time_format  The user's time format.
     *      @type string $user_locale       The user's locale.
     *      @type string $user_addedby      User who registered user.
     *      @type string $user_registered   Timestamp describing the moment when the user registered. Defaults to
     *                                      Y-m-d h:i:s
     * }
     * @param bool     $update Whether the user is being updated rather than created.
     * @param int|null $id     ID of the user to be updated, or NULL if the user is being created.
     */
    $data = ActionFilterHook::getInstance()->applyFilter(
        'ttcms_pre_insert_user_data',
        $data,
        $update,
        $update ? (int) $user_id : null
    );

    if (!$update) {
        /**
         * User object.
         */
        $user->setAddedBy($user_addedby);
        $user->setRegistered($user_registered);

        try {
            $user_id = (
              new UserRepository(
                  new UserMapper(
                      $qudb,
                      new HelperContext()
                  )
              )
          )->insert($user);
        } catch (\PDOException $ex) {
            Cascade::getLogger('error')->error(
                sprintf(
                    'SQLSTATE[%s]: %s',
                    $ex->getCode(),
                    $ex->getMessage()
                ),
                [
                    'User Function' => 'ttcms_insert_user'
                ]
            );
        }
    } else {
        /**
         * User object.
         */
        if ($user_email !== $old_user_data->getEmail()) {
            $user->setActivationKey(null);
        }

        $user->setModified($user_modified);

        try {
            $user_id = (
              new UserRepository(
                  new UserMapper(
                      $qudb,
                      new HelperContext()
                  )
              )
          )->update($user);
        } catch (\PDOException $ex) {
            Cascade::getLogger('error')->error(
                sprintf(
                    'SQLSTATE[%s]: %s',
                    $ex->getCode(),
                    $ex->getMessage()
                ),
                [
                    'User Function' => 'ttcms_insert_user/ttcms_update_user'
                ]
            );
        }
    }

    /**
     * Filters a user's meta values and keys immediately after the user is created or updated
     * and before any user meta is inserted or updated.
     *
     * @since 1.0.0
     * @param array $meta {
     *     Default meta values and keys for the user.
     *
     *     @type string $username       The user's username
     *     @type string $fname          The user's first name.
     *     @type string $lname          The user's last name.
     *     @type string $email          The user's email.
     *     @type string $timezone       The user's timezone.
     *     @type string $date_format    The user's date format.
     *     @type string $time_format    The user's time format.
     *     @type string $bio            The user's bio.
     *     @type string $timezone       The user's timezone.
     *     @type string $date_format    The user's date format.
     *     @type string $time_format    The user's time_format.
     *     @type string $locale         The user's locale.
     *     @type string $status         The user's status.
     *     @type int    $admin_layout   The user's layout option.
     *     @type int    $admin_sidebar  The user's sidebar option.
     *     @type int    $admin_skin     The user's skin option.
     * }
     * @param object $user  User object.
     * @param bool $update  Whether the user is being updated rather than created.
     */
    $meta = ActionFilterHook::getInstance()->applyFilter('insert_user_meta', $meta, $user, $update);

    // Update user meta.
    foreach ($meta as $key => $value) {
        update_user_option((int) $user_id, $key, $value);
    }

    ttcms()->obj['usercache']->clean($user);

    if ($update) {
        /**
         * Fires immediately after an existing user is updated.
         *
         * @since 1.0.0
         * @param int  $user_id       User ID.
         * @param Post $old_user_data Object containing user's data prior to update.
         */
        ActionFilterHook::getInstance()->doAction('profile_update', (int) $user_id, $old_user_data);
    } else {
        /**
         * Fires immediately after a new user is registered.
         *
         * @since 1.0.0
         * @param int $user_id User ID.
         */
        ActionFilterHook::getInstance()->doAction('user_register', (int) $user_id);
    }

    return (int) $user_id;
}

/**
 * Update a user in the database.
 *
 * It is possible to update a user's password by specifying the 'user_pass'
 * value in the $userdata parameter array.
 *
 * See {@see ttcms_insert_user()} For what fields can be set in $userdata.
 *
 * @file app/functions/user.php
 *
 * @since 1.0.0
 * @param array|object|User $userdata An array of user data or a user object of type stdClass or User.
 * @return int|Exception The updated user's id or throw an Exception if the user could not be updated.
 */
function ttcms_update_user($userdata)
{
    if ($userdata instanceof \stdClass) {
        $userdata = get_object_vars($userdata);
    } elseif ($userdata instanceof User) {
        $userdata = $userdata->toArray();
    }

    $ID = isset($userdata['user_id']) ? (int) $userdata['user_id'] : (int) 0;
    if (!$ID) {
        throw new Exception(
            esc_html__(
                'Invalid user id.'
            ),
            'invalid_id'
        );
    }

    // First, get all of the original fields
    $user_obj = get_userdata($ID);
    if (!$user_obj) {
        throw new Exception(
            esc_html__(
                'Invalid user id.'
            ),
            'invalid_id'
        );
    }

    $user = $user_obj->toArray();

    $additional_user_keys = [
        'username',
        'fname',
        'lname',
        'email',
        'timezone',
        'date_format',
        'time_format',
        'locale',
        'bio',
        'role',
        'status',
        'admin_layout',
        'admin_sidebar',
        'admin_skin'
    ];
    // Add additional custom fields
    foreach ($additional_user_keys as $key) {
        $user[$key] = get_user_option($key, (int) $user['user_id']);
    }

    if (!empty($userdata['user_pass']) && $userdata['user_pass'] !== $user_obj->getPassword()) {
        // If password is changing, hash it now
        $plaintext_pass = $userdata['user_pass'];
        $userdata['user_pass'] = (new PasswordHash(ActionFilterHook::getInstance()))->hash($userdata['user_pass']);

        /**
         * Filters whether to send the password change email.
         *
         * @see ttcms_insert_user() For `$user` and `$userdata` fields.
         *
         * @since 1.0.0
         * @param bool  $send     Whether to send the email.
         * @param array $user     The original user array before changes.
         * @param array $userdata The updated user array.
         *
         */
        $send_password_change_email = ActionFilterHook::getInstance()->applyFilter(
            'send_password_change_email',
            true,
            $user,
            $userdata
        );
    }

    if (isset($userdata['user_email']) && $user['user_email'] !== $userdata['user_email']) {
        /**
         * Filters whether to send the email change email.
         *
         * @see ttcms_insert_user() For `$user` and `$userdata` fields.
         *
         * @since 1.0.0
         * @param bool  $send     Whether to send the email.
         * @param array $user     The original user array before changes.
         * @param array $userdata The updated user array.
         *
         */
        $send_email_change_email = ActionFilterHook::getInstance()->applyFilter(
            'send_email_change_email',
            true,
            $user,
            $userdata
        );
    }

    ttcms()->obj['cache']->delete($user['user_email'], 'useremail');

    // Merge old and new fields with new fields overwriting old ones.
    $userdata = array_merge($user, $userdata);
    $user_id = ttcms_insert_user($userdata);

    if (!check_qubus_exception($user_id)) {
        if (!empty($send_password_change_email)) {
            /**
             * Fires when user is updated successfully.
             *
             * @since 1.0.0
             * @param array  $user          The original user array before changes.
             * @param string $plantext_pass Plaintext password before hashing.
             * @param array  $userdata      The updated user array.
             */
            ActionFilterHook::getInstance()->doAction('password_change_email', $user, $plaintext_pass, $userdata);
        }

        if (!empty($send_email_change_email)) {
            /**
             * Fires when user is updated successfully.
             *
             * @since 1.0.0
             * @param array $user     The original user array before changes.
             * @param array $userdata The updated user array.
             */
            ActionFilterHook::getInstance()->doAction('email_change_email', $user, $userdata);
        }
    }

    /**
     * Update the cookies if the username changed.
     */
    $current_user = ttcms_get_current_user();
    if ($current_user->getId() == $ID) {
        if (isset($userdata['user_login']) && $userdata['user_login'] != $current_user->getLogin()) {
            /**
             * Retrieve data from the old secure cookie to set expiration.
             */
            $old_cookie_data = get_secure_cookie_data('TTCMS_COOKIENAME');
            $rememberme = $old_cookie_data->remember === 'yes' ? $old_cookie_data->remember : 'no';
            /**
             * Clear the old cookie data.
             */
            ttcms_clear_auth_cookie();
            /**
             * Set the new secure cookie.
             */
            ttcms_set_auth_cookie($userdata, $rememberme);
        }
    }

    return $user_id;
}

/**
 * Deletes a user from the usermeta document. To delete user entirely from the system,
 * see `ttcms_delete_site_user`.
 *
 * @file app/functions/user.php
 *
 * @since 1.0.0
 * @param int $user_id   ID of user being deleted.
 * @param int $assign_id ID of user to whom posts will be assigned.
 *                       Default: NULL.
 * @return bool True on success or false on failure.
 */
function ttcms_delete_user(int $user_id, $assign_id = null)
{
    $qudb = app()->qudb;

    if (!is_numeric($user_id)) {
        return false;
    }

    if ((int) $user_id == (int) 1) {
        ttcms()->obj['flash']->error(
            esc_html__(
                'You are not allowed to delete the super administrator account.'
            )
        );
        exit();
    }

    $user_id = (int) $user_id;
    $user = get_userdata((int) $user_id);

    if (!$user) {
        return false;
    }

    if ((int) $assign_id > 0) {
        /**
         * Filter hook is triggered when assign_id is greater than zero.
         *
         * Posts will be reassigned before the user is deleted.
         *
         * @since 1.0.0
         * @param int $user_id   ID of user to be deleted.
         * @param int $assign_id ID of user to reassign posts to.
         *                       Default: NULL.
         */
        ActionFilterHook::getInstance()->applyFilter('reassign_posts', (int) $user_id, (int) $assign_id);
    }

    /**
     * Action hook fires immediately before a user is deleted from the usermeta document.
     *
     * @since 1.0.0
     * @param int      $user_id  ID of the user to delete.
     * @param int|null $reassign ID of the user to reassign posts to.
     *                           Default: NULL.
     */
    ActionFilterHook::getInstance()->doAction('delete_user', (int) $user_id, (int) $assign_id);

    $meta = $qudb->getResults(
        $qudb->prepare(
            "SELECT meta_id FROM {$qudb->base_prefix}usermeta WHERE user_id = ?",
            [
                $user_id
            ]
        ),
        ARRAY_A
    );

    if ($meta) {
        foreach ($meta as $mid) {
            (new MetaData(
                $qudb,
                new HelperContext()
            ))->deleteByMid('user', (int) $mid['meta_id']);
        }
    }

    ttcms()->obj['usercache']->clean($user);

    /**
     * Action hook fires immediately after a user has been deleted from the usermeta document.
     *
     * @since 1.0.0
     * @param int $user_id   ID of the user who was deleted.
     * @param int $assign_id ID of the user to whom posts were assigned.
     *                       Default: NULL.
     */
    ActionFilterHook::getInstance()->doAction('deleted_user', (int) $user_id, (int) $assign_id);

    return true;
}

/**
 * New user email queued when account is created.
 *
 * @file app/functions/user.php
 *
 * @since 1.0.0
 *
 * @param int $user_id User id.
 * @param string $pass Plaintext password.
 * @return bool|BadFormatException|WrongKeyOrModifiedCiphertextException|Exception
 *         True on success, false on failure or Exception.
 */
function queue_new_user_email(int $user_id, string $pass)
{
    $qudb = app()->qudb;

    $encrypt = (new NodeQ())->table($qudb->base_prefix . 'encryption')->where('encryption_id', 1)->first();
    $nodeq = (new NodeQ())->table($qudb->prefix . 'login');

    $nodeq->begin();
    try {
        $nodeq->insert([
            'login_userid' => (int) $user_id,
            'login_userpass' => (string) Crypto::encrypt($pass, Key::loadFromAsciiSafeString($encrypt['encryption_key'])),
            'login_sent' => (int) 0
        ]);
        $nodeq->commit();
        return true;
    } catch (BadFormatException $ex) {
        $nodeq->rollback();
        Cascade::getLogger('error')->error(
            sprintf(
                'CRYPTOFORMAT[%s]: %s',
                $ex->getCode(),
                $ex->getMessage()
            )
        );
        return false;
    } catch (WrongKeyOrModifiedCiphertextException $ex) {
        $nodeq->rollback();
        Cascade::getLogger('error')->error(
            sprintf(
                'CRYPTOKEY[%s]: %s',
                $ex->getCode(),
                $ex->getMessage()
            )
        );
        return false;
    } catch (Exception $ex) {
        $nodeq->rollback();
        Cascade::getLogger('error')->error(
            sprintf(
                'NODEQSTATE[%s]: %s',
                $ex->getCode(),
                $ex->getMessage()
            ),
            [
                'User Function' => 'queue_new_user_email'
            ]
        );
        return false;
    }
}

/**
 * Reset password email queued when reset button is clicked on the user's screen.
 *
 * @file app/functions/user.php
 *
 * @since 1.0.0
 *
 * @param User $user User object.
 * @return bool|int|BadFormatException|WrongKeyOrModifiedCiphertextException|Exception
 *         User id on success, false or Exception on failure.
 */
function queue_reset_user_password(User $user)
{
    $qudb = app()->qudb;

    $encrypt = (new NodeQ())->table($qudb->base_prefix . 'encryption')->where('encryption_id', 1)->first();
    $nodeq = (new NodeQ())->table($qudb->prefix . 'password_reset');

    $nodeq->begin();
    try {
        $nodeq->insert([
            'password_reset_userid' => (int) $user->getId(),
            'password_reset_userpass' => (string) Crypto::encrypt($user->getPassword(), Key::loadFromAsciiSafeString($encrypt['encryption_key'])),
            'password_reset_sent' => (int) 0
        ]);
        $nodeq->commit();
        return (int) $user->getId();
    } catch (BadFormatException $ex) {
        $nodeq->rollback();
        Cascade::getLogger('error')->error(
            sprintf(
                'CRYPTOFORMAT[%s]: %s',
                $ex->getCode(),
                $ex->getMessage()
            )
        );
        return false;
    } catch (WrongKeyOrModifiedCiphertextException $ex) {
        $nodeq->rollback();
        Cascade::getLogger('error')->error(
            sprintf(
                'CRYPTOKEY[%s]: %s',
                $ex->getCode(),
                $ex->getMessage()
            )
        );
        return false;
    } catch (Exception $ex) {
        $nodeq->rollback();
        Cascade::getLogger('error')->error(
            sprintf(
                'NODEQSTATE[%s]: %s',
                $ex->getCode(),
                $ex->getMessage()
            ),
            [
                'User Function' => 'queue_reset_user_password'
            ]
        );
        return false;
    }
}

/**
 * Email sent to user with new generated password.
 *
 * @file app/functions/user.php
 *
 * @since 1.0.0
 * @param int $user User array.
 * @param string $password Plaintext password.
 * @return bool|\\PHPMailer\\PHPMailer\\Exception True on success, false on failure or Exception.
 */
function send_reset_password_email(array $user, string $password)
{
    $qudb = app()->qudb;

    $option = new TriTan\Common\Options\Options(
        new \TriTan\Common\Options\OptionsMapper(
            $qudb,
            new HelperContext()
        )
    );

    $site_name = $option->read('sitename');

    $message .= "<p>" . sprintf(
        t__(
            "Hello %s! You requested that your password be reset. Please see your new password below: <br />",
            'tritan-cms'
        ),
        esc_html($user['user_fname'])
    );
    $message .= sprintf(esc_html__('Password: %s'), $password) . "</p>";
    $message .= "<p>" . sprintf(
        esc_html__(
            'If you still have problems with logging in, please contact us at <a href="mailto:%s">%s</a>.'
        ),
        $option->read('admin_email'),
        $option->read('admin_email')
    ) . "</p>";

    $message = process_email_html($message, esc_html__('Password Reset'));
    $headers[] = sprintf("From: %s <auto-reply@%s>", $site_name, get_domain_name());
    if (!function_exists('ttcms_mail_send')) {
        $headers[] = 'Content-Type: text/html; charset="UTF-8"';
        $headers[] = sprintf("X-Mailer: TriTan CMS %s", CURRENT_RELEASE);
    }
    try {
        (new Mailer(ActionFilterHook::getInstance()))->mail(
            esc_html($user['user_email']),
            sprintf(
                esc_html__(
                    '[%s] Notice of Password Reset'
                ),
                $site_name
            ),
            $message,
            $headers
        );
    } catch (\PHPMailer\PHPMailer\Exception $ex) {
        ttcms()->obj['flash']->error($ex->getMessage());
    } catch (Exception $ex) {
        ttcms()->obj['flash']->error($ex->getMessage());
    }
}

/**
 * Email sent to user with changed/updated password.
 *
 * @file app/functions/user.php
 *
 * @since 1.0.0
 * @param int $user User array.
 * @param string $password Plaintext password.
 * @param array $userdata Updated user array.
 * @return bool|\\PHPMailer\\PHPMailer\\Exception True on success, false on failure or Exception.
 */
function send_password_change_email(array $user, string $password, array $userdata)
{
    $qudb = app()->qudb;

    $option = new TriTan\Common\Options\Options(
        new \TriTan\Common\Options\OptionsMapper(
            $qudb,
            new HelperContext()
        )
    );

    $site_name = $option->read('sitename');

    $message .= "<p>" . sprintf(
        t__(
            "Hello %s! This is confirmation that your password on %s was updated to: <br />",
            'tritan-cms'
        ),
        esc_html($user['user_fname']),
        $option->read('sitename')
    );
    $message .= sprintf(esc_html__('Password: %s'), $password) . "</p>";
    $message .= "<p>" . sprintf(
        esc_html__(
            'If you did not initiate a password change/update, please contact us at <a href="mailto:%s">%s</a>.'
        ),
        $option->read('admin_email'),
        $option->read('admin_email')
    ) . "</p>";

    $message = process_email_html($message, esc_html__('Notice of Password Change'));
    $headers[] = sprintf("From: %s <auto-reply@%s>", $site_name, get_domain_name());
    if (!function_exists('ttcms_mail_send')) {
        $headers[] = 'Content-Type: text/html; charset="UTF-8"';
        $headers[] = sprintf("X-Mailer: TriTan CMS %s", CURRENT_RELEASE);
    }
    try {
        (new Mailer(ActionFilterHook::getInstance()))->mail(
            esc_html($user['user_email']),
            sprintf(
                esc_html__(
                    '[%s] Notice of Password Change'
                ),
                $site_name
            ),
            $message,
            $headers
        );
    } catch (\PHPMailer\PHPMailer\Exception $ex) {
        ttcms()->obj['flash']->error($ex->getMessage());
    } catch (Exception $ex) {
        ttcms()->obj['flash']->error($ex->getMessage());
    }
}

/**
 * Email sent to user with changed/updated email.
 *
 * @file app/functions/user.php
 *
 * @since 1.0.0
 * @param array $user       Original user array.
 * @param array $userdata   Updated user array.
 * @return bool|\\PHPMailer\\PHPMailer\\Exception True on success, false on failure or Exception.
 */
function send_email_change_email(array $user, array $userdata)
{
    $qudb = app()->qudb;

    $option = new TriTan\Common\Options\Options(
        new \TriTan\Common\Options\OptionsMapper(
            $qudb,
            new HelperContext()
        )
    );

    $site_name = $option->read('sitename');

    $message .= "<p>" . sprintf(
        t__(
            "Hello %s! This is confirmation that your email on %s was updated to: <br />",
            'tritan-cms'
        ),
        esc_html($user['user_fname']),
        $site_name
    );
    $message .= sprintf(esc_html__('Email: %s'), esc_html($userdata['user_email'])) . "</p>";
    $message .= "<p>" . sprintf(
        esc_html__(
            'If you did not initiate an email change/update, please contact us at <a href="mailto:%s">%s</a>.'
        ),
        $option->read('admin_email'),
        $option->read('admin_email')
    ) . "</p>";

    $message = process_email_html($message, esc_html__('Notice of Email Change'));
    $headers[] = sprintf("From: %s <auto-reply@%s>", $site_name, get_domain_name());
    if (!function_exists('ttcms_mail_send')) {
        $headers[] = 'Content-Type: text/html; charset="UTF-8"';
        $headers[] = sprintf("X-Mailer: TriTan CMS %s", CURRENT_RELEASE);
    }
    try {
        (new Mailer(ActionFilterHook::getInstance()))->mail(
            esc_html($userdata['user_email']),
            sprintf(
                esc_html__(
                    '[%s] Notice of Email Change'
                ),
                $site_name
            ),
            $message,
            $headers
        );
    } catch (\PHPMailer\PHPMailer\Exception $ex) {
        ttcms()->obj['flash']->error($ex->getMessage());
    } catch (Exception $ex) {
        ttcms()->obj['flash']->error($ex->getMessage());
    }
}

/**
 * An extensive list of blacklisted usernames.
 *
 * Uses `blacklisted_usernames` filter.
 *
 * @file app/functions/user.php
 *
 * @since 1.0.0
 * @return array Array of blacklisted usernames.
 */
function blacklisted_usernames() : array
{
    $blacklist = [
        '.htaccess', '.htpasswd', '.well-known', '400', '401', '403', '404',
        '405', '406', '407', '408', '409', '410', '411', '412', '413', '414',
        '415', '416', '417', '421', '422', '423', '424', '426', '428', '429',
        '431', '500', '501', '502', '503', '504', '505', '506', '507', '508',
        '509', '510', '511', 'about', 'about-us', 'abuse', 'access', 'account',
        'accounts', 'ad', 'add', 'admin', 'administration', 'administrator',
        'ads', 'advertise', 'advertising', 'aes128-ctr', 'aes128-gcm',
        'aes192-ctr', 'aes256-ctr', 'aes256-gcm', 'affiliate', 'affiliates',
        'ajax', 'alert', 'alerts', 'alpha', 'amp', 'analytics', 'api', 'app',
        'apps', 'asc', 'assets', 'atom', 'auth', 'authentication', 'authorize',
        'autoconfig', 'autodiscover', 'avatar', 'backup', 'banner', 'banners',
        'beta', 'billing', 'billings', 'blog', 'blogs', 'board', 'bookmark',
        'bookmarks', 'broadcasthost', 'business', 'buy', 'cache', 'calendar',
        'campaign', 'captcha', 'careers', 'cart', 'cas', 'categories',
        'category', 'cdn', 'cgi', 'cgi-bin', 'chacha20-poly1305', 'change',
        'channel', 'channels', 'chart', 'chat', 'checkout', 'clear', 'client',
        'close', 'cms', 'com', 'comment', 'comments', 'community', 'compare',
        'compose', 'config', 'connect', 'contact', 'contest', 'cookies', 'copy',
        'copyright', 'count', 'create', 'crossdomain.xml', 'css',
        'curve25519-sha256', 'customer', 'customers', 'customize', 'dashboard',
        'db', 'deals', 'debug', 'delete', 'desc', 'dev', 'developer',
        'developers', 'diffie-hellman-group-exchange-sha256',
        'diffie-hellman-group14-sha1', 'disconnect', 'discuss', 'dns', 'dns0',
        'dns1', 'dns2', 'dns3', 'dns4', 'docs', 'documentation', 'domain',
        'download', 'downloads', 'downvote', 'draft', 'drop', 'drupal',
        'ecdh-sha2-nistp256', 'ecdh-sha2-nistp384', 'ecdh-sha2-nistp521',
        'edit', 'editor', 'email', 'enterprise', 'error', 'errors', 'event',
        'events', 'example', 'exception', 'exit', 'explore', 'export',
        'extensions', 'false', 'family', 'faq', 'faqs', 'favicon.ico',
        'features', 'feed', 'feedback', 'feeds', 'file', 'files', 'filter',
        'follow', 'follower', 'followers', 'following', 'fonts', 'forgot',
        'forgot-password', 'forgotpassword', 'form', 'forms', 'forum', 'forums',
        'friend', 'friends', 'ftp', 'get', 'git', 'go', 'group', 'groups',
        'guest', 'guidelines', 'guides', 'head', 'header', 'help', 'hide',
        'hmac-sha', 'hmac-sha1', 'hmac-sha1-etm', 'hmac-sha2-256',
        'hmac-sha2-256-etm', 'hmac-sha2-512', 'hmac-sha2-512-etm', 'home',
        'host', 'hosting', 'hostmaster', 'htpasswd', 'http', 'httpd', 'https',
        'humans.txt', 'icons', 'images', 'imap', 'img', 'import', 'info',
        'insert', 'investors', 'invitations', 'invite', 'invites', 'invoice',
        'is', 'isatap', 'issues', 'it', 'jobs', 'join', 'joomla', 'js', 'json',
        'keybase.txt', 'learn', 'legal', 'license', 'licensing', 'limit',
        'live', 'load', 'local', 'localdomain', 'localhost', 'lock', 'login',
        'logout', 'lost-password', 'mail', 'mail0', 'mail1', 'mail2', 'mail3',
        'mail4', 'mail5', 'mail6', 'mail7', 'mail8', 'mail9', 'mailer-daemon',
        'mailerdaemon', 'map', 'marketing', 'marketplace', 'master', 'me',
        'media', 'member', 'members', 'message', 'messages', 'metrics', 'mis',
        'mobile', 'moderator', 'modify', 'more', 'mx', 'my', 'net', 'network',
        'new', 'news', 'newsletter', 'newsletters', 'next', 'nil', 'no-reply',
        'nobody', 'noc', 'none', 'noreply', 'notification', 'notifications',
        'ns', 'ns0', 'ns1', 'ns2', 'ns3', 'ns4', 'ns5', 'ns6', 'ns7', 'ns8',
        'ns9', 'null', 'oauth', 'oauth2', 'offer', 'offers', 'online',
        'openid', 'order', 'orders', 'overview', 'owner', 'page', 'pages',
        'partners', 'passwd', 'password', 'pay', 'payment', 'payments',
        'photo', 'photos', 'pixel', 'plans', 'plugins', 'policies', 'policy',
        'pop', 'pop3', 'popular', 'portfolio', 'post', 'postfix', 'postmaster',
        'poweruser', 'preferences', 'premium', 'press', 'previous', 'pricing',
        'print', 'privacy', 'privacy-policy', 'private', 'prod', 'product',
        'production', 'profile', 'profiles', 'project', 'projects', 'public',
        'purchase', 'put', 'quota', 'qubus', 'qubuscms', 'redirect', 'reduce',
        'refund', 'refunds', 'register', 'registration', 'remove', 'replies',
        'reply', 'request', 'request-password', 'reset', 'reset-password',
        'response', 'report', 'return', 'returns', 'review', 'reviews',
        'robots.txt', 'root', 'rootuser', 'rsa-sha2-2', 'rsa-sha2-512', 'rss',
        'rules', 'sales', 'save', 'script', 'sdk', 'search', 'secure',
        'security', 'select', 'services', 'session', 'sessions', 'settings',
        'setup', 'share', 'shift', 'shop', 'signin', 'signup', 'site', 'sitemap',
        'sites', 'smtp', 'sort', 'source', 'sql', 'ssh', 'ssh-rsa', 'ssl',
        'ssladmin', 'ssladministrator', 'sslwebmaster', 'stage', 'staging',
        'stat', 'static', 'statistics', 'stats', 'status', 'store', 'style',
        'styles', 'stylesheet', 'stylesheets', 'subdomain', 'subscribe', 'sudo',
        'super', 'superuser', 'support', 'survey', 'sync', 'sysadmin', 'system',
        'tablet', 'tag', 'tags', 'team', 'telnet', 'terms', 'terms-of-use',
        'test', 'testimonials', 'theme', 'themes', 'today', 'tools', 'topic',
        'topics', 'tour', 'training', 'translate', 'translations', 'trending',
        'trial', 'tritan', 'tritancms', 'true', 'ttcms', 'umac-128', 'undefined',
        'unfollow', 'unsubscribe', 'update', 'upgrade', 'usenet', 'user',
        'username', 'users', 'uucp', 'var', 'verify', 'video', 'view',
        'void', 'vote', 'webmail', 'webmaster', 'website', 'widget', 'widgets',
        'wiki', 'wordpress', 'wpad', 'write', 'www', 'www-data', 'www1', 'www2',
        'www3', 'www4', 'you', 'yourname', 'yourusername', 'zlib'
    ];

    return ActionFilterHook::getInstance()->applyFilter('blacklisted_usernames', $blacklist);
}

/**
 * Recently published widget.
 *
 * @file app/functions/user.php
 *
 * @since 1.0.0
 * @return 5 recently published posts.
 */
function recently_published_widget()
{
    $qudb = app()->qudb;

    $limit = ActionFilterHook::getInstance()->applyFilter('recently_published_widget_limit', 5);

    try {
        $posts = $qudb->getResults(sprintf("SELECT * FROM {$qudb->prefix}post LIMIT %s", $limit), ARRAY_A);
        $_posts = sort_list($posts, 'post_created', 'DESC');

        foreach ($_posts as $post) {
            echo '<div class="text-muted rp-widget">';
            echo '<table>';
            echo '<tr>';
            echo '<td>' . get_post_datetime(esc_html($post['post_id'])) . '</td>';
            echo '<td>' . sprintf(
                '<a href="%s">%s</a>',
                admin_url(
                    esc_html($post['post_posttype']) . '/' . esc_html($post['post_id']) . '/'
                ),
                esc_html($post['post_title'])
            ) . '</td>';
            echo '</tr>';
            echo '</table>';
            echo '</div>';
        }
    } catch (\PDOException $ex) {
        ttcms()->obj['flash']->error($ex->getMessage());
    }
}

/**
 * TriTan CMS feed widget.
 *
 * @file app/functions/user.php
 *
 * @since 1.0.0
 */
function tritan_cms_feed_widget()
{
    $cache = new \TriTan\Cache('rss');
    if (!$cache->setCache()) {
        $rss1 = new \DOMDocument();
        $rss1->load('https://www.tritancms.com/blog/rss/');
        $feed = [];
        foreach ($rss1->getElementsByTagName('item') as $node) {
            $item = [
                'title' => $node->getElementsByTagName('title')->item(0)->nodeValue,
                'desc' => $node->getElementsByTagName('description')->item(0)->nodeValue,
                'link' => $node->getElementsByTagName('link')->item(0)->nodeValue,
                'date' => $node->getElementsByTagName('pubDate')->item(0)->nodeValue,
            ];
            array_push($feed, $item);
        }
        $limit = 3;
        for ($x = 0; $x < $limit; $x++) {
            $title = str_replace(' & ', ' &amp; ', $feed[$x]['title']);
            $link = $feed[$x]['link'];
            $description = $feed[$x]['desc'];
            $date = date('l F d, Y', strtotime($feed[$x]['date']));
            echo '<p><strong><a href="' . $link . '" title="' . $title . '">' . $title . '</a></strong><br />';
            echo '<small><em>Posted on ' . $date . '</em></small></p>';
            echo '<p>' . $description . '</p>';
        }
    }
    echo $cache->getCache();
}

/**
 * Resets a user's password.
 *
 * @since 1.0.0
 * @param int $user_id ID of user who's password is to be reset. Default: 0.
 * @return int|Exception User id on success or Exception on failure.
 */
function reset_password(int $user_id = 0)
{
    $password = generate_random_password();

    $user = new User();
    $user->setId((int) $user_id);
    $user->setPassword((string) $password);

    try {
        $_user_id = queue_reset_user_password($user);
        ttcms()->obj['flash']->success("The password reset email has been queued for sending.");
        return $_user_id;
    } catch (Exception $ex) {
        ttcms()->obj['flash']->error($ex->getMessage() . " The system was unable to reset the user's password.");
    }
}

/**
 * Print a dropdown list of users.
 *
 * @file app/functions/user.php
 *
 * @since 1.0.0
 * @param int $user_id If working with active record, it will be the user's id.
 * @return array Dropdown list of users.
 */
function get_users_reassign(int $user_id = 0)
{
    $qudb = app()->qudb;

    $list_users = $qudb->from($qudb->base_prefix . 'user')
        ->where('user_id')->in(function ($query) use ($qudb) {
            $query->from($qudb->base_prefix . 'usermeta')
                  ->distinct()
                  ->where('meta_key')->like("%$qudb->prefix%")
                  ->select('user_id');
        })
        ->where('user_id')->notIn(["'$user_id'"])
        ->select()
        ->fetchAssoc()
        ->all();

    foreach ($list_users as $user) {
        echo '<option value="' . (int) esc_html($user['user_id']) . '">' . get_name((int) esc_html($user['user_id'])) . '</option>';
    }
}

/**
 * Retrieves a list of users by site_id.
 *
 * @file app/functions/user.php
 *
 * @since 1.0.0
 * @param int $site_id Site id. Default: 0.
 * @return array User array.
 */
function get_users_by_siteid(int $site_id = 0)
{
    $qudb = app()->qudb;

    $prefix = $site_id <= 1 ? $qudb->base_prefix : $qudb->base_prefix . $site_id;

    $list_users = $qudb->from($qudb->base_prefix . 'user')
        ->where('user_id')->in(function ($query) use ($qudb) {
            $query->from($qudb->base_prefix . 'usermeta')
                  ->distinct()
                  ->where('meta_key')->like("$prefix%")
                  ->select('user_id');
        })
        ->select()
        ->fetchAssoc()
        ->all();

    return $list_users;
}

/**
 * Returns the logged in user's timezone.
 *
 * @file app/functions/user.php
 *
 * @since 1.0.0
 * @return string Logged in user's timezone or system's timezone if false.
 */
function get_user_timezone()
{
    $user_timezone = get_user_option('timezone', get_current_user_id());
    if (is_user_logged_in() && $user_timezone != false) {
        return $user_timezone;
    }
    return c::getInstance()->get('option')->read('system_timezone');
}

/**
 * Returns the logged in user's date format.
 *
 * @file app/functions/user.php
 *
 * @since 1.0.0
 * @return string Logged in user's date format or system's date format if false.
 */
function get_user_date_format()
{
    $user_date_format = get_user_option('date_format', get_current_user_id());
    if (is_user_logged_in() && $user_date_format != false) {
        return $user_date_format;
    }
    return c::getInstance()->get('option')->read('date_format');
}

/**
 * Returns the logged in user's time format.
 *
 * @file app/functions/user.php
 *
 * @since 1.0.0
 * @return string Logged in user's time format or system's time format if false.
 */
function get_user_time_format()
{
    $user_time_format = get_user_option('time_format', get_current_user_id());
    if (is_user_logged_in() && $user_time_format != false) {
        return $user_time_format;
    }
    return c::getInstance()->get('option')->read('time_format');
}

/**
 * Returns the logged in user's datetime format.
 *
 * @file app/functions/user.php
 *
 * @since 1.0.0
 * @return string Logged in user's datetime format or system's datetime format.
 */
function get_user_datetime_format()
{
    $date_format = get_user_date_format();
    $time_format = get_user_time_format();
    return ActionFilterHook::getInstance()->applyFilter(
        'user_datetime_format',
        concat_ws(' ', $date_format, $time_format),
        $time_format,
        $date_format
    );
}

/**
 * Returns datetime based on user's date format, time format, and timezone.
 *
 * @since 1.0.0
 * @param  string $string Datetime string.
 * @param  string $format Format of the datetime string.
 * @return string Datetime string based on logged in user's date format,
 *                time format and timezone. Otherwise, it will use system settings.
 */
function get_user_datetime(string $string, string $format = 'Y-m-d H:i:s')
{
    $datetime = new Date($string, new \DateTimeZone('GMT'));
    $datetime->setTimezone(new \DateTimeZone(get_user_timezone()));
    return $datetime->format($format);
}
