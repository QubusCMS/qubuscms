<?php
namespace TriTan\Interfaces\Queue;

/**
 * Reliable queue interface.
 *
 * Classes implementing this interface preserve the order of messages and
 * guarantee that every item will be executed at least once.
 *
 * @since       1.0.0
 * @package     TriTan CMS
 * @author      Joshua Parker <josh@joshuaparker.blog>
 */
interface ReliableQueueInterface extends QueueInterface
{
}
