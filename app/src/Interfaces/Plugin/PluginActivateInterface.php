<?php
namespace TriTan\Interfaces\Plugin;

/**
 * Plugin Activate Interface
 *
 * @license GPLv3
 *
 * @since 1.0.0
 * @package TriTan CMS
 * @author Joshua Parker <josh@joshuaparker.blog>
 */
interface PluginActivateInterface
{

    /**
     * Activates a specific plugin that is called by $_GET['id'] variable.
     *
     * @since 1.0.0
     * @param string $plugin ID of the plugin to activate
     * @return mixed
     */
    public function activate($plugin);
}
