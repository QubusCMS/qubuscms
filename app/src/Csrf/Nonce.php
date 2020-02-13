<?php
namespace TriTan\Csrf;

use RandomLib\Factory as RandomLibFactory;
use SecurityLib\Strength;

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
class Nonce
{
    private static $verifying;

    private static function token()
    {
        if ($csrf = Cookie::get('TTCMS_CSRF')) {
            return $csrf . Config::$SALT;
        } else {
            $csrf = self::randChar(33);
            Cookie::set('TTCMS_CSRF', $csrf, Config::$CSRF_EXPIRE);
            return $csrf . Config::$SALT;
        }
    }

    public static function create($action, $expire = null)
    {
        $hash = self::getHash($action);

        if (!self::$verifying && $hash) {
            $expire = (int) $expire ? (int) $expire : Config::$NONCE_EXPIRE;
            HashStore::set(self::name($hash), 1, $expire);
        }

        return $hash;
    }

    public static function field($action, $ttl = null)
    {
        $expire = (int) $ttl ? (int) $ttl : Config::$NONCE_EXPIRE;
        $hash = static::create($action, $expire);
        return '<input type="hidden" id="__ttcmsnonce" name="__ttcmsnonce" value="' . esc_html($hash) . '" />' . "\n";
    }

    private static function getHash($action)
    {
        $hash = hash(Config::$TOKEN_HASHER, $action . self::token());
        return substr($hash, 0, Config::$CHAR_LIMIT);
    }

    public static function verify($nonce, $action)
    {
        self::$verifying = true;
        $hash = self::create($action);
        self::$verifying = null;

        if ($hash != $nonce) {
            return false;
        }

        return (bool) HashStore::get(self::name($hash));
    }

    public static function deleteHash($hash)
    {
        return HashStore::delete(self::name($hash));
    }

    public static function delete($action)
    {
        return self::deleteHash(self::getHash($action));
    }

    public static function instance($user = null)
    {
        static $instance = null;

        if (null === $instance) {
            $instance = new Nonce;
        }

        return $instance;
    }

    public static function name($hash)
    {
        return Config::$HASH_NAME_LENGTH <= 0 ? $hash : substr($hash, 0, Config::$HASH_NAME_LENGTH);
    }

    private static function randChar($length = 16)
    {
        $factory = new RandomLibFactory;
        $generator = $factory->getGenerator(new Strength(Strength::MEDIUM));

        return $generator->generateString($length);
    }
}
