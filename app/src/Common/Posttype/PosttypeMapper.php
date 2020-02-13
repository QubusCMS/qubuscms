<?php
namespace TriTan\Common\Posttype;

use TriTan\Interfaces\Posttype\PosttypeMapperInterface;
use TriTan\Interfaces\Database\DatabaseInterface;
use TriTan\Interfaces\ContextInterface;
use TriTan\Common\Posttype\Posttype;
use Qubus\Exception\Data\TypeException;
use Cascade\Cascade;
use \PDOException;

final class PosttypeMapper implements PosttypeMapperInterface
{
    protected $qudb;

    protected $context;

    public function __construct(DatabaseInterface $qudb, ContextInterface $context)
    {
        $this->qudb = $qudb;
        $this->context = $context;
    }

    /**
     * Fetch a posttype object by ID
     *
     * @since 1.0.0
     * @param string $id
     * @return TriTan\Common\Posttype\Posttype|null Returns posttype object if exist and NULL otherwise.
     */
    public function findById($id)
    {
        if (!is_integer($id) || (int) $id < 1) {
            throw new TypeException('The ID of this entity is invalid.', 'invalid_id');
        }

        $posttype = null;

        if (!$data = $this->qudb->getRow(
            $this->qudb->prepare(
                "SELECT * FROM {$this->qudb->prefix}posttype WHERE posttype_id = ?",
                [
                    $id
                ]
            ),
            ARRAY_A
        )) {
            return false;
        }

        if ($data != null) {
            $posttype = $this->create($data);
        }

        if (is_array($posttype)) {
            $posttype = $this->context->obj['util']->toObject($posttype);
        }

        return $posttype;
    }

    /**
     * Fetch requested fields by where clause.
     *
     * @since 1.0.0
     * @param string $fields Fields to retrieve from table.
     * @param array/string $where Where clause (i.e. posttype_slug = ?).
     * @param array $params Parameters that need binding.
     * @param string $method The format of the ending results.
     * @return Posttype object.
     */
    public function findBySql($fields = '*', $where = '', $params = [], $method = 'results')
    {
        if ($where == '') {
            throw new TypeException('Where clause is missing.', 'invalid_where');
        }

        if (count(array_filter($params)) <= 0) {
            throw new TypeException('Parameters are missing.', 'invalid_params');
        }

        if (mb_strpos($fields, 'COUNT') !== false || mb_strpos($fields, 'count') !== false) {
            throw new TypeException(
                'SQL function COUNT or PHP function count() cannot be used.',
                'invalid_sql_function'
            );
        }

        $prepare = $this->qudb->prepare("SELECT $fields FROM {$this->qudb->prefix}posttype WHERE $where", $params);

        try {
            if ($method === 'results') {
                $data = $this->qudb->getResults($prepare, ARRAY_A);
            }

            if ($method === 'column') {
                $data = $this->qudb->getCol($prepare);
            }

            if ($method === 'row') {
                $data = $this->qudb->getRow($prepare, ARRAY_A);
            }

            if ($method === 'variable') {
                $result = $this->qudb->getVar($prepare);
                $data = [$fields => $result];
            }

            $posttypes = [];

            if ($data != null && ($method === 'row' || $method === 'variable')) {
                $posttypes[] = $this->create($data);
                return $posttypes[0];
            } else {
                foreach ($data as $posttype) {
                    $posttypes[] = $this->create($posttype);
                }

                return $posttypes;
            }
        } catch (PDOException $ex) {
            Cascade::getLogger('error')->error(
                sprintf(
                    'POSTTYPEMAPPER[find]: %s',
                    $ex->getMessage()
                ),
                [
                    'PosttypeMapper' => 'PosttypeMapper::findBySql'
                ]
            );
        }
    }

    /**
     * Fetch all posttypes.
     *
     * @since 1.0.0
     * @return object Posttype data object.
     */
    public function findAll()
    {
        $data = $this->qudb->getResults("SELECT * FROM {$this->qudb->prefix}posttype", ARRAY_A);
        $posttypes = [];
        if ($data != null) {
            foreach ($data as $posttype) {
                $posttypes[] = $this->create($posttype);
            }
        }
        return $posttypes;
    }

    /**
     * Create a new instance of Posttype. Optionally populating it
     * from a data array.
     *
     * @param array $data
     * @return TriTan\Common\Posttype\Posttype.
     */
    public function create(array $data = null) : Posttype
    {
        $posttype = $this->__create();
        if ($data) {
            $posttype = $this->populate($posttype, $data);
        }
        return $posttype;
    }

    /**
     * Populate the Posttype object with the data array.
     *
     * @param Posttype $posttype object.
     * @param array $data Posttype data.
     * @return TriTan\Common\Posttype\Posttype
     */
    public function populate(Posttype $posttype, array $data) : Posttype
    {
        $posttype->setId((int) $this->context->obj['escape']->html($data['posttype_id']));
        $posttype->setTitle((string) $this->context->obj['escape']->html($data['posttype_title']));
        $posttype->setSlug((string) $this->context->obj['escape']->html($data['posttype_slug']));
        $posttype->setDescription((string) $this->context->obj['escape']->textarea($data['posttype_description']));
        return $posttype;
    }

    /**
     * Create a new Posttype object.
     *
     * @return TriTan\Common\Posttype\Posttype
     */
    protected function __create() : Posttype
    {
        return new Posttype();
    }

    /**
     * Inserts a new posttype into the posttype document.
     *
     * @since 1.0.0
     * @param Posttype $posttype Posttype object.
     * @return int Last insert id.
     */
    public function insert(Posttype $posttype)
    {
        $this->qudb->getConnection()->throwTransactionExceptions();
        try {
            $result = $this->qudb->transaction(function () use ($posttype) {
                $this->qudb
                    ->insert([
                        'posttype_title' => (string) $posttype->getTitle(),
                        'posttype_slug' => (string) $posttype->getSlug(),
                        'posttype_description' => (string) $posttype->getDescription()
                    ])
                    ->into($this->qudb->prefix . 'posttype');

                return (int) $this->qudb->getConnection()->getPDO()->lastInsertId();
            });

            return (int) $result;
        } catch (PDOException $ex) {
            Cascade::getLogger('error')->error(
                sprintf(
                    'POSTTYPEMAPPER[insert]: %s',
                    $ex->getMessage()
                ),
                [
                    'PosttypeMapper' => 'PosttypeMapper::insert'
                ]
            );
        }
    }

    /**
     * Updates a Posttype object.
     *
     * @since 1.0.0
     * @param Posttype $posttype Posttype object.
     * @return Posttype id.
     */
    public function update(Posttype $posttype)
    {
        $this->qudb->getConnection()->throwTransactionExceptions();
        try {
            $this->qudb->transaction(function () use ($posttype) {
                $this->qudb
                    ->update($this->qudb->prefix . 'posttype')
                    ->where('posttype_id')->is((int) $posttype->getId())
                    ->set([
                        'posttype_title' => (string) $posttype->getTitle(),
                        'posttype_slug' => (string) $posttype->getSlug(),
                        'posttype_description' => (string) $posttype->getDescription()
                    ]);
            });

            return (int) $posttype->getId();
        } catch (PDOException $ex) {
            Cascade::getLogger('error')->error(
                sprintf(
                    'POSTTYPEMAPPER[update]: %s',
                    $ex->getMessage()
                ),
                [
                    'PosttypeMapper' => 'PosttypeMapper::update'
                ]
            );
        }
    }

    /**
     * Save the Posttype object.
     *
     * @since 1.0.0
     * @param Posttype $posttype Posttype object.
     */
    public function save(Posttype $posttype)
    {
        if (is_null($posttype->getId())) {
            $this->insert($posttype);
        } else {
            $this->update($posttype);
        }
    }

    /**
     * Deletes posttype object.
     *
     * @since 1.0.0
     * @param Posttype $posttype Posttype object.
     * @return bool True if deleted, false otherwise.
     */
    public function delete(Posttype $posttype)
    {
        $this->qudb->getConnection()->throwTransactionExceptions();
        try {
            $this->qudb->transaction(function () use ($posttype) {
                $this->qudb
                    ->from($this->qudb->prefix . 'posttype')
                    ->where('posttype_id')->is((int) $posttype->getId())
                    ->delete();
            });
        } catch (PDOException $ex) {
            Cascade::getLogger('error')->error(
                sprintf(
                    'POSTTYPEMAPPER[delete]: %s',
                    $ex->getMessage()
                ),
                [
                    'PosttypeMapper' => 'PosttypeMapper::delete'
                ]
            );
        }
    }
}
