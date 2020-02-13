<?php
namespace TriTan\Interfaces\ACL;

interface PermissionInterface
{
    public function getId();

    public function setId(int $id);

    public function getName();

    public function setName(string $name);

    public function getKey();

    public function setKey(string $key);
}
