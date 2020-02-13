<?php
use TriTan\Common\Password\PasswordGenerate;
use TriTan\Common\Password\PasswordHash;
use TriTan\Common\Uri;
use TriTan\Csrf\Nonce;
use Qubus\Hooks\ActionFilterHook;

$qudb = app()->qudb;

ActionFilterHook::getInstance()->{'doAction'}('maintenance_mode', $app);

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

$app->post('/reset-password/', function () use ($app, $qudb) {
    if (isset($app->req->post['__ttcmsnonce']) && !Nonce::verify($app->req->post['__ttcmsnonce'], 'reset-password')) {
        $app->res->_format('json', 403);
        exit();
    }

    $user = $qudb->getRow(
        $qudb->prepare(
            "SELECT user_id FROM {$qudb->base_prefix}user WHERE user_login = ? AND user_email = ?",
            [
                $app->req->post['username'],
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
                t__('A new password was sent to your email. May take a few minutes to arrive, so please be patient', 'qubus-cms'),
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
            t__('The username or email you entered was incorrect.', 'qubus-cms'),
            $app->req->server['HTTP_REFERER']
        );
    }
});

/**
 * If the requested page does not exist,
 * return a 404.
 */
$app->setError(function () use ($app) {
    $app->res->_format('json', 404);
});
