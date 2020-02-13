<?php
namespace TriTan\Common\Plugin;

use TriTan\Interfaces\Plugin\PluginIsActivatedInterface;
use TriTan\Interfaces\Plugin\PluginIsActivatedMapperInterface;

final class PluginIsActivated implements PluginIsActivatedInterface
{
    public $mapper;

    public function __construct(PluginIsActivatedMapperInterface $mapper)
    {
        $this->mapper = $mapper;
    }

    /**
     * Checks if a particular plugin has been activated.
     *
     * @since 1.0.0
     * @return mixed
     */
    public function isActivated($plugin) : bool
    {
        return $this->mapper->isActivated($plugin);
    }
}
