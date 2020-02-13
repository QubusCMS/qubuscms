<?php
namespace TriTan;

/**
 * NodeQ Class
 *
 * @license GPLv3
 *
 * @since 1.0.0
 * @package Qubus CMS
 * @author Joshua Parker <josh@joshuaparker.blog>
 */
class NodeQ
{

    /**
     * Table name.
     *
     * @var string
     */
    public $name;

    /**
     *
     * @var array
     */
    public $options = [];

    /**
     * Constructor.
     */
    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    /**
     * Database table.
     *
     * @param string $name NodeQ table name.
     * @param array $options
     * @return \TriTan\Laci\Collection Database object, false otherwise.
     */
    public function table($name)
    {
        $this->name = $name;
        return new Laci\Collection(TTCMS_NODEQ_PATH . $this->name . '.json', $this->options);
    }

    /**
     * Checks if a variable is null. If not null, check if integer or string.
     *
     * @since 1.0.0
     * @param string|int $var   Variable to check.
     * @return string|int|null Returns null if empty otherwise a string or an integer.
     */
    public function ifNull($var)
    {
        $_var = ctype_digit($var) ? (int) $var : (string) $var;
        return $var === '' ? null : $_var;
    }
}
