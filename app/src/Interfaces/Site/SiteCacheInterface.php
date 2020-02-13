<?php
namespace TriTan\Interfaces\Site;

interface SiteCacheInterface
{
    /**
     * Update site caches.
     *
     * @since 1.0.0
     * @param Site|null $site Site or site id to be cached.
     */
    public function update($site);

    /**
     * Clean site caches.
     *
     * @since 1.0.0
     * @param object|int $site Site or site id to be cleaned from the cache.
     */
    public function clean($site);
}
