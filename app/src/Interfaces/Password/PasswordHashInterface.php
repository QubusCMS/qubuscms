<?php
namespace TriTan\Interfaces\Password;

interface PasswordHashInterface
{
    /**
     * Algorithm to use when hashing the password (i.e. PASSWORD_DEFAULT, PASSWORD_ARGON2ID).
     *
     * @since 1.0.0
     * @return string Password algorithm.
     */
    public function algorithm();
    /**
     * An associative array containing options.
     *
     * @since 1.0.0
     * @return array Array of options.
     */
    public function options();
    /**
     * Hashes a plain text password.
     *
     * @since 1.0.0
     * @param string $password Plain text password
     * @return mixed
     */
    public function hash(string $password);
}
