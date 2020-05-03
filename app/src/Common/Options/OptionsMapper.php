<?php
namespace TriTan\Common\Options;

use TriTan\Interfaces\Options\OptionsMapperInterface;
use TriTan\Interfaces\Database\DatabaseInterface;
use TriTan\Interfaces\ContextInterface;
use Cascade\Cascade;
use \PDOException;

final class OptionsMapper implements OptionsMapperInterface
{
    protected $qudb;

    protected $context;

    public function __construct(DatabaseInterface $qudb, ContextInterface $context)
    {
        $this->qudb = $qudb;
        $this->context = $context;
    }

    /**
     * Add an option to the table
     */
    public function create($name, $value = '')
    {
        // Make sure the option doesn't already exist
        if ($this->exists($name)) {
            return;
        }

        $_value = $this->context->obj['serializer']->serialize($value);

        $this->context->obj['cache']->delete($name, 'option');

        $this->context->obj['hook']->doAction('add_option', $name, $_value);

        $option_value = $_value;

        $this->qudb->getConnection()->throwTransactionExceptions();
        try {
            $this->qudb->transaction(function () use ($name, $option_value) {
                $this->qudb
                    ->insert([
                        'option_key' => (string) $name,
                        'option_value' => $option_value
                    ])
                    ->into($this->qudb->prefix . 'option');
            });

            $this->qudb->option[$name] = $value;
            return true;
        } catch (PDOException $ex) {
            Cascade::getLogger('error')->error(
                sprintf(
                    'OPTIONSMAPPER[%s]: Error: %s',
                    $ex->getCode(),
                    $ex->getMessage()
                ),
                [
                    'OptionsMapper' => 'OptionsMapper::create'
                ]
            );
        }
    }

    /**
     * Read an option from options_meta.
     * Return value or $default if not found
     */
    public function read($option_key, $default = false)
    {
        $option_key = preg_replace('/\s/', '', $option_key);
        if (empty($option_key)) {
            return false;
        }

        /**
         * Filter the value of an existing option before it is retrieved.
         *
         * The dynamic portion of the hook name, `$option_key`, refers to the option_key name.
         *
         * Passing a truthy value to the filter will short-circuit retrieving
         * the option value, returning the passed value instead.
         *
         * @since 1.0.0
         * @param bool|mixed pre_option_{$option_key} Value to return instead of the option value.
         *                                            Default false to skip it.
         * @param string $option_key Meta key name.
         */
        $pre = $this->context->obj['hook']->applyFilter('pre_option_' . $option_key, false);

        if (false !== $pre) {
            return $pre;
        }

        if (!isset($this->qudb->option[$option_key])) {
            try {
                $result = $this->context->obj['cache']->read($option_key, 'option');

                if (empty($result)) {
                    $result = $this->qudb->getVar(
                        $this->qudb->prepare(
                            "SELECT option_value FROM {$this->qudb->prefix}option WHERE option_key = ?",
                            [
                                $option_key
                            ]
                        )
                    );
                    $this->context->obj['cache']->create($option_key, $result, 'option');
                }
            } catch (PDOException $ex) {
                Cascade::getLogger('error')->error(
                    sprintf(
                        'OPTIONSMAPPER[%s]: Error: %s',
                        $ex->getCode(),
                        $ex->getMessage()
                    ),
                    [
                        'OptionsMapper' => 'OptionsMapper::read'
                    ]
                );
            }

            if (!$result) {
                return false;
            }

            if (!empty($result)) {
                $value = $this->context->obj['html']->purify($result);
            } else {
                $value = $this->context->obj['html']->purify($default);
            }
            $this->qudb->option[$option_key] = $this->context->obj['serializer']->unserialize($value);
        }
        /**
         * Filter the value of an existing option.
         *
         * The dynamic portion of the hook name, `$option_key`, refers to the option name.
         *
         * @since 1.0.0
         * @param mixed $value Value of the option. If stored serialized, it will be
         *                     unserialized prior to being returned.
         * @param string $option_key Option name.
         */
        return $this->context->obj['hook']->applyFilter('get_option_' . $option_key, $this->qudb->option[$option_key]);
    }

    /**
     * Update (add if doesn't exist) an option to options_meta
     */
    public function update($option_key, $newvalue)
    {
        $oldvalue = $this->read($option_key);

        // If the new and old values are the same, no need to update.
        if ($newvalue === $oldvalue) {
            return false;
        }

        if (!$this->exists($option_key)) {
            $this->create($option_key, $newvalue);
            return true;
        }

        $_newvalue = $this->context->obj['serializer']->serialize($newvalue);

        $this->context->obj['cache']->delete($option_key, 'option');

        $this->context->obj['hook']->doAction('update_option', $option_key, $oldvalue, $newvalue);

        $option_value = $_newvalue;

        $this->qudb->getConnection()->throwTransactionExceptions();
        try {
            $this->qudb->transaction(function () use ($option_key, $option_value) {
                $result = $this->qudb
                    ->update($this->qudb->prefix . 'option')
                    ->where('option_key')->is($option_key)
                    ->set([
                        'option_value' => $option_value
                    ]);
            });

            if (@count($result) > 0) {
                $this->qudb->option[$option_key] = $newvalue;
            }
        } catch (PDOException $ex) {
            Cascade::getLogger('error')->error(
                sprintf(
                    'OPTIONSMAPPER[%s]: Error: %s',
                    $ex->getCode(),
                    $ex->getMessage()
                ),
                [
                    'OptionsMapper' => 'OptionsMapper::update'
                ]
            );
        }
    }

    /**
     * Delete an option from the table
     */
    public function delete($name)
    {
        $results = $this->qudb->getRow(
            $this->qudb->prepare(
                "SELECT * FROM {$this->qudb->prefix}option WHERE option_key = ?",
                [
                    $name
                ]
            ),
            ARRAY_A
        );

        if (is_null($results) || !$results) {
            return false;
        }

        $this->context->obj['cache']->delete($name, 'option');

        $this->context->obj['hook']->doAction('delete_option', $name);

        $this->qudb->getConnection()->throwTransactionExceptions();
        try {
            $this->qudb->transaction(function () use ($results) {
                $this->qudb
                    ->from($this->qudb->prefix . 'option')
                    ->where('option_id')->is((int) esc_html($results['option_id']))
                    ->delete();
            });

            return true;
        } catch (PDOException $ex) {
            Cascade::getLogger('error')->error(
                sprintf(
                    'OPTIONSMAPPER[%s]: Error: %s',
                    $ex->getCode(),
                    $ex->getMessage()
                ),
                [
                    'OptionsMapper' => 'OptionsMapper::delete'
                ]
            );
        }
    }

    /**
     * Checks if a key exists in the option table.
     *
     * @since 1.0.0
     * @param string $option_key Key to check against.
     * @return bool
     */
    public function exists($option_key) : bool
    {
        $key = $this->qudb->getRow(
            $this->qudb->prepare(
                "SELECT option_id FROM {$this->qudb->prefix}option WHERE option_key = ?",
                [
                    $option_key
                ]
            ),
            ARRAY_A
        );

        return (int) $key['option_id'] > 0;
    }
}
