<?php
namespace TriTan\Common\Plugin;

use TriTan\Interfaces\Plugin\PluginSlugInterface;

/**
 * Plugin Activate
 *
 * @license GPLv3
 *
 * @since 1.0.0
 * @package TriTan CMS
 * @author Joshua Parker <josh@joshuaparker.blog>
 */
final class PluginSlug implements PluginSlugInterface
{

    /**
     * Returns the slug-version of the string.
     *
     * Example Usage:
     *
     *      PluginSlug::slugify('cookie-content.plugin.php');
     *
     * @file app/src/Common/Plugin/PluginSlug.php
     *
     * @since 1.0.0
     * @param string $string String to slugify.
     * @return string Slugified version of the string
     */
    public function slugify($string)
    {
        $plugin_slug = mb_substr($string, 0, -11);
        return (new \Cocur\Slugify\Slugify(['separator' => '_']))->slugify($plugin_slug);
    }
}
