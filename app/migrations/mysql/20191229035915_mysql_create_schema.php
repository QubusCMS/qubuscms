<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class MysqlCreateSchema extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-abstractmigration-class
     *
     * The following commands can be used in this method and Phinx will
     * automatically reverse them when rolling back:
     *
     *    createTable
     *    renameTable
     *    addColumn
     *    addCustomColumn
     *    renameColumn
     *    addIndex
     *    addForeignKey
     *
     * Any other destructive changes will result in an error when trying to
     * rollback the migration.
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change()
    {
        $this->execute("SET FOREIGN_KEY_CHECKS=0;");

        $factory = new \RandomLib\Factory;
        $generator = $factory->getGenerator(new \SecurityLib\Strength(\SecurityLib\Strength::MEDIUM));

        $host = $this->adapter->getOption('host');
        $name = $this->adapter->getOption('name');
        $user = $this->adapter->getOption('user');
        $pass = $this->adapter->getOption('pass');
        $prefix = $this->adapter->getOption('table_prefix');
        $driver = $this->adapter->getOption('adapter');
        $charset = $this->adapter->getOption('charset');
        $collation = $this->adapter->getOption('collation');
        $port = $this->adapter->getOption('port');
        $url = $this->adapter->getOption('mainsite')['url'];
        $path = $this->adapter->getOption('mainsite')['path'];
        $cookies = $generator->generateString(20, '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ');
        $api_key = $generator->generateString(20, '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ');
        $nonce = $generator->generateString(64, '0123456789abcdefghijklmnopqrst~@\#$%&*():;{[]}/.<>?+=uvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ');

        $NOW = date('Y-m-d H:i:s');
        $post_published = date('Y-m-d H:i:s A');
        $post_published_gmt = gmdate('Y-m-d H:i:s', strtotime($post_published));

        // Migration for table option
        if (!$this->hasTable('option')) :
        $table = $this->table('option', ['id' => false, 'primary_key' => 'option_id']);
        $table
            ->addColumn('option_id', 'integer', ['identity' => true, 'limit' => MysqlAdapter::INT_BIG])
            ->addColumn('option_key', 'string', ['limit' => 191])
            ->addColumn('option_value', 'text', ['limit' => MysqlAdapter::TEXT_LONG])
            ->addIndex(['option_key'], ['unique' => true])
            ->create();

        $data = [
            [
                'option_key' => 'sitename',
                'option_value' => 'Qubus CMS'
            ],
            [
                'option_key' => 'site_description',
                'option_value' => 'Just another Qubus CMS site'
            ],
            [
                'option_key' => 'admin_email',
                'option_value' => 'admin@ttcms.com'
            ],
            [
                'option_key' => 'ttcms_core_locale',
                'option_value' => 'en'
            ],
            [
                'option_key' => 'cookieexpire',
                'option_value' => '604800'
            ],
            [
                'option_key' => 'cookiepath',
                'option_value' => '/'
            ],
            [
                'option_key' => 'cron_jobs',
                'option_value' => '0'
            ],
            [
                'option_key' => 'system_timezone',
                'option_value' => 'America/New_York'
            ],
            [
                'option_key' => 'api_key',
                'option_value' => $api_key
            ],
            [
                'option_key' => 'date_format',
                'option_value' => 'd F Y'
            ],
            [
                'option_key' => 'time_format',
                'option_value' => 'h:i A'
            ],
            [
                'option_key' => 'admin_skin',
                'option_value' => 'skin-purple-light'
            ],
            [
                'option_key' => 'site_cache',
                'option_value' => '0'
            ],
            [
                'option_key' => 'current_site_theme',
                'option_value' => ''
            ],
            [
                'option_key' => 'posts_per_page',
                'option_value' => '6'
            ],
            [
                'option_key' => 'maintenance_mode',
                'option_value' => '0'
            ],
        ];

        $table->insert($data)->saveData();
        endif;

        // Migration for table permission
        if (!$this->hasTable('permission') && !$this->adapter->getOption('exclude')['permission']) :
        $table = $this->table('permission', ['id' => false, 'primary_key' => 'permission_id']);
        $table
            ->addColumn('permission_id', 'integer', ['identity' => true, 'limit' => MysqlAdapter::INT_BIG])
            ->addColumn('permission_key', 'string', ['limit' => 191])
            ->addColumn('permission_name', 'string', ['limit' => 191])
            ->addIndex(['permission_key'], ['unique' => true])
            ->create();

        $data = [
            [
                "permission_key" => "access_admin",
                "permission_name" => "Access Admin"
            ],
            [
                "permission_key" => "create_posts",
                "permission_name" => "Create Posts"
            ],
            [
                "permission_key" => "manage_posts",
                "permission_name" => "Manage Posts"
            ],
            [
                "permission_key" => "update_posts",
                "permission_name" => "Update Posts"
            ],
            [
                "permission_key" => "delete_posts",
                "permission_name" => "Delete Posts"
            ],
            [
                "permission_key" => "publish_posts",
                "permission_name" => "Publish Posts"
            ],
            [
                "permission_key" => "create_users",
                "permission_name" => "Create Users"
            ],
            [
                "permission_key" => "manage_users",
                "permission_name" => "Manage Users"
            ],
            [
                "permission_key" => "update_users",
                "permission_name" => "Update Users"
            ],
            [
                "permission_key" => "delete_users",
                "permission_name" => "Delete Users"
            ],
            [
                "permission_key" => "manage_media",
                "permission_name" => "Manage Media"
            ],
            [
                "permission_key" => "manage_options",
                "permission_name" => "Manage Options"
            ],
            [
                "permission_key" => "manage_settings",
                "permission_name" => "Manage Settings"
            ],
            [
                "permission_key" => "install_plugins",
                "permission_name" => "Install Plugins"
            ],
            [
                "permission_key" => "manage_plugins",
                "permission_name" => "Manage Plugins"
            ],
            [
                "permission_key" => "delete_plugins",
                "permission_name" => "Delete Plugins"
            ],
            [
                "permission_key" => "manage_ftp",
                "permission_name" => "Manage FTP"
            ],
            [
                "permission_key" => "manage_roles",
                "permission_name" => "Manage Roles"
            ],
            [
                "permission_key" => "switch_user",
                "permission_name" => "Switch User"
            ],
            [
                "permission_key" => "create_sites",
                "permission_name" => "Create Sites"
            ],
            [
                "permission_key" => "manage_sites",
                "permission_name" => "Manage Sites"
            ],
            [
                "permission_key" => "update_sites",
                "permission_name" => "Update Sites"
            ],
            [
                "permission_key" => "delete_sites",
                "permission_name" => "Delete Sites"
            ]
        ];

        $table->insert($data)->saveData();
        endif;

        // Migration for table plugin
        if (!$this->hasTable('plugin')) :
        $table = $this->table('plugin', ['id' => false, 'primary_key' => 'plugin_id']);
        $table
            ->addColumn('plugin_id', 'integer', ['identity' => true, 'limit' => MysqlAdapter::INT_BIG])
            ->addColumn('plugin_location', 'string', 'string', ['limit' => 191])
            ->create();
        endif;

        // Migration for table post
        if (!$this->hasTable('post')) :
        $table = $this->table('post', ['id' => false, 'primary_key' => 'post_id']);
        $table
            ->addColumn('post_id', 'integer', ['identity' => true, 'limit' => MysqlAdapter::INT_BIG])
            ->addColumn('post_title', 'string', ['limit' => 191])
            ->addColumn('post_slug', 'string', ['limit' => 191])
            ->addColumn('post_content', 'text', ['null' => true, 'limit' => MysqlAdapter::TEXT_LONG])
            ->addColumn('post_author', 'integer', ['null' => true, 'limit' => MysqlAdapter::INT_BIG])
            ->addColumn('post_posttype', 'string', ['limit' => 191])
            ->addColumn('post_parent', 'integer', ['null' => true, 'limit' => MysqlAdapter::INT_BIG])
            ->addColumn('post_sidebar', 'integer', ['default' => '0', 'limit' => MysqlAdapter::INT_TINY])
            ->addColumn('post_show_in_menu', 'integer', ['default' => '0', 'limit' => MysqlAdapter::INT_TINY])
            ->addColumn('post_show_in_search', 'integer', ['default' => '0', 'limit' => MysqlAdapter::INT_TINY])
            ->addColumn('post_relative_url', 'string', ['limit' => 191])
            ->addColumn('post_featured_image', 'string', ['null' => true, 'limit' => 191])
            ->addColumn('post_status', 'string', ['limit' => 191])
            ->addColumn('post_created', 'string', ['limit' => 30])
            ->addColumn('post_created_gmt', 'datetime', [])
            ->addColumn('post_published', 'string', ['limit' => 30])
            ->addColumn('post_published_gmt', 'datetime', [])
            ->addColumn('post_modified', 'string', ['default' => '0000-00-00 00:00:00', 'limit' => 30])
            ->addColumn('post_modified_gmt', 'datetime', ['default' => '0000-00-00 00:00:00'])
            ->addIndex(['post_slug', 'post_posttype', 'post_parent'])
            ->addForeignKey('post_author', 'user', 'user_id', ['constraint' => $prefix . 'post_post_author', 'delete' => 'SET_NULL', 'update' => 'CASCADE'])
            ->addForeignKey('post_posttype', 'posttype', 'posttype_slug', ['constraint' => $prefix . 'post_post_posttype', 'delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->addForeignKey('post_parent', 'post', 'post_id', ['constraint' => $prefix . 'post_post_parent', 'delete' => 'SET_NULL', 'update' => 'CASCADE'])
            ->create();

        $data = [
            [
                'post_title' => 'Hello World',
                'post_slug' => 'hello-world',
                'post_content' => '<p>Hello world! My name is Qubus CMS, and I was conceived on May 20, 2019. I am a content management framework with no default head. I can be used to store all your content while you use your skills as a developer to custom build a site around me or build RESTful applications. I am hoping that we can build a long lasting relationship.</p>',
                'post_author' => '1',
                'post_posttype' => 'post',
                'post_relative_url' => 'post/hello-world/',
                'post_status' => 'published',
                'post_created' => $post_published,
                'post_created_gmt' => $post_published_gmt,
                'post_published' => $post_published,
                'post_published_gmt' => $post_published_gmt,
                'post_modified' => '0000-00-00 00:00:00',
                'post_modified_gmt' => '0000-00-00 00:00:00'
            ]
        ];

        $table->insert($data)->saveData();
        endif;

        // Migration for table postmeta
        if (!$this->hasTable('postmeta')) :
        $table = $this->table('postmeta', ['id' => false, 'primary_key' => 'meta_id']);
        $table
            ->addColumn('meta_id', 'integer', ['identity' => true, 'limit' => MysqlAdapter::INT_BIG])
            ->addColumn('post_id', 'integer', ['limit' => MysqlAdapter::INT_BIG])
            ->addColumn('meta_key', 'string', ['limit' => 191])
            ->addColumn('meta_value', 'text', ['limit' => MysqlAdapter::TEXT_LONG])
            ->addIndex(['post_id', 'meta_key'], ['unique' => true])
            ->addForeignKey('post_id', 'post', 'post_id', ['constraint' => $prefix . 'postmeta_post_id', 'delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();
        endif;

        // Migration for table post
        if (!$this->hasTable('posttype')) :
        $table = $this->table('posttype', ['id' => false, 'primary_key' => 'posttype_id']);
        $table
            ->addColumn('posttype_id', 'integer', ['identity' => true, 'limit' => MysqlAdapter::INT_BIG])
            ->addColumn('posttype_title', 'string', ['limit' => 191])
            ->addColumn('posttype_slug', 'string', ['limit' => 191])
            ->addColumn('posttype_description', 'text', ['null' => true, 'limit' => MysqlAdapter::TEXT_REGULAR])
            ->addIndex(['posttype_slug'], ['unique' => true])
            ->create();

        $data = [
            [
                'posttype_title' => 'Post',
                'posttype_slug' => 'post'
            ],
            [
                'posttype_title' => 'Page',
                'posttype_slug' => 'page'
            ]
        ];

        $table->insert($data)->saveData();
        endif;

        // Migration for table role
        if (!$this->hasTable('role') && !$this->adapter->getOption('exclude')['role']) :
        $table = $this->table('role', ['id' => false, 'primary_key' => 'role_id']);
        $table
            ->addColumn('role_id', 'integer', ['identity' => true, 'limit' => MysqlAdapter::INT_BIG])
            ->addColumn('role_key', 'char', ['limit' => 22])
            ->addColumn('role_name', 'string', ['limit' => 191])
            ->addColumn('role_perm', 'text', ['limit' => MysqlAdapter::TEXT_LONG])
            ->addIndex(['role_key'], ['unique' => true])
            ->create();

        $data = [
            [
                "role_name" => "Super Administrator",
                "role_perm" => "a:23:{i:0;s:12:\"access_admin\";i:1;s:12:\"create_posts\";i:2;s:12:\"manage_posts\";i:3;s:12:\"update_posts\";i:4;s:12:\"delete_posts\";i:5;s:13:\"publish_posts\";i:6;s:12:\"create_users\";i:7;s:12:\"manage_users\";i:8;s:12:\"update_users\";i:9;s:12:\"delete_users\";i:10;s:12:\"manage_media\";i:11;s:14:\"manage_options\";i:12;s:15:\"manage_settings\";i:13;s:15:\"install_plugins\";i:14;s:14:\"manage_plugins\";i:15;s:14:\"delete_plugins\";i:16;s:10:\"manage_ftp\";i:17;s:12:\"manage_roles\";i:18;s:11:\"switch_user\";i:19;s:12:\"create_sites\";i:20;s:12:\"manage_sites\";i:21;s:12:\"update_sites\";i:22;s:12:\"delete_sites\";}",
                "role_key" => "super"
            ],
            [
                "role_name" => "Administrator",
                "role_perm" => "a:10:{i:0;s:12:\"access_admin\";i:1;s:12:\"create_posts\";i:2;s:12:\"manage_posts\";i:3;s:12:\"update_posts\";i:4;s:12:\"delete_posts\";i:5;s:13:\"publish_posts\";i:6;s:12:\"manage_media\";i:7;s:14:\"manage_options\";i:8;s:15:\"manage_settings\";i:9;s:14:\"manage_plugins\";}",
                "role_key" => "admin"
            ],
            [
                "role_name" => "Editor",
                "role_perm" => "a:7:{i:0;s:12:\"access_admin\";i:1;s:12:\"create_posts\";i:2;s:12:\"manage_posts\";i:3;s:12:\"update_posts\";i:4;s:12:\"delete_posts\";i:5;s:13:\"publish_posts\";i:6;s:12:\"manage_media\";}",
                "role_key" => "editor"
            ]
        ];

        $table->insert($data)->saveData();
        endif;

        // Migration for table site
        if (!$this->hasTable('site') && !$this->adapter->getOption('exclude')['site']) :
        $table = $this->table('site', ['id' => false, 'primary_key' => 'site_id']);
        $table
            ->addColumn('site_id', 'integer', ['identity' => true, 'limit' => MysqlAdapter::INT_BIG])
            ->addColumn('site_name', 'string', ['limit' => 191])
            ->addColumn('site_slug', 'string', ['limit' => 191])
            ->addColumn('site_domain', 'string', ['limit' => 191])
            ->addColumn('site_path', 'string', ['limit' => 191])
            ->addColumn('site_owner', 'integer', ['limit' => MysqlAdapter::INT_BIG])
            ->addColumn('site_status', 'string', ['limit' => 191])
            ->addColumn('site_registered', 'datetime', [])
            ->addColumn('site_modified', 'datetime', ['default' => '0000-00-00 00:00:00'])
            ->addIndex(['site_slug', 'site_domain', 'site_path'])
            ->addForeignKey('site_owner', 'user', 'user_id', ['constraint' => $prefix . 'site_site_owner', 'delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        $data = [
            [
                "site_name" => "Qubus Site",
                "site_slug" => "qubus_site",
                "site_domain" => "localhost",
                "site_path" => "/qubus/",
                "site_owner" => 1,
                "site_status" => "public",
                "site_registered" => $NOW,
            ]
        ];

        $table->insert($data)->saveData();
        endif;

        // Migration for table site
        if (!$this->hasTable('user') && !$this->adapter->getOption('exclude')['user']) :
        $table = $this->table('user', ['id' => false, 'primary_key' => 'user_id']);
        $table
            ->addColumn('user_id', 'integer', ['identity' => true, 'limit' => MysqlAdapter::INT_BIG])
            ->addColumn('user_login', 'string', ['limit' => 191])
            ->addColumn('user_fname', 'string', ['limit' => 191])
            ->addColumn('user_lname', 'string', ['limit' => 191])
            ->addColumn('user_email', 'string', ['limit' => 191])
            ->addColumn('user_pass', 'string', ['limit' => 191])
            ->addColumn('user_url', 'string', ['limit' => 191])
            ->addColumn('user_timezone', 'string', ['limit' => 191])
            ->addColumn('user_date_format', 'char', ['limit' => 22])
            ->addColumn('user_time_format', 'char', ['limit' => 22])
            ->addColumn('user_locale', 'char', ['limit' => 22])
            ->addColumn('user_activation_key', 'string', ['null' => true, 'limit' => 191])
            ->addColumn('user_addedby', 'integer', ['null' => true, 'limit' => MysqlAdapter::INT_BIG])
            ->addColumn('user_registered', 'datetime', [])
            ->addColumn('user_modified', 'datetime', ['null' => true])
            ->addIndex(['user_login'], ['unique' => true])
            ->addForeignKey('user_addedby', 'user', 'user_id', ['constraint' => $prefix . 'user_user_addedby', 'delete' => 'SET_NULL', 'update' => 'CASCADE'])
            ->create();

        $data = [
            [
                "user_login" => "qubus",
                "user_fname" => "Qubus",
                "user_lname" => "CMS",
                "user_email" => "qubus@ttcms.com",
                "user_pass" => '$2y$10$quJ8KUfpAz7ghUUWemS7reQCl7FO6imVUVr3QsIywF5MUkZIVAWL6',
                "user_timezone" => 'America/New_York',
                "user_date_format" => 'd F Y',
                "user_time_format" => 'h:i A',
                "user_addedby" => 1,
                "user_registered" => $NOW,
            ]
        ];

        $table->insert($data)->saveData();
        endif;

        // Migration for table usermeta
        if (!$this->hasTable('usermeta') && !$this->adapter->getOption('exclude')['usermeta']) :
        $table = $this->table('usermeta', ['id' => false, 'primary_key' => 'meta_id']);
        $table
            ->addColumn('meta_id', 'integer', ['identity' => true, 'limit' => MysqlAdapter::INT_BIG])
            ->addColumn('user_id', 'integer', ['limit' => MysqlAdapter::INT_BIG])
            ->addColumn('meta_key', 'string', ['limit' => 191])
            ->addColumn('meta_value', 'text', ['limit' => MysqlAdapter::TEXT_LONG])
            ->addIndex(['user_id', 'meta_key'], ['unique' => true])
            ->addForeignKey('user_id', 'user', 'user_id', ['constraint' => $prefix . 'usermeta_user_id', 'delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->create();

        $data = [
            [
                "user_id" => 1,
                "meta_key" => $prefix . "username",
                "meta_value" => "qubus"
            ],
            [
                "user_id" => 1,
                "meta_key" => $prefix . "fname",
                "meta_value" => "Qubus"
            ],
            [
                "user_id" => 1,
                "meta_key" => $prefix . "lname",
                "meta_value" => "CMS"
            ],
            [
                "user_id" => 1,
                "meta_key" => $prefix . "email",
                "meta_value" => "qubus@ttcms.com"
            ],
            [
                "user_id" => 1,
                "meta_key" => $prefix . "bio",
                "meta_value" => ""
            ],
            [
                "user_id" => 1,
                "meta_key" => $prefix . "role",
                "meta_value" => "1"
            ],
            [
                "user_id" => 1,
                "meta_key" => $prefix . "timezone",
                "meta_value" => "America/New_York"
            ],
            [
                "user_id" => 1,
                "meta_key" => $prefix . "date_format",
                "meta_value" => "d F Y"
            ],
            [
                "user_id" => 1,
                "meta_key" => $prefix . "time_format",
                "meta_value" => "h:i A"
            ],
            [
                "user_id" => 1,
                "meta_key" => $prefix . "locale",
                "meta_value" => "en"
            ],
            [
                "user_id" => 1,
                "meta_key" => $prefix . "status",
                "meta_value" => "A"
            ],
            [
                "user_id" => 1,
                "meta_key" => $prefix . "admin_layout",
                "meta_value" => "0"
            ],
            [
                "user_id" => 1,
                "meta_key" => $prefix . "admin_sidebar",
                "meta_value" => "0"
            ],
            [
                "user_id" => 1,
                "meta_key" => $prefix . "admin_skin",
                "meta_value" => "skin-purple-light"
            ]
        ];

        $table->insert($data)->saveData();
        endif;

        $this->execute("SET FOREIGN_KEY_CHECKS=1;");

        if (strpos($_SERVER['SERVER_SOFTWARE'], 'Apache') !== false && !file_exists('.htaccess')) {
            $f = fopen('.htaccess', 'w');
            fclose($f);

            $htaccess_file = <<<EOF
<IfModule mod_rewrite.c>
RewriteEngine On
Options All -Indexes

# Some hosts may require you to use the `RewriteBase` directive.
# If you need to use the `RewriteBase` directive, it should be the
# absolute physical path to the directory that contains this htaccess file.
#
# RewriteBase /

# Exclude assets, images and other directories from rewrite rules
RewriteRule ^(app|bin|languages|mu-plugins|plugins|private|static|vendor)($|/) - [L]
RewriteRule \.(jpg|jpeg|png|gif|ico|txt|xml|gz)$ - [L]

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . index.php [L]
</IfModule>

EOF;
            if (!file_exists('.htaccess')) {
                file_put_contents('.htaccess', $htaccess_file);
            }
        }

        if (!file_exists('config.php')) {
            copy('config.sample.php', 'config.php');
            $file = 'config.php';
            $config = file_get_contents($file);

            $config = str_replace('{cookies_secret_key}', $cookies, $config);
            $config = str_replace('{nonce_salt}', $nonce, $config);
            $config = str_replace('{release}', trim(file_get_contents('RELEASE')), $config);
            $config = str_replace('{datenow}', $NOW, $config);
            $config = str_replace('{driver}', $driver, $config);
            $config = str_replace('{prefix}', $prefix, $config);
            $config = str_replace('{charset}', $charset, $config);
            $config = str_replace('{collation}', $collation, $config);
            $config = str_replace('{port}', $port, $config);
            $config = str_replace('{hostname}', $host, $config);
            $config = str_replace('{database}', $name, $config);
            $config = str_replace('{username}', $user, $config);
            $config = str_replace('{password}', $pass, $config);
            $config = str_replace('{url}', $url, $config);
            $config = str_replace('{path}', $path, $config);

            file_put_contents($file, $config);
        }
    }
}
