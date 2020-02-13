<?php namespace TriTan\Cache;

/**
 * Qubus CMS Abstract Cache Class.
 *
 * @license GPLv3
 *
 * @since 1.0.0
 * @package Qubus CMS
 * @subpackage Cache
 * @author Joshua Parker <josh@joshuaparker.blog>
 */
abstract class AbstractCache
{
    abstract public function read($key, $namespace);

    abstract public function create($key, $data, $namespace, $ttl);

    abstract public function delete($key, $namespace);

    abstract public function flush();

    abstract public function flushNamespace($namespace);

    abstract public function set($key, $data, $namespace, $ttl);

    abstract public function getStats();

    abstract public function increment($key, $offset, $namespace);

    abstract public function decrement($key, $offset, $namespace);

    abstract protected function uniqueKey($key, $namespace);

    abstract protected function exists($key, $namespace);
}
