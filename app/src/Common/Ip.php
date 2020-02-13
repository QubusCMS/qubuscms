<?php
namespace TriTan\Common;

use TriTan\Interfaces\IpInterface;

class Ip implements IpInterface
{
    /**
     * Application object.
     * @var object
     */
    protected $app;

    public function __construct()
    {
        $this->app = \Liten\Liten::getInstance();
    }

    public function retrieve()
    {
        if (!empty($this->app->req->server['HTTP_CLIENT_IP'])) {
            //ip from share internet
            $ip = $this->app->req->server['HTTP_CLIENT_IP'];
        } elseif (!empty($this->app->req->server['HTTP_X_FORWARDED_FOR'])) {
            //ip pass from proxy
            $ip = $this->app->req->server['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $this->app->req->server['REMOTE_ADDR'];
        }
        return $ip;
    }
}
