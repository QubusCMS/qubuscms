<?php
namespace TriTan\Common\Post;

use TriTan\Common\Post\PostQueryIterator;
use TriTan\Common\Container as c;

final class PostQuery extends PostQueryIterator
{
    /**
     * Query object
     *
     * @since 1.0.0
     * @var object
     */
    protected $query = null;

    public function __construct($query_arguments = [])
    {
        c::getInstance()->get('post');
        $this->query = new PostQueryIterator($query_arguments);
        parent::__construct($this->query->posts);
    }

    /**
     * Act as proxy for PostQuery
     *
     * @since 1.0.0
     */
    public function __call($method, $arguments)
    {
        if (method_exists($this->query, $method)) {
            return call_user_func_array(array($this->query, $method), $arguments);
        }
    }

    public function __get($property)
    {
        if (isset($this->query->$property)) {
            return $this->query->$property;
        }
    }
}
