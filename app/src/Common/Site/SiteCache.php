<?php
namespace TriTan\Common\Site;

use TriTan\Common\Site\Site;
use TriTan\Interfaces\Cache\ObjectCacheInterface;
use TriTan\Interfaces\Site\SiteCacheInterface;
use Qubus\Hooks\Interfaces\ActionFilterHookInterface;

final class SiteCache implements SiteCacheInterface
{
    protected $cache;
    protected $hook;

    public function __construct(ObjectCacheInterface $cache, ActionFilterHookInterface $hook)
    {
        $this->cache = $cache;
        $this->hook = $hook;
    }

    /**
     * Update user caches.
     *
     * @since 1.0.0
     * @param Site $site Site object to be cached.
     * @return bool|null Returns false on failure.
     */
    public function update($site)
    {
        if (empty($site)) {
            return;
        }

        $_site = $site->toArray();

        $this->cache->create((int) $site->getId(), $_site, 'sites');
        $this->cache->create((int) $site->getId() . 'short', $_site, 'site_details');
        $this->cache->create($site->getSlug(), (int) $site->getId(), 'siteslugs');
        $this->cache->create($site->getDomain(), (int) $site->getId(), 'sitedomains');
        $this->cache->create($site->getPath(), (int) $site->getId(), 'sitepaths');
    }

    /**
     * Clean Site caches.
     *
     * Uses `clean_popst_cache` action.
     *
     * @since 1.0.0
     * @param int|Site $site Site object to be cleaned from the cache.
     */
    public function clean($site)
    {
        if (empty($site)) {
            return;
        }

        $site_id = $site;
        $site = get_site($site_id);
        if (!$site) {
            if (!is_numeric($site_id)) {
                return;
            }

            // Make sure a Site object exists even when the site has been deleted.
            $site = new Site();
            $site->setId($site_id);
            $site->setDomain('');
            $site->setPath('');
        }

        $site_id = $site->getId();
        $site_domain_path_key = md5($site->getDomain() . $site->getPath());

        $this->cache->delete((int) $site->getId(), 'sites');
        $this->cache->delete((int) $site->getId(), 'site_details');
        $this->cache->delete((int) $site->getId() . 'short', 'site_details');
        $this->cache->delete($site->getSlug(), 'siteslugs');
        $this->cache->delete($site->getDomain(), 'sitedomains');
        $this->cache->delete($site->getPath(), 'sitepaths');
        $this->cache->delete($site_domain_path_key, 'site_lookup');
        $this->cache->delete($site_domain_path_key, 'site_id_cache');
        $this->cache->delete('current_site_' . $site->getDomain(), 'site_options');
        $this->cache->delete('current_site_' . $site->getDomain() . $site->getPath(), 'site_options');

        /**
        * Fires immediately after the given site's cache is cleaned.
        *
        * @since 1.0.0
        * @param int    $site_id              Site id.
        * @param Site   $site                 Site object.
        * @param string $site_domain_path_key md5 hash of site_domain and site_path.
        */
        $this->hook->doAction('clean_site_cache', (int) $site_id, $site, $site_domain_path_key);

        $this->cache->set('last_changed', microtime(), 'sites');
    }
}
