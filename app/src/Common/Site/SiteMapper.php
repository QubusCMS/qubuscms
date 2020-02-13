<?php
namespace TriTan\Common\Site;

use TriTan\Interfaces\Site\SiteMapperInterface;
use TriTan\Interfaces\Database\DatabaseInterface;
use TriTan\Interfaces\ContextInterface;
use TriTan\Common\Site\Site;
use Qubus\Exception\Data\TypeException;
use Cascade\Cascade;
use \PDOException;

final class SiteMapper implements SiteMapperInterface
{
    protected $qudb;

    protected $context;

    public function __construct(DatabaseInterface $qudb, ContextInterface $context)
    {
        $this->qudb = $qudb;
        $this->context = $context;
    }

    /**
     * Fetch a site object by ID
     *
     * @since 1.0.0
     * @param string $id
     * @return TriTan\Common\Site\Site|null Returns site object if exist and NULL otherwise.
     */
    public function findById($id)
    {
        if (!is_integer($id) || (int) $id < 1) {
            throw new TypeException(
                'The ID of this entity is invalid.',
                'invalid_id'
            );
        }

        $site = $this->findBy('id', $id);

        return $site;
    }

    /**
     * Return only the main site fields.
     *
     * @since 1.0.0
     * @param string $field The field to query against: 'id', 'ID', 'email' or 'login'.
     * @param string|int $value The field value
     * @return object|false Raw site object
     */
    public function findBy($field, $value)
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
            $value = $this->trim($value);
        }

        if (!$value) {
            return false;
        }

        switch ($field) {
            case 'id':
                $site_id = (int) $value;
                $db_field = 'site_id';
                break;
            case 'slug':
                $site_id = $this->context->obj['cache']->read($value, 'siteslugs');
                $db_field = 'site_slug';
                break;
            case 'domain':
                $value = $this->context->obj['sanitizer']->item($value, '', '');
                $site_id = $this->context->obj['cache']->read($value, 'sitedomains');
                $db_field = 'site_domain';
                break;
            case 'path':
                $value = $this->context->obj['sanitizer']->item($value, '', '');
                $site_id = $this->context->obj['cache']->read($value, 'sitepaths');
                $db_field = 'site_path';
                break;
            default:
                return false;
        }

        $site = null;

        if (false !== $site_id) {
            if ($data = $this->context->obj['cache']->read($site_id, 'sites')) {
                is_array($data) ? $this->context->obj['util']->toObject($data) : $data;
            }
        }

        if (!$data = $this->qudb->getRow(
            $this->qudb->prepare(
                "SELECT * FROM {$this->qudb->base_prefix}site WHERE $db_field = ?",
                [
                    $value
                ]
            ),
            ARRAY_A
        )) {
            return false;
        }

        if ($data != null) {
            $site = $this->create($data);
            $this->context->obj['sitecache']->update($site);
        }

        if (is_array($site)) {
            $site = $this->context->obj['util']->toObject($site);
        }

        return $site;
    }

    /**
     * Fetch requested fields by where clause.
     *
     * @since 1.0.0
     * @param string $fields Fields to retrieve from table.
     * @param array/string $where Where clause (i.e. site_slug = ?).
     * @param array $params Parameters that need binding.
     * @param string $method The format of the ending results.
     * @return Site object.
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

        $prepare = $this->qudb->prepare("SELECT $fields FROM {$this->qudb->base_prefix}site WHERE $where", $params);

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
                    'POSTMAPPER[find]: %s',
                    $ex->getMessage()
                ),
                [
                    'SiteMapper' => 'SiteMapper::findBySql'
                ]
            );
        }
    }

    public function findAll()
    {
        $data = $this->qudb->getResults("SELECT * FROM {$this->qudb->base_prefix}site", ARRAY_A);
        $sites = [];
        if ($data != null) {
            foreach ($data as $site) {
                $sites[] = $this->create($site);
            }
        }
        return $sites;
    }

    /**
     * Create a new instance of Site. Optionally populating it
     * from a data array.
     *
     * @param array $data
     * @return TriTan\Common\Site\Site.
     */
    public function create(array $data = null) : Site
    {
        $site = $this->__create();
        if ($data) {
            $site = $this->populate($site, $data);
        }
        return $site;
    }

    /**
     * Populate the Site object with the data array.
     *
     * @param Site $site object.
     * @param array $data Site data.
     * @return TriTan\Common\Site\Site
     */
    public function populate(Site $site, array $data) : Site
    {
        $site->setId((int) $this->context->obj['escape']->html($data['site_id']));
        $site->setName((string) $this->context->obj['escape']->html($data['site_name']));
        $site->setSlug((string) $this->context->obj['escape']->html($data['site_slug']));
        $site->setDomain((string) $this->context->obj['escape']->html($data['site_domain']));
        $site->setPath((string) $this->context->obj['escape']->html($data['site_path']));
        $site->setOwner((int) $this->context->obj['escape']->html($data['site_owner']));
        $site->setStatus((string) $this->context->obj['escape']->html($data['site_status']));
        $site->setRegistered((string) $this->context->obj['escape']->html($data['site_registered']));
        $site->setModified((string) $this->context->obj['escape']->html($data['site_modified']));
        return $site;
    }

    /**
     * Create a new Site object.
     *
     * @return TriTan\Common\Site\Site
     */
    protected function __create() : Site
    {
        return new Site();
    }

    /**
     * Inserts a new site into the site document.
     *
     * @since 1.0.0
     * @param Site $site Site object.
     * @return int Last insert id.
     */
    public function insert(Site $site)
    {
        $this->qudb->getConnection()->throwTransactionExceptions();
        try {
            $result = $this->qudb->transaction(function () use ($site) {
                $this->qudb
                    ->insert([
                        'site_name' => (string) $site->getName(),
                        'site_slug' => (string) $site->getSlug(),
                        'site_domain' => (string) $site->getDomain(),
                        'site_path' => (string) $site->getPath(),
                        'site_owner' => (int) $site->getOwner(),
                        'site_status' => (string) $site->getStatus(),
                        'site_registered' => (string) $site->getRegistered(),
                        'site_modified' => '' == $site->getModified() ? null : (string) $site->getModified()
                    ])
                    ->into($this->qudb->base_prefix . 'site');

                return (int) $this->qudb->getConnection()->getPDO()->lastInsertId();
            });

            return (int) $result;
        } catch (PDOException $ex) {
            Cascade::getLogger('error')->error(
                sprintf(
                    'SITEMAPPER[insert]: %s',
                    $ex->getMessage()
                ),
                [
                    'SiteMapper' => 'SiteMapper::insert'
                ]
            );
        }
    }

    /**
     * Updates a Site object.
     *
     * @since 1.0.0
     * @param Site $site Site object.
     * @return Site's id.
     */
    public function update(Site $site)
    {
        $this->qudb->getConnection()->throwTransactionExceptions();
        try {
            $this->qudb->transaction(function () use ($site) {
                $this->qudb
                    ->update($this->qudb->base_prefix . 'site')
                    ->where('site_id')->is((int) $site->getId())
                    ->set([
                        'site_name' => (string) $site->getName(),
                        'site_slug' => (string) $site->getSlug(),
                        'site_domain' => (string) $site->getDomain(),
                        'site_path' => (string) $site->getPath(),
                        'site_owner' => (int) $site->getOwner(),
                        'site_status' => (string) $site->getStatus(),
                        'site_modified' => '' == $site->getModified() ? null : (string) $site->getModified()
                    ]);
            });

            return (int) $site->getId();
        } catch (PDOException $ex) {
            Cascade::getLogger('error')->error(
                sprintf(
                    'SITEMAPPER[update]: %s',
                    $ex->getMessage()
                ),
                [
                    'SiteMapper' => 'SiteMapper::update'
                ]
            );
        }
    }

    /**
     * Save the Site object.
     *
     * @since 1.0.0
     * @param Site $site Site object.
     */
    public function save(Site $site)
    {
        if (is_null($site->getId())) {
            $this->insert($site);
        } else {
            $this->update($site);
        }
    }

    /**
     * Deletes site object.
     *
     * @since 1.0.0
     * @param Site $site Site object.
     * @return bool True if deleted, false otherwise.
     */
    public function delete(Site $site)
    {
        $this->qudb->getConnection()->throwTransactionExceptions();
        try {
            $this->qudb->transaction(function () use ($site) {
                $this->qudb
                    ->from($this->qudb->base_prefix . 'site')
                    ->where('site_id')->is((int) $site->getId())
                    ->delete();
            });
        } catch (PDOException $ex) {
            Cascade::getLogger('error')->error(
                sprintf(
                    'SITEMAPPER[delete]: %s',
                    $ex->getMessage()
                ),
                [
                    'SiteMapper' => 'SiteMapper::delete'
                ]
            );
        }
    }
}
