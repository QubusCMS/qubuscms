<?php
namespace TriTan\Interfaces\Plugin;

/**
 * Plugin Slug Interface
 *
 * @license GPLv3
 *
 * @since 1.0.0
 * @package TriTan CMS
 * @author Joshua Parker <josh@joshuaparker.blog>
 */
interface PluginSlugInterface
{

    /**
     * Returns the slug-version of the string.
     *
     * @since 1.0.0
     * @param @param string $string String to slugify.
     * @return string Slugified version of the string
     */
    public function slugify($string);
}
