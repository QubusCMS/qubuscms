<?php
namespace TriTan\Interfaces\Plugin;

/**
 * Plugin Deactivate Interface
 *
 * @license GPLv3
 *
 * @since 1.0.0
 * @package TriTan CMS
 * @author Joshua Parker <josh@joshuaparker.blog>
 */
interface PluginDeactivateInterface
{

    /**
     * Fired during plugin deactivation.
     *
     * @since 1.0.0
     */
    public function deactivate($plugin);
}
