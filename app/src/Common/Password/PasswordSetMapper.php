<?php
namespace TriTan\Common\Password;

use TriTan\Interfaces\Password\PasswordSetMapperInterface;
use TriTan\Interfaces\Database\DatabaseInterface;
use TriTan\Interfaces\Password\PasswordHashInterface;
use Cascade\Cascade;
use \PDOException;

final class PasswordSetMapper implements PasswordSetMapperInterface
{
    protected $qudb;

    protected $hasher;

    public function __construct(DatabaseInterface $qudb, PasswordHashInterface $hasher)
    {
        $this->qudb = $qudb;
        $this->hasher = $hasher;
    }

    /**
     * Used by PasswordCheck::check() in order to rehash
     * an old password that was hashed using MD5 function.
     *
     * @since 1.0.0
     * @param string $password User password.
     * @param int $user_id User ID.
     * @return mixed
     */
    public function set(string $password, int $user_id)
    {
        $hash = $this->hasher->hash($password);

        $this->qudb->getConnection()->throwTransactionExceptions();
        try {
            $this->qudb->transaction(function () use ($user_id, $hash) {
                $this->qudb
                    ->update($this->qudb->base_prefix . 'user')
                    ->where('user_id')->is($user_id)
                    ->set([
                        'user_pass' => $hash
                    ]);
            });
        } catch (PDOException $ex) {
            Cascade::getLogger('error')->error(
                $ex->getMessage(),
                [
                    'PasswordSetMapper' => 'PasswordSetMapper::set'
                ]
            );
            return $ex->getMessage();
        }
    }
}
