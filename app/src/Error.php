<?php
namespace TriTan;

/**
 * Error API: Error Class
 *
 * Based on WordPress error API. Container for checking for Qubus CMS
 * errors and error messages. Return Error and use {@link is_ttcms_error()} to
 * check if this class is returned. Many core Qubus CMS functions pass this
 * class in the event of an error and if not handled properly will result in
 * code errors.
 *
 * @since       1.0.0
 * @package     Qubus CMS
 * @author      Joshua Parker <josh@joshuaparker.blog>
 */
class Error
{

    /**
     * Stores the list of errors.
     *
     * @since 1.0.0
     * @var array
     */
    public $errors = [];

    /**
     * Stores the list of data for error codes.
     *
     * @since 1.0.0
     * @var array
     */
    public $error_data = [];

    /**
     * Initialize the error.
     *
     * If `$code` is empty, the other parameters will be ignored.
     * When `$code` is not empty, `$message` will be used even if
     * it is empty. The `$data` parameter will be used only if it
     * is not empty.
     *
     * Though the class is constructed with a single error code and
     * message, multiple codes can be added using the `add()` method.
     *
     * @since 1.0.0
     * @param string|int $code Error code
     * @param string $message Error message
     * @param mixed $data Optional. Error data.
     */
    public function __construct($code = '', $message = '', $data = '')
    {
        if (empty($code)) {
            return;
        }

        $this->errors[$code][] = $message;

        if (!empty($data)) {
            $this->error_data[$code] = $data;
        }
    }

    /**
     * Retrieve all error codes.
     *
     * @since 1.0.0
     * @return array List of error codes, if available.
     */
    public function getErrorCodes()
    {
        if (empty($this->errors)) {
            return [];
        }

        return array_keys($this->errors);
    }

    /**
     * Retrieve first error code available.
     *
     * @since 1.0.0
     * @return string|int Empty string, if no error codes.
     */
    public function getErrorCode()
    {
        $codes = $this->getErrorCodes();

        if (empty($codes)) {
            return '';
        }

        return $codes[0];
    }

    /**
     * Retrieve all error messages or error messages matching code.
     *
     * @since 1.0.0
     * @param string|int $code Optional. Retrieve messages matching code, if exists.
     * @return array Error strings on success, or empty array on failure (if using code parameter).
     */
    public function getErrorMessages($code = '')
    {
        // Return all messages if no code specified.
        if (empty($code)) {
            $all_messages = [];
            foreach ((array) $this->errors as $code => $messages) {
                $all_messages = array_merge($all_messages, $messages);
            }
            return $all_messages;
        }

        if (isset($this->errors[$code])) {
            return $this->errors[$code];
        } else {
            return [];
        }
    }

    /**
     * Get single error message.
     *
     * This will get the first message available for the code. If no code is
     * given then the first code available will be used.
     *
     * @since 1.0.0
     * @param string|int $code Optional. Error code to retrieve message.
     * @return string
     */
    public function getErrorMessage($code = '')
    {
        if (empty($code)) {
            $code = $this->getErrorCode();
        }
        $messages = $this->getErrorMessages($code);
        if (empty($messages)) {
            return '';
        }
        return $messages[0];
    }

    /**
     * Retrieve error data for error code.
     *
     * @since 1.0.0
     * @param string|int $code Optional. Error code.
     * @return mixed Error data, if it exists.
     */
    public function getErrorData($code = '')
    {
        if (empty($code)) {
            $code = $this->getErrorCode();
        }
        if (isset($this->error_data[$code])) {
            return $this->error_data[$code];
        }
    }

    /**
     * Add an error or append additional message to an existing error.
     *
     * @since 1.0.0
     * @param string|int $code Error code.
     * @param string $message Error message.
     * @param mixed $data Optional. Error data.
     */
    public function add($code, $message, $data = '')
    {
        $this->errors[$code][] = $message;
        if (!empty($data)) {
            $this->error_data[$code] = $data;
        }
    }

    /**
     * Add data for error code.
     *
     * The error code can only contain one error data.
     *
     * @since 1.0.0
     * @param mixed $data Error data.
     * @param string|int $code Error code.
     */
    public function addData($data, $code = '')
    {
        if (empty($code)) {
            $code = $this->getErrorCode();
        }

        $this->error_data[$code] = $data;
    }

    /**
     * Removes the specified error.
     *
     * This function removes all error messages associated with the specified
     * error code, along with any error data for that code.
     *
     * @since 1.0.0
     * @param string|int $code Error code.
     */
    public function remove($code)
    {
        unset($this->errors[$code]);
        unset($this->error_data[$code]);
    }
}
