<?php
namespace TriTan\Common\Post;

use TriTan\Common\Container as c;
use TriTan\Interfaces\Post\PostInterface;

/**
 * Post Domain
 *
 * @license GPLv3
 *
 * @since 1.0.0
 * @package Qubus CMS
 * @author Joshua Parker <josh@joshuaparker.blog>
 */
final class Post implements PostInterface
{
    /**
     * Post post_id.
     *
     * @since 1.0.0
     * @var int
     */
    private $post_id;

    /**
     * Post Title.
     *
     * @since 1.0.0
     * @var string
     */
    private $post_title;

    /**
     * Post slug.
     *
     * @since 1.0.0
     * @var string
     */
    private $post_slug;

    /**
     * Post content.
     *
     * @since 1.0.0
     * @var string
     */
    private $post_content;

    /**
     * Post author.
     *
     * @since 1.0.0
     * @var int
     */
    private $post_author;

    /**
     * Post type slug.
     *
     * @since 1.0.0
     * @var string
     */
    private $post_posttype;

    /**
     * Post parent id.
     *
     * @since 1.0.0
     * @var int
     */
    private $post_parent;

    /**
     * Post sidebar.
     *
     * @since 1.0.0
     * @var int
     */
    private $post_sidebar;

    /**
     * Post show in menu.
     *
     * @since 1.0.0
     * @var int
     */
    private $post_show_in_menu;

    /**
     * Post show in search.
     *
     * @since 1.0.0
     * @var int
     */
    private $post_show_in_search;


    /**
     * Post relative url.
     *
     * @since 1.0.0
     * @var string
     */
    private $post_relative_url;

    /**
     * Post featured image.
     *
     * @since 1.0.0
     * @var string
     */
    private $post_featured_image;

    /**
     * Post status.
     *
     * @since 1.0.0
     * @var int
     */
    private $post_status;

    /**
     * Post created datetime.
     *
     * @since 1.0.0
     * @var string
     */
    private $post_created;

    /**
     * Post created datetime in GMT.
     *
     * @since 1.0.0
     * @var string
     */
    private $post_created_gmt;

    /**
     * Post published datetime.
     *
     * @since 1.0.0
     * @var string
     */
    private $post_published;

    /**
     * Post published datetime in GMT.
     *
     * @since 1.0.0
     * @var string
     */
    private $post_published_gmt;

    /**
     * Post modified datetime.
     *
     * @since 1.0.0
     * @var string
     */
    private $post_modified;

    /**
     * Post modified datetime in GMT.
     *
     * @since 1.0.0
     * @var string
     */
    private $post_modified_gmt;

    public function getId(): int
    {
        return $this->post_id;
    }

    public function setId(int $id)
    {
        return $this->post_id = $id;
    }

    public function getTitle()
    {
        return $this->post_title;
    }

    public function setTitle(string $title)
    {
        return $this->post_title = $title;
    }

    public function getSlug()
    {
        return $this->post_slug;
    }

    public function setSlug(string $slug)
    {
        return $this->post_slug = $slug;
    }

    public function getContent()
    {
        return $this->post_content;
    }

    public function setContent(string $content)
    {
        return $this->post_content = $content;
    }

    public function getAuthor(): int
    {
        return $this->post_author;
    }

    public function setAuthor(int $author)
    {
        return $this->post_author = $author;
    }

    public function getPosttype()
    {
        return $this->post_posttype;
    }

    public function setPosttype(string $posttype)
    {
        return $this->post_posttype = $posttype;
    }

    public function getParent(): int
    {
        return $this->post_parent;
    }

    public function setParent(int $parent)
    {
        return $this->post_parent = $parent;
    }

    public function getSidebar(): int
    {
        return $this->post_sidebar;
    }

    public function setSidebar(int $sidebar)
    {
        return $this->post_sidebar = $sidebar;
    }

    public function getShowInMenu(): int
    {
        return $this->post_show_in_menu;
    }

    public function setShowInMenu(int $menu)
    {
        return $this->post_show_in_menu = $menu;
    }

    public function getShowInSearch(): int
    {
        return $this->post_show_in_search;
    }

    public function setShowInSearch(int $search)
    {
        return $this->post_show_in_search = $search;
    }

    public function getRelativeUrl()
    {
        return $this->post_relative_url;
    }

    public function setRelativeUrl(string $url)
    {
        return $this->post_relative_url = $url;
    }

    public function getFeaturedImage()
    {
        return $this->post_featured_image;
    }

    public function setFeaturedImage(string $image)
    {
        return $this->post_featured_image = $image;
    }

    public function getStatus()
    {
        return $this->post_status;
    }

    public function setStatus(string $status)
    {
        return $this->post_status = $status;
    }

    public function getCreated()
    {
        return $this->post_created;
    }

    public function setCreated(string $created)
    {
        return $this->post_created = $created;
    }

    public function getCreatedGmt()
    {
        return $this->post_created_gmt;
    }

    public function setCreatedGmt(string $created)
    {
        return $this->post_created_gmt = $created;
    }

    public function getPublished()
    {
        return $this->post_published;
    }

    public function setPublished(string $published)
    {
        return $this->post_published = $published;
    }

    public function getPublishedGmt()
    {
        return $this->post_published_gmt;
    }

    public function setPublishedGmt(string $published)
    {
        return $this->post_published_gmt = $published;
    }

    public function getModified()
    {
        return $this->post_modified;
    }

    public function setModified(string $modified)
    {
        return $this->post_modified = $modified;
    }

    public function getModifiedGmt()
    {
        return $this->post_modified_gmt;
    }

    public function setModifiedGmt(string $modified)
    {
        return $this->post_modified_gmt = $modified;
    }

    /**
     * Magic method for checking the existence of a certain custom field.
     *
     * @since 1.0.0
     * @param string $key Post meta key to check if set.
     * @return bool Whether the given post meta key is set.
     */
    public function __isset($key)
    {
        if (isset($this->{$key})) {
            return true;
        }

        return c::getInstance()->get('meta')->exists('post', $this->getId(), c::getInstance()->get('tbl_prefix') . $key);
    }

    /**
     * Magic method for accessing custom fields.
     *
     * @since 1.0.0
     * @param string $key Post meta key to retrieve.
     * @return mixed Value of the given post meta key (if set). If `$key` is 'id', the post ID.
     */
    public function __get($key)
    {
        if (isset($this->{$key})) {
            $value = $this->{$key};
        } else {
            $value = c::getInstance()->get('postmeta')->read($this->getId(), c::getInstance()->get('tbl_prefix') . $key, true);
        }

        return $value;
    }

    /**
     * Magic method for setting custom post fields.
     *
     * This method does not update custom fields in the database. It only stores
     * the value on the Post instance.
     *
     * @since 1.0.0
     * @param string $key   Post meta key.
     * @param mixed  $value Post meta value.
     */
    public function __set($key, $value)
    {
        $this->{$key} = $value;
    }

    /**
     * Magic method for unsetting a certain custom field.
     *
     * @since 1.0.0
     * @param string $key Post meta key to unset.
     */
    public function __unset($key)
    {
        if (isset($this->{$key})) {
            unset($this->{$key});
        }
    }

    /**
     * Determine whether the post exists in the database.
     *
     * @since 1.0.0
     * @return bool True if post exists in the database, false if not.
     */
    public function exists()
    {
        return !empty($this->post_id);
    }

    /**
     * Retrieve the value of a property or meta key.
     *
     * Retrieves from the post and postmeta table.
     *
     * @since 1.0.0
     * @param string $key Property
     * @return mixed
     */
    public function get($key)
    {
        return $this->__get($key);
    }

    /**
     * Determine whether a property or meta key is set
     *
     * Consults the post and postmeta tables.
     *
     * @since 1.0.0
     * @param string $key Property
     * @return bool
     */
    public function hasProp($key)
    {
        return $this->__isset($key);
    }

    /**
     * Return an array representation.
     *
     * @since 1.0.0
     * @return array Array representation.
     */
    public function toArray()
    {
        return get_object_vars($this);
    }
}
