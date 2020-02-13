<?php
namespace TriTan\Common\User;

use TriTan\Interfaces\User\UserMapperInterface;
use TriTan\Interfaces\Database\DatabaseInterface;
use TriTan\Interfaces\ContextInterface;
use TriTan\Common\User\User;
use TriTan\Common\Date;
use Qubus\Exception\Data\TypeException;
use Cascade\Cascade;
use \PDOException;

final class UserMapper implements UserMapperInterface
{
    protected $qudb;

    protected $context;

    public function __construct(DatabaseInterface $qudb, ContextInterface $context)
    {
        $this->qudb = $qudb;
        $this->context = $context;
    }

    /**
     * Fetch a user object by ID
     *
     * @since 1.0.0
     * @param string $id
     * @return TriTan\Common\User\User|null Returns user object if exist and NULL otherwise.
     */
    public function findById(int $id)
    {
        if (!is_integer($id) || (int) $id < 1) {
            throw new TypeException('The ID of this entity is invalid.', 'invalid_id');
        }

        try {
            $user = $this->findBy('id', $id);
        } catch (PDOException $ex) {
            Cascade::getLogger('error')->error(
                sprintf(
                    'USERMAPPER[find]: %s',
                    $ex->getMessage()
                ),
                [
                    'UserMapper' => 'UserMapper::findById'
                ]
            );
        }

        return $user;
    }

    /**
     * Return only the main user fields.
     *
     * @since 1.0.0
     * @param string $field The field to query against: 'id', 'ID', 'email' or 'login'.
     * @param string|int $value The field value
     * @return object|false Raw user object
     */
    public function findBy(string $field, $value)
    {

        // 'ID' is an alias of 'id'.
        if ('ID' === $field) {
            $field = 'id';
        }

        if ('id' == $field) {
            // Make sure the value is numeric to avoid casting objects, for example,
            // to int 1.
            if (!is_numeric($value)) {
                return false;
            }
            $value = intval($value);
            if ($value < 1) {
                return false;
            }
        } else {
            $value = $this->context->obj['util']->trim($value);
        }

        if (!$value) {
            return false;
        }

        switch ($field) {
            case 'id':
                $user_id = (int) $value;
                $db_field = 'user_id';
                break;
            case 'email':
                $user_id = $this->context->obj['cache']->read($value, 'useremail');
                $db_field = 'user_email';
                break;
            case 'login':
                $value = $this->context->obj['sanitizer']->username($value);
                $user_id = $this->context->obj['cache']->read($value, 'userlogins');
                $db_field = 'user_login';
                break;
            default:
                return false;
        }

        $user = null;

        if (false !== $user_id) {
            if ($data = $this->context->obj['cache']->read($user_id, 'users')) {
                is_array($data) ? $this->toObject($data) : $data;
            }
        }

        if (!$data = $this->qudb->getRow(
            $this->qudb->prepare(
                "SELECT * FROM {$this->qudb->base_prefix}user WHERE $db_field = ?",
                [
                    $value
                ]
            ),
            ARRAY_A
        )) {
            return false;
        }

        if ($data != null) {
            $user = $this->create($data);
            $this->context->obj['usercache']->update($user);
        }

        if (is_array($user)) {
            $user = $this->context->obj['util']->toObject($user);
        }

        return $user;
    }

    /**
     * Fetch requested fields by where clause.
     *
     * @since 1.0.0
     * @param string $fields Database fields.
     * @param array/string $where Where clause (i.e. ['user_login' => 'qubus']
     *                            is equivalent to `where user_login = 'qubus'`.
     * @return Post object.
     */
    public function findBySql(string $fields, $where = '')
    {
        try {
            $sql = $this->qudb
                ->from($this->qudb->base_prefix . 'user')
                ->select([$fields]);

            if ($where != '') {
                $sql->where($where);
            }

            $data = $sql->all(function ($data) {
                $array = [];
                foreach ($data as $d) {
                    $array[] = $d;
                }
                return $array;
            });

            $users = [];
            if ($data != null) {
                foreach ($data as $user) {
                    $users[] = $this->create($user);
                }
            }

            return $users;
        } catch (PDOException $ex) {
            Cascade::getLogger('error')->error(
                sprintf(
                    'USERMAPPER[find]: %s',
                    $ex->getMessage()
                ),
                [
                    'UserMapper' => 'UserMapper::findBySql'
                ]
            );
        }
    }

    /**
     * Fetch all users.
     *
     * @since 1.0.0
     * @return User User data object.
     */
    public function findAll()
    {
        $data = $this->qudb->getResults("SELECT * FROM {$this->qudb->base_prefix}user", ARRAY_A);
        $users = [];
        if ($data != null) {
            foreach ($data as $user) {
                $users[] = $this->create($user);
            }
        }
        return $users;
    }

    /**
     * Create a new instance of User. Optionally populating it
     * from a data array.
     *
     * @param array $data
     * @return TriTan\Common\User\User.
     */
    public function create(array $data = null) : User
    {
        $user = $this->__create();
        if ($data) {
            $user = $this->populate($user, $data);
        }
        return $user;
    }

    /**
     * Populate the User object with the data array.
     *
     * @param User $user object.
     * @param array $data User data.
     * @return TriTan\Common\User\User
     */
    public function populate(User $user, array $data) : User
    {
        $user->setId((int) $this->context->obj['escape']->html($data['user_id']));
        $user->setLogin((string) $this->context->obj['escape']->html($data['user_login']));
        $user->setFname((string) $this->context->obj['escape']->html($data['user_fname']));
        $user->setLname((string) $this->context->obj['escape']->html($data['user_lname']));
        $user->setEmail((string) $this->context->obj['escape']->html($data['user_email']));
        $user->setPassword((string) $this->context->obj['html']->purify($data['user_pass']));
        $user->setUrl((string) $this->context->obj['escape']->html($data['user_url']));
        $user->setTimezone((string) $this->context->obj['escape']->html($data['user_timezone']));
        $user->setDateFormat((string) $this->context->obj['escape']->html($data['user_date_format']));
        $user->setTimeFormat((string) $this->context->obj['escape']->html($data['user_time_format']));
        $user->setLocale((string) $this->context->obj['escape']->html($data['user_locale']));
        $user->setAddedBy((int) $this->context->obj['escape']->html($data['user_addedby']));
        $user->setRegistered((string) $this->context->obj['escape']->html($data['user_registered']));
        $user->setModified((string) $this->context->obj['escape']->html($data['user_modified']));
        $user->setActivationKey((string) $this->context->obj['escape']->html($data['user_activation_key']));
        return $user;
    }

    /**
     * Create a new User object.
     *
     * @return TriTan\Common\User\User
     */
    protected function __create() : User
    {
        return new User();
    }

    /**
     * Inserts a new user into the user document.
     *
     * @since 1.0.0
     * @param User $user User object.
     * @return int Last insert id.
     */
    public function insert(User $user)
    {
        $this->qudb->getConnection()->throwTransactionExceptions();
        try {
            $result = $this->qudb->transaction(function () use ($user) {
                $this->qudb
                    ->insert([
                        'user_login' => (string) $user->getLogin(),
                        'user_fname' => (string) $user->getFname(),
                        'user_lname'  => (string) $user->getLname(),
                        'user_email'  => (string) $user->getEmail(),
                        'user_pass'   => (string) $user->getPassword(),
                        'user_url' => (string) $user->getUrl(),
                        'user_timezone' => (string) $user->getTimezone(),
                        'user_date_format' => (string) $user->getDateFormat(),
                        'user_time_format' => (string) $user->getTimeFormat(),
                        'user_locale' => (string) $user->getLocale(),
                        'user_addedby' => (int) $user->getAddedBy(),
                        'user_registered' => (string) $user->getRegistered(),
                        'user_activation_key' => '' == $user->getActivationKey() ? null : (string) $user->getActivationKey(),
                        'user_modified' => null
                    ])
                    ->into($this->qudb->base_prefix . 'user');

                return (int) $this->qudb->getConnection()->getPDO()->lastInsertId();
            });

            return (int) $result;
        } catch (PDOException $ex) {
            Cascade::getLogger('error')->error(
                sprintf(
                    'USERMAPPER[insert]: %s',
                    $ex->getMessage()
                ),
                [
                    'UserMapper' => 'UserMapper::insert'
                ]
            );
        }
    }

    /**
     * Updates a User object.
     *
     * @since 1.0.0
     * @param User $user User object.
     * @return The user's id.
     */
    public function update(User $user)
    {
        $this->qudb->getConnection()->throwTransactionExceptions();
        try {
            $this->qudb->transaction(function () use ($user) {
                $this->qudb
                    ->update($this->qudb->base_prefix . 'user')
                    ->where('user_id')->is((int) $user->getId())
                    ->set([
                        'user_login' => (string) $user->getLogin(),
                        'user_fname' => (string) $user->getFname(),
                        'user_lname'  => (string) $user->getLname(),
                        'user_email'  => (string) $user->getEmail(),
                        'user_pass'   => (string) $user->getPassword(),
                        'user_url' => (string) $user->getUrl(),
                        'user_timezone' => (string) $user->getTimezone(),
                        'user_date_format' => (string) $user->getDateFormat(),
                        'user_time_format' => (string) $user->getTimeFormat(),
                        'user_locale' => (string) $user->getLocale(),
                        'user_modified' => '' == $user->getModified() ? (string) (new Date())->current('db') : (string) $user->getModified(),
                        'user_activation_key' => '' == $user->getActivationKey() ? null : (string) $user->getActivationKey()
                    ]);
            });

            return (int) $user->getId();
        } catch (PDOException $ex) {
            Cascade::getLogger('error')->error(
                sprintf(
                    'USERMAPPER[update]: %s',
                    $ex->getMessage()
                ),
                [
                    'UserMapper' => 'UserMapper::update'
                ]
            );
        }
    }

    /**
     * Save the User object.
     *
     * @since 1.0.0
     * @param User $user User object.
     */
    public function save(User $user)
    {
        if (is_null($user->getId())) {
            $this->insert($user);
        } else {
            $this->update($user);
        }
    }

    /**
     * Deletes user object.
     *
     * @since 1.0.0
     * @param User $user User object.
     * @return bool True if deleted, false otherwise.
     */
    public function delete(User $user)
    {
        $this->qudb->getConnection()->throwTransactionExceptions();
        try {
            $this->qudb->transaction(function () use ($user) {
                $this->qudb
                    ->from($this->qudb->base_prefix . 'user')
                    ->where('user_id')->is((int) $user->getId())
                    ->delete();
            });
        } catch (PDOException $ex) {
            Cascade::getLogger('error')->error(
                sprintf(
                    'USERMAPPER[delete]: %s',
                    $ex->getMessage()
                ),
                [
                    'UserMapper' => 'UserMapper::delete'
                ]
            );
        }
    }
}
