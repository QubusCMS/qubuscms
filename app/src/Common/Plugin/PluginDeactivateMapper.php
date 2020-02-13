<?php
namespace TriTan\Common\Plugin;

use TriTan\Interfaces\Plugin\PluginDeactivateMapperInterface;
use Cascade\Cascade;
use \PDOException;

/**
 * Plugin Activate Mapper
 *
 * @license GPLv3
 *
 * @since 1.0.0
 * @package TriTan CMS
 * @author Joshua Parker <josh@joshuaparker.blog>
 */

final class PluginDeactivateMapper implements PluginDeactivateMapperInterface
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
     * Deactivates a specific plugin that is called by $_GET['id'] variable.
     *
     * @since 1.0.0
     * @param string $plugin ID of the plugin to deactivate.
     */
    public function deactivate($plugin)
    {
        $this->qudb->getConnection()->throwTransactionExceptions();
        try {
            $this->qudb->transaction(function () use ($plugin) {
                $this->qudb
                    ->from($this->qudb->prefix . 'plugin')
                    ->where('plugin_location')->is($plugin)
                    ->delete();
            });
        } catch (PDOException $ex) {
            Cascade::getLogger('error')->error(
                sprintf(
                    'PLUGINDEACTIVATEMAPPER[delete]: %s',
                    $ex->getMessage()
                ),
                [
                    'PluginDeactivateMapper' => 'PluginDeactivateMapper::deactivate'
                ]
            );
        }
    }
}
