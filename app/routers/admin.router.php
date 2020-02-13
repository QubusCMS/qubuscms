<?php
use TriTan\Common\Container as c;
use Cascade\Cascade;
use TriTan\Common\Uri;
use TriTan\Common\FileSystem;
use TriTan\Csrf\Nonce;
use Qubus\Hooks\ActionFilterHook;

$qudb = app()->qudb;
$opt = new \TriTan\Common\Options\Options(
    new TriTan\Common\Options\OptionsMapper(
        $qudb,
        new TriTan\Common\Context\HelperContext()
    )
);

/**
 * Before router checks to make sure the logged in user
 * us allowed to access admin.
 */
$app->before('GET|POST', '/admin(.*)', function () {
    if (!is_user_logged_in()) {
        ttcms()->obj['flash']->{'error'}(
            esc_html__('401 - Error: Unauthorized.'),
            login_url()
        );
        exit();
    }
    if (!current_user_can('access_admin')) {
        ttcms()->obj['flash']->{'error'}(
            esc_html__('403 - Error: Forbidden.'),
            home_url()
        );
        exit();
    }
});

$app->group('/admin', function () use ($app, $qudb, $opt) {
    $app->get('/', function () use ($app) {
        $app->foil->render(
            'main::admin/index',
            [
                'title' => esc_html__('Admin Dashboard')
            ]
        );
    });

    $app->before('GET', '/media/', function () {
        if (!current_user_can('manage_media')) {
            ttcms()->obj['flash']->{'error'}(
                esc_html__('You do not have permission to manage the media library.'),
                admin_url()
            );
            exit();
        }
    });

    $app->get('/media/', function () use ($app) {
        $app->foil->render(
            'main::admin/media',
            [
                'title' => esc_html__('Media Library')
            ]
        );
    });

    $app->before('GET', '/ftp/', function () {
        if (!current_user_can('manage_ftp')) {
            ttcms()->obj['flash']->{'error'}(
                esc_html__('You do not have permission to manage FTP.'),
                admin_url()
            );
            exit();
        }
    });

    $app->get('/ftp/', function () use ($app) {
        $app->foil->render(
            'main::admin/ftp',
            [
                'title' => esc_html__('FTP')
            ]
        );
    });

    /**
     * Before route checks to make sure the logged in user
     * us allowed to manage options/settings.
     */
    $app->before('POST', '/options/', function () use ($app) {
        if (!current_user_can('manage_options')) {
            ttcms()->obj['flash']->{'error'}(
                esc_html__('You do not have permission to manage options.'),
                admin_url()
            );
            exit();
        }

        if (isset($app->req->post['__ttcmsnonce']) && !Nonce::verify($app->req->post['__ttcmsnonce'], 'options')) {
            ttcms()->obj['flash']->{'error'}(
                esc_html__('Invalid or missing CSRF token.'),
                $app->req->server['HTTP_REFERER']
            );
            exit();
        }
    });

    $app->post('/options/', function () use ($app, $opt) {
        if ($app->req->isPost()) {
            $options = $app->req->post;

            if (!empty(array_filter($options))) {
                foreach ($options as $option => $value) {
                    $option = trim($option);

                    if (!is_array($value)) {
                        $value = trim($value);
                    }

                    $value = (new TriTan\Common\Utils())->unslash($value);
                    $opt->update($option, $value);
                }

                ttcms()->obj['flash']->{'success'}(
                    ttcms()->obj['flash']->{'notice'}(200),
                    $app->req->server['HTTP_REFERER']
                );
            }
        }
    });

    /**
     * Before route checks to make sure the logged in user
     * us allowed to manage options/settings.
     */
    $app->before('GET|POST', '/options-general/', function () use ($app) {
        if (!current_user_can('manage_options')) {
            ttcms()->obj['flash']->{'error'}(
                esc_html__('You do not have permission to manage options.'),
                admin_url()
            );
            exit();
        }

        if (isset($app->req->post['__ttcmsnonce']) && !Nonce::verify($app->req->post['__ttcmsnonce'], 'options-general')) {
            ttcms()->obj['flash']->{'error'}(
                esc_html__('Invalid or missing CSRF token.'),
                $app->req->server['HTTP_REFERER']
            );
            exit();
        }
    });

    $app->match('GET|POST', '/options-general/', function () use ($app, $qudb, $opt) {
        if ($app->req->isPost()) {
            $options = [
                'sitename', 'site_description', 'admin_email', 'ttcms_core_locale',
                'cookieexpire', 'cookiepath', 'cron_jobs', 'site_cache',
                'system_timezone', 'api_key', 'maintenance_mode'
            ];
            foreach ($options as $option_name) {
                if (!isset($app->req->post[$option_name])) {
                    continue;
                }
                $value = $app->req->post[$option_name];
                $opt->update($option_name, $value);
            }

            $current_site = get_site((int) $qudb->site_id);

            if ($current_site) {
                $site_slug = ttcms_unique_site_slug(
                    $current_site->getSlug(),
                    $app->req->post['sitename'],
                    (int) $qudb->site_id
                );
                $site = new TriTan\Common\Site\Site();
                $site->setId((int) $qudb->site_id);
                $site->setName($app->req->post['sitename']);
                $site->setSlug($site_slug);
                $site->setDomain($current_site->getDomain());
                $site->setPath($current_site->getPath());
                $site->setOwner($current_site->getOwner());
                $site->setStatus($current_site->getStatus());
                $site->setRegistered($current_site->getRegistered());
                $site->setModified((string) (new \TriTan\Common\Date())->current('db'));

                $site_id = (
                    new \TriTan\Common\Site\SiteRepository(
                        new \TriTan\Common\Site\SiteMapper(
                            $qudb,
                            new \TriTan\Common\Context\HelperContext()
                        )
                    )
                )->{'update'}($site);
            }

            if (!is_ttcms_exception($site_id)) {
                // do nothing.
            } else {
                Cascade::getLogger('error')->{'error'}(
                    sprintf(
                        'SQLSTATE[%s]: %s',
                        $site_id->getCode(),
                        $site_id->getMessage()
                    ),
                    [
                        'Router' => '/options-general/'
                    ]
                );
            }

            ttcms()->obj['flash']->{'success'}(
                ttcms()->obj['flash']->{'notice'}(200),
                $app->req->server['HTTP_REFERER']
            );
        }

        $app->foil->render(
            'main::admin/options-general',
            [
                'title' => esc_html__('General Options'),
            ]
        );
    });

    /**
     * Before route checks to make sure the logged in user
     * us allowed to manage options/settings.
     */
    $app->before('GET|POST', '/options-reading/', function () use ($app) {
        if (!current_user_can('manage_options')) {
            ttcms()->obj['flash']->{'error'}(
                esc_html__('You do not have permission to manage options.'),
                admin_url()
            );
            exit();
        }

        if (isset($app->req->post['__ttcmsnonce']) && !Nonce::verify($app->req->post['__ttcmsnonce'], 'options-reading')) {
            ttcms()->obj['flash']->{'error'}(
                esc_html__('Invalid or missing CSRF token.'),
                $app->req->server['HTTP_REFERER']
            );
            exit();
        }
    });

    $app->match('GET|POST', '/options-reading/', function () use ($app, $opt) {
        if ($app->req->isPost()) {
            $options = [
                'current_site_theme', 'posts_per_page', 'date_format', 'time_format'
            ];

            foreach ($options as $option_name) {
                if (!isset($app->req->post[$option_name])) {
                    continue;
                }
                $value = $app->req->post[$option_name];
                $opt->update($option_name, $value);
            }

            ttcms()->obj['flash']->{'success'}(
                ttcms()->obj['flash']->notice(200),
                $app->req->server['HTTP_REFERER']
            );
        }

        $app->foil->render(
            'main::admin/options-reading',
            [
                'title' => esc_html__('Reading Options'),
            ]
        );
    });

    /**
     * Before route check.
     */
    $app->before('GET|POST', '/plugin/', function () {
        if (!current_user_can('manage_plugins')) {
            ttcms()->obj['flash']->{'error'}(
                esc_html__("You do not have permission to manage plugins."),
                admin_url()
            );
        }
    });

    $app->get('/plugin/', function () use ($app) {
        $app->foil->render(
            'main::admin/plugin/index',
            [
                'title' => esc_html__('Plugins')
            ]
        );
    });

    $app->before('GET|POST', '/plugin/activate/', function () {
        if (!current_user_can('manage_plugins')) {
            ttcms()->obj['flash']->{'error'}(
                esc_html__('Permission denied to activate a plugin.'),
                admin_url()
            );
            exit();
        }
    });

    $app->get('/plugin/activate/', function () use ($app) {
        ob_start();

        $plugin_name = $app->req->get['id'];

        /**
         * This function will validate a plugin and make sure
         * there are no errors before activating it.
         *
         * @since 1.0.0
         */
        validate_plugin($plugin_name);

        if (ob_get_length() > 0) {
            $output = ob_get_clean();
            $error = new TriTan\Error(
                'unexpected_output',
                esc_html__('The plugin generated unexpected output.'),
                $output
            );

            Cascade::getLogger('error')->{'error'}(
                sprintf(
                    'PLUGIN[%s]: %s',
                    $error->getErrorCode(),
                    $error->getErrorMessage()
                )
            );

            ttcms()->obj['flash']->{'error'}($error->getErrorMessage());
        }
        ob_end_clean();

        (new Uri(ActionFilterHook::getInstance()))->{'redirect'}($app->req->server['HTTP_REFERER']);
    });

    $app->before('GET|POST', '/deactivate/', function () {
        if (!current_user_can('manage_plugins')) {
            ttcms()->obj['flash']->{'error'}(
                esc_html__('Permission denied to deactivate a plugin.'),
                admin_url()
            );
            exit();
        }
    });

    $app->get('/plugin/deactivate/', function () use ($app) {
        $pluginName = $app->req->get['id'];
        /**
         * Fires before a specific plugin is deactivated.
         *
         * $pluginName refers to the plugin's
         * name (i.e. smtp.plugin.php).
         *
         * @since 1.0.0
         * @param string $pluginName The plugin's base name.
         */
        ActionFilterHook::getInstance()->{'doAction'}('deactivate_plugin', $pluginName);

        /**
         * Fires as a specifig plugin is being deactivated.
         *
         * $pluginName refers to the plugin's
         * name (i.e. smtp.plugin.php).
         *
         * @since 1.0.0
         * @param string $pluginName The plugin's base name.
         */
        ActionFilterHook::getInstance()->{'doAction'}('deactivate_' . $pluginName);

        deactivate_plugin($pluginName);

        /**
         * Fires after a specific plugin has been deactivated.
         *
         * $pluginName refers to the plugin's
         * name (i.e. smtp.plugin.php).
         *
         * @since 1.0.0
         * @param string $pluginName The plugin's base name.
         */
        ActionFilterHook::getInstance()->{'doAction'}('deactivated_plugin', $pluginName);

        (new Uri(ActionFilterHook::getInstance()))->{'redirect'}($app->req->server['HTTP_REFERER']);
    });

    /**
     * Before route check.
     */
    $app->before('GET|POST|PATCH|PUT|OPTIONS|DELETE', '/connector/', function () {
        if (!is_user_logged_in()) {
            ttcms()->obj['flash']->{'error'}(
                esc_html__("You do not have permission to access requested screen"),
                admin_url()
            );
            exit();
        }
    });

    $app->match('GET|POST|PATCH|PUT|OPTIONS|DELETE', '/connector/', function () use ($app) {
        error_reporting(0);
        try {
            (
                new FileSystem(
                    ActionFilterHook::getInstance()
                )
            )->{'mkdir'}(
                BASE_PATH . 'private' . DS . 'sites' . DS . (int) c::getInstance()->get('site_id') . DS . 'uploads' . DS . '__optimized__' . DS
            );
        } catch (\TriTan\Exception\IOException $e) {
            Cascade::getLogger('error')->error(
                sprintf(
                    'IOSTATE[%s]: Unable to create directory: %s',
                    $e->getCode(),
                    $e->getMessage()
                ),
                [
                    'Router' => '/connector/'
                ]
            );
        }
        $opts = [
            // 'debug' => true,
            'locale' => 'en_US.UTF-8',
            'roots' => [
                [
                    'driver' => 'LocalFileSystem',
                    'startPath' => c::getInstance()->get('site_path') . 'uploads' . DS,
                    'path' => c::getInstance()->get('site_path') . 'uploads' . DS,
                    'alias' => 'Media Library',
                    'mimeDetect' => 'auto',
                    'accessControl' => 'access',
                    'tmbURL' => site_url('private/sites/' . (int) c::getInstance()->get('site_id') . '/uploads/' . '.tmb'),
                    'tmpPath' => c::getInstance()->get('site_path') . 'uploads' . DS . '.tmb',
                    'URL' => site_url('private/sites/' . (int) c::getInstance()->get('site_id') . '/uploads/'),
                    'attributes' => [
                        [
                            'read' => true,
                            'write' => true,
                            'locked' => false
                        ],
                        [
                            'pattern' => '/\__optimized__/',
                            'read' => false,
                            'write' => false,
                            'hidden' => true,
                            'locked' => true
                        ],
                        [
                            'pattern' => '/\.gitkeep/',
                            'read' => false,
                            'write' => false,
                            'hidden' => true,
                            'locked' => true
                        ],
                        [
                            'pattern' => '/\.gitignore/',
                            'read' => false,
                            'write' => false,
                            'hidden' => true,
                            'locked' => true
                        ],
                        [
                            'pattern' => '/\.htaccess/',
                            'read' => false,
                            'write' => false,
                            'hidden' => true,
                            'locked' => true
                        ],
                        [
                            'pattern' => '/\index.html/',
                            'read' => false,
                            'write' => false,
                            'hidden' => true,
                            'locked' => false
                        ],
                        [
                            'pattern' => '/\.tmb/',
                            'read' => false,
                            'write' => false,
                            'hidden' => true,
                            'locked' => false
                        ],
                        [
                            'pattern' => '/\.quarantine/',
                            'read' => false,
                            'write' => false,
                            'hidden' => true,
                            'locked' => false
                        ],
                        [
                            'pattern' => '/\.DS_Store/',
                            'read' => false,
                            'write' => false,
                            'hidden' => true,
                            'locked' => false
                        ],
                        [
                            'pattern' => '/\.json$/',
                            'read' => true,
                            'write' => true,
                            'hidden' => false,
                            'locked' => false
                        ]
                    ],
                    'uploadMaxSize' => '500M',
                    'uploadAllow' => [
                        'text/plain', 'image/png', 'image/jpeg', 'image/gif', 'application/zip',
                        'text/csv', 'application/pdf', 'application/msword', 'application/vnd.ms-excel',
                        'application/vnd.ms-powerpoint', 'application/msword', 'application/vnd.ms-excel',
                        'application/vnd.ms-powerpoint', 'video/mp4'
                    ],
                    'uploadOrder' => ['allow', 'deny']
                ]
            ]
        ];
        // run elFinder
        $connector = new elFinderConnector(new elFinder($opts));
        $connector->run();
    });

    /**
     * Before route check.
     */
    $app->before('GET|POST|PATCH|PUT|OPTIONS|DELETE', '/ftp-connector/', function () {
        if (!is_user_logged_in()) {
            ttcms()->obj['flash']->{'error'}(
                esc_html__("You do not have permission to access requested screen"),
                admin_url()
            );
            exit();
        }
    });

    $app->match('GET|POST|PATCH|PUT|OPTIONS|DELETE', '/ftp-connector/', function () use ($app) {
        error_reporting(0);
        try {
            (
                new FileSystem(
                    ActionFilterHook::getInstance()
                )
            )->{'mkdir'}(
                BASE_PATH . 'private' . DS . 'sites' . DS . (int) c::getInstance()->get('site_id') . DS . 'uploads' . DS . '__optimized__' . DS
            );
        } catch (\TriTan\Exception\IOException $e) {
            Cascade::getLogger('error')->error(
                sprintf(
                    'IOSTATE[%s]: Unable to create directory: %s',
                    $e->getCode(),
                    $e->getMessage()
                ),
                [
                    'Router' => '/ftp-connector/'
                ]
            );
        }
        $opts = [
            // 'debug' => true,
            'locale' => 'en_US.UTF-8',
            'roots' => [
                [
                    'driver' => 'LocalFileSystem',
                    'path' => BASE_PATH . 'private' . DS,
                    'tmbURL' => site_url('private/.tmb'),
                    'tmpPath' => BASE_PATH . 'private' . DS . '.tmb',
                    'detectDirIcon' => 'favicon.ico',
                    'alias' => 'Files',
                    'mimeDetect' => 'auto',
                    'accessControl' => 'access',
                    'attributes' => [
                        [
                            'read' => true,
                            'write' => true,
                            'locked' => false
                        ],
                        [
                            'pattern' => '/\.gitkeep/',
                            'read' => false,
                            'write' => false,
                            'hidden' => true,
                            'locked' => true
                        ],
                        [
                            'pattern' => '/\.gitignore/',
                            'read' => false,
                            'write' => false,
                            'hidden' => true,
                            'locked' => true
                        ],
                        [
                            'pattern' => '/\.htaccess/',
                            'read' => false,
                            'write' => false,
                            'hidden' => true,
                            'locked' => true
                        ],
                        [
                            'pattern' => '/\index.html/',
                            'read' => false,
                            'write' => false,
                            'hidden' => true,
                            'locked' => false
                        ],
                        [
                            'pattern' => '/\.tmb/',
                            'read' => false,
                            'write' => false,
                            'hidden' => true,
                            'locked' => false
                        ],
                        [
                            'pattern' => '/\.quarantine/',
                            'read' => false,
                            'write' => false,
                            'hidden' => true,
                            'locked' => false
                        ],
                        [
                            'pattern' => '/\.DS_Store/',
                            'read' => false,
                            'write' => false,
                            'hidden' => true,
                            'locked' => false
                        ],
                        [
                            'pattern' => '/\.json$/',
                            'read' => true,
                            'write' => true,
                            'hidden' => false,
                            'locked' => false
                        ]
                    ],
                    'uploadMaxSize' => '500M',
                    'uploadAllow' => [
                        'text/plain', 'text/html', 'application/json', 'application/xml',
                        'application/javascript'
                    ],
                    'uploadOrder' => ['allow', 'deny']
                ],
                [
                    'driver' => 'LocalFileSystem',
                    'startPath' => c::getInstance()->get('site_path') . 'uploads' . DS,
                    'path' => c::getInstance()->get('site_path') . 'uploads' . DS,
                    'alias' => 'Media Library',
                    'mimeDetect' => 'auto',
                    'accessControl' => 'access',
                    'tmbURL' => site_url('private/sites/' . (int) c::getInstance()->get('site_id') . '/uploads/' . '.tmb'),
                    'tmpPath' => c::getInstance()->get('site_path') . 'uploads' . DS . '.tmb',
                    'URL' => site_url('private/sites/' . (int) c::getInstance()->get('site_id') . '/uploads/'),
                    'attributes' => [
                        [
                            'read' => true,
                            'write' => true,
                            'locked' => false
                        ],
                        [
                            'pattern' => '/\__optimized__/',
                            'read' => false,
                            'write' => false,
                            'hidden' => true,
                            'locked' => true
                        ],
                        [
                            'pattern' => '/\.gitkeep/',
                            'read' => false,
                            'write' => false,
                            'hidden' => true,
                            'locked' => true
                        ],
                        [
                            'pattern' => '/\.gitignore/',
                            'read' => false,
                            'write' => false,
                            'hidden' => true,
                            'locked' => true
                        ],
                        [
                            'pattern' => '/\.htaccess/',
                            'read' => false,
                            'write' => false,
                            'hidden' => true,
                            'locked' => true
                        ],
                        [
                            'pattern' => '/\index.html/',
                            'read' => false,
                            'write' => false,
                            'hidden' => true,
                            'locked' => false
                        ],
                        [
                            'pattern' => '/\.tmb/',
                            'read' => false,
                            'write' => false,
                            'hidden' => true,
                            'locked' => false
                        ],
                        [
                            'pattern' => '/\.quarantine/',
                            'read' => false,
                            'write' => false,
                            'hidden' => true,
                            'locked' => false
                        ],
                        [
                            'pattern' => '/\.DS_Store/',
                            'read' => false,
                            'write' => false,
                            'hidden' => true,
                            'locked' => false
                        ],
                        [
                            'pattern' => '/\.json$/',
                            'read' => true,
                            'write' => true,
                            'hidden' => false,
                            'locked' => false
                        ]
                    ],
                    'uploadMaxSize' => '500M',
                    'uploadAllow' => [
                        'text/plain', 'image/png', 'image/jpeg', 'image/gif', 'application/zip',
                        'text/csv', 'application/pdf', 'application/msword', 'application/vnd.ms-excel',
                        'application/vnd.ms-powerpoint', 'application/msword', 'application/vnd.ms-excel',
                        'application/vnd.ms-powerpoint', 'video/mp4'
                    ],
                    'uploadOrder' => ['allow', 'deny']
                ]
            ]
        ];
        // run elFinder
        $connector = new elFinderConnector(new elFinder($opts));
        $connector->run();
    });

    /**
     * Before route check.
     */
    $app->before('GET|POST', '/elfinder/', function () {
        if (!is_user_logged_in()) {
            ttcms()->obj['flash']->{'error'}(
                esc_html__("You don't have permission to view the requested screen"),
                login_url()
            );
            exit();
        }
    });

    $app->match('GET|POST', '/elfinder/', function () use ($app) {
        $app->foil->render(
            'main::admin/elfinder',
            [
                'title' => 'elfinder 2.1'
            ]
        );
    });

    /**
     * Before route check.
     */
    $app->before('GET|POST', '/permission.*', function () {
        if (!current_user_can('manage_roles')) {
            ttcms()->obj['flash']->{'error'}(
                esc_html__("You don't have permission to manage roles/permissions."),
                admin_url()
            );
            exit();
        }
    });

    $app->match('GET|POST', '/permission/', function () use ($app) {
        $app->foil->render(
            'main::admin/permission/index',
            [
                'title' => esc_html__('Manage Permissions')
            ]
        );
    });

    $app->match('GET|POST', '/permission/(\d+)/', function ($id) use ($app, $qudb) {
        if (isset($app->req->post['__ttcmsnonce']) && !Nonce::verify($app->req->post['__ttcmsnonce'], "update-permission-{$id}")) {
            ttcms()->obj['flash']->{'error'}(
                esc_html__('Invalid or missing CSRF token.'),
                $app->req->server['HTTP_REFERER']
            );
            exit();
        }

        if ($app->req->isPost()) {
            $permission = new \TriTan\Common\Acl\Permission();
            $permission->setId((int) $id);
            $permission->setKey($app->req->post['permission_key']);
            $permission->setName($app->req->post['permission_name']);

            $perm_id = (
                new TriTan\Common\Acl\PermissionRepository(
                    new \TriTan\Common\Acl\PermissionMapper(
                        $qudb
                    )
                )
            )->{'update'}($permission);

            if (!is_ttcms_exception($perm_id)) {
                ttcms()->obj['flash']->{'success'}(
                    ttcms()->obj['flash']->{'notice'}(200),
                    $app->req->server['HTTP_REFERER']
                );
            } else {
                Cascade::getLogger('error')->{'error'}($perm_id->getMessage());
                ttcms()->obj['flash']->{'error'}($perm_id->getMessage());
            }
        }

        $perm = (
            new TriTan\Common\Acl\PermissionRepository(
                new \TriTan\Common\Acl\PermissionMapper(
                    $qudb
                )
            )
        )->{'findById'}((int) $id);


        /**
         * If the database table doesn't exist, then it
         * is false and a 404 should be sent.
         */
        if ($perm == false) {
            $app->res->_format('json', 404);
            exit();
        } elseif (empty($perm) == true) {
            $app->res->_format('json', 404);
            exit();
        } elseif ((int) $perm->getId() <= 0) {
            $app->res->_format('json', 404);
            exit();
        } else {
            $app->foil->render(
                'main::admin/permission/update',
                [
                    'title' => esc_html__('Update Permission'),
                    'perm' => $perm
                ]
            );
        }
    });

    $app->match('GET|POST', '/permission/create/', function () use ($app, $qudb) {
        if (isset($app->req->post['__ttcmsnonce']) && !Nonce::verify($app->req->post['__ttcmsnonce'], 'create-permission')) {
            ttcms()->obj['flash']->{'error'}(
                esc_html__('Invalid or missing CSRF token'),
                $app->req->server['HTTP_REFERER']
            );
            exit();
        }

        if ($app->req->isPost()) {
            $permission = new \TriTan\Common\Acl\Permission();
            $permission->setKey($app->req->post['permission_key']);
            $permission->setName($app->req->post['permission_name']);

            $perm_id = (
                new TriTan\Common\Acl\PermissionRepository(
                    new \TriTan\Common\Acl\PermissionMapper(
                        $qudb
                    )
                )
            )->{'insert'}($permission);

            if (!is_ttcms_exception($perm_id)) {
                ttcms()->obj['flash']->{'success'}(
                    ttcms()->obj['flash']->{'notice'}(200),
                    admin_url('permission/')
                );
            } else {
                Cascade::getLogger('error')->{'error'}($perm_id->getMessage());
                ttcms()->obj['flash']->{'error'}($perm_id->getMessage());
            }
        }

        $app->foil->render(
            'main::admin/permission/create',
            [
                'title' => esc_html__('Create New Permission')
            ]
        );
    });

    /**
     * Before route check.
     */
    $app->before('GET|POST', '/role(.*)', function () {
        if (!current_user_can('manage_roles')) {
            ttcms()->obj['flash']->{'error'}(
                esc_html__("You don't have permission to manage roles/permissions."),
                admin_url()
            );
            exit();
        }
    });

    $app->match('GET|POST', '/role/', function () use ($app, $qudb) {
        $roles = (
            new TriTan\Common\Acl\RoleRepository(
                new TriTan\Common\Acl\RoleMapper(
                    $qudb
                )
            )
        )->{'findAll'}('full');

        $app->foil->render(
            'main::admin/role/index',
            [
                'title' => esc_html__('Manage Roles'),
                'roles' => $roles
            ]
        );
    });

    $app->match('GET|POST', '/role/(\d+)/', function ($id) use ($app, $qudb) {
        $role = (
            new TriTan\Common\Acl\RoleRepository(
                new TriTan\Common\Acl\RoleMapper(
                    $qudb
                )
            )
        )->{'findById'}((int) $id);

        /**
         * If the database table doesn't exist, then it
         * is false and a 404 should be sent.
         */
        if ($role == false) {
            $app->res->_format('json', 404);
            exit();
        } elseif (empty($role) == true) {
            $app->res->_format('json', 404);
            exit();
        } elseif ((int) $role->getId() <= 0) {
            $app->res->_format('json', 404);
            exit();
        } else {
            $app->foil->render(
                'main::admin/role/update',
                [
                    'title' => esc_html__('Update Role'),
                    'role' => $role
                ]
            );
        }
    });

    $app->match('GET|POST', '/role/create/', function () use ($app, $qudb) {
        if (isset($app->req->post['__ttcmsnonce']) && !Nonce::verify($app->req->post['__ttcmsnonce'], 'create-role')) {
            ttcms()->obj['flash']->{'error'}(
                esc_html__('Invalid or missing CSRF token'),
                $app->req->server['HTTP_REFERER']
            );
            exit();
        }

        if ($app->req->isPost()) {
            $permission = (
                new TriTan\Common\Serializer()
            )->{'serialize'}($app->req->post['role_perm']);

            $role = new \TriTan\Common\Acl\Role();
            $role->setKey(_trim($app->req->post['role_key']));
            $role->setName($app->req->post['role_name']);
            $role->setPermission($permission);

            $role_id = (
                new TriTan\Common\Acl\RoleRepository(
                    new TriTan\Common\Acl\RoleMapper(
                        $qudb
                    )
                )
            )->{'insert'}($role);

            if (!is_ttcms_exception($role_id)) {
                $id = $role_id;

                ttcms()->obj['flash']->{'success'}(
                    ttcms()->obj['flash']->notice(200),
                    admin_url('role/' . (int) $id . '/')
                );
            } else {
                ttcms()->obj['flash']->{'error'}($e->getMessage());
            }
        }

        $perms = (
            new TriTan\Common\Acl\PermissionRepository(
                new \TriTan\Common\Acl\PermissionMapper(
                    $qudb
                )
            )
        )->{'findAll'}('full');

        $app->foil->render(
            'main::admin/role/create',
            [
                'title' => esc_html__('Create Role'),
                'perms' => $perms
            ]
        );
    });

    $app->post('/role/edit-role/', function () use ($app, $qudb) {
        if (isset($app->req->post['__ttcmsnonce']) && !Nonce::verify($app->req->post['__ttcmsnonce'], "update-role-{$app->req->post['role_id']}")) {
            ttcms()->obj['flash']->{'error'}(
                esc_html__('Invalid or missing CSRF token'),
                $app->req->server['HTTP_REFERER']
            );
            exit();
        }

        $permission = (new TriTan\Common\Serializer())->{'serialize'}($app->req->post['role_perm']);

        $role = new \TriTan\Common\Acl\Role();
        $role->setId((int) $app->req->post['role_id']);
        $role->setKey((string) _trim($app->req->post['role_key']));
        $role->setName((string) $app->req->post['role_name']);
        $role->setPermission($permission);

        $role_id = (
            new TriTan\Common\Acl\RoleRepository(
                new TriTan\Common\Acl\RoleMapper(
                    $qudb
                )
            )
        )->{'update'}($role);

        if (!is_ttcms_exception($role_id)) {
            ttcms()->obj['flash']->{'success'}(ttcms()->obj['flash']->notice(200));
        } else {
            ttcms()->obj['flash']->{'error'}($role_id->getMessage());
        }

        (new Uri(ActionFilterHook::getInstance()))->{'redirect'}($app->req->server['HTTP_REFERER']);
    });

    /**
     * Before route check.
     */
    $app->before('GET|POST', '/system-snapshot/', function () {
        if (!current_user_can('manage_settings')) {
            ttcms()->obj['flash']->{'error'}(
                esc_html__(
                    "You don't have permission to view the System Snapshot Report screen."
                ),
                admin_url()
            );
        }
    });

    $app->get('/system-snapshot/', function () use ($app, $qudb) {
        $user = $qudb->from($qudb->base_prefix . 'usermeta')
            ->where('meta_key')->is($qudb->prefix . 'status')
            ->where('meta_value')->is('A');

        $dbversion = $qudb->getConnection()->getPDO()->getAttribute(\PDO::ATTR_DRIVER_NAME) .
            ' ' . $qudb->getConnection()->getPDO()->getAttribute(\PDO::ATTR_SERVER_VERSION);

        $app->foil->render(
            'main::admin/system-snapshot',
            [
                'title' => esc_html__('System Snapshot Report'),
                'user' => (int) $user->count('meta_id'),
                'dbversion' => $dbversion
            ]
        );
    });

    /**
     * Before route check.
     */
    $app->before('GET|POST', '/flush-cache/', function () {
        if (!current_user_can('manage_settings')) {
            ttcms()->obj['flash']->{'error'}(
                esc_html__("You are not allowed to flush the site cache."),
                admin_url()
            );
            exit();
        }
    });

    $app->get('/flush-cache/', function () use ($app, $opt) {
        if ($opt->read('current_site_theme')
            !== 'null' && $opt->read('current_site_theme')
            !== '' &&
            $opt->read('current_site_theme') !== false
        ) {
            $app->fenom->clearAllCompiles();
        }
        ttcms()->obj['cache']->{'flush'}();
        /**
         * Fires after cache has been flushed.
         *
         * @since 1.0.0
         */
        ActionFilterHook::getInstance()->{'doAction'}('flush_cache');

        ttcms()->obj['flash']->{'success'}(
            esc_html__('Cache flushed successfully.'),
            $app->req->server['HTTP_REFERER']
        );
    });

    /**
     * If the requested page does not exist,
     * return a 404.
     */
    $app->setError(function () use ($app) {
        $app->res->_format('json', 404);
    });
});
