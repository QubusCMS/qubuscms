<?php
namespace TriTan\Common\User;

use TriTan\Interfaces\User\UserRoleMapperInterface;
use TriTan\Interfaces\Database\DatabaseInterface;

final class UserRoleMapper implements UserRoleMapperInterface
{
    protected $qudb;

    public function __construct(DatabaseInterface $qudb)
    {
        $this->qudb = $qudb;
    }

    public function has(string $role): bool
    {
        $check = $this->qudb->getRow(
            $this->qudb->prepare(
                "SELECT role_id FROM {$this->qudb->base_prefix}role WHERE role_key = ?",
                [
                    $role
                ]
            ),
            ARRAY_A
        );

        $exist = $this->qudb->getVar(
            $this->qudb->prepare(
                "SELECT COUNT(*) FROM {$this->qudb->base_prefix}usermeta WHERE meta_key = ? AND user_id = ? AND meta_value = ?",
                [
                    $this->qudb->prefix . 'role',
                    get_current_user_id(),
                    $check['role_id']
                ]
            )
        );

        return $exist > 0;
    }
}
