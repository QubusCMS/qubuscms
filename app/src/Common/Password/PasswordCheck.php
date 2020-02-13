<?php
namespace TriTan\Common\Password;

use TriTan\Interfaces\Password\PasswordCheckInterface;
use TriTan\Interfaces\Password\PasswordSetMapperInterface;
use TriTan\Interfaces\Password\PasswordHashInterface;
use Qubus\Hooks\Interfaces\ActionFilterHookInterface;

final class PasswordCheck implements PasswordCheckInterface
{
    protected $mapper;

    protected $hasher;

    protected $hook;

    public function __construct(PasswordSetMapperInterface $mapper, PasswordHashInterface $hasher, ActionFilterHookInterface $hook)
    {
        $this->mapper = $mapper;
        $this->hasher = $hasher;
        $this->hook = $hook;
    }

    /**
     * Checks a plain text password against a hashed password.
     *
     * Uses `check_password` filter.
     *
     * @since 1.0.0
     * @param string $password Plain test password.
     * @param string $hash Hashed password in the database to check against.
     * @param int $user_id User ID.
     * @return bool True if the password and hash match, or false otherwise.
     */
    public function check(string $password, string $hash, int $user_id = 0) : bool
    {
        // If the hash is still md5...
        if (strlen($hash) <= 32) {
            $check = ($hash == md5($password));
            if ($check && $user_id) {
                // Rehash using new hash.
                $this->mapper->set($password, $user_id);
                $hash = $this->hasher->hash($password);
            }
            /**
             * Filters the password check.
             *
             * @since 1.0.0
             * @param bool $check      Whether the passwords match.
             * @param string $password The plaintext password.
             * @param string $hash     The hashed password.
             * @param int $user_id     The user id.
             */
            return $this->hook->applyFilter('check_password', $check, $password, $hash, $user_id);
        }

        /**
         * Checks if password needs rehashing just in case the algorithm or options were changed.
         */
        if (password_needs_rehash($hash, $this->hasher->algorithm(), $this->hasher->options())) {
            $this->mapper->set($password, $user_id);
            $hash = $this->hasher->hash($password);
        }

        $check = password_verify($password, $hash);

        /**
             * Filters the password check.
             *
             * @since 1.0.0
             * @param bool $check      Whether the passwords match.
             * @param string $password The plaintext password.
             * @param string $hash     The hashed password.
             * @param int $user_id     The user id.
             */
        return $this->hook->applyFilter('check_password', $check, $password, $hash, $user_id);
    }
}
