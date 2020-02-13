<?php
namespace TriTan\Common\Password;

use TriTan\Interfaces\Password\PasswordHashInterface;
use Qubus\Hooks\Interfaces\ActionFilterHookInterface;

final class PasswordHash implements PasswordHashInterface
{
    protected $hook;

    public function __construct(ActionFilterHookInterface $hook)
    {
        $this->hook = $hook;
    }

    /**
    * Algorithm to use when hashing the password (i.e. PASSWORD_DEFAULT, PASSWORD_ARGON2ID).
    *
    * @since 1.0.0
    * @return string Password algorithm.
    */
    public function algorithm()
    {
        /**
        * Filters the password_hash() hashing algorithm.
        *
        * @since 1.0.0
        * @param string $algo Hashing algorithm. Default: PASSWORD_ARGON2ID
        */
        return $this->hook->applyFilter('password_hash_algo', PASSWORD_ARGON2ID);
    }

    /**
     * An associative array containing options.
     *
     * @since 1.0.0
     * @return array Array of options.
     */
    public function options(): array
    {
        /**
         * Filters the password_hash() options parameter.
         *
         * @since 1.0.0
         * @param array $options Options to pass to password_hash() function.
         */
        return $this->hook->applyFilter(
            'password_hash_options',
            (array) ['memory_cost' => 1<<12, 'time_cost' => 2, 'threads' => 2]
        );
    }

    /**
     * Hashes a plain text password.
     *
     * @since 1.0.0
     * @param string $password Plain text password
     * @return string Hashed password.
     */
    public function hash(string $password)
    {
        return password_hash($password, $this->algorithm(), $this->options());
    }
}
