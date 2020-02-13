<?php
namespace TriTan\Csrf;

/**
  * Fast PHP nonce and CSRF tokens tool
  *
  * @author Samuel Elh <samelh.com/contact>
  * @version 0.1
  * @link http://github.com/elhardoum/nonce-php
  * @link https://samelh.com
  * @license GPL-3.0
  * @see https://github.com/elhardoum/nonce-php/blob/master/readme.md
  */
class Config
{
    public static $CSRF_EXPIRE = 7200; // 2 hrs
    public static $SALT = '<r*>iVIF`Mcjj&+fkJ4D2,-geI]:-{^|~97.2p:/~+Q?&J_fe2A0i~H?89SeJ:Ztt>';
    public static $CHAR_LIMIT = 22;
    public static $TOKEN_HASHER = 'sha256';
    public static $NONCE_EXPIRE = 600; // 10 min
    public static $COOKIE_PATH = '/';
    public static $COOKIE_DOMAIN = 'example.com';
    public static $HASH_NAME_LENGTH = 11;
    public static $STORE_CTX_SET = ['TriTan\Csrf\Cookie', 'set'];
    public static $STORE_CTX_GET = ['TriTan\Csrf\Cookie', 'get'];
    public static $STORE_CTX_DELETE = ['TriTan\Csrf\Cookie', 'delete'];

    public static function storeContextSet()
    {
        return self::$STORE_CTX_SET;
    }

    public static function storeContextGet()
    {
        return self::$STORE_CTX_GET;
    }

    public static function storeContextDelete()
    {
        return self::$STORE_CTX_DELETE;
    }
}
