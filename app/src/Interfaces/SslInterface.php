<?php
namespace TriTan\Interfaces;

interface SslInterface
{
    /**
     * Determines if SSL is used.
     *
     * @since 1.0.0
     * @return bool True if SSL, otherwise false.
     */
    public function isEnabled(): bool;
}
