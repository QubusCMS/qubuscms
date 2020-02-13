<?php
namespace TriTan\Interfaces\Posttype;

use TriTan\Common\Posttype\Posttype;

interface PosttypeRepositoryInterface
{
    public function findById($id);
    public function findBySql($fields = '*', $where = '', $params = [], $method = 'results');
    public function findAll();
    public function insert(Posttype $posttype);
    public function update(Posttype $posttype);
    public function delete(Posttype $posttype);
}
