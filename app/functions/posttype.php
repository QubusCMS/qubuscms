<?php
use TriTan\Common\Posttype\PosttypeRepository;
use TriTan\Common\Posttype\PosttypeMapper;
use TriTan\Common\Posttype\PosttypeCache;
use TriTan\Common\Posttype\Posttype;
use TriTan\Common\Context\HelperContext;
use Qubus\Hooks\ActionFilterHook;
use Qubus\Exception\Exception;
use Qubus\Exception\Data\TypeException;

/**
 * Qubus CMS Post Type Functions
 *
 * @license GPLv3
 *
 * @since 1.0.0
 * @package Qubus CMS
 * @author Joshua Parker <josh@joshuaparker.blog>
 */

/**
 * Retrieves post type data given a post type ID or post type array.
 *
 * @file app/functions/posttype.php
 *
 * @since 1.0.0
 * @param int|Posttype $posttype Posttype ID or postttype object.
 * @param bool         $output   If set to OBJECT, data will return as an object, ARRAY_A
 *                               as an associative array or ARRAY_N as a numeric array.
 *                               Default: 'OBJECT'.
 * @return array|Posttype
 */
function get_posttype($posttype, $output = OBJECT)
{
    $qudb = app()->qudb;

    if ($posttype instanceof Posttype) {
        $_posttype = $posttype;
    } elseif (is_object($posttype)) {
        if (empty($posttype->getId())) {
            return null;
        } else {
            try {
                $_posttype = (new PosttypeRepository(
                    new PosttypeMapper(
                        $qudb,
                        new HelperContext()
                    )
                ))->findById((int) $posttype->getId());
            } catch (\PDOException $ex) {
                Cascade::getLogger('error')->error(
                    sprintf(
                        'SQLSTATE[%s]: %s',
                        $ex->getCode(),
                        $ex->getMessage()
                    ),
                    [
                        'Post Type Function' => 'get_posttype'
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
                        'Post Type Function' => 'get_posttype'
                    ]
                );
            }
        }
    } else {
        try {
            $_posttype = (new PosttypeRepository(
                new PosttypeMapper(
                    $qudb,
                    new HelperContext()
                )
            ))->findById((int) $posttype);
        } catch (\PDOException $ex) {
            Cascade::getLogger('error')->error(
                sprintf(
                    'SQLSTATE[%s]: %s',
                    $ex->getCode(),
                    $ex->getMessage()
                ),
                [
                  'Post Type Function' => 'get_posttype'
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
                  'Post Type Function' => 'get_posttype'
              ]
            );
        }
    }

    if (!$_posttype) {
        return null;
    }

    if ($output === ARRAY_A || $output === false) {
        $_posttype = $_posttype->toArray();
    } elseif ($output === ARRAY_N) {
        $_posttype = array_values($_posttype->toArray());
    }

    /**
     * Fires after a post type is retrieved.
     *
     * @since 1.0.0
     * @param Posttype $_posttype Posttype data.
     */
    $_posttype = ActionFilterHook::getInstance()->applyFilter('get_posttype', $_posttype);

    return $_posttype;
}

/**
 * A function which retrieves a Qubus CMS post type title.
 *
 * Purpose of this function is for the `posttype_title`
 * filter.
 *
 * @file app/functions/posttype.php
 *
 * @since 1.0.0
 * @param int $posttype_id The unique id of a posttype.
 * @return string|false Posttype title or false on failure.
 */
function get_posttype_title($posttype_id = 0)
{
    $posttype = get_posttype($posttype_id);

    if (!$posttype) {
        return false;
    }

    $title = esc_html($posttype->getTitle());
    /**
     * Filters the posttype title.
     *
     * @since 1.0.0
     *
     * @param string    $title The posttype's title.
     * @param string    $posttype_id The posttype id.
     */
    return ActionFilterHook::getInstance()->applyFilter('posttype_title', $title, $posttype_id);
}

/**
 * A function which retrieves a Qubus CMS posttype slug.
 *
 * Purpose of this function is for the `posttype_slug`
 * filter.
 *
 * @file app/functions/posttype.php
 *
 * @since 1.0.0
 * @param int $posttype_id The unique id of a posttype.
 * @return string|false Posttype slug or false on failure.
 */
function get_posttype_slug($posttype_id = 0)
{
    $posttype = get_posttype($posttype_id);

    if (!$posttype) {
        return false;
    }

    $slug = esc_html($posttype->getSlug());
    /**
     * Filters the posttype's slug.
     *
     * @since 1.0.0
     *
     * @param string    $slug The posttype's slug.
     * @param int       $posttype_id The posttype id.
     */
    return ActionFilterHook::getInstance()->applyFilter('posttype_slug', $slug, $posttype_id);
}

/**
 * A function which retrieves a Qubus CMS posttype description.
 *
 * Purpose of this function is for the `posttype_description`
 * filter.
 *
 * @file app/functions/posttype.php
 *
 * @since 1.0.0
 * @param int $posttype_id The unique id of a posttype.
 * @return string|false Posttype description or false on failure.
 */
function get_posttype_description($posttype_id = 0)
{
    $posttype = get_posttype($posttype_id);

    if (!$posttype) {
        return false;
    }

    $description = esc_html($posttype->getDescription());
    /**
     * Filters the posttype's description.
     *
     * @since 1.0.0
     *
     * @param string    $description The posttype's description.
     * @param int       $posttype_id The posttype id.
     */
    return ActionFilterHook::getInstance()->applyFilter('posttype_description', $description, $posttype_id);
}

/**
 * A function which retrieves a Qubus CMS posttype's permalink.
 *
 * Purpose of this function is for the `posttype_permalink`
 * filter.
 *
 * @file app/functions/posttype.php
 *
 * @since 1.0.0
 * @param int $posttype_id Posttype id.
 * @return string
 */
function get_posttype_permalink($posttype_id = 0)
{
    $link = esc_url(site_url(get_posttype_slug($posttype_id) . '/'));
    /**
     * Filters the posttype's link.
     *
     * @since 1.0.0
     *
     * @param string    $link The posttype's permalink.
     * @param int       $posttype_id The posttype id.
     */
    return ActionFilterHook::getInstance()->applyFilter('posttype_permalink', $link, $posttype_id);
}

/**
 * Creates a unique posttype slug.
 *
 * @since 1.0.0
 * @param string $original_slug     Original slug of posttype.
 * @param string $original_title    Original title of posttype.
 * @param int $posttype_id          Unique posttype id.
 * @return string Unique posttype slug.
 */
function ttcms_unique_posttype_slug($original_slug, $original_title, $posttype_id)
{
    if ($posttype_id <= 0) {
        $posttype_slug = ttcms_slugify($original_title, 'posttype');
    } elseif (ttcms_posttype_slug_exist($posttype_id, $original_slug)) {
        $posttype_slug = ttcms_slugify($original_title, 'posttype');
    } else {
        $posttype_slug = $original_slug;
    }
    /**
     * Filters the unique posttype slug before returned.
     *
     * @since 1.0.0
     * @param string    $posttype_slug      Unique posttype slug.
     * @param string    $original_slug      The posttype's original slug.
     * @param string    $original_title     The posttype's original title before slugified.
     * @param int       $posttype_id        The posttype's unique id.
     */
    return ActionFilterHook::getInstance()->applyFilter(
        'ttcms_unique_posttype_slug',
        $posttype_slug,
        $original_slug,
        $original_title,
        $posttype_id
    );
}

/**
 * Insert or update a posttype.
 *
 * All of the `$posttypedata` array fields have filters associated with the values. The filters
 * have the prefix 'pre_' followed by the field name. An example using 'posttype_title' would have
 * the filter called, 'pre_posttype_title' that can be hooked into.
 *
 * @file app/functions/posttype.php
 *
 * @since 1.0.0
 * @param array $posttypedata An array of data that is used for insert or update.
 *
 *      @type string $posttype_title        The posttype's title.
 *      @type string $posttype_slug         The posttype's slug.
 *      @type string $posttype_description  The posttype's description.
 *
 * @return int|null The newly created posttype's posttype_id, Exception or returns null
 *                  if the posttype could not be created or updated.
 * @throws Exception
 */
function ttcms_insert_posttype($posttypedata, $exception = false)
{
    $qudb = app()->qudb;

    // Are we updating or creating?
    if (!empty($posttypedata['posttype_id'])) {
        $update = true;
        $posttype_id = (int) $posttypedata['posttype_id'];
        $posttype_before = get_posttype((int) $posttype_id);

        if (is_null($posttype_before)) {
            if ($exception) {
                throw new Exception(t__('Invalid posttype id.', 'tritan-cms'), 'invalid_posttype_id');
            }
            return null;
        }

        $previous_slug = get_posttype_slug((int) $posttype_id);
        /**
         * Fires immediately before a posttype is inserted into the posttype document.
         *
         * @since 1.0.0
         * @param string    $previous_slug  Slug of the post before it was created.
         *                                  or updated.
         * @param int       $posttype_id    The posttype's posttype_id.
         * @param bool      $update         Whether this is an existing posttype or a new posttype.
         */
        ActionFilterHook::getInstance()->doAction('posttype_previous_slug', $previous_slug, (int) $posttype_id, $update);

        /**
         * Create new posttype object.
         */
        $posttype = new Posttype();
        $posttype->setId($posttype_id);
    } else {
        $update = false;

        $previous_slug = null;
        /**
         * Fires immediately before a posttype is inserted into the posttype document.
         *
         * @since 1.0.0
         * @param string    $previous_slug  Slug of the posttype before it is created.
         *                                  or updated.
         * @param int       $posttype_id    The posttype's posttype_id.
         * @param bool      $update         Whether this is an existing posttype or a new posttype.
         */
        ActionFilterHook::getInstance()->doAction('posttype_previous_slug', $previous_slug, (int) $posttype_id, $update);

        /**
         * Create new posttype object.
         */
        $posttype = new Posttype();
    }

    $raw_posttype_title = $posttypedata['posttype_title'];
    $sanitized_posttype_title = ttcms()->obj['sanitizer']->item($raw_posttype_title);
    /**
     * Filters a posttypes's title before the posttype is created or updated.
     *
     * @since 1.0.0
     * @param string $sanitized_posttype_title Posttype title after it has been sanitized.
     * @param string $raw_posttype_title The posttype's title.
     */
    $posttype_title = ActionFilterHook::getInstance()->applyFilter(
        'pre_posttype_title',
        (string) $sanitized_posttype_title,
        (string) $raw_posttype_title
    );
    $posttype->setTitle($posttype_title);

    if (isset($posttypedata['posttype_slug'])) {
        /**
         * ttcms_unique_posttype_slug will take the original slug supplied and check
         * to make sure that it is unique. If not unique, it will make it unique
         * by adding a number at the end.
         */
        $posttype_slug = ttcms_unique_posttype_slug($posttypedata['posttype_slug'], $posttype_title, $posttype_id);
    } else {
        /**
         * For an update, don't modify the post_slug if it
         * wasn't supplied as an argument.
         */
        $posttype_slug = $posttype_before->getSlug();
    }

    $raw_posttype_slug = $posttype_slug;
    $sanitized_posttype_slug = ttcms()->obj['sanitizer']->item($raw_posttype_slug);
    /**
     * Filters a posttypes's slug before the posttype is created or updated.
     *
     * @since 1.0.0
     * @param string $sanitized_posttype_slug Posttype slug after it has been sanitized.
     * @param string $raw_posttype_slug The posttype's slug.
     */
    $posttype_slug = ActionFilterHook::getInstance()->applyFilter(
        'pre_posttype_slug',
        (string) $sanitized_posttype_slug,
        (string) $raw_posttype_slug
    );
    $posttype->setSlug($posttype_slug);

    $raw_posttype_description = $posttypedata['posttype_description'];
    $sanitized_posttype_description = ttcms()->obj['sanitizer']->item($raw_posttype_description);
    /**
     * Filters a posttypes's description before the posttype is created or updated.
     *
     * @since 1.0.0
     * @param string $sanitized_posttype_description Posttype description after it has been sanitized.
     * @param string $raw_posttype_description The posttype's description.
     */
    $posttype_description = ActionFilterHook::getInstance()->applyFilter(
        'pre_posttype_description',
        (string) $sanitized_posttype_description,
        (string) $raw_posttype_description
    );
    $posttype->setDescription($posttype_description);

    /*
     * Filters whether the posttype is null.
     *
     * @since 1.0.0
     * @param bool  $maybe_empty Whether the posttype should be considered "null".
     * @param array $_postdata   Array of post data.
     */
    $maybe_null = !$posttype_title && !$posttype_slug;
    if (ActionFilterHook::getInstance()->applyFilter('ttcms_insert_posttype_empty_content', $maybe_null, $posttypedata)) {
        if ($exception) {
            throw new Exception(t__('The title and slug are null', 'tritan-cms'), 'empty_content');
        }
        return null;
    }

    $compacted = compact('posttype_title', 'posttype_slug', 'posttype_description');
    $data = ttcms()->obj['util']->unslash($compacted);

    /**
     * Filters posttype data before the record is created or updated.
     *
     * @since 1.0.0
     * @param array    $data
     *     Values and keys for the posttype.
     *
     *      @type string $posttype_title  The posttype's title.
     *      @type string $posttype_slug   The posttype's slug.
     *      @type string $posttype_author The posttype's description.
     *
     * @param bool     $update      Whether the posttype is being updated rather than created.
     * @param int|null $posttype_id ID of the posttype to be updated or created.
     */
    $data = ActionFilterHook::getInstance()->applyFilter('ttcms_before_insert_posttype_data', $data, $update, $posttype_id);

    if ($update) {
        try {
            $posttype_id = (
            new PosttypeRepository(
                new PosttypeMapper(
                    $qudb,
                    new HelperContext()
                )
            ))->update($posttype);
        } catch (\PDOException $ex) {
            Cascade::getLogger('error')->error(
                sprintf(
                    'SQLSTATE[%s]: %s',
                    $ex->getCode(),
                    $ex->getMessage()
                ),
                [
                  'Post Type Function' => 'ttcms_insert_posttype'
                ]
            );
        }
    } else {
        /**
         * Fires immediately before a posttype is inserted into the posttype document.
         *
         * @since 1.0.0
         * @param array $data Array of posttype data.
         */
        ActionFilterHook::getInstance()->doAction('pre_posttype_insert', (int) $data);

        try {
            $posttype_id = (
              new PosttypeRepository(
                  new PosttypeMapper(
                      $qudb,
                      new HelperContext()
                  )
              ))->insert($posttype);
        } catch (\PDOException $ex) {
            Cascade::getLogger('error')->error(
                sprintf(
                    'SQLSTATE[%s]: %s',
                    $ex->getCode(),
                    $ex->getMessage()
                ),
                [
                    'Post Type Function' => 'ttcms_insert_posttype'
                ]
            );
        }
    }

    $posttype = get_posttype((int) $posttype_id);

    (new PosttypeCache(
        ttcms()->obj['cache'],
        ActionFilterHook::getInstance()
    ))->clean($posttype);

    if ($update) {
        /**
         * Action hook triggered after existing posttype has been updated.
         *
         * @since 1.0.0
         * @param int   $posttype_id    Posttype id.
         * @param array $posttype       Posttype object.
         */
        ActionFilterHook::getInstance()->doAction('update_posttype', (int) $posttype_id, $posttype);
        $posttype_after = get_posttype((int) $posttype_id);

        /**
         * If posttype slug has changed, update all posts that may be affected
         * by this change.
         *
         * @since 1.0.0
         */
        if (is_post_posttype_exist($posttype_id) && ((string) esc_html($posttype_before->getSlug()) != (string) esc_html($posttype_after->getSlug()))) {
            update_post_relative_url_posttype(
                $posttype_id,
                esc_html($posttype_before->getSlug()),
                (string) esc_html($posttype_after->getSlug())
            );
        }

        (new PosttypeCache(
            ttcms()->obj['cache'],
            ActionFilterHook::getInstance()
        ))->clean($posttype);

        /**
         * Action hook triggered after existing post has been updated.
         *
         * @since 1.0.0
         * @param int       $posttype_id      Posttype id.
         * @param object    $posttype_after   Posttype object following the update.
         * @param object    $posttype_before  Posttype object before the update.
         */
        ActionFilterHook::getInstance()->doAction('posttype_updated', (int) $posttype_id, $posttype_after, $posttype_before);
    }

    /**
     * Action hook triggered after posttype has been saved.
     *
     * @since 1.0.0
     * @param int   $posttype_id    The posttype's id.
     * @param array $posttype       Posttype object.
     * @param bool  $update         Whether this is an existing posttype or a new posttype.
     */
    ActionFilterHook::getInstance()->doAction('ttcms_after_insert_posttype_data', (int) $posttype_id, $posttype, $update);

    return (int) $posttype_id;
}

/**
 * Update a posttype in the post document.
 *
 * See {@see ttcms_insert_posttype()} For what fields can be set in $posttypedata.
 *
 * @file app/functions/posttype.php
 *
 * @since 1.0.0
 * @param array|object $posttypedata An array of posttype data or a posttype object.
 * @return int|null The updated posttype's id, Exception or return null if posttype could not be updated.
 * @throws Exception
 */
function ttcms_update_posttype($posttypedata = [], $exception = false)
{
    if (is_object($posttypedata)) {
        $posttypedata = get_object_vars($posttypedata);
    }

    // First, get all of the original fields.
    $posttype = get_posttype((int) $posttypedata['posttype_id'], 'ARRAY_A');

    if (is_null($posttype)) {
        if ($exception) {
            throw new Exception(t__('Invalid posttype id.'), 'invalid_posttype_id');
        }
        return null;
    }

    // Merge old and new fields with new fields overwriting old ones.
    $_posttypedata = array_merge($posttype, $posttypedata);

    return ttcms_insert_posttype($_posttypedata);
}

/**
 * Deletes a posttype from the posttype document.
 *
 * @since 1.0.0
 * @param int $posttype_id The id of the posttype to delete.
 * @return string|false Posttype title or false on failure.
 */
function ttcms_delete_posttype($posttype_id = 0)
{
    $qudb = app()->qudb;

    $posttype = get_posttype($posttype_id);

    if (!$posttype) {
        return false;
    }

    /**
     * Action hook fires before a posttype is deleted.
     *
     * @since 1.0.0
     * @param int $posttype_id Posttype id.
     */
    ActionFilterHook::getInstance()->doAction('before_delete_posttype', (int) $posttype_id);

    /**
     * Action hook fires immediately before a posttype is deleted from the
     * posttype document.
     *
     * @since 1.0.0
     * @param int $posttype_id Posttype ID.
     */
    ActionFilterHook::getInstance()->doAction('delete_posttype', (int) $posttype_id);

    try {
        (new PosttypeRepository(
            new PosttypeMapper(
                $qudb,
                new HelperContext()
            )
        ))->delete($posttype);
    } catch (\PDOException $ex) {
        Cascade::getLogger('error')->error(
            sprintf(
                'SQLSTATE[%s]: %s',
                $ex->getCode(),
                $ex->getMessage()
            ),
            [
                'Post Type Function' => 'ttcms_delete_posttype'
            ]
        );
    }

    /**
     * Action hook fires immediately after a posttype is deleted from the posttype document.
     *
     * @since 1.0.0
     * @param int $posttype_id Posttype id.
     */
    ActionFilterHook::getInstance()->doAction('deleted_posttype', (int) $posttype_id);

    (new PosttypeCache(
        ttcms()->obj['cache'],
        ActionFilterHook::getInstance()
    ))->clean($posttype);

    /**
     * Action hook fires after a posttype is deleted.
     *
     * @since 1.0.0
     * @param int $posttype_id Posttype id.
     */
    ActionFilterHook::getInstance()->doAction('after_delete_posttype', (int) $posttype_id);

    return $posttype;
}

/**
 * Function used to dynamically generate post screens
 * based on post type.
 *
 * @file app/functions/posttype.php
 *
 * @since 1.0.0
 * @return array
 */
function get_all_posttypes()
{
    $qudb = app()->qudb;

    try {
        $posttypes = (
          new PosttypeRepository(
              new PosttypeMapper(
                  $qudb,
                  new HelperContext()
              )
          ))->findAll();
    } catch (\PDOException $ex) {
        Cascade::getLogger('error')->error(
            sprintf(
                'SQLSTATE[%s]: %s',
                $ex->getCode(),
                $ex->getMessage()
            ),
            [
                'Post Type Function' => 'get_posttype_by'
            ]
        );
    }

    return $posttypes;
}

/**
 * Returns the array subtype for a given array ID of a specific type.
 *
 * @since 1.0.0
 * @param string $array_type Type of array to request metadata for. (e.g. post, user, site).
 * @param int    $array_id   ID of the array to retrieve its subtype.
 * @return string The array subtype or an empty string if unspecified subtype.
 */
function get_array_subtype($array_type, $array_id)
{
    $array_id = (int) $array_id;
    $array_subtype = '';

    if ($array_type == 'post') {
        $post_type = get_posttype($array_id);
        if (!$post_type) {
            return;
        }
        $array_subtype = $post_type->getSlug();
    } elseif ($array_type == 'user') {
        $user = get_user_by('id', $array_id);
        if (!$user) {
            return;
        }
        $array_subtype = 'user';
    } elseif ($array_type == 'site') {
        $site = get_site($array_id);
        if (!$site) {
            return;
        }
        $array_subtype = 'site';
    }

    return ActionFilterHook::getInstance()->applyFilter("get_array_subtype_{$array_type}", $array_subtype, $array_id);
}
