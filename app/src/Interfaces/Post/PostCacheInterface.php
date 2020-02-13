<?php
namespace TriTan\Interfaces\Post;

interface PostCacheInterface
{
    /**
     * Update post caches.
     *
     * @since 1.0.0
     * @param Post|null $post Post or post id to be cached.
     */
    public function update($post);

    /**
     * Clean post caches.
     *
     * @since 1.0.0
     * @param Post|int $post Post or post id to be cleaned from the cache.
     */
    public function clean($post);
}
