<?php

use TriTan\Common\Container as c;

$app->before('GET|POST|PUT|DELETE|PATCH|HEAD', '/rest(.*)', function () use ($app) {
    if ($app->req->get['key'] !== c::getInstance()->get('option')->{'read'}('api_key') || c::getInstance()->get('option')->{'read'}('api_key') === null) {
        $app->res->_format('json', 401);
        exit();
    }
});

// RESTful API
$app->group('/rest', function () use ($app, $db) {

    /**
     * Will result in /rest/.
     */
    $app->get('/', function () use ($app) {
        $app->res->_format('json', 404);
    });

    /**
     * Will result in /rest/v1/.
     */
    $app->get('/v1/', function () use ($app) {
        $app->res->_format('json', 404);
    });

    /**
     * Will result in /rest/v1/dbtable/
     */
    $app->get('/v1/(\w+)', function ($table) use ($app) {
        $t = $app->qudb->from($table);

        if (isset($app->req->get['by']) === true) {
            if (isset($app->req->get['order']) !== true) {
                $app->req->get['order'] = 'ASC';
            }
            $t->orderBy($app->req->get['by'], $app->req->get['order']);
        }

        if (isset($app->req->get['limit']) === true) {
            $t->limit($app->req->get['limit']);
            if (isset($app->req->get['offset']) === true) {
                $t->offset($app->req->get['offset']);
            }
        }

        /**
         * Use closure as callback.
         */
        $q = $t->select()
        ->fetchAssoc()
        ->all();
        /**
         * If the database table doesn't exist, then it
         * is false and a 404 should be sent.
         */
        if ($q === false) {
            $app->res->_format('json', 404);
        }
        /**
         * If the query is legit, but there
         * is no data in the table, then a 200
         * status should be sent. Why? Check out
         * the accepted answer at
         * http://stackoverflow.com/questions/13366730/proper-rest-response-for-empty-table/13367198#13367198
         */ elseif (empty($q) === true) {
            $app->res->_format('json');
        }
        /**
         * If we get to this point, the all is well
         * and it is ok to process the query and print
         * the results in a json format.
         */ else {
            $app->res->_format('json', 200, $q);
        }
    });

    /**
     * Will result in /rest/v1/dbtable/columnname/operator/data/
     */
    $app->get('/v1/(\w+)/(\w+)/(\w+)/(.+)', function ($table, $field, $func, $any) use ($app) {
        $t = $app->qudb->from($table);
        $q = $t->where($field)->{$func}($any)->select();
        /**
         * Use closure as callback.
         */
        $results = $q->fetchAssoc()->all();
        /**
         * If the database table doesn't exist, then it
         * is false and a 404 should be sent.
         */
        if ($results === false) {
            $app->res->_format('json', 404);
        }
        /**
         * If the query is legit, but there
         * is no data in the table, then a 200
         * status should be sent. Why? Check out
         * the accepted answer at
         * http://stackoverflow.com/questions/13366730/proper-rest-response-for-empty-table/13367198#13367198
         */ elseif (empty($results) === true) {
            $app->res->_format('json');
        }
        /**
         * If we get to this point, the all is well
         * and it is ok to process the query and print
         * the results in a json format.
         */ else {
            $app->res->_format('json', 200, $results);
        }
    });

    /**
     * Will result in /rest/v1/dbtable/columnname/data/
     */
    $app->get('/v1/(\w+)/(\w+)/(.+)', function ($table, $field, $any) use ($app) {
        $t = $app->qudb->from($table);
        $q = $t->where($field)->is($any)->select();
        /**
         * Use closure as callback.
         */
        $results = $q->fetchAssoc()->all();
        /**
         * If the database table doesn't exist, then it
         * is false and a 404 should be sent.
         */
        if ($results === false) {
            $app->res->_format('json', 404);
        }
        /**
         * If the query is legit, but there
         * is no data in the table, then a 200
         * status should be sent. Why? Check out
         * the accepted answer at
         * http://stackoverflow.com/questions/13366730/proper-rest-response-for-empty-table/13367198#13367198
         */ elseif (empty($results) === true) {
            $app->res->_format('json');
        }
        /**
         * If we get to this point, the all is well
         * and it is ok to process the query and print
         * the results in a json format.
         */ else {
            $app->res->_format('json', 200, $results);
        }
    });

    $app->delete('/v1/(\w+)/(\w+)/(\d+)', function ($table, $field, $id) use ($app) {
        $query = [
                sprintf('DELETE FROM %s WHERE %s = ?', $table, $field),
        ];

        $query = sprintf('%s;', implode(' ', $query));
        $result = $app->qudb->getConnection()->query($query, [$id]);

        if ($result === false) {
            $app->res->_format('json', 404);
        } elseif (empty($result) === true) {
            $app->res->_format('json', 204);
        } else {
            $app->res->_format('json');
        }
    });

    if (in_array($http = strtoupper($app->req->server['REQUEST_METHOD']), ['POST', 'PUT']) === true) {
        if (preg_match('~^\x78[\x01\x5E\x9C\xDA]~', $data = _file_get_contents('php://input')) > 0) {
            $data = gzuncompress($data);
        }
        if ((array_key_exists('CONTENT_TYPE', $app->req->server) === true) && (empty($data) !== true)) {
            if (strncasecmp($app->req->server['CONTENT_TYPE'], 'application/json', 16) === 0) {
                $app->req->globals['_' . $http] = json_decode($data, true);
            } elseif ((strncasecmp($app->req->server['CONTENT_TYPE'], 'application/x-www-form-urlencoded', 33) === 0) && (strncasecmp($app->req->server['REQUEST_METHOD'], 'PUT', 3) === 0)) {
                parse_str($data, $app->req->globals['_' . $http]);
            }
        }
        if ((isset($app->req->globals['_' . $http]) !== true) || (is_array($app->req->globals['_' . $http]) !== true)) {
            $app->req->globals['_' . $http] = [];
        }
        unset($data);
    }

    $app->post('/v1/(\w+)/', function ($table) use ($app) {
        if (empty($app->req->post) === true) {
            $app->res->_format('json', 204);
        } elseif (is_array($app->req->post) === true) {
            $queries = [];

            if (count($app->req->post) == count($app->req->post, COUNT_RECURSIVE)) {
                $app->req->post = [$app->req->post];
            }

            foreach ($app->req->post as $row) {
                $data = [];

                foreach ($row as $key => $value) {
                    $data[sprintf('%s', $key)] = $value;
                }

                $query = [
                        sprintf('INSERT INTO %s (%s) VALUES (%s)', $table, implode(', ', array_keys($data)), implode(', ', array_fill(0, count($data), '?'))),
                ];

                $queries[] = [
                        sprintf('%s;', implode(' ', $query)),
                        $data,
                ];
            }

            if (count($queries) > 1) {
                $app->qudb->getConnection()->getPDO()->beginTransaction();

                while (is_null($query = array_shift($queries)) !== true) {
                    if (($result = $app->qudb->getConnection()->query($query[0], array_values($query[1]))) === false) {
                        $app->qudb->query->getConnection()->getPDO()->rollBack();
                        break;
                    }
                }

                if (($result !== false) && ($app->qudb->getConnection()->getPDO()->inTransaction() === true)) {
                    $result = $app->qudb->getConnection()->getPDO()->commit();
                }
            } elseif (is_null($query = array_shift($queries)) !== true) {
                $result = $app->qudb->getConnection()->query($query[0], array_values($query[1]));
            }

            if ($result === false) {
                $app->res->_format('json', 409);
            } else {
                $app->res->_format('json', 201);
            }
        }
    });

    $app->put('/v1/(\w+)/(\w+)/(\d+)', function ($table, $field, $id) use ($app) {
        if (empty($app->req->globals['_PUT']) === true) {
            $app->res->_format('json', 204);
        } elseif (is_array($app->req->globals['_PUT']) === true) {
            $data = [];

            foreach ($app->req->globals['_PUT'] as $key => $value) {
                $data[$key] = sprintf('%s = ?', $key);
            }

            $query = [
                    sprintf('UPDATE %s SET %s WHERE %s = ?', $table, implode(', ', $data), $field),
            ];

            $query = sprintf('%s;', implode(' ', $query));
            $values = array_values($app->req->globals['_PUT']);
            $result = $app->qudb->getConnection()->query($query, array_merge($values, [$id]));

            if ($result === false) {
                $app->res->_format('json', 409);
            } elseif (empty($result) === true) {
                $app->res->_format('json', 204);
            } else {
                $app->res->_format('json');
            }
        }
    });
});
