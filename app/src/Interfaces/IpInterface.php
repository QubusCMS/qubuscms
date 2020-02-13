<?php
namespace TriTan\Interfaces;

interface IpInterface
{
    /**
     * Retrieve's a clients ip address.
     *
     * @return string Client's IP address.
     */
    public function retrieve();
}
