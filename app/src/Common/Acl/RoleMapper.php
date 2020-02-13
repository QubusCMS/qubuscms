<?php
namespace TriTan\Common\Acl;

use TriTan\Interfaces\Database\DatabaseInterface;
use TriTan\Interfaces\ContextInterface;
use TriTan\Interfaces\Acl\RoleMapperInterface;
use TriTan\Common\Acl\Role;
use Qubus\Exception\Data\TypeException;
use Cascade\Cascade;
use \PDOException;

final class RoleMapper implements RoleMapperInterface
{
    protected $qudb;

    protected $context;

    public function __construct(DatabaseInterface $qudb, ContextInterface $context)
    {
        $this->qudb = $qudb;
        $this->context = $context;
    }

    public function findById(int $id)
    {
        if (!is_integer($id) || (int) $id < 1) {
            throw new TypeException(
                'The ID of this entity is invalid.',
                'invalid_id'
            );
        }

        $data = $this->qudb->getRow(
            $this->qudb->prepare(
                "SELECT * FROM {$this->qudb->base_prefix}role WHERE role_id = ?",
                [
                    $id
                ]
            ),
            ARRAY_A
        );

        $permission = null;
        if ($data != null) {
            $permission = $this->create($data);
        }
        return $permission;
    }

    public function findAll($format = 'ids')
    {
        $_format = strtolower($format);

        $data = $this->qudb->getResults("SELECT * FROM {$this->qudb->base_prefix}role ORDER BY role_name", ARRAY_A);

        $roles = [];
        if ($data != null) {
            foreach ($data as $role) {
                $roles[] = $this->create($role);
            }
        }

        $resp = [];
        foreach ($roles as $r) {
            if ($_format == 'full') {
                $resp[] = ["ID" => $r->getId(), "Name" => $r->getName(), "Key" => $r->getKey()];
            } else {
                $resp[] = $r->getId();
            }
        }
        return $resp;
    }

    public function findNameById(int $id): string
    {
        $data = $this->qudb->getRow(
            $this->qudb->prepare(
                "SELECT * FROM {$this->qudb->base_prefix}role WHERE role_id = ?",
                [
                    $id
                ]
            ),
            ARRAY_A
        );

        $role_name = null;
        if ($data != null) {
            $role_name = $this->create($data);
        }
        return $role_name->getName();
    }

    public function findIdByKey(string $key): int
    {
        $data = $this->qudb->getRow(
            $this->qudb->prepare(
                "SELECT * FROM {$this->qudb->base_prefix}role WHERE role_key = ?",
                [
                    $key
                ]
            ),
            ARRAY_A
        );

        $role_id = null;
        if ($data != null) {
            $role_id = $this->create($data);
        }
        return $role_id->getId();
    }

    /**
    * Create a new instance of Role. Optionally populating it
    * from a data array.
    *
    * @param array $data
    * @return TriTan\Common\Acl\Role.
    */
    public function create(array $data = null) : Role
    {
        $permission = $this->__create();
        if ($data) {
            $permission = $this->populate($permission, $data);
        }
        return $permission;
    }

    /**
     * Populate the Role object with the data array.
     *
     * @param Role $permission object.
     * @param array $data Role data.
     * @return TriTan\Common\Acl\Role
     */
    public function populate(Role $permission, array $data) : Role
    {
        $permission->setId((int) $this->context->obj['escape']->html($data['role_id']));
        $permission->setName((string) $this->context->obj['escape']->html($data['role_name']));
        $permission->setPermission((string) $this->context->obj['html']->purify($data['role_perm']));
        $permission->setKey((string) $this->context->obj['escape']->html($data['role_key']));
        return $permission;
    }

    /**
     * Create a new Role object.
     *
     * @return TriTan\Common\Acl\Role
     */
    protected function __create() : Role
    {
        return new Role();
    }

    public function insert(Role $role)
    {
        $this->qudb->getConnection()->throwTransactionExceptions();
        try {
            $result = $this->qudb->transaction(function () use ($role) {
                $this->qudb
                    ->insert([
                        'role_key' => $role->getKey(),
                        'role_name' => $role->getName(),
                        'role_perm'   => $role->getPermission()
                    ])
                    ->into($this->qudb->base_prefix . 'role');

                return (int) $this->qudb->getConnection()->getPDO()->lastInsertId();
            });

            return (int) $result;
        } catch (PDOException $ex) {
            Cascade::getLogger('error')->error(
                sprintf(
                    'ROLEMAPPER[insert]: %s',
                    $ex->getMessage()
                ),
                [
                    'RoleMapper' => 'insert'
                ]
            );
        }
    }

    public function update(Role $role)
    {
        $this->qudb->getConnection()->throwTransactionExceptions();
        try {
            $this->qudb->transaction(function () use ($role) {
                $this->qudb
                    ->update($this->qudb->base_prefix . 'role')
                    ->where('role_id')->is($role->getId())
                    ->set([
                        'role_key' => $role->getKey(),
                        'role_name' => $role->getName(),
                        'role_perm'   => $role->getPermission()
                    ]);
            });

            return (int) $role->getId();
        } catch (PDOException $ex) {
            Cascade::getLogger('error')->error(
                sprintf(
                    'ROLEMAPPER[insert]: %s',
                    $ex->getMessage()
                ),
                [
                    'RoleMapper' => 'update'
                ]
            );
        }
    }

    /**
     * Save the Role object.
     *
     * @since 1.0.0
     * @param Role $role Role object.
     */
    public function save(Role $role)
    {
        if (is_null($role->getId())) {
            $this->insert($role);
        } else {
            $this->update($role);
        }
    }

    public function delete(Role $role)
    {
        $this->qudb->getConnection()->throwTransactionExceptions();
        try {
            $this->qudb->transaction(function () use ($role) {
                $this->qudb
                    ->from($this->qudb->base_prefix . 'role')
                    ->where('role_id')->is($role->getId())
                    ->delete();
            });
        } catch (PDOException $ex) {
            Cascade::getLogger('error')->error(
                sprintf(
                    'ROLEMAPPER[delete]: %s',
                    $ex->getMessage()
                ),
                [
                    'RoleMapper' => 'delete'
                ]
            );
        }
    }
}
