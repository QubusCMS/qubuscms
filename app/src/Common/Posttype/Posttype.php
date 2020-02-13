<?php
namespace TriTan\Common\Posttype;

use TriTan\Interfaces\Posttype\PosttypeInterface;

/**
 * Posttype Domain
 *
 * @license GPLv3
 *
 * @since 1.0.0
 * @package TriTan CMS
 * @author Joshua Parker <josh@joshuaparker.blog>
 */
final class Posttype implements PosttypeInterface
{
    /**
     * Posttype post_id.
     *
     * @since 1.0.0
     * @var int
     */
    private $posttype_id;

    /**
     * Posttype Title.
     *
     * @since 1.0.0
     * @var string
     */
    private $posttype_title;

    /**
     * Posttype slug.
     *
     * @since 1.0.0
     * @var string
     */
    private $posttype_slug;

    /**
     * Posttype description.
     *
     * @since 1.0.0
     * @var string
     */
    private $posttype_description;

    public function getId(): int
    {
        return $this->posttype_id;
    }

    public function setId($id)
    {
        return $this->posttype_id = $id;
    }

    public function getTitle()
    {
        return $this->posttype_title;
    }

    public function setTitle($title)
    {
        return $this->posttype_title = $title;
    }

    public function getSlug()
    {
        return $this->posttype_slug;
    }

    public function setSlug($slug)
    {
        return $this->posttype_slug = $slug;
    }

    public function getDescription()
    {
        return $this->posttype_description;
    }

    public function setDescription($description)
    {
        return $this->posttype_description = $description;
    }

    /**
     * Determine whether the posttype exists.
     *
     * @since 1.0.0
     * @return bool True if posttype exists, false if not.
     */
    public function exists()
    {
        return !empty($this->posttype_id);
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
