<?php
use TriTan\Common\Password\PasswordGenerate;
use TriTan\Common\Password\PasswordHash;
use TriTan\Common\Uri;
use TriTan\Csrf\Nonce;
use Qubus\Hooks\ActionFilterHook;

$qudb = app()->qudb;

/**
 * Before router checks to make sure the logged in user
 * us allowed to access admin.
 */
$app->before('GET|POST', '/login', function () {
    ActionFilterHook::getInstance()->{'doAction'}('before_router_login');
});

$app->group('/login', function () use ($app) {
    /**
     * Before route check.
     */
    $app->before('GET|POST', '/', function () use ($app) {
        if (is_user_logged_in()) {
            $redirect_to = (
                $app->req->get['redirect_to'] != null ? $app->req->get['redirect_to'] : admin_url()
            );
            (new Uri(ActionFilterHook::getInstance()))->{'redirect'}($redirect_to);
        }

        if (isset($app->req->post['__ttcmsnonce']) && !Nonce::verify($app->req->post['__ttcmsnonce'], 'ttcms-login')) {
            ttcms()->obj['flash']->{'error'}(
                esc_html__('Invalid or missing CSRF token.'),
                $app->req->server['HTTP_REFERER']
            );
            exit();
        }

        /**
         * Fires before a user has logged in.
         *
         * @since 1.0.0
         */
        ActionFilterHook::getInstance()->{'doAction'}('ttcms_login');
    });

    $app->match('GET|POST', '/', function () use ($app) {
        if ($app->req->isPost()) {
            throttle_client([
              'throttleKey' => 'adminLoginKey',
              'id'        	=> 'admin-login',
              'timeout'   	=> ActionFilterHook::getInstance()->{'applyFilter'}('throttle_client_timeout', 360),	// Throttle user for 360 seconds
              'passes'    	=> ActionFilterHook::getInstance()->{'applyFilter'}('throttle_client_passes', 5),	// if they attempt this action MORE than 5 times
              'interval'  	=> ActionFilterHook::getInstance()->{'applyFilter'}('throttle_client_interval', 60),	// within 60 seconds
              'throttled' 	=> function ($seconds) { // They've been throttled
                  ttcms_die(
                      sprintf(
                          t__('Login attempts exceeded. Try again in <strong>%s</strong>.'),
                          seconds_to_minutes($seconds)
                      )
                  );
              }
          ]);
            /**
             * Filters where the admin should be redirected after successful login.
             */
            $login_link = ActionFilterHook::getInstance()->{'applyFilter'}(
                'admin_login_redirect',
                admin_url()
            );
            /**
             * This function is documented in app/functions/auth-function.php.
             *
             * @since 1.0.0
             */
            ttcms_authenticate_user(
                $app->req->post['user_login'],
                $app->req->post['user_pass'],
                $app->req->post['rememberme']
            );

            (new Uri(ActionFilterHook::getInstance()))->{'redirect'}($login_link);
        }

        $app->foil->render(
            'main::auth/index',
            [
                'title' => t__('Login', 'tritan-cms')
            ]
        );
    });
});

$app->before('GET|POST', '/logout/', function () {
    ActionFilterHook::getInstance()->{'doAction'}('before_router_logout');
});

$app->get('/logout/', function () use ($app) {
    if (strpos($app->req->server['HTTP_REFERER'], 'admin') !== false) {
        $logout_link = ActionFilterHook::getInstance()->{'applyFilter'}(
            'user_logout_redirect',
            login_url()
        );
    } else {
        $logout_link = ActionFilterHook::getInstance()->{'applyFilter'}(
            'admin_logout_redirect',
            $app->req->server['HTTP_REFERER']
        );
    }

    /**
     * This function is documented in app/functions/auth-function.php.
     *
     * @since 1.0.0
     */
    ttcms_clear_auth_cookie();

    /**
     * Fires after a user has logged out.
     *
     * @since 1.0.0
     */
    ActionFilterHook::getInstance()->{'doAction'}('ttcms_logout');

    (new Uri(ActionFilterHook::getInstance()))->{'redirect'}($logout_link);
});

$app->before('GET|POST', '/reset-password/', function () use ($app) {
    ActionFilterHook::getInstance()->{'doAction'}('before_router_reset_password');

    if (isset($app->req->post['__ttcmsnonce']) && !Nonce::verify($app->req->post['__ttcmsnonce'], 'reset-password')) {
        $app->res->_format('json', 403);
        exit();
    }
});

$app->match('GET|POST', '/reset-password/', function () use ($app, $qudb) {
    if ($app->req->isPost()) {
        $user = $qudb->getRow(
            $qudb->prepare(
                "SELECT user_id FROM {$qudb->base_prefix}user WHERE user_email = ?",
                [
                    $app->req->post['email']
                ]
            ),
            ARRAY_A
        );

        if ((int) esc_html($user['user_id']) >= 1) {
            $password = (new PasswordGenerate(ActionFilterHook::getInstance()))->{'generate'}();

            $qudb->getConnection()->throwTransactionExceptions();
            try {
                $qudb->transaction(function ($qudb) use ($user, $password) {
                    $qudb
                        ->update($qudb->base_prefix . 'user')
                        ->where('user_id')->is((int) esc_html($user['user_id']))
                        ->set([
                            'user_pass' => (new PasswordHash(ActionFilterHook::getInstance()))->{'hash'}($password)
                        ]);
                });

                /**
                 * This action fires after user's password has been reset.
                 *
                 * @since 1.0.0
                 * @param array $user       User data array.
                 * @param string $password  Plaintext password.
                 */
                ActionFilterHook::getInstance()->{'doAction'}(
                    'reset_password_route',
                    $user,
                    $password
                );

                ttcms()->obj['flash']->{'success'}(
                    t__(
                        'A new password was sent to your email. May take a few minutes to arrive, so please be patient',
                        'tritan-cms'
                    ),
                    $app->req->server['HTTP_REFERER']
                );
            } catch (\PDOException $ex) {
                Cascade::getLogger('error')->{'error'}(
                    sprintf(
                        'SQLSTATE[%s]: %s',
                        $ex->getCode(),
                        $ex->getMessage()
                    ),
                    [
                        'Index Router' => '/reset-password/'
                    ]
                );

                ttcms()->obj['flash']->{'error'}(
                    $ex->getMessage(),
                    $app->req->server['HTTP_REFERER']
                );
            }
        } else {
            ttcms()->obj['flash']->{'error'}(
                t__('The email you entered was incorrect.', 'tritan-cms'),
                $app->req->server['HTTP_REFERER']
            );
        }
    }

    $app->foil->render(
        'main::auth/reset',
        [
            'title' => t__('Reset Password', 'tritan-cms')
        ]
    );
});

/**
 * If the requested page does not exist,
 * return a 404.
 */
$app->setError(function () use ($app) {
    $app->res->_format('json', 404);
});
