<?php
/**
 * Qubus CMS Deprecated Functions
 *
 * @license GPLv3
 *
 * @since       1.0.0
 * @package     Qubus CMS
 * @author      Joshua Parker <josh@joshuaparker.blog>
 */

 /**
  * Check whether variable is an Qubus CMS Error.
  *
  * Returns true if $object is an object of the `\TriTan\Error` class.
  *
  * @file app/functions/deprecated.php
  *
  * @deprecated since release 1.0.0
  * @param mixed $object Check if unknown variable is an `\TriTan\Error` object.
  * @return bool True, if `\TriTan\Error`. False, if not `\TriTan\Error`.
  */
 function is_ttcms_error($object)
 {
     _deprecated_function(__FUNCTION__, '1.0.0', 'check_qubus_error');
     return check_qubus_error($object);
 }

 /**
  * Check whether variable is an Qubus CMS Exception.
  *
  * Returns true if $object is an object of the `\TriTan\Exception\BaseException` class.
  *
  * @file app/functions/deprecated.php
  *
  * @deprecated since release 1.0.0
  * @param mixed $object Check if unknown variable is an `\\TriTan\\Exception\\BaseException` object.
  * @return bool True, if `\\TriTan\\Exception\\BaseException`. False, if not `\\TriTan\\Exception\\BaseException`.
  */
 function is_ttcms_exception($object)
 {
     _deprecated_function(__FUNCTION__, '1.0.0', 'check_qubus_exception');
     return check_qubus_exception($object);
 }
