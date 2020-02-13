<?php
namespace TriTan\Common\Plugin;

use TriTan\Interfaces\Plugin\PluginIsActivatedMapperInterface;

/**
 * Plugin Activate Mapper
 *
 * @license GPLv3
 *
 * @since 1.0.0
 * @package Qubus CMS
 * @author Joshua Parker <josh@joshuaparker.blog>
 */

final class PluginIsActivatedMapper implements PluginIsActivatedMapperInterface
{
    /**
     * Database object.
     *
     * @var object
     */
    protected $qudb;

    /**
     * __construct class constructor
     *
     * @since 1.0.0
     * @param object $db Database interface.
     */
    public function __construct()
    {
        $this->qudb = app()->qudb;
    }

    /**
     * Checks if a particular plugin has been activated.
     *
     * @since 1.0.0
     * @return mixed
     */
    public function isActivated($plugin) : bool
    {
        $count = $this->qudb->getVar(
            $this->qudb->prepare(
                "SELECT COUNT(*) FROM {$this->qudb->prefix}plugin WHERE plugin_location = ?",
                [
                    $plugin
                ]
            )
        );

        return $count > 0 ? true : false;
    }
}
