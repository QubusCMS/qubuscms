<?php
namespace TriTan\Common\Plugin;

use TriTan\Interfaces\Plugin\PluginDeactivateInterface;
use TriTan\Interfaces\Plugin\PluginDeactivateMapperInterface;

final class PluginDeactivate implements PluginDeactivateInterface
{
    /**
     * Plugin deactivate Mapper object.
     *
     * @var object
     */
    protected $mapper;

    /**
     * __construct class constructor
     *
     * @since 1.0.0
     * @param object $mapper Deactivate plugin interface.
     */
    public function __construct(PluginDeactivateMapperInterface $mapper)
    {
        $this->mapper = $mapper;
    }

    /**
     * Deactivates a specific plugin that is called by $_GET['id'] variable.
     *
     * @since 1.0.0
     * @param string $plugin ID of the plugin to deactivate.
     */
    public function deactivate($plugin)
    {
        return $this->mapper->deactivate($plugin);
    }
}
