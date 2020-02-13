<?php
namespace TriTan\Interfaces\Plugin;

interface PluginIsActivatedMapperInterface
{
    /**
     * Checks if a particular plugin has been activated.
     *
     * @since 1.0.0
     * @return mixed
     */
    public function isActivated($plugin) : bool;
}
