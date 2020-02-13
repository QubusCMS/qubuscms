<?php
namespace TriTan\Common\Acl;

use TriTan\Interfaces\Database\DatabaseInterface;
use TriTan\Interfaces\ContextInterface;
use TriTan\Interfaces\Acl\PermissionMapperInterface;
use TriTan\Common\Acl\Permission;
use Cascade\Cascade;
use \PDOException;

final class PermissionMapper implements PermissionMapperInterface
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
            throw new InvalidArgumentException(
                'The ID of this entity is invalid.',
                'invalid_id'
            );
        }

        $data = $this->qudb->getRow(
            $this->qudb->prepare(
                "SELECT * FROM {$this->qudb->base_prefix}permission WHERE permission_id = ?",
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

        $data = $this->qudb->getResults("SELECT * FROM {$this->qudb->base_prefix}permission ORDER BY permission_name", ARRAY_A);

        $permissions = [];
        if ($data != null) {
            foreach ($data as $permission) {
                $permissions[] = $this->create($permission);
            }
        }

        $resp = [];
        foreach ($permissions as $r) {
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
                "SELECT * FROM {$this->qudb->base_prefix}permission WHERE permission_id = ?",
                [
                    $id
                ]
            ),
            ARRAY_A
        );

        $permission_name = null;
        if ($data != null) {
            $permission_name = $this->create($data);
        }
        return $permission_name->getName();
    }

    public function findKeyById(int $id)
    {
        $data = $this->qudb->getRow(
            $this->qudb->prepare(
                "SELECT * FROM {$this->qudb->base_prefix}permission WHERE permission_id = ?",
                [
                    $id
                ]
            ),
            ARRAY_A
        );

        $permission_id = null;
        if ($data != null) {
            $permission_id = $this->create($data);
        }
        return $permission_id->getKey();
    }

    /**
    * Create a new instance of Permission. Optionally populating it
    * from a data array.
    *
    * @param array $data
    * @return TriTan\Common\Acl\Permission.
    */
    public function create(array $data = null) : Permission
    {
        $permission = $this->__create();
        if ($data) {
            $permission = $this->populate($permission, $data);
        }
        return $permission;
    }

    /**
     * Populate the Permission object with the data array.
     *
     * @param Permission $permission object.
     * @param array $data Permission data.
     * @return TriTan\Common\Acl\Permission
     */
    public function populate(Permission $permission, array $data) : Permission
    {
        $permission->setId((int) $this->context->obj['escape']->html($data['permission_id']));
        $permission->setName((string) $this->context->obj['escape']->html($data['permission_name']));
        $permission->setKey((string) $this->context->obj['escape']->html($data['permission_key']));
        return $permission;
    }

    /**
     * Create a new Permission object.
     *
     * @return TriTan\Common\Acl\Permission
     */
    protected function __create() : Permission
    {
        return new Permission();
    }

    public function insert(Permission $permission)
    {
        $this->qudb->getConnection()->throwTransactionExceptions();
        try {
            $result = $this->qudb->transaction(function () use ($permission) {
                $this->qudb
                    ->insert([
                        'permission_key' => $permission->getKey(),
                        'permission_name' => $permission->getName()
                    ])
                    ->into($this->qudb->base_prefix . 'permission');

                return (int) $this->qudb->getConnection()->getPDO()->lastInsertId();
            });

            return (int) $result;
        } catch (PDOException $ex) {
            Cascade::getLogger('error')->error(
                sprintf(
                    'PERMISSIONMAPPER[insert]: %s',
                    $ex->getMessage()
                ),
                [
                    'PermissionMapper' => 'insert'
                ]
            );
        }
    }

    public function update(Permission $permission)
    {
        $this->qudb->getConnection()->throwTransactionExceptions();
        try {
            $this->qudb->transaction(function () use ($permission) {
                $this->qudb
                    ->update($this->qudb->base_prefix . 'permission')
                    ->where('permission_id')->is($permission->getId())
                    ->set([
                        'permission_key' => $permission->getKey(),
                        'permission_name' => $permission->getName()
                    ]);
            });

            return (int) $permission->getId();
        } catch (PDOException $ex) {
            Cascade::getLogger('error')->error(
                sprintf(
                    'PERMISSIONMAPPER[insert]: %s',
                    $ex->getMessage()
                ),
                [
                    'PermissionMapper' => 'update'
                ]
            );
        }
    }

    /**
     * Save the Permission object.
     *
     * @since 1.0.0
     * @param Permission $permission Permission object.
     */
    public function save(Permission $permission)
    {
        if (is_null($permission->getId())) {
            $this->insert($permission);
        } else {
            $this->update($permission);
        }
    }

    public function delete(Permission $permission)
    {
        $this->qudb->getConnection()->throwTransactionExceptions();
        try {
            $this->qudb->transaction(function () use ($permission) {
                $this->qudb
                    ->from($this->qudb->base_prefix . 'permission')
                    ->where('permission_id')->is($permission->getId())
                    ->delete();
            });
        } catch (PDOException $ex) {
            Cascade::getLogger('error')->error(
                sprintf(
                    'PERMISSIONMAPPER[delete]: %s',
                    $ex->getMessage()
                ),
                [
                    'PermissionMapper' => 'delete'
                ]
            );
        }
    }
}
