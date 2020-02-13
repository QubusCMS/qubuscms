<?php
use Spatie\ImageOptimizer\OptimizerChainFactory;

/**
 * Qubus CMS Global Scope Functions.
 *
 * @license GPLv3
 *
 * @since 1.0.0
 * @package Qubus CMS
 * @author Joshua Parker <josh@joshuaparker.blog>
 */

/**
 * Sets up PHPMailer global scope.
 *
 * @file app/functions/dependency.php
 *
 * @since 1.0.0
 * @param bool $bool Set whether to use exceptions for error handling.
 *                   Default: true.
 */
function _ttcms_phpmailer($bool = true)
{
    $phpMailer = new \PHPMailer\PHPMailer\PHPMailer($bool);
    return $phpMailer;
}

/**
 * Sets up Qubus CMS Logger global scope.
 *
 * @file app/functions/dependency.php
 *
 * @since 1.0.0
 */
function _ttcms_logger()
{
    $logger = new \TriTan\Logger();
    return $logger;
}

/**
 * Sets up Qubus CMS Flash Messages global scope.
 *
 * @file app/functions/dependency.php
 *
 * @since 1.0.0
 */
function _ttcms_flash()
{
    $flash = new TriTan\Common\FlashMessages();
    return $flash;
}

/**
 * Sets up random number and string generator global scope.
 *
 * @file app/functions/dependency.php
 *
 * @since 1.0.0
 * @return string
 */
function _ttcms_random_lib()
{
    $factory = new \RandomLib\Factory;
    $generator = $factory->getGenerator(new \SecurityLib\Strength(\SecurityLib\Strength::MEDIUM));
    return $generator;
}

/**
 * Image optimizer.
 *
 * @file app/functions/dependency.php
 *
 * @since 1.0.0
 * @param string $pathToImage       Path to original image.
 * @param string $pathToOptimized  Path to where optimized image should be saved.
 * @return string Optimized image.
 */
function _ttcms_image_optimizer($pathToImage, $pathToOptimized)
{
    $optimizerChain = OptimizerChainFactory::create();
    return $optimizerChain->setTimeout(30)->optimize($pathToImage, $pathToOptimized);
}

function post_query()
{
    return new \TriTan\Common\Post\PostQuery(['posttype__in' => 'post']);
}
