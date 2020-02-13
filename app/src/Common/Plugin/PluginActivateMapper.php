<?php
namespace TriTan\Common\Plugin;

use TriTan\Interfaces\Plugin\PluginActivateMapperInterface;
use Cascade\Cascade;
use \PDOException;

/**
 * Plugin Activate Mapper
 *
 * @license GPLv3
 *
 * @since 1.0.0
 * @package Qubus CMS
 * @author Joshua Parker <josh@joshuaparker.blog>
 */

final class PluginActivateMapper implements PluginActivateMapperInterface
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
     * Activates a specific plugin that is called by $_GET['id'] variable.
     *
     * @since 1.0.0
     * @param string $plugin ID of the plugin to activate
     * @return mixed
     */
    public function activate($plugin)
    {
        $this->qudb->getConnection()->throwTransactionExceptions();
        try {
            $this->qudb->transaction(function () use ($plugin) {
                $this->qudb
                    ->insert([
                        'plugin_location' => $plugin
                    ])
                    ->into($this->qudb->prefix . 'plugin');
            });
        } catch (PDOException $ex) {
            Cascade::getLogger('error')->error(
                sprintf(
                    'PLUGINACTIVATEMAPPER[insert]: %s',
                    $ex->getMessage()
                ),
                [
                    'PluginActivateMapper' => 'PluginActivatedMapper::activate'
                ]
            );
        }
    }
}
