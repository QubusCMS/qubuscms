<?php
namespace TriTan\Common\User;

use TriTan\Interfaces\User\UserPermissionMapperInterface;
use TriTan\Interfaces\Database\DatabaseInterface;
use TriTan\Interfaces\ContextInterface;

final class UserPermissionMapper implements UserPermissionMapperInterface
{
    protected $qudb;

    protected $context;

    public function __construct(DatabaseInterface $qudb, ContextInterface $context)
    {
        $this->qudb = $qudb;
        $this->context = $context;
    }

    public function has(string $permission): bool
    {
        $user_role = $this->qudb->getVar(
            $this->qudb->prepare(
                "SELECT meta_value FROM {$this->qudb->base_prefix}usermeta WHERE meta_key = ? AND user_id = ?",
                [
                    $this->qudb->prefix . 'role',
                    get_current_user_id()
                ]
            )
        );

        $perms = $this->qudb->getVar(
            $this->qudb->prepare(
                "SELECT role_perm FROM {$this->qudb->base_prefix}role WHERE role_id = ?",
                [
                    $this->context->obj['escape']->html($user_role)
                ]
            )
        );

        $clean_permission = $this->context->obj['html']->purify($perms);
        $perm = $this->context->obj['serializer']->unserialize($clean_permission);

        if (in_array($permission, $perm)) {
            return true;
        }
        return false;
    }
}
