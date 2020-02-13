<?php
namespace TriTan\Interfaces\Plugins;

interface ActivatedPluginsInterface
{
    /**
     * Returns a list of all plugins that have been activated.
     *
     * @since 1.0.0
     * @return mixed
     */
    public function get();
}
