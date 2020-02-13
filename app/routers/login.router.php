<?php
use TriTan\Common\Uri;
use TriTan\Csrf\Nonce;
use Qubus\Hooks\ActionFilterHook;

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
        throttle_client([
            'throttleKey' => 'adminLoginKey',
            'id'        	=> 'admin-login',
            'timeout'   	=> ActionFilterHook::getInstance()->{'applyFilter'}('throttle_client_timeout', 360),	// Throttle user for 360 seconds
            'passes'    	=> ActionFilterHook::getInstance()->{'applyFilter'}('throttle_client_passes', 5),	// if they attempt this action MORE than 5 times
            'interval'  	=> ActionFilterHook::getInstance()->{'applyFilter'}('throttle_client_interval', 60),	// within 60 seconds
            'throttled' 	=> function ($seconds) { // They've been throttled
                ttcms_die(
                    sprintf(
                        t__(
                            'Login attempts exceeded. Try again in <strong>%s</strong>.'
                        ),
                        seconds_to_minutes($seconds)
                    )
                );
            }
        ]);

        if ($app->req->isPost()) {
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
            'main::login/index',
            [
                'title' => t__('Login', 'qubus-cms')
            ]
        );
    });
});
