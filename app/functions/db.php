<?php
use TriTan\Common\Container as c;
use TriTan\Common\Posttype\PosttypeRepository;
use TriTan\Common\Posttype\PosttypeMapper;
use TriTan\Common\Post\PostRepository;
use TriTan\Common\Post\PostMapper;
use TriTan\Common\Site\SiteRepository;
use TriTan\Common\Site\SiteMapper;
use TriTan\Common\Context\HelperContext;
use Qubus\Hooks\ActionFilterHook;
use Qubus\Exception\Data\TypeException;
use Cocur\Slugify\Slugify;
use Cascade\Cascade;

/**
 * Qubus CMS Database Related Functions
 *
 * For the most part, these are general purpose functions
 * that use the database to retrieve information.
 *
 * @license GPLv3
 *
 * @since 1.0.0
 * @package Qubus CMS
 * @author Joshua Parker <josh@joshuaparker.blog>
 */

/**
 * Retrieve post type by a given field from the post type table.
 *
 * @file app/functions/db.php
 *
 * @since 1.0.0
 * @param string     $field The field to retrieve the post type with.
 * @param int|string $value A value for $field (posttype_id, posttype_slug).
 * @return Posttype|PDOException|TypeException
 */
function get_posttype_by(string $field, $value)
{
    $qudb = app()->qudb;

    $sanitize_field = ttcms()->obj['sanitizer']->item($field);
    $sanitize_value = ttcms()->obj['sanitizer']->item($value);

    try {
        return (
            new PosttypeRepository(
                new PosttypeMapper(
                    $qudb
                )
            )
        )->findBySql('*', "$sanitize_field = ?", [$sanitize_value], 'row');
    } catch (\PDOException $ex) {
        Cascade::getLogger('error')->error(
            sprintf(
                'SQLSTATE[%s]: %s',
                $ex->getCode(),
                $ex->getMessage()
            ),
            [
                'Db Function' => 'get_posttype_by'
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
                'Db Function' => 'get_posttype_by'
            ]
        );
    }
}

/**
 * A function which retrieves a Qubus CMS post id.
 *
 * @file app/functions/db.php
 *
 * @since 1.0.0
 * @param string $post_slug The unique slug of a post.
 * @return int|\\PDOException|\\Qubus\\Exception\\Data\\TypeException Post id.
 */
function get_post_id($post_slug = null)
{
    $qudb = app()->qudb;

    $sanitize_value = ttcms()->obj['sanitizer']->item($post_slug);

    try {
        return (
            new PostRepository(
                new PostMapper(
                    $qudb,
                    new HelperContext()
                )
            )
        )->findBySql('post_id', 'post_slug = ?', [$sanitize_value], 'variable');
    } catch (\PDOException $ex) {
        Cascade::getLogger('error')->error(
            sprintf(
                'SQLSTATE[%s]: %s',
                $ex->getCode(),
                $ex->getMessage()
            ),
            [
                'Db Function' => 'get_post_id'
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
                'Db Function' => 'get_post_id'
            ]
        );
    }
}

/**
 * Creates unique slug based on title
 *
 * @file app/functions/db.php
 *
 * @since 1.0.0
 * @param string $title Text to be slugified.
 * @param string $table Table the text is saved to (i.e. post, posttype, site)
 * @return string Slug.
 */
function ttcms_slugify(string $title, $table = null)
{
    $qudb = app()->qudb;

    $sanitize_table = ttcms()->obj['sanitizer']->item($table);

    /**
     * Instantiate the slugify class.
     */
    $slugify = new Slugify();
    $slug = $slugify->slugify($title);
    /**
     * Slug field to filter by based on table
     * being called.
     */
    $field = $sanitize_table . '_slug';

    $titles = [];

    /**
     * Query post/posttype/site document.
     */
    if ($sanitize_table === 'site') {
        $table = $qudb->base_prefix . $sanitize_table;
    } else {
        $table = $qudb->prefix . $sanitize_table;
    }

    $slug_var = "$slug%";
    $sql = "SELECT *"
        . " FROM $table"
        . " WHERE $field LIKE ?";

    $results = $qudb->getResults(
        $qudb->prepare($sql, [$slug_var]),
        ARRAY_A
    );
    if (count($results) > 0) {
        foreach ($results as $item) {
            $titles[] = $item["$field"];
        }
    }

    $total = count($titles);
    $last = end($titles);

    /**
     * No equal results, return $slug
     */
    if ($total == 0) {
        return $slug;
    } elseif ($total == 1) { // If we have only one result, we look if it has a number at the end.
        /**
         * Take the only value of the array, because there is only 1.
         */
        $exists = $titles[0];

        /**
         * Kill the slug and see what happens
         */
        $exists = str_replace($slug, "", $exists);

        /**
         * If there is no light about, there was no number at the end.
         * We added it now
         */
        if ("" == trim($exists)) {
            return $slug . "-1";
        } else { // If not..........
            /**
             * Obtain the number because of REGEX it will be there... ;-)
             */
            $number = str_replace("-", "", $exists);

            /**
             * Number plus one.
             */
            $number++;

            return $slug . "-" . $number;
        }
    } else { // If there is more than one result, we need the last one.
        /**
         * Last value
         */
        $exists = $last;

        /**
         * Delete the actual slug and see what happens
         */
        $exists = str_replace($slug, "", $exists);

        /**
         * Obtain the number, easy.
         */
        $number = str_replace("-", "", $exists);

        /**
         * Increment number +1
         */
        $number++;

        return $slug . "-" . $number;
    }
}

/**
 * Retrieve all published posts or all published posts by post type.
 *
 * @file app/functions/db.php
 *
 * @since 1.0.0
 * @param string $post_type Post type.
 * @param int $limit        Number of posts to show.
 * @param null|int $offset  The offset of the first row to be returned.
 * @return array|\\PDOException Array of published posts or posts by particular post type.
 */
function get_all_posts($post_type = null, int $limit = 0, $offset = null, $status = 'all')
{
    $qudb = app()->qudb;

    $sanitize_posttype = ttcms()->obj['sanitizer']->item($post_type);
    $sanitize_limit = ttcms()->obj['sanitizer']->item($limit);
    $sanitize_offset = ttcms()->obj['sanitizer']->item($offset);
    $sanitize_status = ttcms()->obj['sanitizer']->item($status);

    if ($post_type != null) {
        $prepare = $qudb->prepare(
            "SELECT * FROM {$qudb->prefix}post WHERE post_type = ?",
            [
                $sanitize_posttype
            ]
        );

        if ($status !== 'all') {
            $where .= $qudb->prepare(
                " AND WHERE post_status = ?",
                [
                    $sanitize_status
                ]
            );
        }

        if ($limit > 0 && $offset != null) {
            $where .= sprintf(" LIMIT %s OFFSET %s", $sanitize_limit, $sanitize_offset);
        } elseif ($limit > 0 && $offset == null) {
            $where .= sprintf(" LIMIT %s", $sanitize_limit);
        } elseif ($limit <= 0 && $offset != null) {
            $where .= sprintf(" OFFSET ?", $sanitize_offset);
        }
        $qudb->getResults($prepare . $where, ARRAY_A);
    } else {
        if ($status !== 'all') {
            $where = $qudb->prepare(
                " WHERE post_status = ?",
                [
                    $sanitize_status
                ]
            );
        }

        if ($limit > 0 && $offset != null) {
            $where .= sprintf(" LIMIT %s OFFSET %s", $sanitize_limit, $sanitize_offset);
        } elseif ($limit > 0 && $offset == null) {
            $where .= sprintf(" LIMIT %s", $sanitize_limit);
        } elseif ($limit <= 0 && $offset != null) {
            $where .= sprintf(" OFFSET ?", $sanitize_offset);
        }

        try {
            $qudb->getResults("SELECT * FROM {$qudb->prefix}post{$where}", ARRAY_A);
        } catch (\PDOException $ex) {
            Cascade::getLogger('error')->error(
                sprintf(
                    'SQLSTATE[%s]: %s',
                    $ex->getCode(),
                    $ex->getMessage()
                ),
                [
                    'Db Function' => 'get_all_posts'
                ]
            );
        }
    }
    return false;
}

/**
 * Returns a list of internal links for TinyMCE.
 *
 * @file app/functions/db.php
 *
 * @since 1.0.0
 * @return array|\\PDOException|\\Qubus\\Exception\\Data\\TypeException
 */
function tinymce_link_list()
{
    $qudb = app()->qudb;

    try {
        return (
            new PostRepository(
                new PostMapper(
                    $qudb,
                    new HelperContext()
                )
            )
        )->findBySql('post_title, post_relative_url', 'post_status = ?', ['published']);
    } catch (\PDOException $ex) {
        Cascade::getLogger('error')->error(
            sprintf(
                'SQLSTATE[%s]: %s',
                $ex->getCode(),
                $ex->getMessage()
            ),
            [
                'Db Function' => 'tinymce_link_list'
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
                'Db Function' => 'tinymce_link_list'
            ]
        );
    }
}

/**
 * Update post's relative url if posttype slug has been updated.
 *
 * @file app/functions/db.php
 *
 * @since 1.0.0
 * @access private
 * @param string $old_slug  Old posttype slug.
 * @param string $new_slug  New posttype slug.
 */
function update_post_relative_url_posttype(string $old_slug, string $new_slug)
{
    $qudb = app()->qudb;

    $qudb->getConnection()->throwTransactionExceptions();
    try {
        $qudb->transaction(function () use ($qudb, $old_slug, $new_slug) {
            $prepare = $qudb->prepare(
                "UPDATE {$qudb->prefix}post SET post_relative_url = REPLACE(post_relative_url,'?','?')",
                [
                  $old_slug,
                  $new_slug
                ]
            );
            $qudb->getConnection()->query($prepare);
        });
    } catch (\PDOException $ex) {
        Cascade::getLogger('error')->error(
            sprintf(
                'SQLSTATE[%s]: %s',
                $ex->getCode(),
                $ex->getMessage()
            ),
            [
                'Db Function' => 'update_post_relative_url_posttype'
            ]
        );
    }
}

/**
 * Checks if a slug exists among records from the posttype table.
 *
 * @file app/functions/db.php
 *
 * @since 1.0.0
 * @param int    $posttype_id Posttype id to check against.
 * @param string $slug        Slug to search for.
 * @return bool Returns true if posttype slug exists or false otherwise.
 */
function ttcms_posttype_slug_exist(int $posttype_id, string $slug) : bool
{
    $qudb = app()->qudb;

    $exist = $qudb->getVar(
        $qudb->prepare(
            "SELECT COUNT(*) FROM {$qudb->prefix}posttype WHERE posttype_slug = ? AND posttype_id <> ?",
            [
              $slug,
              $posttype_id
            ]
        )
    );

    return $exist > 0;
}

/**
 * Checks if a slug exists among records from the post table.
 *
 * @file app/functions/db.php
 *
 * @since 1.0.0
 * @param int|null  $post_id    Post id to check against or null.
 * @param string    $slug       Slug to search for.
 * @param string    $post_type  The post type to filter.
 * @return bool Returns true if post slug exists or false otherwise.
 */
function ttcms_post_slug_exist($post_id, string $slug, string $post_type) : bool
{
    $qudb = app()->qudb;

    $exist = $qudb->getVar(
        $qudb->prepare(
            "SELECT COUNT(*) FROM {$qudb->prefix}post WHERE post_slug = ? AND post_id <> ? AND post_posttype = ?",
            [
              $slug,
              $post_id,
              $post_type
            ]
        )
    );
    return $exist > 0;
}

/**
 * Checks if a slug exists among records from the site document.
 *
 * @file app/functions/db.php
 *
 * @since 1.0.0
 * @param int|null  $site_id    Site id to check against or null.
 * @param string    $slug       Slug to search for.
 * @return bool Returns true if site slug exists or false otherwise.
 */
function ttcms_site_slug_exist($site_id, string $slug) : bool
{
    $qudb = app()->qudb;

    $exist = $qudb->getVar(
        $qudb->prepare(
            "SELECT COUNT(*) FROM {$qudb->base_prefix}site WHERE site_slug = ? AND site_id <> ?",
            [
              $slug,
              $site_id
            ]
        )
    );

    return $exist > 0;
}

/**
 * Checks if a post has any children.
 *
 * @file app/functions/db.php
 *
 * @since 1.0.0
 * @param int $post_id Post id to check.
 * @return bool|array|\\PDOException False if post has not children or array of children if true.
 */
function is_post_parent(int $post_id)
{
    $qudb = app()->qudb;

    try {
        $children = $qudb->getResults(
            $qudb->prepare(
                "SELECT * FROM {$qudb->prefix}post WHERE post_parent = ?",
                [
                  $post_id
                ]
            ),
            ARRAY_A
        );

        if (count($children) <= 0) {
            return false;
        }

        return $children;
    } catch (\PDOException $ex) {
        Cascade::getLogger('error')->error(
            sprintf(
                'SQLSTATE[%s]: %s',
                $ex->getCode(),
                $ex->getMessage()
            ),
            [
                'Db Function' => 'is_post_parent'
            ]
        );
    }
}

/**
 * Checks if a given posttype exists on posts.
 *
 * @since 1.0.0
 * @param string $post_posttype Posttype slug to check for.
 * @return bool|\\PDOException Returns true if posttype exists or false otherwise.
 */
function is_post_posttype_exist(string $post_posttype) : bool
{
    $qudb = app()->qudb;

    try {
        $exist = $qudb->getVar(
            $qudb->prepare(
                "SELECT COUNT(*) FROM {$qudb->prefix}post WHERE post_posttype = ?",
                [
                  $post_posttype
                ]
            )
        );

        return $exist > 0;
    } catch (\PDOException $ex) {
        Cascade::getLogger('error')->error(
            sprintf(
                'SQLSTATE[%s]: %s',
                $ex->getCode(),
                $ex->getMessage()
            ),
            [
                'Db Function' => 'is_post_posttype_exist'
            ]
        );
    }
}

/**
 * Reassigns posts to a different user.
 *
 * @file app/functions/db.php
 *
 * @since 1.0.0
 * @param int $user_id    ID of user being removed.
 * @param int $assign_id  ID of user to whom posts will be assigned.
 * @return bool|\\PDOException
 */
function reassign_posts(int $user_id, int $assign_id)
{
    $qudb = app()->qudb;

    try {
        $count = $qudb->getVar(
            $qudb->prepare(
                "SELECT COUNT(*) FROM {$qudb->prefix}post WHERE post_author = ?",
                [
                  $user_id
                ]
            )
        );
    } catch (\PDOException $ex) {
        Cascade::getLogger('error')->error(
            sprintf(
                'SQLSTATE[%s]: %s',
                $ex->getCode(),
                $ex->getMessage()
            ),
            [
                'Db Function' => 'reassign_posts'
            ]
        );
    }

    if ($count > 0) {
        $qudb->getConnection()->throwTransactionExceptions();
        try {
            $qudb->transaction(function ($qudb) use ($user_id, $assign_id) {
                $qudb
                    ->update($qudb->prefix . 'post')
                    ->where('post_author')->is((int) $user_id)
                    ->set([
                        'post_author' => (int) $assign_id
                    ]);
            });
            return true;
        } catch (\PDOException $ex) {
            ttcms()->obj['flash']->error(
                sprintf(
                    esc_html__(
                        'Reassign post error: %s'
                    ),
                    $ex->getMessage()
                )
            );
        }
    }
}

/**
 * Reassigns sites to a different user.
 *
 * @file app/functions/db.php
 *
 * @since 1.0.0
 * @param int   $user_id    ID of user being removed.
 * @param array $params     User parameters (assign_id and role).
 * @return bool|\\PDOException
 */
function reassign_sites(int $user_id, array $params = [])
{
    $qudb = app()->qudb;

    if (!is_numeric($user_id)) {
        return false;
    }

    if ((int) $user_id <= 0) {
        return false;
    }

    try {
        $count = $qudb->getVar(
            $qudb->prepare(
                "SELECT COUNT(*) FROM {$qudb->base_prefix}site WHERE site_owner = ?",
                [
                  $user_id
                ]
            )
        );
    } catch (\PDOException $ex) {
        Cascade::getLogger('error')->error(
            sprintf(
                'SQLSTATE[%s]: %s',
                $ex->getCode(),
                $ex->getMessage()
            ),
            [
                'Db Function' => 'reassign_sites'
            ]
        );
    }

    if ($count > 0) {
        $qudb->getConnection()->throwTransactionExceptions();
        try {
            $qudb->transaction(function ($qudb) use ($user_id, $params) {
                $qudb
                    ->update($qudb->base_prefix . 'site')
                    ->where('site_owner')->is((int) $user_id)
                    ->set([
                        'site_owner' => (int) $params['assign_id']
                    ]);
            });
            return true;
        } catch (\PDOException $ex) {
            ttcms()->obj['flash']->error(
                sprintf(
                    esc_html__(
                        'Reassign site error: %s'
                    ),
                    $ex->getMessage()
                )
            );
        }
    }
}

/**
 * Checks if the requested user is an admin of any sites or has any admin roles.
 *
 * @file app/functions/db.php
 *
 * @since 1.0.0
 * @param int $user_id ID of user to check.
 * @return bool|\\PDOException Returns true if user has sites and false otherwise.
 */
function does_user_have_sites(int $user_id = 0)
{
    $qudb = app()->qudb;

    try {
        $count = $qudb->getVar(
            $qudb->prepare(
                "SELECT COUNT(*) FROM {$qudb->base_prefix}site WHERE site_owner = ?",
                [
                    $user_id
                ]
            )
        );

        if ($count > 0) {
            return true;
        }
    } catch (\PDOException $ex) {
        Cascade::getLogger('error')->error(
            sprintf(
                'SQLSTATE[%s]: %s',
                $ex->getCode(),
                $ex->getMessage()
            ),
            [
                'Db Function' => 'does_user_have_sites'
            ]
        );
    }

    $option = get_user_option('role', $user_id);
    if ((int) $option == (int) 1 || (int) $option == (int) 2) {
        return true;
    }
    return false;
}

/**
 * Get an array of sites by user.
 *
 * @since 1.0.0
 * @param int $user_id The user's id.
 * @return array|\\PDOException|\\Qubus\\Exception\\Data\\TypeException
 */
function get_users_sites(int $user_id)
{
    $qudb = app()->qudb;

    try {
        return (
            new SiteRepository(
                new SiteMapper(
                    $qudb,
                    new HelperContext()
                )
            )
        )->findBySql('*', 'site_owner = ?', [$user_id]);
    } catch (\PDOException $ex) {
        Cascade::getLogger('error')->error(
            sprintf(
                'SQLSTATE[%s]: %s',
                $ex->getCode(),
                $ex->getMessage()
            ),
            [
                'Db Function' => 'get_users_sites'
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
                'Db Function' => 'get_users_sites'
            ]
        );
    }
}

/**
 * Populate the option cache.
 *
 * @access private
 * @since 1.0.0
 * @return bool|\\PDOException
 */
function populate_options_cache()
{
    $qudb = app()->qudb;

    try {
        $options = $qudb->getResults("SELECT * FROM {$qudb->prefix}option", ARRAY_A);
        foreach ($options as $option) {
            ttcms()->obj['cache']->create($option['option_key'], $option['option_value'], 'option');
        }
        return true;
    } catch (\PDOException $ex) {
        Cascade::getLogger('error')->error(
            sprintf(
                'SQLSTATE[%s]: %s',
                $ex->getCode(),
                $ex->getMessage()
            ),
            [
                'Db Function' => 'populate_options_cache'
            ]
        );
    }
}

/**
 * Populate the usermeta cache.
 *
 * @access private
 * @since 1.0.0
 * @return bool|\\PDOException
 */
function populate_usermeta_cache()
{
    $qudb = app()->qudb;

    try {
        $umeta = $qudb->getResults("SELECT * FROM {$qudb->base_prefix}usermeta", ARRAY_A);
        foreach ($umeta as $meta) {
            (new \TriTan\Common\MetaData(
                $qudb,
                new HelperContext()
            ))->updateMetaDataCache('user', [$meta['user_id']]);
        }
        return true;
    } catch (\PDOException $ex) {
        Cascade::getLogger('error')->error(
            sprintf(
                'SQLSTATE[%s]: %s',
                $ex->getCode(),
                $ex->getMessage()
            ),
            [
                'Db Function' => 'populate_usermeta_cache'
            ]
        );
    }
}

/**
 * Populate the postmeta cache.
 *
 * @access private
 * @since 1.0.0
 * @return bool|\\PDOException
 */
function populate_postmeta_cache()
{
    $qudb = app()->qudb;

    try {
        $pmeta = $qudb->getResults("SELECT * FROM {$qudb->prefix}postmeta", ARRAY_A);
        foreach ($pmeta as $meta) {
            (new \TriTan\Common\MetaData(
                $qudb,
                new HelperContext()
            ))->updateMetaDataCache(
                $qudb->prefix . 'post',
                [
                    $meta[$qudb->prefix . 'post_id']
                ]
            );
        }
        return true;
    } catch (\PDOException $ex) {
        Cascade::getLogger('error')->error(
            sprintf(
                'SQLSTATE[%s]: %s',
                $ex->getCode(),
                $ex->getMessage()
            ),
            [
                'Db Function' => 'populate_postmeta_cache'
            ]
        );
    }
}

/**
 * Gets a total number of posts based on posttype.
 *
 * @since 1.0.0
 * @return int|\\PDOException Returns int on success or PDOException on failure.
 */
function read_total_posts()
{
    $qudb = app()->qudb;

    /**
     * Filters the post posttype.
     *
     * @since 1.0.0
     */
    $posttype = ActionFilterHook::getInstance()->applyFilter('total_posts_posttype', 'post');
    try {
        $prepare = $qudb->prepare(
            "SELECT COUNT(*) FROM {$qudb->prefix}post WHERE post_posttype = ?",
            [
                $posttype
            ]
        );
        $count = $qudb->getVar($prepare);
        return (int) $count;
    } catch (\PDOException $ex) {
        Cascade::getLogger('error')->error(
            sprintf(
                'SQLSTATE[%s]: %s',
                $ex->getCode(),
                $ex->getMessage()
            ),
            [
                'Db Function' => 'total_posts'
            ]
        );
    }
}

c::getInstance()->set('total_posts', read_total_posts());
