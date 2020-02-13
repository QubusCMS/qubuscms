<?php
namespace TriTan\Common\Plugin;

use TriTan\Interfaces\Plugin\PluginGetActivatedMapperInterface;

final class PluginGetActivatedMapper implements PluginGetActivatedMapperInterface
{
    protected $qudb;

    public function __construct()
    {
        $this->qudb = app()->qudb;
    }

    /**
     * Returns a list of all plugins that have been activated.
     *
     * @since 1.0.0
     * @return mixed
     */
    public function get()
    {
        $results = $this->qudb->from($this->qudb->prefix . 'plugin')
          ->select()
          ->fetchAssoc()
          ->all();
        return $results;
    }
}
