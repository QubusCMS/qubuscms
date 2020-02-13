<?php
namespace TriTan\Interfaces\Plugin;

/**
 * Plugin Textdomain Interface
 *
 * @license GPLv3
 *
 * @since 1.0.0
 * @package TriTan CMS
 * @author Joshua Parker <josh@joshuaparker.blog>
 */
interface Plugini18nInterface
{

    /**
     * Load the plugin text domain for translation.
     *
     * @since    1.0.0
     */
    public function loadPluginTextdomain();
}
