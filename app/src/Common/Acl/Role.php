<?php
namespace TriTan\Common\Acl;

use TriTan\Interfaces\Acl\RoleInterface;

/**
 * Role Domain
 *
 * @license GPLv3
 *
 * @since 1.0.0
 * @package Qubus CMS
 * @author Joshua Parker <josh@joshuaparker.blog>
 */
final class Role implements RoleInterface
{
    /**
     * Role id.
     *
     * @since 1.0.0
     * @var int
     */
    private $role_id;

    /**
     * Role name.
     *
     * @since 1.0.0
     * @var string
     */
    private $role_name;

    /**
     * Role permissions.
     *
     * @since 1.0.0
     * @var string
     */
    private $role_perm;

    /**
     * Role key.
     *
     * @since 1.0.0
     * @var string
     */
    private $role_key;

    public function getId()
    {
        return $this->role_id;
    }

    public function setId(int $id)
    {
        return $this->role_id = $id;
    }

    public function getName()
    {
        return $this->role_name;
    }

    public function setName(string $name)
    {
        return $this->role_name = $name;
    }

    public function getPermission()
    {
        return $this->role_perm;
    }

    public function setPermission(string $permission)
    {
        return $this->role_perm = $permission;
    }

    public function getKey()
    {
        return $this->role_key;
    }

    public function setKey(string $key)
    {
        return $this->role_key = $key;
    }
}
