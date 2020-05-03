<?php
use TriTan\Common\Container as c;
use TriTan\Common\Site\Site;
use TriTan\Common\Site\SiteRepository;
use TriTan\Common\Site\SiteMapper;
use TriTan\Common\Context\HelperContext;
use TriTan\Common\User\User;
use TriTan\Common\Date;
use TriTan\Database\Schema\CreateTable;
use TriTan\Common\Acl\RoleRepository;
use TriTan\Common\Acl\RoleMapper;
use TriTan\Common\MetaData;
use TriTan\Common\FileSystem;
use Qubus\Hooks\ActionFilterHook;
use Qubus\Exception\Error;
use Qubus\Exception\Exception;
use Qubus\Exception\Data\TypeException;
use Cascade\Cascade;

/**
 * Qubus CMS Site Functions
 *
 * @license GPLv3
 *
 * @since 1.0.0
 * @package Qubus CMS
 * @author Joshua Parker <josh@joshuaparker.blog>
 */

/**
 * Retrieves site data given a site ID or site object.
 *
 * @file app/functions/site.php
 *
 * @since 1.0.0
 * @param int|Site|null $site Site ID or object.
 * @param bool $output If set to OBJECT, data will return as an object, ARRAY_A
 *                     as an associative array or ARRAY_N as a numeric array.
 *                     Default: 'OBJECT'.
 * @return array|object|Site
 */
function get_site($site, $output = OBJECT)
{
    $qudb = app()->qudb;

    if ($site instanceof Site) {
        $_site = $site;
    } elseif (is_object($site)) {
        if (empty($site->getId())) {
            $_site = null;
        } else {
            try {
                $_site = (
                  new SiteRepository(
                      new SiteMapper(
                          $qudb,
                          new HelperContext()
                      )
                  ))->findById($site->getId());
            } catch (\PDOException $ex) {
                Cascade::getLogger('error')->error(
                    sprintf(
                        'SQLSTATE[%s]: %s',
                        $ex->getCode(),
                        $ex->getMessage()
                    ),
                    [
                        'Site Function' => 'get_site'
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
                        'Site Function' => 'get_site'
                    ]
                );
            }
        }
    } else {
        try {
            $_site = (
              new SiteRepository(
                  new SiteMapper(
                      $qudb,
                      new HelperContext()
                  )
              ))->findById((int) $site);
        } catch (\PDOException $ex) {
            Cascade::getLogger('error')->error(
                sprintf(
                    'SQLSTATE[%s]: %s',
                    $ex->getCode(),
                    $ex->getMessage()
                ),
                [
                    'Site Function' => 'get_site'
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
                    'Site Function' => 'get_site'
                ]
            );
        }
    }

    if (!$_site) {
        return null;
    }

    if ($output === ARRAY_A || $output === false) {
        $_site = $_site->toArray();
    } elseif ($output === ARRAY_N) {
        $_site = array_values($_site->toArray());
    }

    /**
     * Fires after a site is retrieved.
     *
     * @since 1.0.0
     * @param array|Site $_site Site data.
     */
    $_site = ActionFilterHook::getInstance()->applyFilter('get_site', $_site);

    return $_site;
}

/**
 * Checks whether the given site domain exists.
 *
 * @file app/functions/site.php
 *
 * @since 1.0.0
 * @param string $sitedomain Site domain to check against.
 * @return bool If site domain exists, return true otherwise return false.
 */
function site_domain_exists(string $sitedomain) : bool
{
    $qudb = app()->qudb;

    $site = $qudb->getVar(
        $qudb->prepare(
            "SELECT COUNT(*) FROM {$qudb->base_prefix}site WHERE site_domain = ?",
            [
                $sitedomain
            ]
        )
    );

    $exists = $site > 0 ? true : false;

    /**
     * Filters whether the given site domain exists or not.
     *
     * @since 1.0.0
     * @param bool $exists          Whether the site's domain is taken or not.
     * @param string $sitedomain    Site domain to check.
     */
    return ActionFilterHook::getInstance()->applyFilter('site_domain_exists', $exists, $sitedomain);
}

/**
 * Checks whether the given site exists.
 *
 * @file app/functions/site.php
 *
 * @since 1.0.0
 * @param string $site_domain   Site domain to check against.
 * @param string $site_path     Site path to check against.
 * @return bool If site exists, return true otherwise return false.
 */
function site_exists(string $site_domain, string $site_path) : bool
{
    $qudb = app()->qudb;

    $site = $qudb->getVar(
        $qudb->prepare(
            "SELECT COUNT(*) FROM {$qudb->base_prefix}site WHERE site_domain = ? AND site_path = ?",
            [
                $site_domain,
                $site_path
            ]
        )
    );

    $exists = $site > 0 ? true : false;

    /**
     * Filters whether the given sitedata exists or not.
     *
     * @since 1.0.0
     * @param bool $exists          Whether the site exists or not.
     * @param string $site_domain   Site domain to check against.
     * @param string $site_path     Site path to check against.
     */
    return ActionFilterHook::getInstance()->applyFilter('site_exists', $exists, $site_domain, $site_path);
}

/**
 * Adds user meta data for specified site.
 *
 * @file app/functions/site.php
 *
 * @since 1.0.0
 * @param int $site_id  Site ID.
 * @param array $params Parameters to set (assign_id or role).
 * @return bool True if usermete and role is added.
 */
function add_site_usermeta(int $site_id, array $params = [])
{
    $qudb = app()->qudb;

    $prefix = $qudb->base_prefix . "{$site_id}_";

    $userdata = get_userdata((int) $params['assign_id']);
    $data = [
        'username' => $userdata->getLogin(),
        'fname' => $userdata->getFname(),
        'lname' => $userdata->getLname(),
        'email' => $userdata->getEmail(),
        'bio' => $userdata->bio,
        'status' => $userdata->status,
        'admin_layout' => $userdata->admin_layout <= 0 ? (int) 0 : (int) $userdata->admin_layout,
        'admin_sidebar' => $userdata->admin_sidebar <= 0 ? (int) 0 : (int) $userdata->admin_sidebar,
        'admin_skin' => $userdata->admin_skin == null ? (string) 'skin-purple-light' : (string) $userdata->admin_skin
    ];
    foreach ($data as $meta_key => $meta_value) {
        update_usermeta((int) $params['assign_id'], $prefix . $meta_key, $meta_value);
    }

    $user = new User();
    $user->setId((int) $params['assign_id']);
    $user->setRole((string) $params['role']);

    return true;
}

/**
 * Create the needed directories when a new site is created.
 *
 * @file app/functions/hook.php
 *
 * @since 1.0.0
 * @param int $site_id Site ID.
 * @param object $site Site object.
 * @param bool $update Whether the site is being created or updated.
 * @return bool True on success or false on failure.
 */
function create_site_directories(int $site_id, $site, bool $update) : bool
{
    if ($update) {
        return false;
    }

    $site = get_site((int) $site_id);
    if (!$site) {
        return false;
    }

    try {
        ttcms()->obj['file']->mkdir(c::getInstance()->get('sites_dir') . (int) $site_id . DS . 'dropins' . DS);
        ttcms()->obj['file']->mkdir(
            c::getInstance()->get('sites_dir') . (int) $site_id . DS . 'files' . DS . 'cache' . DS
        );
        ttcms()->obj['file']->mkdir(
            c::getInstance()->get('sites_dir') . (int) $site_id . DS . 'files' . DS . 'logs' . DS
        );
        ttcms()->obj['file']->mkdir(c::getInstance()->get('sites_dir') . (int) $site_id . DS . 'themes' . DS);
        ttcms()->obj['file']->mkdir(c::getInstance()->get('sites_dir') . (int) $site_id . DS . 'uploads' . DS);
        ttcms()->obj['file']->mkdir(
            c::getInstance()->get('sites_dir') . (int) $site_id . DS . 'uploads' . DS . '__optimized__' . DS
        );
    } catch (Exception $ex) {
        Cascade::getLogger('error')->error(sprintf('IOSTATE[%s]: Forbidden: %s', $ex->getCode(), $ex->getMessage()));
    }

    return true;
}

/**
 * Deletes user meta data when site/user is deleted.
 *
 * @file app/functions/site.php
 *
 * @access private
 * @since 1.0.0
 * @param int $site_id Site ID.
 * @param object $old_site Site object of site that was deleted.
 * @return bool|\\PDOException|\\Qubus\\Exception\\Exception True on success or false on failure.
 */
function delete_site_usermeta(int $site_id, $old_site)
{
    $qudb = app()->qudb;

    $prefix = $qudb->base_prefix . $site_id;

    if (!is_numeric($site_id)) {
        return false;
    }

    if ((int) $site_id !== (int) $old_site->getId()) {
        return false;
    }

    $qudb->getConnection()->throwTransactionExceptions();
    try {
        $qudb->transaction(function ($qudb) use ($prefix) {
            $qudb
                ->from($qudb->base_prefix . 'usermeta')
                ->where('meta_key')->like("%$prefix%")
                ->delete();
        });

        $users = get_users_by_siteid($site_id);
        foreach ($users as $user) {
            $_user = new User();
            $_user->setId($user['user_id']);
            $_user->setLogin($user['user_login']);
            $_user->setEmail($user['user_email']);
            ttcms()->obj['usercache']->clean($user);
        }
    } catch (\PDOException $ex) {
        Cascade::getLogger('error')->error(sprintf('ERROR[%s]: %s', $ex->getCode(), $ex->getMessage()));
    } catch (Exception $ex) {
        Cascade::getLogger('error')->error(sprintf('ERROR[%s]: %s', $ex->getCode(), $ex->getMessage()));
    }

    return true;
}

/**
 * Deletes site tables when site is deleted.
 *
 * @file app/functions/site.php
 *
 * @since 1.0.0
 * @param int    $site_id  Site ID.
 * @param object $old_site Site object.
 * @return bool True on success or false on failure.
 */
function delete_site_tables(int $site_id, $old_site)
{
    $qudb = app()->qudb;

    if (!is_numeric($site_id)) {
        return false;
    }

    if ((int) $site_id !== (int) $old_site->getId()) {
        return false;
    }

    $tables = [];
    $prefix = $qudb->base_prefix . "{$site_id}_";
    $sql = $qudb->getResults(
        $qudb->prepare(
            "SHOW TABLES LIKE ?",
            [
                "$prefix%"
            ]
        ),
        ARRAY_A
    );

    foreach ($sql as $value) {
        foreach ($value as $row) {
            $tables[] = $row;
        }
    }

    /**
     * Filters the tables to drop when the site is deleted.
     *
     * @since 1.0.0
     * @param array $tables  Name array of the site tables to be dropped.
     * @param int   $site_id The ID of the site to drop tables for.
     */
    $drop_tables = ActionFilterHook::getInstance()->applyFilter('site_drop_tables', $tables, $site_id);

    try {
        $qudb->getConnection()->getPDO()->exec("SET FOREIGN_KEY_CHECKS=0;");

        foreach ((array) $drop_tables as $table) {
            $qudb->getConnection()->getPDO()->exec("DROP TABLE IF EXISTS $table");
        }

        $qudb->getConnection()->getPDO()->exec("SET FOREIGN_KEY_CHECKS=1;");
    } catch (\PDOException $ex) {
        Cascade::getLogger('error')->error(
            sprintf(
                'SQLSTATE[%s]: %s',
                $ex->getCode(),
                $ex->getMessage()
            ),
            [
                'Site Function' => 'delete_site_tables'
            ]
        );
    }

    return true;
}

/**
 * Deletes the site directory when the site is deleted.
 *
 * @file app/functions/site.php
 *
 * @since 1.0.0
 * @param int $site_id Site ID.
 * @param object $old_site Site object.
 * @return bool True on success or false on failure.
 */
function delete_site_directories(int $site_id, $old_site)
{
    if ((int) $site_id <= (int) 0) {
        return false;
    }

    if ((int) $site_id !== (int) $old_site->getId()) {
        return false;
    }

    ttcms()->obj['file']->rmdir(c::getInstance()->get('sites_dir') . (int) $site_id . DS);

    return true;
}

/**
 * Retrieve the current site id.
 *
 * @file app/functions/site.php
 *
 * @since 1.0.0
 * @return int Site ID.
 */
function get_current_site_id() : int
{
    return ttcms()->obj['util']->absint(c::getInstance()->get('site_id'));
}

/**
 * Update main site based Constants in config file.
 *
 * @file app/functions/site.php
 *
 * @since 1.0.0
 * @return bool
 */
function update_main_site()
{
    $qudb = app()->qudb;

    $main_site = $qudb->getRow(
        $qudb->prepare(
            "SELECT site_domain, site_path FROM {$qudb->base_prefix}site WHERE site_id = ?",
            [
                1
            ]
        ),
        ARRAY_A
    );

    if (esc_html($main_site['site_domain'])
        === TTCMS_MAINSITE
        && esc_html($main_site['site_path'])
        === TTCMS_MAINSITE_PATH
    ) {
        return false;
    }

    $qudb->getConnection()->throwTransactionExceptions();
    try {
        $qudb->transaction(function ($qudb) {
            $qudb->update($qudb->base_prefix . 'site')
                ->where('site_id')->is(1)
                ->set([
                    'site_domain' => (string) TTCMS_MAINSITE,
                    'site_path' => (string) TTCMS_MAINSITE_PATH,
                    'site_modified' => (string) (new Date())->current('db')
                ]);
        });
    } catch (\PDOException $ex) {
        Cascade::getLogger('error')->error(
            sprintf(
                'ERROR[%s]: %s',
                $ex->getCode(),
                $ex->getMessage()
            ),
            [
                'Site Function' => 'update_main_site'
            ]
        );
    }
}

/**
 * Retrieve a list of users based on site.
 *
 * @file app/functions/site.php
 *
 * @since 1.0.0
 * @return array Users data.
 */
function get_multisite_users()
{
    $qudb = app()->qudb;

    $list_users = $qudb->from($qudb->base_prefix . 'user')
        ->where('user_id')->in(function ($query) use ($qudb) {
            $query->from($qudb->base_prefix . 'usermeta')
                  ->distinct()
                  ->where('meta_key')->like("$qudb->prefix%")
                  ->select('user_id');
        })
        ->select()
        ->fetchAssoc()
        ->all();

    return $list_users;
}

/**
 * Add user to a site.
 *
 * @file app/functions/site.php
 *
 * @since 1.0.0
 * @param User|int $user  User to add to a site.
 * @param Site|int $site  Site to add user to.
 * @param string $role      Role to assign to user for this site.
 * @return int|false User id on success or false on failure.
 */
function add_user_to_site($user, $site, string $role)
{
    $qudb = app()->qudb;

    try {
        $acl = new RoleRepository(
            new RoleMapper(
                $qudb
            )
        );
        $role_id = $acl->findIdByKey($role);
    } catch (\PDOException $ex) {
        Cascade::getLogger('error')->error(
            sprintf(
                'SQLSTATE[%s]: %s',
                $ex->getCode(),
                $ex->getMessage()
            ),
            [
                'Site Function' => 'add_user_to_site'
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
                'Site Function' => 'add_user_to_site'
            ]
        );
    }

    if ($user instanceof User) {
        $userdata = $user;
    } else {
        $userdata = get_userdata($user);
    }

    if ($site instanceof Site) {
        $_site = $site;
    } else {
        $_site = get_site($site);
    }

    if (!username_exists($userdata->getLogin())) {
        return false;
    }

    if (!site_exists($_site->getDomain(), $_site->getPath())) {
        return false;
    }

    // Store values to save in user meta.
    $meta = [];

    $meta['username'] = $userdata->getLogin();

    $meta['fname'] = $userdata->getFname();

    $meta['lname'] = $userdata->getLname();

    $meta['email'] = $userdata->getEmail();

    $meta['bio'] = null;

    $meta['role'] = (int) $role_id;

    $meta['status'] = (string) 'A';

    $meta['admin_layout'] = (int) 0;

    $meta['admin_sidebar'] = (int) 0;

    $meta['admin_skin'] = (string) 'skin-purple-light';

    /**
     * Filters a user's meta values and keys immediately after the user is added
     * and before any user meta is inserted.
     *
     * @since 1.0.0
     * @param array $meta {
     *     Default meta values and keys for the user.
     *
     *     @type string $username       The user's username
     *     @type string $fname          The user's first name.
     *     @type string $lname          The user's last name.
     *     @type string $email          The user's email.
     *     @type string $bio            The user's bio.
     *     @type string $role           The user's role.
     *     @type string $status         The user's status.
     *     @type int    $admin_layout   The user's layout option.
     *     @type int    $admin_sidebar  The user's sidebar option.
     *     @type int    $admin_skin     The user's skin option.
     * }
     * @param $userdata User object.
     */
    $meta = ActionFilterHook::getInstance()->applyFilter('add_user_usermeta', $meta, $userdata);

    // Make sure meta data doesn't already exist for this user.
    $site_id = $_site->getId();
    $prefix = $qudb->base_prefix . "{$site_id}_";
    if (!get_usermeta($userdata->getId(), $prefix . $meta['role'], true)) {
        // Update user meta.
        foreach ($meta as $key => $value) {
            update_usermeta($userdata->getId(), $prefix . $key, $value);
        }
    }

    return (int) $userdata->getId();
}

/**
 * Insert a site into the database.
 *
 * Some of the `$sitedata` array fields have filters associated with the values. Exceptions are
 * 'site_owner', 'site_registered' and 'site_modified' The filters have the prefix 'pre_'
 * followed by the field name. An example using 'site_name' would have the filter called,
 * 'pre_site_name' that can be hooked into.
 *
 * @file app/functions/site.php
 *
 * @since 1.0.0
 * @param array|Site $sitedata {
 *     An array or Site array of user data arguments.
 *
 *     @type int        $site_id            Sites's ID. If supplied, the site will be updated.
 *     @type string     $site_domain        The site's domain.
 *     @type string     $site_name          The site's name/title.
 *     @type string     $site_path          The site's path.
 *     @type int        $site_owner         The site's owner.
 *     @type string     $site_status        The site's status.
 *     @type string     $site_registered    Date the site registered. Format is 'Y-m-d H:i:s'.
 *     @type string     $site_modified      Date the site's record was updated. Format is 'Y-m-d H:i:s'.
 * }
 * @param bool $exception       Whether to throw and exception or error.
 * @return int|Exception|Error  The newly created site's site_id or throws an Exception or Error if the site could not
 *                              be created.
 */
function ttcms_insert_site($sitedata, bool $exception = false)
{
    $qudb = app()->qudb;

    if ($sitedata instanceof Site) {
        $sitedata = get_object_vars($sitedata);
    }

    // Are we updating or creating?
    if (!empty($sitedata['site_id'])) {
        $update = true;
        $site_id = (int) $sitedata['site_id'];
        $site_before = get_site((int) $site_id);

        if (is_null($site_before)) {
            if ($exception) {
                throw new Exception(
                    esc_html__(
                        'The ID of this entity is invalid.'
                    ),
                    'invalid_id'
                );
            } else {
                return new Error(
                    'invalid_id',
                    esc_html__(
                        'The ID of this entity is invalid.'
                    )
                );
            }
        }
        $previous_status = get_site_status((int) $site_id);
        /**
         * Fires immediately before a site is inserted into the site document.
         *
         * @since 1.0.0
         * @param string    $previous_status    Status of the site before it is created.
         *                                      or updated.
         * @param int       $site_id            The site's site_id.
         * @param bool      $update             Whether this is an existing site or a new site.
         */
        ActionFilterHook::getInstance()->doAction('site_previous_status', $previous_status, (int) $site_id, $update);

        /**
         * Create new site object.
         */
        $site = new Site();
        $site->setId((int) $site_id);
    } else {
        $update = false;

        $previous_status = 'new';
        /**
         * Fires immediately before a site is inserted into the site document.
         *
         * @since 1.0.0
         * @param string    $previous_status    Status of the site before it is created.
         *                                      or updated.
         * @param int       $site_id            The site's site_id.
         * @param bool      $update             Whether this is an existing site or a new site.
         */
        ActionFilterHook::getInstance()->doAction('site_previous_status', $previous_status, (int) $site_id, $update);

        /**
         * Create new site object.
         */
        $site = new Site();
    }

    $raw_site_domain = isset($sitedata['subdomain']) ? trim(strtolower($sitedata['subdomain'])) . '.' . ttcms()->obj['app']->req->server['HTTP_HOST'] : trim(strtolower($sitedata['site_domain']));
    $sanitized_site_domain = ttcms()->obj['sanitizer']->item($raw_site_domain);
    /**
     * Filters a site's domain before the site is created or updated.
     *
     * @since 1.0.0
     * @param string $sanitized_site_domain Site domain after it has been sanitized
     * @param string $pre_site_domain The sites domain.
     */
    $pre_site_domain = ActionFilterHook::getInstance()->applyFilter(
        'pre_site_domain',
        (string) $sanitized_site_domain,
        (string) $raw_site_domain
    );

    $site_domain = trim($pre_site_domain);

    // site_domain cannot be empty.
    if (empty($site_domain)) {
        if ($exception) {
            throw new Exception(
                esc_html__(
                    'Cannot create a site with an empty domain name.'
                ),
                'empty_value'
            );
        } else {
            return new Error(
                'empty_value',
                esc_html__(
                    'Cannot create a site with an empty domain name.'
                )
            );
        }
    }

    if (!$update && site_domain_exists($site_domain)) {
        if ($exception) {
            throw new Exception(
                esc_html__(
                    'Sorry, that site already exists!'
                ),
                'duplicate'
            );
        } else {
            return new Error(
                'duplicate',
                esc_html__(
                    'Sorry, that site already exists!'
                )
            );
        }
    }
    $site->setDomain($site_domain);

    $raw_site_name = $sitedata['site_name'];
    $sanitized_site_name = ttcms()->obj['sanitizer']->item($raw_site_name);
    /**
     * Filters a site's name before the site is created or updated.
     *
     * @since 1.0.0
     * @param string $sanitized_site_name Site name after it has been sanitized
     * @param string $raw_site_name The site's name.
     */
    $site_name = ActionFilterHook::getInstance()->applyFilter(
        'pre_site_name',
        (string) $sanitized_site_name,
        (string) $raw_site_name
    );
    $site->setName($site_name);

    if (isset($sitedata['site_slug'])) {
        /**
         * ttcms_unique_site_slug will take the original slug supplied and check
         * to make sure that it is unique. If not unique, it will make it unique
         * by adding a number at the end.
         */
        $site_slug = ttcms_unique_site_slug($sitedata['site_slug'], $site_name, $site_id);
    } else {
        /**
         * For an update, don't modify the site_slug if it
         * wasn't supplied as an argument.
         */
        $site_slug = $site_before->getSlug();
    }

    $raw_site_slug = $site_slug;
    $sanitized_site_slug = ttcms()->obj['sanitizer']->item($raw_site_slug);
    /**
     * Filters a site's slug before created/updated.
     *
     * @since 1.0.0
     * @param string $sanitized_site_slug Site slug after it has been sanitized
     * @param string $raw_site_slug The site's slug.
     */
    $site_slug = ActionFilterHook::getInstance()->applyFilter(
        'pre_site_slug',
        (string) $sanitized_site_slug,
        (string) $raw_site_slug
    );
    $site->setSlug($site_slug);

    $raw_site_path = $sitedata['site_path'];
    $sanitized_site_path = ttcms()->obj['sanitizer']->item($raw_site_path);
    /**
     * Filters a site's path before the site is created or updated.
     *
     * @since 1.0.0
     * @param string $sanitized_site_path Site path after it has been sanitized
     * @param string $raw_site_path The site's path.
     */
    $site_path = ActionFilterHook::getInstance()->applyFilter(
        'pre_site_path',
        (string) $sanitized_site_path,
        (string) $raw_site_path
    );
    $site->setPath($site_path);

    /*
     * If there is no update, just check for `email_exists`. If there is an update,
     * check if current email and new email are the same, or not, and check `email_exists`
     * accordingly.
     */
    if ((!$update || (!empty($site_before) && 0 !== strcasecmp($site_domain . $site_path, $site_before->getDomain() . $site_before->getPath()))) && site_exists($site_domain, $site_path)
    ) {
        if ($exception) {
            throw new Exception(
                esc_html__(
                    'Sorry, that site domain and path is already used.'
                ),
                'duplicate'
            );
        } else {
            return new Error(
                'duplicate',
                esc_html__(
                    'Sorry, that site domain and path is already used.'
                )
            );
        }
    }

    $site_owner = $sitedata['site_owner'] == '' ? get_current_user_id() : $sitedata['site_owner'];
    $site->setOwner($site_owner);

    $raw_site_status = $sitedata['site_status'] == '' ? (string) 'public' : $sitedata['site_status'];
    $sanitized_site_status = ttcms()->obj['sanitizer']->item($raw_site_status);
    /**
     * Filters a site's status before the site is created or updated.
     *
     * @since 1.0.0
     * @param string $sanitized_site_status Site status after it has been sanitized
     * @param string $raw_site_status The site's status.
     */
    $site_status = ActionFilterHook::getInstance()->applyFilter(
        'pre_site_status',
        (string) $sanitized_site_status,
        (string) $raw_site_status
    );
    $site->setStatus($site_status);

    $site_registered = (string) (new Date())->current('db');

    $site_modified = (string) (new Date())->current('db');

    $compacted = compact(
        'site_id',
        'site_name',
        'site_slug',
        'site_domain',
        'site_path',
        'site_owner',
        'site_status',
        'site_registered',
        'site_modified'
    );
    $data = ttcms()->obj['util']->unslash($compacted);

    /**
     * Filters site data before the record is created or updated.
     *
     * @since 1.0.0
     * @param array    $data {
     *     Values and keys for the site.
     *
     *     @type string $site_id        The site's id
     *     @type string $site_domain    The site's domain
     *     @type string $site_name      The site's name/title.
     *     @type string $site_slug      The site's slug.
     *     @type string $site_path      The site's path.
     *     @type int    $site_owner     The site's owner.
     *     @type string $site_status    The site's status.
     * }
     * @param bool     $update      Whether the site is being updated rather than created.
     * @param int|null $site_id     ID of the site to be updated, or NULL if the site is being created.
     */
    $data = ActionFilterHook::getInstance()->applyFilter(
        'ttcms_pre_insert_site_data',
        $data,
        $update,
        $update ? (int) $site_id : null
    );

    if ($update) {
        $site->setModified($site_modified);

        try {
            $site_id = (new SiteRepository(
                new SiteMapper(
                    $qudb,
                    new HelperContext()
                )
            ))->update($site);
        } catch (\PDOException $ex) {
            Cascade::getLogger('error')->error(
                sprintf(
                    'SQLSTATE[%s]: %s',
                    $ex->getCode(),
                    $ex->getMessage()
                ),
                [
                    'Site Function' => 'ttcms_insert_site'
                ]
            );
        }

        if (false === $site_id) {
            if ($exception) {
                throw new Exception(
                    esc_html__(
                        'Could not update site within the site table.'
                    ),
                    'write_error'
                );
            } else {
                return new Error(
                    'write_error',
                    esc_html__(
                        'Could not update site within the site table.'
                    )
                );
            }
        }
    } else {
        $site->setRegistered($site_registered);

        try {
            $site_id = (new SiteRepository(
                new SiteMapper(
                    $qudb,
                    new HelperContext()
                )
            ))->insert($site);
        } catch (\PDOException $ex) {
            Cascade::getLogger('error')->error(
                sprintf(
                    'SQLSTATE[%s]: %s',
                    $ex->getCode(),
                    $ex->getMessage()
                ),
                [
                    'Site Function' => 'ttcms_insert_site'
                ]
            );
        }

        if (false === $site_id) {
            if ($exception) {
                throw new Exception(
                    esc_html__(
                        'Could not write data to site table.'
                    ),
                    'write_error'
                );
            } else {
                return new Error(
                    'write_error',
                    esc_html__(
                        'Could not write data to site table.'
                    )
                );
            }
        }
    }

    $site = get_site((int) $site_id);

    ttcms()->obj['sitecache']->clean($site);

    if ($update) {
        /**
         * Fires immediately after an existing site is updated.
         *
         * @since 1.0.0
         * @param int  $site_id    Site ID.
         * @param Site $site       Site data object.
         */
        ActionFilterHook::getInstance()->doAction('update_site', $site_id, $site);
        $site_after = get_site((int) $site_id);
        /**
         * Action hook triggered after existing site has been updated.
         *
         * @since 1.0.0
         * @param int  $site_id      Site id.
         * @param Site $site_after   Site object following the update.
         * @param Site $site_before  Site object before the update.
         */
        ActionFilterHook::getInstance()->doAction('site_updated', (int) $site_id, $site_after, $site_before);
    }

    /**
     * Fires immediately after a new site is saved.
     *
     * @since 1.0.0
     * @param int  $site_id Site ID.
     * @param Site $site    Site object.
     * @param bool $update  Whether this is an existing site or a new site.
     */
    ActionFilterHook::getInstance()->doAction('save_site', $site_id, $site, $update);

    /**
     * Action hook triggered after site has been saved.
     *
     * The dynamic portion of this hook, `$site_status`,
     * is the site's status.
     *
     * @since 1.0.0
     * @param int  $site_id    The site's id.
     * @param Site $site       Site object.
     * @param bool $update     Whether this is an existing site or a new site.
     */
    ActionFilterHook::getInstance()->doAction("save_site_{$site_status}", (int) $site_id, $site, $update);

    /**
     * Action hook triggered after site has been saved.
     *
     * @since 1.0.0
     * @param int  $site_id    The site's id.
     * @param Site $site       Site object.
     * @param bool $update     Whether this is an existing site or a new site.
     */
    ActionFilterHook::getInstance()->doAction('ttcms_after_insert_site_data', (int) $site_id, $site, $update);

    return $site_id;
}

/**
 * Update a site in the database.
 *
 * See ttcms_insert_site() For what fields can be set in $sitedata.
 *
 * @file app/functions/site.php
 *
 * @since 1.0.0
 * @param int|array|Site $sitedata An array of site data or a site object or site id.
 * @return int The updated site's id..
 * @throws \\Qubus\\Exception\\Exception|\\Qubus\\Exception\\Error
 *         Throws an Exception or Error if the site could not be updated
 */
function ttcms_update_site($sitedata, bool $exception = false)
{
    $qudb = app()->qudb;

    $prefix = $qudb->base_prefix;

    if ($sitedata instanceof Site) {
        $sitedata = get_object_vars($sitedata);
    }

    $details = $qudb->getRow(
        $qudb->prepare(
            "SELECT site_owner FROM {$qudb->base_prefix}site WHERE site_id = ?",
            [
                $sitedata['site_id']
            ]
        ),
        ARRAY_A
    );
    if ((int) $details['site_owner'] !== (int) $sitedata['site_owner']) {
        $owner_change = true;
        $previous_owner = $details['site_owner'];
    } else {
        $owner_change = false;
    }

    $ID = isset($sitedata['site_id']) ? (int) $sitedata['site_id'] : (int) 0;
    if ((int) $ID <= 0) {
        if ($exception) {
            throw new Exception(
                esc_html__(
                    'The ID of this entity is invalid.'
                ),
                'invalid_id'
            );
        } else {
            return new Error(
                'invalid_id',
                esc_html__(
                    'The ID of this entity is invalid.'
                )
            );
        }
    }

    $site_id = ttcms_insert_site($sitedata);

    /**
     * If the site admin has changed, delete usermeta data of the old admin
     * and add usermeta data for the new
     */
    if ($site_id > 0 && $owner_change) {
        $meta_key = $prefix . $site_id;
        $old_meta = $qudb->getResults(
            $qudb->prepare(
                "SELECT meta_key, meta_value FROM {$qudb->base_prefix}usermeta WHERE user_id = ? AND meta_key LIKE ?",
                [
                    $previous_owner,
                    "%$meta_key%"
                ]
            ),
            ARRAY_A
        );
        foreach ($old_meta as $meta) {
            delete_usermeta((int) $previous_owner, $meta['meta_key'], $meta['meta_value']);
        }
        add_user_to_site((int) $sitedata['site_owner'], (int) $sitedata['site_id'], 'admin');
    }

    ttcms()->obj['sitecache']->clean($site_id);

    return $site_id;
}

/**
 * Deletes a site.
 *
 * @file app/functions/site.php
 *
 * @since 1.0.0
 * @param int $site_id ID of site to delete.
 * @param bool $exception Whether to throw an exception.
 * @return bool|Error|Exception Returns true on delete or throw.
 * @throws \\Qubus\\Exception\\Exception|\\Qubus\\Exception\\Error
 *         Throws an Exception or Error if site could not be deleted.
 */
function ttcms_delete_site(int $site_id, bool $exception = false)
{
    $qudb = app()->qudb;

    if ((int) $site_id == (int) 1) {
        ttcms()->obj['flash']->error(
            esc_html__(
                'You are not allowed to delete the main site.'
            )
        );
        exit();
    }

    $old_site = get_site((int) $site_id);

    if (!$old_site) {
        if ($exception) {
            throw new Exception(
                esc_html__(
                    'Site does not exist.'
                ),
                'not_found'
            );
        } else {
            return new Error(
                'not_found',
                esc_html__(
                    'Site does not exist.'
                )
            );
        }
    }

    /**
     * Action hook triggered before the site is deleted.
     *
     * @since 1.0.0
     * @param int  $id       Site ID.
     * @param Site $old_site Data object of site to be deleted.
     */
    ActionFilterHook::getInstance()->doAction('delete_site', (int) $site_id, $old_site);

    try {
        $delete = (
          new SiteRepository(
              new SiteMapper(
                  $qudb,
                  new HelperContext()
              )
          )
      )->delete($old_site);
    } catch (\PDOException $ex) {
        Cascade::getLogger('error')->error(
            sprintf(
                'SQLSTATE[%s]: %s',
                $ex->getCode(),
                $ex->getMessage()
            ),
            [
                'Site Function' => 'ttcms_delete_site'
            ]
        );
    }

    if (is_ttcms_exception($delete)) {
        $exception ? sprintf('ERROR[%s]: %s', $delete->getCode(), $delete->getMessage()) : new Error($delete->getCode(), $delete->getMessage());
    }

    /**
     * Action hook triggered after the site is deleted.
     *
     * @since 1.0.0
     * @param int $id        Site ID.
     * @param Site $old_site Site object that was deleted.
     */
    ActionFilterHook::getInstance()->doAction('deleted_site', (int) $site_id, $old_site);

    ttcms()->obj['sitecache']->clean($old_site);

    return true;
}

/**
 * Delete site user.
 *
 * @file app/functions/site.php
 *
 * @since 1.0.0
 * @param int $user_id      The id of user to be deleted.
 * @param array $params     User parameters (assign_id and role).
 * @param bool $exception   Whether or not to throw an exception.
 * @return bool|Exception|Error Returns true if successful or will throw and exception or error otherwise.
 */
function ttcms_delete_site_user(int $user_id, array $params = [], bool $exception = false)
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

    $user = get_userdata((int) $user_id);

    if (!$user) {
        return false;
    }

    $sites = get_users_sites((int) $user_id);

    if ((int) $params['assign_id'] > 0) {
        $assign_user = get_userdata((int) $params['assign_id']);
        /**
         * Clean cache of the assigned user.
         */
        ttcms()->obj['usercache']->clean($assign_user);
        /**
         * We need to reassign the site(s) to the selected user and create the
         * needed usermeta for the site.
         */
        if (null != $sites && false != $sites) {
            foreach ($sites as $site) {
                /**
                 * Create new site object from array.
                 */

                /*$_site = new Site();
                $_site->setId((int) $site['site_id']);
                $_site->setSlug($site['site_slug']);
                $_site->setDomain($site['site_domain']);
                $_site->setPath($site['site_path']);*/

                ttcms()->obj['sitecache']->clean($site);
                add_user_to_site((int) $params['assign_id'], (int) $site->getId(), $params['role']);
            }
            /**
             * Filter hook is triggered when assign_id is greater than zero.
             *
             * Sites will be reassigned before the user is deleted.
             *
             * @since 1.0.0
             * @param int $user_id    ID of user to be deleted.
             * @param array $params   User parameters (assign_id and role).
             */
            $params = ActionFilterHook::getInstance()->applyFilter('reassign_sites', (int) $user_id, $params);
        }
    } else {
        if (null != $sites && false != $sites) {
            foreach ($sites as $old_site) {
                $qudb->getConnection()->throwTransactionExceptions();
                try {
                    $qudb->transaction(function ($qudb) use ($user_id) {
                        $qudb
                            ->from($qudb->base_prefix . 'site')
                            ->where('site_owner')->is((int) $user_id)
                            ->delete();
                    });
                } catch (\PDOException $ex) {
                    if ($exception) {
                        sprintf('ERROR[%s]: %s', $ex->getCode(), $ex->getMessage());
                    } else {
                        return new Error($ex->getCode(), $ex->getMessage());
                    }
                }

                $site = (
                    new SiteMapper(
                        $qudb,
                        new HelperContext()
                    )
                )->create($old_site);

                ttcms()->obj['sitecache']->clean((int) $site->getId());

                /**
                 * Action hook triggered after the site is deleted.
                 *
                 * @since 1.0.0
                 * @param int  $site_id    Site ID.
                 * @param Site $site       Site object that was deleted.
                 */
                ActionFilterHook::getInstance()->doAction('deleted_site', (int) $site->getId(), $site);
            }
        }
    }

    /**
     * Action hook fires immediately before a user is deleted from the usermeta document.
     *
     * @since 1.0.0
     * @param int   $user_id  ID of the user to delete.
     * @param array $params   User parameters (assign_id and role).
     */
    ActionFilterHook::getInstance()->doAction('delete_site_user', (int) $user_id, $params);

    /**
     * Finally delete the user.
     */
    $qudb->getConnection()->throwTransactionExceptions();
    try {
        $qudb->transaction(function ($qudb) use ($user_id) {
            $qudb
                ->from($qudb->base_prefix . 'user')
                ->where('user_id')->is((int) $user_id)
                ->delete();
        });

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
                ))->deleteByMid('user', $mid['meta_id']);
            }
        }
    } catch (\PDOException $ex) {
        if ($exception) {
            return sprintf('ERROR[%s]: %s', $ex->getCode(), $ex->getMessage());
        } else {
            return new Error(sprintf('ERROR[%s]: %s', $ex->getCode(), $ex->getMessage()));
        }
    }

    /**
     * Clear the cache of the deleted user.
     */
    ttcms()->obj['usercache']->clean($user);

    /**
     * Action hook fires immediately after a user has been deleted from the usermeta document.
     *
     * @since 1.0.0
     * @param int $user_id     ID of the user who was deleted.
     * @param array $params    User parameters (assign_id and role).
     */
    ActionFilterHook::getInstance()->doAction('deleted_site_user', (int) $user_id, $params);

    return true;
}

/**
 * Creates new tables and user meta for site admin after new site
 * is created.
 *
 * @file app/functions/site.php
 *
 * @since 1.0.0
 * @access private Used when the action hook `site_register` is called.
 * @param int $site_id  Site id of the newly created site.
 * @param Site $site  Site object of newly created site.
 * @param bool $update  Whether the site is being created or updated.
 * @return int|bool Returns the site id if successful or false on failure.
 */
function new_site_schema(int $site_id, $site, bool $update)
{
    $qudb = app()->qudb;

    if ($update) {
        return false;
    }

    $site = get_site((int) $site_id);

    if (!$site) {
        return false;
    }

    $userdata = get_userdata((int) $site->getOwner());
    $api_key = _ttcms_random_lib()->generateString(
        20,
        '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'
    );

    $base_prefix = $qudb->base_prefix;
    $site_prefix = $base_prefix . "{$site_id}_";
    $now = (new Date())->current('db', true);

    $qudb->schema()->create($site_prefix . 'option', function (CreateTable $table) {
        $table->integer('option_id')->size('big')->autoincrement();
        $table->string('options_key', 191);
        $table->unique('option_key', 'option_key');
        $table->text('option_value')->size('big');
    });
    $qudb->schema()->create($site_prefix . 'plugin', function (CreateTable $table) {
        $table->integer('plugin_id')->size('big')->autoincrement();
        $table->string('plugin_location', 191);
        $table->unique('plugin_location', 'plugin_location');
    });
    $qudb->schema()->create($site_prefix . 'post', function (CreateTable $table) {
        $table->integer('post_id')->size('big')->autoincrement();
        $table->string('post_title', 191)->notNull();
        $table->string('post_slug', 191)->notNull();
        $table->unique(['post_slug','post_posttype'], 'post_slug');
        $table->text('post_content')->size('big');
        $table->integer('post_author')->size('big');
        $table->foreign('post_author', $site_prefix . 'post_post_author')
              ->references($base_prefix . 'user', 'user_id')
              ->onDelete('set null')
              ->onUpdate('cascade');
        $table->string('post_posttype', 191)->notNull();
        $table->foreign('post_posttype', $site_prefix . 'post_post_posttype')
              ->references($site_prefix . 'posttype', 'posttype_slug')
              ->onDelete('cascade')
              ->onUpdate('cascade');
        $table->integer('post_parent')->size('big');
        $table->foreign('post_parent', $site_prefix . 'post_post_parent')
              ->references($site_prefix . 'post', 'post_id')
              ->onDelete('set null')
              ->onUpdate('cascade');
        $table->integer('post_sidebar')->size('tiny')->defaultValue(0);
        $table->integer('post_show_in_menu')->size('tiny')->defaultValue(0);
        $table->integer('post_show_in_search')->size('tiny')->defaultValue(0);
        $table->string('post_relative_url', 191);
        $table->string('post_featured_image', 191);
        $table->string('post_status', 191);
        $table->string('post_created', 30);
        $table->dateTime('post_created_gmt');
        $table->string('post_published', 30);
        $table->dateTime('post_published_gmt');
        $table->string('post_modified', 30)->defaultValue('0000-00-00 00:00:00');
        $table->dateTime('post_modified_gmt')->defaultValue('0000-00-00 00:00:00');
    });
    $qudb->schema()->create($site_prefix . 'postmeta', function (CreateTable $table) {
        $table->integer('meta_id')->size('big')->autoincrement();
        $table->integer('post_id')->size('big');
        $table->foreign('post_id', $site_prefix . 'postmeta_post_id')
              ->references($site_prefix . 'post', 'post_id')
              ->onDelete('cascade')
              ->onUpdate('cascade');
        $table->string('meta_key', 191);
        $table->unique(['post_id', 'meta_key'], 'meta_key');
        $table->text('meta_value')->size('big');
    });
    $qudb->schema()->create($site_prefix . 'posttype', function (CreateTable $table) {
        $table->integer('posttype_id')->size('big')->autoincrement();
        $table->string('posttype_title', 191);
        $table->string('posttype_slug', 191);
        $table->unique('posttype_slug', 'posttype_slug');
        $table->text('posttype_description');
    });

    $insert_data = (
        new FileSystem(
            ActionFilterHook::getInstance()
        )
    )->getContents(APP_PATH . 'views/_layouts/new_site_db_insert.tpl');
    $insert_data = str_replace('{site_prefix}', $site_prefix, $insert_data);
    $insert_data = str_replace('{base_prefix}', $base_prefix, $insert_data);
    $insert_data = str_replace('{sitename}', $site->getName(), $insert_data);
    $insert_data = str_replace('{admin_email}', $userdata->getEmail(), $insert_data);
    $insert_data = str_replace('{api_key}', $api_key, $insert_data);
    $insert_data = str_replace('{created_date}', $api_key, $insert_data);
    $insert_data = str_replace('{created_date_gmt}', $now, $insert_data);
    $insert_data = str_replace('{published_date}', $api_key, $insert_data);
    $insert_data = str_replace('{published_date_gmt}', $now, $insert_data);

    try {
        $qudb->getConnection()->getPDO()->exec($insert_data);
    } catch (\PDOException $ex) {
        Cascade::getLogger('error')->error(
            sprintf(
                'SQLSTATE[new_site]: %s',
                $ex->getMessage()
            ),
            [
                'Site Function' => 'new_site_schema'
            ]
        );
    }
    // Store values to save in user meta.
    $meta = [];
    $meta['username'] = $userdata->getLogin();
    $meta['fname'] = $userdata->getFname();
    $meta['lname'] = $userdata->getLname();
    $meta['email'] = $userdata->getEmail();
    $meta['bio'] = null;
    $meta['role'] = (int) 2;
    $meta['status'] = (string) 'A';
    $meta['admin_layout'] = (int) 0;
    $meta['admin_sidebar'] = (int) 0;
    $meta['admin_skin'] = (string) 'skin-purple-light';
    /**
     * Filters a user's meta values and keys immediately after the user is added
     * and before any user meta is inserted.
     *
     * @since 1.0.0
     * @param array $meta {
     *     Default meta values and keys for the user.
     *
     *     @type string $username       The user's username
     *     @type string $fname          The user's first name.
     *     @type string $lname          The user's last name.
     *     @type string $email          The user's email.
     *     @type string $bio            The user's bio.
     *     @type string $role           The user's role.
     *     @type string $status         The user's status.
     *     @type int    $admin_layout   The user's layout option.
     *     @type int    $admin_sidebar  The user's sidebar option.
     *     @type int    $admin_skin     The user's skin option.
     * }
     * @param object $userdata   User object.
     */
    $meta = ActionFilterHook::getInstance()->applyFilter('new_site_usermeta', $meta, $userdata);
    // Update user meta.
    foreach ($meta as $key => $value) {
        update_usermeta($userdata->getId(), $site_prefix . $key, $value);
    }
    return (int) $site->getId();
}

/**
 * Adds status label for site's table.
 *
 * @file app/functions/site.php
 *
 * @since 1.0.0
 * @param string $status Status to check for.
 * @return string Site's status.
 */
function ttcms_site_status_label(string $status)
{
    $label = [
        'public' => 'label-success',
        'archive' => 'label-danger'
    ];

    /**
     * Filters the label result.
     *
     * @since 1.0.0
     * @param
     */
    return ActionFilterHook::getInstance()->applyFilter('site_status_label', $label[$status], $status);
}

/**
 * Checks if site exists or is archived.
 *
 * @file app/functions/site.php
 *
 * @since 1.0.0
 */
function does_site_exist()
{
    $qudb = app()->qudb;

    $base_url = site_url();
    $site_path = str_replace('index.php', '', ttcms()->obj['app']->req->server['PHP_SELF']);
    $site_domain = str_replace(['http://', 'https://', $site_path], '', $base_url);

    $site = (
        new SiteRepository(
            new SiteMapper(
                $qudb,
                new HelperContext()
            )
        )
    )->findBySql(
        'site_id, site_status',
        'site_domain = ? AND site_path = ?',
        [
            $site_domain,
            $site_path
        ]
    );

    if (!$site) {
        ttcms()->obj['app']->res->_format('json', 404);
        exit();
    }

    if (esc_html($site['site_status']) === 'archive') {
        ttcms()->obj['app']->res->_format('json', 503);
        exit();
    }
}

/**
 * A function which retrieves Qubus CMS site name.
 *
 * Purpose of this function is for the `site_name`
 * filter.
 *
 * @file app/functions/site.php
 *
 * @since 1.0.0
 * @param int $site_id The unique id of a site.
 * @return string Site's name on success or false on failure.
 */
function get_site_name(int $site_id = 0)
{
    $site = get_site((int) $site_id);

    if (!$site) {
        return false;
    }

    $name = $site->getName();
    /**
     * Filters the site name.
     *
     * @since 1.0.0
     *
     * @param string    $name The site's name.
     * @param int       $site_id The site ID.
     */
    return ActionFilterHook::getInstance()->applyFilter('site_name', $name, $site_id);
}

/**
 * A function which retrieves Qubus CMS site domain.
 *
 * Purpose of this function is for the `site_domain`
 * filter.
 *
 * @file app/functions/site.php
 *
 * @since 1.0.0
 * @param int $site_id The unique id of a site.
 * @return string Site's domain on success or false on failure.
 */
function get_site_domain(int $site_id = 0)
{
    $site = get_site((int) $site_id);

    if (!$site) {
        return false;
    }

    $domain = $site->getDomain();
    /**
     * Filters the site domain.
     *
     * @since 1.0.0
     *
     * @param string    $domain The site's domain.
     * @param int       $site_id The site ID.
     */
    return ActionFilterHook::getInstance()->applyFilter('site_domain', $domain, $site_id);
}

/**
 * A function which retrieves Qubus CMS site path.
 *
 * Purpose of this function is for the `site_path`
 * filter.
 *
 * @file app/functions/site.php
 *
 * @since 1.0.0
 * @param int $site_id The unique id of a site.
 * @return string Site's path on success or false on failure.
 */
function get_site_path(int $site_id = 0)
{
    $site = get_site((int) $site_id);

    if (!$site) {
        return false;
    }

    $path = $site->getPath();
    /**
     * Filters the site path.
     *
     * @since 1.0.0
     *
     * @param string    $path The site's path.
     * @param int       $site_id The site ID.
     */
    return ActionFilterHook::getInstance()->applyFilter('site_path', $path, $site_id);
}

/**
 * A function which retrieves Qubus CMS site owner.
 *
 * Purpose of this function is for the `site_owner`
 * filter.
 *
 * @file app/functions/site.php
 *
 * @since 1.0.0
 * @param int $site_id The unique id of a site.
 * @return string Site's owner on success or false on failure.
 */
function get_site_owner(int $site_id = 0)
{
    $site = get_site((int) $site_id);

    if (!$site) {
        return false;
    }

    $owner = $site->getOwner();
    /**
     * Filters the site owner.
     *
     * @since 1.0.0
     *
     * @param string    $owner The site's owner.
     * @param int       $site_id The site ID.
     */
    return ActionFilterHook::getInstance()->applyFilter('site_owner', $owner, $site_id);
}

/**
 * A function which retrieves Qubus CMS site status.
 *
 * Purpose of this function is for the `site_status`
 * filter.
 *
 * @file app/functions/site.php
 *
 * @since 1.0.0
 * @param int $site_id The unique id of a site.
 * @return string Site's status on success or false on failure.
 */
function get_site_status(int $site_id = 0)
{
    $site = get_site((int) $site_id);

    if (!$site) {
        return false;
    }

    $status = esc_html($site->getStatus());
    /**
     * Filters the site status.
     *
     * @since 1.0.0
     *
     * @param string    $status The site's status.
     * @param int       $site_id The site ID.
     */
    return ActionFilterHook::getInstance()->applyFilter('site_status', $status, $site_id);
}

/**
 * Retrieve the details of a site from the site document and site options.
 *
 * @file app/functions/site.php
 *
 * @since 1.0.0
 * @param int|string|array $fields A site's id or an array of site data.
 * @param bool $get_all Whether to retrieve all data or only data from site document.
 * @return bool|Error|\TriTan\Site Site details on success or false.
 */
function get_site_details($fields = null, bool $get_all = true)
{
    $qudb = app()->qudb;

    if (is_array($fields)) {
        if (null !== $fields['site_id']) {
            $site_id = (int) $fields['site_id'];
        } elseif (isset($fields['site_domain']) && isset($fields['site_path'])) {
            $key = md5($fields['site_domain'] . $fields['site_path']);
            $site = ttcms()->obj['cache']->read($key, 'site_lookup');

            if (false !== $site) {
                return $site;
            }

            if (substr($fields['site_domain'], 0, 4) == 'www.') {
                $nowww = substr($fields['site_domain'], 4);
                $site = $qudb->getRow(
                    $qudb->prepare(
                        "SELECT * FROM {$qudb->base_prefix}site WHERE site_domain IN(?,?) AND site_path = ? ORDER BY site_domain DESC",
                        [
                            $nowww,
                            $fields['site_domain'],
                            $fields['site_path']
                        ]
                    ),
                    ARRAY_A
                );
            } else {
                $site = $qudb->getRow(
                    $qudb->prepare(
                        "SELECT * FROM {$qudb->base_prefix}site WHERE site_domain = ? AND site_path = ?",
                        [
                            $fields['site_domain'],
                            $fields['site_path']
                        ]
                    ),
                    ARRAY_A
                );
            }

            if (null !== $site) {
                ttcms()->obj['cache']->set((int) $site['site_id'] . 'short', $site, 'site_details');
                $site_id = (int) $site['site_id'];
            } else {
                return false;
            }
        } elseif (isset($fields['site_domain'])) {
            $key = md5($fields['site_domain']);
            $site = ttcms()->obj['cache']->read($key, 'site_lookup');

            if (false !== $site) {
                return $site;
            }

            if (substr($fields['site_domain'], 0, 4) == 'www.') {
                $nowww = substr($fields['site_domain'], 4);
                $site = $qudb->getRow(
                    $qudb->prepare(
                        "SELECT * FROM {$qudb->base_prefix}site WHERE site_domain IN(?,?) ORDER BY site_domain DESC",
                        [
                            $nowww,
                            $fields['site_domain']
                        ]
                    ),
                    ARRAY_A
                );
            } else {
                $site = $qudb->getRow(
                    $qudb->prepare(
                        "SELECT * FROM {$qudb->base_prefix}site WHERE site_domain = ?",
                        [
                            $fields['site_domain']
                        ]
                    ),
                    ARRAY_A
                );
            }

            if ($site) {
                ttcms()->obj['cache']->set((int) $site['site_id'] . 'short', $site, 'site_details');
                $site_id = (int) $site['site_id'];
            } else {
                return false;
            }
        } else {
            return false;
        }
    } else {
        if (!$fields) {
            $site_id = get_current_site_id();
        } elseif (!is_numeric($fields)) {
            $site_id = call_user_func_array("get_{$fields}", [(int) $site['site_id']]);
        } else {
            $site_id = $fields;
        }
    }

    $site_id = (int) $site_id;
    $all = $get_all == true ? '' : 'short';
    $details = ttcms()->obj['cache']->read($site_id . $all, 'site_details');

    if ($details) {
        if (!is_object($details)) {
            if (-1 == $details) {
                return false;
            } else {
                // Clear old pre-json object. Cache clients do better with that.
                ttcms()->obj['cache']->delete($site_id . $all, 'site_details');
                unset($details);
            }
        } else {
            return $details;
        }
    }

    // Try the other cache.
    if ($get_all) {
        $details = ttcms()->obj['cache']->read($site_id . 'short', 'site_details');
    } else {
        $details = ttcms()->obj['cache']->read($site_id, 'site_details');
        // If short was requested and full cache is set, we can return.
        if ($details) {
            if (!is_object($details)) {
                if (-1 == $details) {
                    return false;
                } else {
                    // Clear old pre-json object. Cache clients do better with that.
                    ttcms()->obj['cache']->delete($site_id, 'site_details');
                    unset($details);
                }
            } else {
                return $details;
            }
        }
    }

    if (empty($details)) {
        $details = (new SiteRepository(
            new SiteMapper(
                $qudb,
                new HelperContext()
            )
        ))->findById($site_id);
        if (!$details) {
            // Set the full cache.
            ttcms()->obj['cache']->set($site_id, -1, 'site_details');
            return false;
        }
    }

    if (!$details instanceof SiteRepository) {
        return null;
    }

    if (!$get_all) {
        ttcms()->obj['cache']->set($site_id . $all, $details, 'site_details');
        return $details;
    }

    /**
     * Filters a site's details.
     *
     * @since 1.0.0
     * @param object $details The site's details.
     */
    $details = ActionFilterHook::getInstance()->applyFilter('site_details', $details);

    ttcms()->obj['cache']->set($site_id . $all, $details, 'site_details');

    $key = md5($details->getDomain() . $details->getPath());
    ttcms()->obj['cache']->set($key, $details, 'site_lookup');

    return $details;
}

/**
 * Creates a unique site slug.
 *
 * @file app/functions/site.php
 *
 * @since 1.0.0
 * @param string $original_slug     Original slug of site.
 * @param string $original_title    Original title of site.
 * @param int|null $site_id         Unique site id or null.
 * @return string Unique site slug.
 */
function ttcms_unique_site_slug(string $original_slug, string $original_title, $site_id)
{
    if (ttcms_site_slug_exist($site_id, $original_slug)) {
        $site_slug = ttcms_slugify($original_title, 'site');
    } else {
        $site_slug = $original_slug;
    }
    /**
     * Filters the unique site slug before returned.
     *
     * @since 1.0.0
     * @param string    $site_slug      Unique site slug.
     * @param string    $original_slug  The site's original slug.
     * @param string    $original_title The site's original title before slugified.
     * @param int       $post_id        The site's unique id.
     */
    return ActionFilterHook::getInstance()->applyFilter(
        'ttcms_unique_site_slug',
        $site_slug,
        $original_slug,
        $original_title,
        $site_id
    );
}

/**
 * Retrieves info about current site.
 *
 * @since 1.0.0
 * @param type $show
 * @param type $filter
 * @return string
 */
function get_siteinfo($show = '', $filter = 'raw')
{
    $dispatch = [
        'homeurl' => home_url(),
        'siteurl' => site_url(),
        'description' => c::getInstance()->get('option')->read('site_description'),
        'sitename' => c::getInstance()->get('option')->read('sitename'),
        'timezone' => c::getInstance()->get('option')->read('system_timezone'),
        'admin_email' => c::getInstance()->get('option')->read('admin_email'),
        'locale' => c::getInstance()->get('option')->read('ttcms_core_locale'),
        'release' => ttcms()->obj['file']->getContents('RELEASE'),
        'theme_directory_url' => get_theme_directory_uri(),
        'javascript_directory_url' => get_javascript_directory_uri(),
        'less_directory_url' => get_less_directory_uri(),
        'css_directory_url' => get_css_directory_uri(),
        'image_directory_url' => get_image_directory_uri()
    ];

    $output = $show == '' ? $dispatch['sitename'] : $dispatch[$show];

    $url = true;
    if (strpos($show, 'url') === false &&
        strpos($show, 'home') === false) {
        $url = false;
    }

    if ('display' == $filter) {
        if ($url) {
            /**
             * Filters the URL returned by get_siteinfo().
             *
             * @since 1.0.0
             * @param mixed $output The URL returned by siteinfo().
             * @param mixed $show   Type of information requested.
             */
            $output = ActionFilterHook::getInstance()->applyFilter('siteinfo_url', $output, $show);
        } else {
            /**
             * Filters the site information returned by get_siteinfo().
             *
             * @since 1.0.0
             * @param mixed $output The requested non-URL site information.
             * @param mixed $show   Type of information requested.
             */
            $output = ActionFilterHook::getInstance()->applyFilter('siteinfo', $output, $show);
        }
    }

    return $output;
}
