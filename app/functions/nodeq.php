<?php
use TriTan\Common\Mailer;
use TriTan\NodeQ;
use TriTan\Common\Date;
use TriTan\Common\Container as c;
use Qubus\Hooks\ActionFilterHook;
use Qubus\Exception\Exception;
use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use Cascade\Cascade;

/**
 * Qubus CMS NodeQ Functions
 *
 * @license GPLv3
 *
 * @since 1.0.0
 * @package Qubus CMS
 * @author Joshua Parker <josh@joshuaparker.blog>
 */

/**
 * Generates the encryption node if it does not exist.
 *
 * @file app/functions/nodeq.php
 *
 * @since 1.0.0
 * @access private
 * @return bool
 */
function generate_encryption_node()
{
    $qudb = app()->qudb;
    $nodeq = new NodeQ();

    $encrypt = $nodeq->table($qudb->prefix . 'encryption');

    if ($encrypt->count() > 0) {
        return false;
    }

    $encrypt->begin();
    try {
        $key = Key::createNewRandomKey();
        $encrypt->insert([
            'encryption_key' => (string) $key->saveToAsciiSafeString(),
            'encryption_created_at' => (string) (new Date())->current('db')
        ]);
        $encrypt->commit();
    } catch (Exception $ex) {
        $encrypt->rollback();
        Cascade::getLogger('error')->error(
            sprintf(
                'SQLSTATE[%s]: %s',
                $ex->getCode(),
                $ex->getMessage()
            ),
            [
                'NodeQ Functions' => 'generate_php_encryption'
            ]
        );
    }
}

/**
 * Login Details Email
 *
 * Function used to send login details to new
 * user.
 *
 * @file app/functions/nodeq.php
 *
 * @since 1.0.0
 */
function ttcms_nodeq_login_details()
{
    $qudb = app()->qudb;
    $nodeq = new NodeQ();
    $option = new \TriTan\Common\Options\Options(
        new TriTan\Common\Options\OptionsMapper(
            $qudb,
            new TriTan\Common\Context\HelperContext()
        )
    );

    $sql = $nodeq->table($qudb->prefix . 'login')->where('login_sent', (int) 0)->get();
    $decrypt = $nodeq->table($qudb->prefix . 'encryption')->where('encryption_id', 1)->first();

    if (count(array_filter($sql)) == 0) {
        foreach ($sql as $r) {
            $nodeq->table($qudb->prefix . 'login')->where('login_id', (int) esc_html($r['login_id']))->delete();
        }
    }

    if (count($sql) > 0) {
        foreach ($sql as $r) {
            $site_name = $option->read('sitename');
            $user = get_userdata((int) $r['login_userid']);
            try {
                $password = Crypto::decrypt(
                    $r['login_userpass'],
                    Key::loadFromAsciiSafeString($decrypt['encryption_key'])
                );
            } catch (Defuse\Crypto\Exception\BadFormatException $ex) {
                Cascade::getLogger('error')->error(
                    sprintf(
                        'CRYPTOFORMAT[%s]: %s',
                        $ex->getCode(),
                        $ex->getMessage()
                    )
                );
            } catch (Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $e) {
                Cascade::getLogger('error')->error(
                    sprintf(
                        'CRYPTOKEY[%s]: %s',
                        $ex->getCode(),
                        $ex->getMessage()
                    )
                );
            } catch (Exception $ex) {
                Cascade::getLogger('error')->error(
                    sprintf(
                        'CRYPTO[%s]: %s',
                        $ex->getCode(),
                        $ex->getMessage()
                    )
                );
            }

            $message = esc_html__('Hi there,') . "<br />";
            $message .= "<p>" . sprintf(esc_html__("Welcome to %s! Here's how to log in: "), $site_name);
            $message .= site_url() . "</p>";
            $message .= sprintf(esc_html__('Username: %s'), $user->getLogin()) . "<br />";
            $message .= sprintf(esc_html__('Password: %s'), $password) . "<br />";
            $message .= "<p>" . sprintf(
                esc_html__(
                    'If you have any problems, please contact us at <a href="mailto:%s">%s</a>.'
                ),
                $option->read('admin_email'),
                $option->read('admin_email')
            ) . "</p>";

            $message = process_email_html($message, esc_html__('New Account'));
            $headers[] = sprintf("From: %s <auto-reply@%s>", $site_name, get_domain_name());
            if (!function_exists('ttcms_mail_send')) {
                $headers[] = 'Content-Type: text/html; charset="UTF-8"';
                $headers[] = sprintf("X-Mailer: Qubus CMS %s", CURRENT_RELEASE);
            }
            try {
                (new Mailer(ActionFilterHook::getInstance()))->mail(
                    $user->getEmail(),
                    sprintf(
                        esc_html__('[%s] New Account'),
                        $site_name
                    ),
                    $message,
                    $headers
                );
            } catch (\PHPMailer\PHPMailer\Exception $e) {
                ttcms()->obj['flash']->error($e->getMessage());
            }

            $upd = $nodeq->table($qudb->prefix . 'login')->where('login_id', (int) esc_html($r['login_id']));
            $upd->update([
                'login_sent' => 1
            ]);
        }
    }
}

/**
 * Reset Password Email
 *
 * Function used to send reset password to a user.
 *
 * @file app/functions/nodeq.php
 *
 * @since 1.0.0
 */
function ttcms_nodeq_reset_password()
{
    $qudb = app()->qudb;
    $nodeq = new NodeQ();
    $option = new \TriTan\Common\Options\Options(
        new TriTan\Common\Options\OptionsMapper(
            $qudb,
            new TriTan\Common\Context\HelperContext()
        )
    );

    $sql = $nodeq->table($qudb->prefix . 'password_reset')->where('password_reset_sent', (int) 0)->get();
    $decrypt = $nodeq->table($qudb->prefix . 'encryption')->where('encryption_id', 1)->first();

    if (count($sql) == 0) {
        foreach ($sql as $r) {
            $nodeq->table($qudb->prefix . 'password_reset')
              ->where('password_reset_id', (int) esc_html($r['password_reset_id']))
              ->delete();
        }
    }

    if (count($sql) > 0) {
        foreach ($sql as $r) {
            $site_name = ttcms()->obj['option']->read('sitename');
            $user = get_userdata((int) $r['password_reset_userid']);
            try {
                $password = Crypto::decrypt(
                    $r['password_reset_userpass'],
                    Key::loadFromAsciiSafeString($decrypt['encryption_key'])
                );
            } catch (Defuse\Crypto\Exception\BadFormatException $ex) {
                Cascade::getLogger('error')->error(
                    sprintf(
                        'CRYPTOFORMAT[%s]: %s',
                        $ex->getCode(),
                        $ex->getMessage()
                    )
                );
            } catch (Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $e) {
                Cascade::getLogger('error')->error(
                    sprintf(
                        'CRYPTOKEY[%s]: %s',
                        $ex->getCode(),
                        $ex->getMessage()
                    )
                );
            } catch (Exception $ex) {
                Cascade::getLogger('error')->error(
                    sprintf(
                        'CRYPTO[%s]: %s',
                        $ex->getCode(),
                        $ex->getMessage()
                    )
                );
            }

            $message = esc_html__('Hi there,') . "<br />";
            $message .= "<p>" . sprintf(esc_html__("Your password has been reset for %s: "), $site_name);
            $message .= site_url() . "</p>";
            $message .= sprintf(esc_html__('Username: %s'), $user->getLogin()) . "<br />";
            $message .= sprintf(esc_html__('Password: %s'), $password) . "<br />";
            $message .= "<p>" . sprintf(
                esc_html__(
                    'If you have any problems, please contact us at <a href="mailto:%s">%s</a>.'
                ),
                ttcms()->obj['option']->read('admin_email'),
                ttcms()->obj['option']->read('admin_email')
            ) . "</p>";

            $message = process_email_html($message, esc_html__('Password Reset'));
            $headers[] = sprintf("From: %s <auto-reply@%s>", $site_name, get_domain_name());
            if (!function_exists('ttcms_mail_send')) {
                $headers[] = 'Content-Type: text/html; charset="UTF-8"';
                $headers[] = sprintf("X-Mailer: Qubus CMS %s", CURRENT_RELEASE);
            }
            try {
                (new Mailer(ActionFilterHook::getInstance()))->mail(
                    $user->getEmail(),
                    sprintf(
                        esc_html__('[%s] Password Reset'),
                        $site_name
                    ),
                    $message,
                    $headers
                );
            } catch (\PHPMailer\PHPMailer\Exception $e) {
                ttcms()->obj['flash']->error($e->getMessage());
            }

            $upd = $nodeq->table($qudb->prefix . 'password_reset')->where('password_reset_id', (int) esc_html($r['password_reset_id']));
            $upd->update([
                'password_reset_sent' => 1
            ]);
        }
    }
}
