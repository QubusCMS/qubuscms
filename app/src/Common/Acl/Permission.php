<?php
namespace TriTan\Common\Acl;

use TriTan\Interfaces\Acl\PermissionInterface;

/**
 * Permission Domain
 *
 * @license GPLv3
 *
 * @since 1.0.0
 * @package TriTan CMS
 * @author Joshua Parker <josh@joshuaparker.blog>
 */
final class Permission implements PermissionInterface
{
    /**
     * Permission id.
     *
     * @since 1.0.0
     * @var int
     */
    private $permission_id;

    /**
     * Permission name.
     *
     * @since 1.0.0
     * @var string
     */
    private $permission_name;

    /**
     * Permission key.
     *
     * @since 1.0.0
     * @var string
     */
    private $permission_key;

    public function getId(): int
    {
        return $this->permission_id;
    }

    public function setId(int $id)
    {
        return $this->permission_id = $id;
    }

    public function getName()
    {
        return $this->permission_name;
    }

    public function setName(string $name)
    {
        return $this->permission_name = $name;
    }

    public function getKey()
    {
        return $this->permission_key;
    }

    public function setKey(string $key)
    {
        return $this->permission_key = $key;
    }
}
