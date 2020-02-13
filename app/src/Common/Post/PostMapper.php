<?php
namespace TriTan\Common\Post;

use TriTan\Interfaces\Post\PostMapperInterface;
use TriTan\Interfaces\Database\DatabaseInterface;
use TriTan\Interfaces\ContextInterface;
use TriTan\Common\Post\Post;
use Qubus\Exception\Data\TypeException;
use Cascade\Cascade;
use \PDOException;

final class PostMapper implements PostMapperInterface
{
    protected $qudb;

    protected $context;

    public function __construct(DatabaseInterface $qudb, ContextInterface $context)
    {
        $this->qudb = $qudb;
        $this->context = $context;
    }

    /**
     * Fetch a post object by ID
     *
     * @since 1.0.0
     * @param string $id
     * @return TriTan\Common\Post\Post|null Returns post object if exist and NULL otherwise.
     */
    public function findById(int $id)
    {
        if (!is_integer($id) || (int) $id < 1) {
            throw new TypeException(
                'The ID of this entity is invalid.',
                'invalid_id'
            );
        }

        try {
            $post = $this->findBy('id', $id);
        } catch (PDOException $ex) {
            Cascade::getLogger('error')->error(
                sprintf(
                    'POSTMAPPER[find]: %s',
                    $ex->getMessage()
                ),
                [
                    'PostMapper' => 'PostMapper::findById'
                ]
            );
        }

        return $post;
    }

    /**
     * Return only the main post fields.
     *
     * @since 1.0.0
     * @param string $field The field to query against: 'id', 'ID', 'email' or 'login'.
     * @param string|int $value The field value
     * @return object|false Raw post object
     */
    public function findBy(string $field, $value)
    {
        // 'ID' is an alias of 'id'.
        if ('ID' === $field) {
            $field = 'id';
        }

        if ('id' == $field) {
            // Make sure the value is numeric to avoid casting objects, for example,
            // to int 1.
            if (!is_numeric($value)) {
                return false;
            }
            $value = intval($value);
            if ($value < 1) {
                return false;
            }
        } else {
            $value = $this->trim($value);
        }

        if (!$value) {
            return false;
        }

        switch ($field) {
            case 'id':
                $post_id = (int) $value;
                $db_field = 'post_id';
                break;
            case 'slug':
                $post_id = $this->context->obj['cache']->read($value, 'postslugs');
                $db_field = 'post_slug';
                break;
            case 'type':
                $value = $this->context->obj['sanitizer']->item($value, '', '');
                $post_id = $this->context->obj['cache']->read($value, 'post_posttypes');
                $db_field = 'post_type';
                break;
            default:
                return false;
        }

        $post = null;

        if (false !== $post_id) {
            if ($data = $this->context->obj['cache']->read($post_id, 'posts')) {
                is_array($data) ? $this->toObject($data) : $data;
            }
        }

        if (!$data = $this->qudb->getRow(
            $this->qudb->prepare(
                "SELECT * FROM {$this->qudb->prefix}post WHERE $db_field = ?",
                [
                    $value
                ]
            ),
            ARRAY_A
        )) {
            return false;
        }

        if ($data != null) {
            $post = $this->create($data);
            $this->context->obj['postcache']->update($post);
        }

        if (is_array($post)) {
            $post = $this->toObject($post);
        }

        return $post;
    }

    /**
     * Fetch requested fields by where clause.
     *
     * @since 1.0.0
     * @param string $fields Fields to retrieve from table.
     * @param array/string $where Where clause (i.e. post_slug = ?).
     * @param array $params Parameters that need binding.
     * @param string $method The format of the ending results.
     * @return Post object.
     */
    public function findBySql($fields = '*', $where = '', $params = [], $method = 'results')
    {
        if ($where == '') {
            throw new TypeException('Where clause is missing.', 'invalid_where');
        }

        if (count(array_filter($params)) <= 0) {
            throw new TypeException('Parameters are missing.', 'invalid_params');
        }

        if (mb_strpos($fields, 'COUNT') !== false || mb_strpos($fields, 'count') !== false) {
            throw new TypeException(
                'SQL function COUNT or PHP function count() cannot be used.',
                'invalid_sql_function'
            );
        }

        $prepare = $this->qudb->prepare("SELECT $fields FROM {$this->qudb->prefix}post WHERE $where", $params);

        try {
            if ($method === 'results') {
                $data = $this->qudb->getResults($prepare, ARRAY_A);
            }

            if ($method === 'column') {
                $data = $this->qudb->getCol($prepare);
            }

            if ($method === 'row') {
                $data = $this->qudb->getRow($prepare, ARRAY_A);
            }

            if ($method === 'variable') {
                $result = $this->qudb->getVar($prepare);
                $data = [$fields => $result];
            }

            $posttypes = [];

            if ($data != null && ($method === 'row' || $method === 'variable')) {
                $posttypes[] = $this->create($data);
                return $posttypes[0];
            } else {
                foreach ($data as $posttype) {
                    $posttypes[] = $this->create($posttype);
                }

                return $posttypes;
            }
        } catch (PDOException $ex) {
            Cascade::getLogger('error')->error(
                sprintf(
                    'POSTMAPPER[find]: %s',
                    $ex->getMessage()
                ),
                [
                    'PostMapper' => 'PostMapper::findBySql'
                ]
            );
        }
    }

    /**
     * Fetch all posts by particular type.
     *
     * @since 1.0.0
     * @param string $type
     * @return object Post data object.
     */
    public function findByType(string $type)
    {
        try {
            $data = $this->qudb->getResults(
                $this->qudb->prepare(
                    "SELECT * FROM {$this->qudb->prefix}post WHERE post_posttype = ?",
                    [
                        $type
                    ]
                ),
                ARRAY_A
            );
            $posts = [];
            if ($data != null) {
                foreach ($data as $post) {
                    $posts[] = $this->create($post);
                }
            }
            return $posts;
        } catch (PDOException $ex) {
            Cascade::getLogger('error')->error(
                sprintf(
                    'POSTMAPPER[find]: %s',
                    $ex->getMessage()
                ),
                [
                    'PostMapper' => 'PostMapper::findByType'
                ]
            );
        }
    }

    /**
     * Fetch all posts.
     *
     * @since 1.0.0
     * @return object Post data object.
     */
    public function findAll()
    {
        $data = $this->qudb->getResults("SELECT * FROM {$this->qudb->prefix}post", ARRAY_A);
        $posts = [];
        if ($data != null) {
            foreach ($data as $post) {
                $posts[] = $this->create($post);
            }
        }
        return $posts;
    }

    /**
     * Create a new instance of Post. Optionally populating it
     * from a data array.
     *
     * @param array $data
     * @return TriTan\Common\Post\Post.
     */
    public function create(array $data = null) : Post
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
    public function populate(Post $post, array $data) : Post
    {
        $post->setId((int) $this->context->obj['escape']->html($data['post_id']));
        $post->setTitle((string) $this->context->obj['escape']->html($data['post_title']));
        $post->setSlug((string) $this->context->obj['escape']->html($data['post_slug']));
        $post->setContent((string) $this->context->obj['html']->purify($data['post_content']));
        $post->setAuthor((int) $this->context->obj['escape']->html($data['post_author']));
        $post->setPosttype((string) $this->context->obj['escape']->html($data['post_posttype']));
        $post->setParent((int) $this->context->obj['escape']->html($data['post_parent']));
        $post->setSidebar((int) $this->context->obj['escape']->html($data['post_sidebar']));
        $post->setShowInMenu((int) $this->context->obj['escape']->html($data['post_show_in_menu']));
        $post->setShowInSearch((int) $this->context->obj['escape']->html($data['post_show_in_search']));
        $post->setRelativeUrl((string) $this->context->obj['escape']->html($data['post_relative_url']));
        $post->setFeaturedImage((string) $this->context->obj['escape']->html($data['post_featured_image']));
        $post->setStatus((string) $this->context->obj['escape']->html($data['post_status']));
        $post->setCreated((string) $this->context->obj['escape']->html($data['post_created']));
        $post->setCreatedGmt((string) $this->context->obj['escape']->html($data['post_created_gmt']));
        $post->setPublished((string) $this->context->obj['escape']->html($data['post_published']));
        $post->setPublishedGmt((string) $this->context->obj['escape']->html($data['post_published_gmt']));
        $post->setModified((string) $this->context->obj['escape']->html($data['post_modified']));
        $post->setModifiedGmt((string) $this->context->obj['escape']->html($data['post_modified_gmt']));
        return $post;
    }

    /**
     * Create a new Post object.
     *
     * @return TriTan\Common\Post\Post
     */
    protected function __create() : Post
    {
        return new Post();
    }

    /**
     * Inserts a new post into the post document.
     *
     * @since 1.0.0
     * @param Post $post Post object.
     * @return int Last insert id.
     */
    public function insert(Post $post)
    {
        $post_parent = (int) $post->getParent() <= 0 ? null : (int) $post->getParent();

        $this->qudb->getConnection()->throwTransactionExceptions();
        try {
            $result = $this->qudb->transaction(function () use ($post, $post_parent) {
                $this->qudb
                    ->insert([
                        'post_title' => (string) $post->getTitle(),
                        'post_slug' => (string) $post->getSlug(),
                        'post_content' => (string) $post->getContent(),
                        'post_author' => (int) $post->getAuthor(),
                        'post_posttype' => (string) $post->getPosttype(),
                        'post_parent' => $post_parent,
                        'post_sidebar' => (int) $post->getSidebar(),
                        'post_show_in_menu' => (int) $post->getShowInMenu(),
                        'post_show_in_search' => (int) $post->getShowInSearch(),
                        'post_relative_url' => (string) $post->getRelativeUrl(),
                        'post_featured_image' => (string) $post->getFeaturedImage(),
                        'post_status' => (string) $post->getStatus(),
                        'post_created' => (string) $post->getCreated(),
                        'post_created_gmt' => (string) $post->getCreatedGmt(),
                        'post_published' => (string) $post->getPublished(),
                        'post_published_gmt' => (string) $post->getPublishedGmt(),
                        'post_modified' => '' == $post->getModified() ? null : (string) $post->getModified(),
                        'post_modified_gmt' => '' == $post->getModifiedGmt() ? null : (string) $post->getModifiedGmt()
                    ])
                    ->into($this->qudb->prefix . 'post');

                return (int) $this->qudb->getConnection()->getPDO()->lastInsertId();
            });

            return (int) $result;
        } catch (PDOException $ex) {
            Cascade::getLogger('error')->error(
                sprintf(
                    'POSTMAPPER[insert]: %s',
                    $ex->getMessage()
                ),
                [
                    'PostMapper' => 'PostMapper::insert'
                ]
            );
        }
    }

    /**
     * Updates a Post object.
     *
     * @since 1.0.0
     * @param Post $post Post object.
     */
    public function update(Post $post)
    {
        $post_parent = (int) $post->getParent() <= 0 ? null : (int) $post->getParent();

        $this->qudb->getConnection()->throwTransactionExceptions();
        try {
            $this->qudb->transaction(function () use ($post, $post_parent) {
                $this->qudb
                    ->update($this->qudb->prefix . 'post')
                    ->where('post_id')->is((int) $post->getId())
                    ->set([
                        'post_title' => (string) $post->getTitle(),
                        'post_slug' => (string) $post->getSlug(),
                        'post_content' => (string) $post->getContent(),
                        'post_author' => (int) $post->getAuthor(),
                        'post_posttype' => (string) $post->getPosttype(),
                        'post_parent' => $post_parent,
                        'post_sidebar' => (int) $post->getSidebar(),
                        'post_show_in_menu' => (int) $post->getShowInMenu(),
                        'post_show_in_search' => (int) $post->getShowInSearch(),
                        'post_relative_url' => (string) $post->getRelativeUrl(),
                        'post_featured_image' => (string) $post->getFeaturedImage(),
                        'post_status' => (string) $post->getStatus(),
                        'post_created' => (string) $post->getCreated(),
                        'post_created_gmt' => (string) $post->getCreatedGmt(),
                        'post_published' => (string) $post->getPublished(),
                        'post_published_gmt' => (string) $post->getPublishedGmt(),
                        'post_modified' => '' == $post->getModified() ? null : (string) $post->getModified(),
                        'post_modified_gmt' => '' == $post->getModifiedGmt() ? null : (string) $post->getModifiedGmt()
                    ]);
            });

            return (int) $post->getId();
        } catch (PDOException $ex) {
            Cascade::getLogger('error')->error(
                sprintf(
                    'POSTMAPPER[update]: %s',
                    $ex->getMessage()
                ),
                [
                    'PostMapper' => 'PostMapper::update'
                ]
            );
        }
    }

    /**
     * Save the Post object.
     *
     * @since 1.0.0
     * @param Post $post Post object.
     */
    public function save(Post $post)
    {
        if (is_null($post->getId())) {
            $this->insert($post);
        } else {
            $this->update($post);
        }
    }

    /**
     * Deletes post object.
     *
     * @since 1.0.0
     * @param Post $post Post object.
     * @return bool True if deleted, false otherwise.
     */
    public function delete(Post $post)
    {
        $this->qudb->getConnection()->throwTransactionExceptions();
        try {
            $this->qudb->transaction(function () use ($post) {
                $this->qudb
                    ->from($this->qudb->prefix . 'post')
                    ->where('post_id')->is($post->getId())
                    ->delete();
            });
        } catch (PDOException $ex) {
            Cascade::getLogger('error')->error(
                sprintf(
                    'POSTMAPPER[delete]: %s',
                    $ex->getMessage()
                ),
                [
                    'PostMapper' => 'PostMapper::delete'
                ]
            );
        }
    }
}
