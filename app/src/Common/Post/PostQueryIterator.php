<?php
namespace TriTan\Common\Post;

use TriTan\Common\Container as c;
use TriTan\Common\Post\Post;
use TriTan\Common\Options\Options;
use TriTan\Common\Options\OptionsMapper;
use TriTan\Common\Context\HelperContext;
use TriTan\Common\Date;
use Qubus\Hooks\ActionFilterHook;
use Cascade\Cascade;
use \PDOException;

/**
 * PostQueryIterator class.
 *
 * This class is inspired by WordPress WP_Query().
 */

class PostQueryIterator implements \Iterator
{
    protected $qudb;
    /**
     * Query vars set by user.
     *
     * @since 1.0.0
     * @var array
     */
    protected $query;

    protected $request;

    /**
     * Query vars after parsing.
     *
     * @since 1.0.0
     * @var array
     */
    public $query_vars = [];

    /**
     * Holds data for a single object (post, page, etc.) that is queried.
     *
     * @since 1.0.0
     * @var object|array
     */
    public $prop;

    /**
     * The id of the queried object.
     *
     * @since 1.0.0
     * @var int
     */
    public $prop_id;

    /**
     * List of posts.
     *
     * @since 1.0.0
     * @var array
     */
    public $posts;

    /**
     * Amount of posts for current query.
     *
     * @since 1.0.0
     * @var int.
     */
    public $post_count = 0;

    /**
     * Index of the current item in the loop.
     *
     * @since 1.0.0
     * @var int
     */
    public $current_index = -1;

    /**
     * Whether the loop has started.
     *
     * @since 1.0.0
     * @var bool
     */
    public $in_the_loop = false;

    /**
     * The current post.
     *
     * @since 1.0.0
     * @var Post
     */
    public $post;

    /**
     * Number os posts found for current query.
     *
     * @since 1.0.0
     * @var int
     */
    public $found_posts = 0;

    /**
     *
     * @var type
     */
    public $original_post;

    /**
     * Is the current query for a single post.
     *
     * @since 1.0.0
     * @var bool
     */
    public $is_single = false;

    /**
     * Is the current query for a page.
     *
     * @since 1.0.0
     * @var bool
     */
    public $is_page = false;

    /**
     * Is current query for a paged result.
     *
     * @since 1.0.0
     * @var bool
     */
    public $is_paged = false;

    /**
     * Is the current query for the admin.
     *
     * @since 1.0.0
     * @var bool
     */
    public $is_admin = false;

    /**
     * Is current query for a posttype archive.
     *
     * @since 1.0.0
     * @var bool
     */
    public $is_singular;

    /**
     * Hashes ans stores query vars state so that we can determine if any var
     * has changed.
     *
     * @since 1.0.0
     * @var bool
     */
    private $query_vars_hash = false;

    /**
     * Have the vars we started with change?
     *
     * @since 1.0.0
     * @var bool
     */
    private $query_vars_changed = true;

    /**
     *
     * @var type
     */
    public $is_posttype_archive = false;

    public function __construct($query = '')
    {
        $this->qudb = app()->qudb;
        if (!empty($query)) {
            $this->query($query);
        }

        $this->original_post = c::getInstance()->get('post');
    }

    private function initQueryFlags()
    {
        $this->is_single            = false;
        $this->is_page              = false;
        $this->is_paged             = false;
        $this->is_admin             = false;
        $this->is_singular          = false;
        $this->is_posttype_archive  = false;
    }

    /**
     * Initiates object properties and sets default values.
     *
     * @since 1.0.0
     */
    public function init()
    {
        unset($this->posts);
        unset($this->query);
        $this->query_vars = [];
        unset($this->prop);
        unset($this->prop_id);
        $this->post_count   = 0;
        $this->current_index = -1;
        $this->in_the_loop  = false;
        unset($this->post);
        $this->found_posts  = 0;

        $this->initQueryFlags();
    }

    /**
     * Re-parse the query vars.
     *
     * @since 1.0.0
     */
    public function parseQueryVars()
    {
        $this->parseQuery();
    }

    /**
     * Fills in the query variables, which do not exist within the parameter.
     *
     * @since 1.0.0
     * @param array $array Defined query variables.
     * @return array Complete query variables with empty ones returning empty/null.
     */
    public function fillQueryVars($array)
    {
        $keys = [
            'p',
            'name',
            'pagename',
            'page_id',
            'paged',
            'title'
        ];

        foreach ($keys as $key) {
            if (!isset($array[$key])) {
                $array[$key] = '';
            }
        }

        $array_keys = [
            'posttype__in',
            'posttype__not_in',
            'post_id__in',
            'post_id__not_in',
            'post_status__in',
            'post_status__not_in',
            'post_parent__in',
            'post_parent__not_in',
            'author__in',
            'author__not_in'
        ];

        foreach ($array_keys as $key) {
            if (!isset($array[$key])) {
                $array[$key] = [];
            }
        }

        return $array;
    }

    public function parseQuery($query = '')
    {
        if (!empty($query)) {
            $this->init();
            $this->query = $this->query_vars = ttcms()->obj['util']->{'parseArgs'}($query);
        } elseif (!isset($this->query)) {
            $this->query = $this->query_vars;
        }

        $this->query_vars         = $this->fillQueryVars($this->query_vars);
        $qv                       = &$this->query_vars;
        $this->query_vars_changed = true;

        if (!is_scalar($qv['p']) || $qv['p'] < 0) {
            $qv['p']     = 0;
        } else {
            $qv['p'] = intval($qv['p']);
        }

        $qv['page_id']  = absint($qv['page_id']);
        $qv['paged']    = absint($qv['paged']);
        $qv['author']   = preg_replace('|[^0-9,-]|', '', $qv['author']); // comma separated list of positive or negative integers
        $qv['pagename'] = trim($qv['pagename']);
        $qv['name']     = trim($qv['name']);
        $qv['title']    = trim($qv['title']);

        if ('' != $qv['name']) {
            $this->is_single = true;
        } elseif ($qv['p']) {
            $this->is_single = true;
        } elseif ('' != $qv['pagename'] || !empty($qv['page_id'])) {
            $this->is_page   = true;
            $this->is_single = false;
        } else {
            if (empty($qv['author']) || ($qv['author'] == '0')) {
                $this->is_author = false;
            } else {
                $this->is_author = true;
            }

            if ('' != $qv['author_name']) {
                $this->is_author = true;
            }
        }

        if ('' != $qv['paged'] && (intval($qv['paged']) > 1)) {
            $this->is_paged = true;
        }

        if (is_admin()) {
            $this->is_admin = true;
        }

        $this->is_singular = $this->is_single || $this->is_page;

        $this->query_vars_hash    = md5(serialize($this->query_vars));
        $this->query_vars_changed = false;
        /**
         * Fires after the main query vars have been parsed.
         *
         * @since 1.0.0
         * @param PostQueryIterator $this The PostQueryIterator instance (passed by reference).
         */
        ActionFilterHook::getInstance()->doActionRefArray('parse_query', [&$this]);
    }

    /**
     * Retrieves an array of posts based on query variables.
     *
     * @since 1.0.0
     * @return Post[]|int[] Array of post objects or post id's.
     */
    private function getPosts()
    {
        /**
         * Fires after the query variable object is created, but before the actual query is run.
         *
         * @since 1.0.0
         * @param PostQueryIterator $this The PostQueryIterator instance (passed by reference).
         */
        ActionFilterHook::getInstance()->doActionRefArray('pre_get_posts', [&$this]);

        $q = &$this->query_vars;

        // Fill again in case pre_get_posts unset some vars.
        $q = $this->fillQueryVars($q);

        $hash = md5(serialize($this->query_vars));
        if ($hash != $this->query_vars_hash) {
            $this->query_vars_changed = true;
            $this->query_vars_hash    = $hash;
        }

        unset($hash);

        $where                      = '';
        $q['fields']                = '';
        $q['post_type']             = '';
        $q['posts_per_page']        = '';
        $q['post__in']              = '';
        $q['post__not_in']          = '';
        $q['post_parent']           = '';
        $q['post_parent__in']       = '';
        $q['post_parent__not_in']   = '';
        $q['orderby']               = '';
        $q['groupby']               = '';

        $groupby = '';
        $orderby = '';

        $post_type = $q['post_type'];
        if (! empty($post_type) && is_array($post_type)) {
        }

        if (empty($q['posts_per_page'])) {
            $q['posts_per_page'] = (
                new Options(
                    new OptionsMapper(
                        $this->qudb,
                        new HelperContext()
                    )
                )
            )->read('posts_per_page');
        }

        $q['posts_per_page'] = (int) $q['posts_per_page'];
        if ($q['posts_per_page'] < -1) {
            $q['posts_per_page'] = abs($q['posts_per_page']);
        } elseif ($q['posts_per_page'] == 0) {
            $q['posts_per_page'] = 1;
        }

        if ($q['fields'] == 'ids') {
            $fields = "{$this->qudb->prefix}post.post_id";
        } elseif ($q['fields'] == 'id=>parent') {
            $fields = "{$this->qudb->prefix}post.post_id, {$this->qudb->prefix}post.post_parent";
        } else {
            $fields = "{$this->qudb->prefix}post.*";
        }

        if ('' !== $q['title']) {
            $where .= $this->qudb->prepare(" AND {$this->qudb->prefix}post.post_title = ?", [ $q['title'] ]);
        }

        if ('' != $q['name']) {
            $where .= $this->qudb->prepare(" AND {$this->qudb->prefix}post.post_slug = ?", [ $q['name'] ]);
        }

        if ($q['p']) {
            $where .= $this->qudb->prepare(" AND {$this->qudb->prefix}post.post_id = ?" . [ $q['p'] ]);
        } elseif ($q['post__in']) {
            $post__in = implode(',', $q['post__in']);
            $where .= $this->qudb->prepare(" AND {$this->qudb->prefix}post.post_id IN (?)", [ $post__in ]);
        } elseif ($q['post__not_in']) {
            $post__not_in = implode(',', $q['post__not_in']);
            $where .= $this->qudb->prepare(" AND {$this->qudb->prefix}post.post_id NOT IN (?)", [ $post__not_in ]);
        }

        if (is_numeric($q['post_parent'])) {
            $where .= $this->qudb->prepare(" AND {$this->qudb->prefix}post.post_parent = ?", [ $q['post_parent'] ]);
        } elseif ($q['post_parent__in']) {
            $post_parent__in = implode(',', $q['post_parent__in']);
            $where .= $this->qudb->prepare(" AND {$this->qudb->prefix}post.post_parent IN (?)", [ $post_parent__in ]);
        } elseif ($q['post_parent__not_in']) {
            $post_parent__not_in = implode(',', $q['post_parent__not_in']);
            $where .= $this->qudb->prepare(" AND {$this->qudb->prefix}post.post_parent NOT IN (?)", [ $post_parent__not_in ]);
        }

        if ($q['page_id']) {
            $where .= $this->qudb->prepare(" AND {$this->qudb->prefix}post.post_id = ?" . [ $q['page_id'] ]);
        }

        if (! empty($q['author__not_in'])) {
            $author__not_in = implode(',', array_unique((array) $q['author__not_in']));
            $where .= $this->qudb->prepare(" AND {$this->qudb->prefix}post.post_author NOT IN (?)", [ $author__not_in ]);
        } elseif (! empty($q['author__in'])) {
            $author__in = implode(',', array_unique((array) $q['author__in']));
            $where .= $this->qudb->prepare(" AND {$this->qudb->prefix}post.post_author IN (?)", [ $author__in ]);
        }

        if (! empty($q['posttype__not_in'])) {
            $posttype__not_in = implode(',', $q['posttype__not_in']);
            $where .= $this->qudb->prepare(" AND {$this->qudb->prefix}post.post_posttype NOT IN (?)", [ $posttype__not_in ]);
        } elseif (! empty($q['posttype__in'])) {
            $posttype__in = implode(',', array_unique((array) $q['posttype__in']));
            $where .= $this->qudb->prepare(" AND {$this->qudb->prefix}post.post_posttype IN (?)", [ $posttype__in ]);
        }

        if (! empty($q['post_status__in'])) {
            $post_status__in = implode(',', $q['post_status__in']);
            $where .= $this->qudb->prepare(
                " AND post_status IN (?)",
                [
                    $post_status__in
                ]
            );
        }

        if (! empty($q['post_status__not_in'])) {
            $post_status__not_in = implode(',', $q['post_status__not_in']);
            $where .= $this->qudb->prepare(
                " AND post_status NOT IN (?)",
                [
                    $post_status__not_in
                ]
            );
        }

        if (! empty($q['orderby']) && is_array($q['orderby'])) {
            $orderby = sprintf(" ORDER BY %s", implode(',', $q['orderby']));
        } elseif (! empty($q['orderby'])) {
            $orderby = sprintf(" ORDER BY %s", $q['orderby']);
        }

        if (! empty($q['groupby']) && is_array($q['groupby'])) {
            $groupby = sprintf(" GROUP BY %s", implode(',', $q['groupby']));
        } elseif (! empty($q['orderby'])) {
            $groupby = sprintf(" GROUP BY %s", $q['groupby']);
        }

        $posts_per_page = $q['posts_per_page'];

        $this->request = "SELECT $fields FROM {$this->qudb->prefix}post WHERE 1=1$where$groupby$orderby LIMIT $posts_per_page OFFSET 0";

        if ('ids' == $q['fields']) {
            if (null === $this->posts) {
                try {
                    $data = $this->qudb->getCol($this->request);
                } catch (\PDOException $ex) {
                    Cascade::getLogger('error')->error(
                        sprintf(
                            'SQLSTATE[%s]: %s',
                            $ex->getCode(),
                            $ex->getMessage()
                        ),
                        [
                            'Post Query Iterator' => 'getPosts'
                        ]
                    );
                }

                $this->posts = [];
                if ($data != null) {
                    foreach ($data as $post) {
                        $this->posts[] = $this->create((array) $post);
                    }
                }
            }

            $this->posts      = array_map('intval', $this->posts);
            $this->post_count = count($this->posts);
            $this->setFoundPosts();

            return $this->posts;
        }

        if ('id=>parent' == $q['fields']) {
            if (null === $this->posts) {
                try {
                    $data = $this->qudb->getResults($this->request, ARRAY_A);
                } catch (\PDOException $ex) {
                    Cascade::getLogger('error')->error(
                        sprintf(
                            'SQLSTATE[%s]: %s',
                            $ex->getCode(),
                            $ex->getMessage()
                        ),
                        [
                            'Post Query Iterator' => 'getPosts'
                        ]
                    );
                }

                $this->posts = [];
                if ($data != null) {
                    foreach ($data as $post) {
                        $this->posts[] = $this->create($post);
                    }
                }
            }

            $this->post_count = count($this->posts);
            $this->setFoundPosts();

            $r = [];
            foreach ($this->posts as $key => $post) {
                $this->posts[ $key ]->post_id   = (int) $post->post_id;
                $this->posts[ $key ]->post_parent = (int) $post->post_parent;

                $r[ (int) $post->post_id ] = (int) $post->parent_id;
            }

            return $r;
        }

        try {
            $data = $this->qudb->getResults($this->request, ARRAY_A);
        } catch (\PDOException $ex) {
            Cascade::getLogger('error')->error(
                sprintf(
                    'SQLSTATE[%s]: %s',
                    $ex->getCode(),
                    $ex->getMessage()
                ),
                [
                    'Post Query Iterator' => 'getPosts'
                ]
            );
        }

        $this->posts = [];
        if ($data != null) {
            foreach ($data as $post) {
                $this->posts[] = $this->create($post);
            }
        }

        if ($this->posts) {
            $this->post_count = count($this->posts);
            $this->posts = array_map('get_post', $this->posts);
            $this->post = reset($this->posts);
        } else {
            $this->post_count = 0;
            $this->posts      = [];
        }

        return $this->posts;
    }

    /**
     * Set the amount of found posts for the current query.
     *
     * @since 1.0.0
     * @return type
     */
    private function setFoundPosts()
    {
        if (is_array($this->posts) && ! $this->posts) {
            return;
        }

        if (is_array($this->posts)) {
            $this->found_posts = count($this->posts);
        } else {
            if (null === $this->posts) {
                $this->found_posts = 0;
            } else {
                $this->found_posts = 1;
            }
        }

        /**
         * Filters the number of found posts for the query.
         *
         * @since 1.0.0
         * @param int               $found_posts The number of posts found.
         * @param PostQueryIterator $this        The PostQueryIterator instance (passed by reference).
         */
        $this->found_posts = ActionFilterHook::getInstance()->applyFilterRefArray('found_posts', [$this->found_posts, &$this]);
    }

    /**
     * Index of the current post.
     *
     * @since 1.0.0
     * @return int
     */
    public function key()
    {
        return $this->current_index;
    }

    /**
     *
     * @return type
     */
    public function current()
    {
        if (!isset($this->posts[$this->key()])) {
            return null;
        }

        $current_post = $this->posts[$this->current_index];
        c::getInstance()->set('current_post', $current_post);
        $this->setupPostData($current_post);
        return $current_post;
    }

    /**
     * Set up the next post and iterate current post index.
     *
     * @since 1.0.0
     * @return Post The next post.
     */
    public function next()
    {
        $this->current_index++;

        $this->post = $this->posts[ $this->key() ];
        return $this->post;
    }

    /**
     * Sets the current post.
     *
     * Retrieves the next post, sets up the post and sets the 'in the loop'
     * property to true.
     *
     * @since 1.0.0
     * @global Post TriTan\Container::getInstance()->get('post')
     */
    public function thePost()
    {
        $this->in_the_loop = true;

        if ($this->current_index == -1) { // loop has just started
            /**
             * Fires once the loop has started.
             *
             * @since 1.0.0
             * @param PostQueryIterator $this The PostQueryIterator instance (passed by reference).
             */
            ActionFilterHook::getInstance()->doActionRefArray('loop_start', [&$this]);
        }

        $post = $this->next();
        c::getInstance()->set('post', $post);
        $this->setupPostData($post);
    }

    /**
     * Determines whether there are more posts available in the loop.
     *
     * Calls the 'loop_end' action when the loop is complete.
     *
     * @since 1.0.0
     * @return bool True if posts are available or false if end of the loop.
     */
    public function hasPosts()
    {
        if ($this->current_index + 1 < $this->post_count) {
            return true;
        } elseif ($this->current_index + 1 == $this->post_count && $this->post_count > 0) {
            /**
             * Fires once the loop has ended.
             *
             * @since 1.0.0
             * @param PostQueryIterator $this The PostQueryIterator instance (passed by reference).
             */
            ActionFilterHook::getInstance()->doActionRefArray('loop_end', [&$this]);
            /**
             * Does some cleanup after the loop.
             */
            $this->rewind();
        } elseif (0 === $this->post_count) {
            /**
             * Fires if no results are found in a post query.
             *
             * @since 1.0.0
             * @param PostQueryIterator $this The PostQueryIterator instance.
             */
            ActionFilterHook::getInstance()->doAction('loop_no_results', $this);
        }
        return false;
    }

    /**
     * Rewind the posts and reset post index.
     *
     * @since 1.0.0
     */
    public function rewind()
    {
        $this->current_index = -1;
        if ($this->post_count > 0) {
            $this->post = $this->posts[0];
        }
    }

    /**
     * Sets up the Post query by parsing query string.
     *
     * @since 1.0.0
     * @param array $query Array of query arguments.
     * @return Post[]|int[] Array of post objects or post id's.
     */
    public function query($query)
    {
        $this->init();
        $this->query = $this->query_vars = ttcms()->obj['util']->{'parseArgs'}($query);
        return $this->getPosts();
    }

    /**
     * Retrieve queried object.
     *
     * @since 1.0.0
     * @return object
     */
    public function getProp()
    {
        if (isset($this->prop)) {
            return $this->prop;
        }

        $this->prop    = null;
        $this->prop_id = null;

        if ($this->is_singular && !empty($this->post)) {
            $this->prop = c::getInstance()->prop('post');
            $this->prop_id = (int) c::getInstance()->prop('post', 'post_id');
        }

        return $this->prop;
    }

    /**
     * Retrieve id of the current queried object.
     *
     * @since 1.0.0
     * @return int
     */
    public function getPropId()
    {
        $this->getProp();

        if (isset($this->prop_id)) {
            return (int) $this->prop_id;
        }

        return 0;
    }

    /**
     * Is the query for a single page?
     *
     * @since 1.0.0
     * @param int|string|array $page Optional. Page id, title, slug or array. Default empty.
     * @return bool True if single page, false otherwise.
     */
    public function isPage($page = ''): bool
    {
        if (!$this->is_page) {
            return false;
        }

        if (empty($page)) {
            return true;
        }

        $page_obj = $this->getProp();

        $page = array_map('strval', (array) $page);

        if (in_array((string) $page_obj->post_id, $page)) {
            return true;
        } elseif (in_array($page_obj->post_title, $page)) {
            return true;
        } elseif (in_array($page_obj->post_slug, $page)) {
            return true;
        }

        return false;
    }

    public function isPaged(): bool
    {
        return (bool) $this->is_paged;
    }

    /**
     * Signifies whether the current query is for a single post.
     *
     * @since 1.0.0
     * @param int|string|array $post Optional. Post id, title, slug or array. Default empty.
     * @return bool True if single post, false otherwise.
     */
    public function isSingle($post = ''): bool
    {
        if (!$this->is_single) {
            return false;
        }

        if (empty($post)) {
            return true;
        }

        $post_obj = $this->getProp();

        $post = array_map('strval', (array) $post);

        if (in_array((string) $post_obj->post_id, $post)) {
            return true;
        } elseif (in_array($post_obj->post_title, $post)) {
            return true;
        } elseif (in_array($post_obj->post_slug, $post)) {
            return true;
        }

        return false;
    }

    /**
     * Is the query for an existing single post of any post type (post or page)?
     *
     * @since 1.0.0
     * @param string|array $post_types Optional. Post type or array of post types. Default empty.
     * @return bool Whether the query is for an existing single post of any of the given post types.
     */
    public function isSingular($post_types = ''): bool
    {
        if (empty($post_types) || !$this->is_singular) {
            return (bool) $this->is_singular;
        }

        $post_obj = $this->getProp();

        return in_array($post_obj->posttype, (array) $post_types);
    }

    /**
     * Used to check if query is the main query.
     *
     * @since 1.0.0
     * @global PostQueryIterator TriTan\Container::getInstance()->get('query')
     *                           Global PostQueryIterator instance.
     * @return bool
     */
    public function isMainQuery(): bool
    {
        return c::getInstance()->get('query') === $this;
    }

    /**
     * Sets up global post data.
     *
     * @since 1.0.0
     * @global int             TriTan\Container::getInstance()->get('post_id')
     * @global User            TriTan\Container::getInstance()->get('authordata')
     * @global string|int|bool TriTan\Container::getInstance()->get('currentdate')
     * @global string|int|bool TriTan\Container::getInstance()->get('authordata')
     * @param Post|object|int|array $post Post instance or object, array or post id.
     * @return bool True when complete.
     */
    public function setupPostData($post)
    {
        if (!($post instanceof Post)) {
            $post = get_post($post);
        }

        if (!$post) {
            return;
        }

        // register post_id
        $id = (int) $post->getId();
        c::getInstance()->set('post_id', $id);
        c::getInstance()->set('authordata', get_userdata((int) $post->getAuthor()));
        c::getInstance()->set('currentdate', (new Date())->db2Date('F j, Y', $post->getPublished(), false));
        c::getInstance()->set('currentmonth', (new Date())->db2Date('m', $post->getPublished(), false));

        /**
         * Fires once the post data has been setup.
         *
         * @since 1.0.0
         * @param Post              $post The Post object (passed by reference).
         * @param PostQueryIterator $this The current PostQueryIterator object (passed by reference).
         */
        ActionFilterHook::getInstance()->doActionRefArray('the_post', [&$post, &$this]);

        return true;
    }

    /**
     * After looping through the query, this function restores the
     * global TriTan\Container::getInstance()->get('post') to the current post in
     * the query.
     *
     * @since 1.0.0
     * @global Post TriTan\Container::getInstance()->get('post')
     */
    public function resetPostData()
    {
        if (!empty($this->post)) {
            c::getInstance()->set('post', $this->post);
            $this->setupPostData($this->post);
        }
    }

    /**
     * Create a new Post object.
     *
     * @return TriTan\Common\Post\Post
     */
    protected function __create(): Post
    {
        return new Post();
    }

    /**
     * Create a new instance of Post. Optionally populating it
     * from a data array.
     *
     * @param array $data
     * @return TriTan\Common\Post\Post.
     */
    public function create(array $data = null): Post
    {
        $post = $this->__create();
        if ($data) {
            $post = $this->populate($post, $data);
        }
        return $post;
    }

    /**
     * Populate the Post object with the data array.
     *
     * @param Post $post object.
     * @param array $data Post data.
     * @return TriTan\Common\Post\Post
     */
    public function populate(Post $post, array $data): Post
    {
        $post->setId((int) ttcms()->obj['escape']->html($data['post_id']));
        $post->setTitle((string) ttcms()->obj['escape']->html($data['post_title']));
        $post->setSlug((string) ttcms()->obj['escape']->html($data['post_slug']));
        $post->setContent((string) ttcms()->obj['html']->purify($data['post_content']));
        $post->setAuthor((int) ttcms()->obj['escape']->html($data['post_author']));
        $post->setPosttype((string) ttcms()->obj['escape']->html($data['post_posttype']));
        $post->setParent((int) ttcms()->obj['escape']->html($data['post_parent']));
        $post->setSidebar((int) ttcms()->obj['escape']->html($data['post_sidebar']));
        $post->setShowInMenu((int) ttcms()->obj['escape']->html($data['post_show_in_menu']));
        $post->setShowInSearch((int) ttcms()->obj['escape']->html($data['post_show_in_search']));
        $post->setRelativeUrl((string) ttcms()->obj['escape']->html($data['post_relative_url']));
        $post->setFeaturedImage((string) ttcms()->obj['escape']->html($data['post_featured_image']));
        $post->setStatus((string) ttcms()->obj['escape']->html($data['post_status']));
        $post->setCreated((string) ttcms()->obj['escape']->html($data['post_created']));
        $post->setPublished((string) ttcms()->obj['escape']->html($data['post_published']));
        $post->setModified((string) ttcms()->obj['escape']->html($data['post_modified']));
        return $post;
    }

    /**
     * Number of posts in the current query.
     *
     * @since 1.0.0
     * @return int Number of posts in the query.
     */
    public function count(): int
    {
        return (int) $this->post_count;
    }

    /**
     *
     * @return type
     */
    public function valid()
    {
        $valid = isset($this->posts[$this->current_index]);
        if (!$valid) {
            $this->resetPostData();

            $this->setupPostData($this->original_post);
            c::getInstance()->set('post', $this->original_post);
        }
        return $valid;
    }
}
