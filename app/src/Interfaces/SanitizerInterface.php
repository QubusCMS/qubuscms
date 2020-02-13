<?php
namespace TriTan\Interfaces;

interface SanitizerInterface
{
    /**
     * Sanitizes a string key.
     *
     * @since 1.0.0
     * @param string $key String key
     * @return string Sanitized key
     */
    public function key(string $key);

    /**
     * Sanitizes an item according to type.
     *
     * @since 1.0.0
     * @param mixed $item     Item to sanitize.
     * @param string $type    Item type (i.e. string, float, int, etc.).
     * @param string $context The context for which the string is being sanitized.
     * @return string|null Sanitized string or null if item is empty.
     */
    public function item($item, $type = 'string', $context = 'save');

    /**
     * Sanitizes a username, stripping out unsafe characters.
     *
     * @since 1.0.0
     * @param string    $username The username to be sanitized.
     * @param bool      $strict If set, limits $username to specific characters. Default false.
     * @return string Sanitized username.
     */
    public function username($username, $strict = false);
}
