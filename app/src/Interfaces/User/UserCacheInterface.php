<?php
namespace TriTan\Interfaces\User;

interface UserCacheInterface
{
    /**
     * Update user caches.
     *
     * @since 1.0.0
     * @param object|int $user User object or user id to be cached.
     */
    public function update($user);

    /**
     * Clean user caches.
     *
     * @since 1.0.0
     * @param object|int $user User object or user id to be cleaned from the cache.
     */
    public function clean($user);
}
