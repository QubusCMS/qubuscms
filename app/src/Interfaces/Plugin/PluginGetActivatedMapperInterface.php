<?php
namespace TriTan\Interfaces\Plugin;

interface PluginGetActivatedMapperInterface
{
    /**
     * Returns a list of all plugins that have been activated.
     *
     * @since 1.0.0
     * @return mixed
     */
    public function get();
}
