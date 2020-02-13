<?php
namespace TriTan\Common\Post;

use TriTan\Common\Container as c;
use TriTan\Interfaces\Post\PostMetaDataInterface;
use TriTan\Interfaces\MetaDataInterface;
use TriTan\Interfaces\UtilsInterface;

final class PostMetaData implements PostMetaDataInterface
{
    protected $meta;

    protected $util;

    public function __construct(MetaDataInterface $meta, UtilsInterface $util)
    {
        $this->meta = $meta;
        $this->util = $util;
    }

    /**
     * Add meta data field to a post.
     *
     * @since 1.0.0
     * @param int    $post_id    Post ID.
     * @param string $meta_key   Metadata name.
     * @param mixed  $meta_value Metadata value. Must be serializable if non-scalar.
     * @param bool   $unique     Optional. Whether the same key should not be added.
     *                           Default false.
     * @return int|false Meta ID on success, false on failure.
     */
    public function create($post_id, $meta_key, $meta_value, $unique = false)
    {
        return $this->meta->create('post', $post_id, $meta_key, $meta_value, $unique);
    }

    /**
     * Retrieve post meta field for a post.
     *
     * @since 1.0.0
     * @param int    $post_id Post ID.
     * @param string $key     Optional. The meta key to retrieve. By default, returns
     *                        data for all keys. Default empty.
     * @param bool   $single  Optional. Whether to return a single value. Default false.
     * @return mixed Will be an array if $single is false. Will be value of meta data
     *               field if $single is true.
     */
    public function read($post_id, $key = '', $single = false)
    {
        return $this->meta->read('post', $post_id, $key, $single);
    }

    /**
     * Update post meta field based on post ID.
     *
     * Use the $prev_value parameter to differentiate between meta fields with the
     * same key and post ID.
     *
     * If the meta field for the post does not exist, it will be added.
     *
     * @since 1.0.0
     * @param int    $post_id    Post ID.
     * @param string $meta_key   Metadata key.
     * @param mixed  $meta_value Metadata value. Must be serializable if non-scalar.
     * @param mixed  $prev_value Optional. Previous value to check before removing.
     *                           Default empty.
     * @return int|bool Meta ID if the key didn't exist, true on successful update,
     *                  false on failure.
     */
    public function update($post_id, $meta_key, $meta_value, $prev_value = '')
    {
        return $this->meta->update('post', $post_id, $meta_key, $meta_value, $prev_value);
    }

    /**
     * Remove metadata matching criteria from a post.
     *
     * You can match based on the key, or key and value. Removing based on key and
     * value, will keep from removing duplicate metadata with the same key. It also
     * allows removing all metadata matching key, if needed.
     *
     * @since 1.0.0
     * @param int    $post_id    Post ID.
     * @param string $meta_key   Metadata name.
     * @param mixed  $meta_value Optional. Metadata value. Must be serializable if
     *                           non-scalar. Default empty.
     * @return bool True on success, false on failure.
     */
    public function delete($post_id, $meta_key, $meta_value = '')
    {
        return $this->meta->delete('post', $post_id, $meta_key, $meta_value);
    }

    /**
     * Get post meta data by meta ID.
     *
     * @since 1.0.0
     * @param int $mid Meta id.
     * @return array|bool
     */
    public function readByMid($mid)
    {
        return $this->meta->readByMid('post', $mid);
    }

    /**
     * Update post meta data by meta ID.
     *
     * @since 1.0.0
     * @param int $mid
     * @param string $meta_key Meta key.
     * @param string $meta_value Meta value.
     * @return bool
     */
    public function updateByMid($mid, $meta_key, $meta_value)
    {
        $_meta_key = $this->util->unslash($meta_key);
        $_meta_value = $this->util->unslash($meta_value);
        return $this->meta->updateByMid('post', $mid, $_meta_key, $_meta_value);
    }

    /**
     * Delete post meta data by meta ID.
     *
     * @since 1.0.0
     * @param int $mid Meta id.
     * @return bool
     */
    public function deleteByMid($mid)
    {
        return $this->meta->deleteByMid('post', $mid);
    }
}
