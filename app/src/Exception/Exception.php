<?php
namespace TriTan\Exception;

/**
 * Tritan CMS Exception Class
 *
 * This extends the default `LitenException` class to allow converting
 * exceptions to and from `Error` objects.
 *
 * Unfortunately, because an `Error` object may contain multiple messages and error
 * codes, only the first message for the first error code in the instance will be
 * accessible through the exception's methods.
 *
 * @since       1.0.0
 * @package     TriTan CMS
 * @author      Joshua Parker <josh@joshuaparker.blog>
 */
class Exception extends \TriTan\Exception\BaseException
{
}
