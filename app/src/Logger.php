<?php
namespace TriTan;

use TriTan\Common\Container as c;
use TriTan\Exception;
use Qubus\Hooks\ActionFilterHook as hook;
use Cascade\Cascade;

/**
 * Event Logger for Errors and Activity.
 *
 * @license GPLv3
 *
 * @since 1.0.0
 * @package TriTan CMS
 * @author Joshua Parker <josh@joshuaparker.blog>
 */
class Logger
{

    /**
     * Application object.
     * @var type
     */
    public $app;

    public $hook;

    public $db;

    public function __construct()
    {
        $this->app = \Liten\Liten::getInstance();
        $this->hook = hook::getInstance();
    }

    /**
     * Writes a log to the log table in the database.
     *
     * @since 1.0.0
     */
    public function writeLog($action, $process, $record, $uname)
    {
        $create = date("Y-m-d H:i:s", time());
        $current_date = strtotime($create);
        /* 20 days after creation date */
        $expire = date("Y-m-d H:i:s", $current_date += 1728000);

        $expires_at = $this->hook->applyFilter('activity_log_expires', $expire);

        $this->db->beginTransaction();
        try {
            $this->db->insert("{$this->db->site_prefix}activity", [
                'action' => (string) $action,
                'process' => (string) $process,
                'record' => (string) $record,
                'uname' => (string) $uname,
                'created_at' => (string) $create,
                'expires_at' => (string) $expires_at
            ]);

            $this->db->commit();
        } catch (Exception $ex) {
            $this->db->rollback();
            Cascade::getLogger('error')->error($ex->getMessage());
            c::getInstance()->get('context')->obj['flash']->error(c::getInstance()->get('context')->obj['flash']->notice(409));
        }
    }

    /**
     * Purges audit trail logs that are older than 30 days old.
     *
     * @since 1.0.0
     */
    public function purgeActivityLog()
    {
        $log_count = $this->db->getVar(
            $this->db->prepare(
                "SELECT COUNT(*) FROM {$this->db->site_prefix}activity WHERE expires_at <= ?",
                [
                    date('Y-m-d H:i:s', time())
                ]
            )
        );

        if ($log_count > 0) {
            $this->db->beginTransaction();
            try {
                $this->db->delete("{$this->db->site_prefix}activity", lte('expires_at', date('Y-m-d H:i:s', time())));

                $this->db->commit();
            } catch (Exception $ex) {
                $this->db->rollback();
                Cascade::getLogger('error')->error($ex->getMessage());
                c::getInstance()->get('context')->obj['flash']->error(c::getInstance()->get('context')->obj['flash']->notice(409));
            }
        }
    }

    /**
     * Purges system error logs that are older than 30 days old.
     *
     * @since 1.0.0
     */
    public function purgeErrorLog()
    {
        $logs = glob(c::getInstance()->get('site_path') . 'files' . DS . 'logs' . DS . '*.txt');
        if (is_array($logs)) {
            foreach ($logs as $log) {
                $filelastmodified = filemtime($log);
                if ((time() - $filelastmodified) >= 30 * 24 * 3600 && is_file($log)) {
                    unlink($log);
                }
            }
        }
    }

    public function logError($type, $string, $file, $line)
    {
        /*$date = new \DateTime();
        $this->db->beginTransaction();
        try {
            $this->db->insert("{$this->db->site_prefix}error", [
                'time' => $date->getTimestamp(),
                'type' => (int) $type,
                'string' => (string) $string,
                'file' => (string) $file,
                'line' => (int) $line,
                'add_date' => (string) (new Common\Date())->format()
            ]);
            $this->db->commit();
        } catch (Exception $ex) {
            $this->db->rollback();
            Cascade::getLogger('error')->error($ex->getMessage());
            c::getInstance()->get('context')->obj['flash']->error(c::getInstance()->get('context')->obj['flash']->notice(409));
        }*/
    }

    public function errorConstantToName($value)
    {
        $values = array(
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_STRICT => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED',
            E_ALL => 'E_ALL'
        );

        return $values[$value];
    }
}
