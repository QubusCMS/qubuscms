<?php
use TriTan\Queue\NodeqQueue as Queue;
use Cascade\Cascade;
use Qubus\Hooks\ActionFilterHook;
use Qubus\Exception\Exception;

$qudb = app()->qudb;
$nodeq = new \TriTan\NodeQ;
$opt = new \TriTan\Common\Options\Options(
    new TriTan\Common\Options\OptionsMapper(
        $qudb,
        new TriTan\Common\Context\HelperContext()
    )
);

/**
 * Cron Router
 *
 * @license GPLv3
 *
 * @since 1.0.0
 * @package Qubus CMS
 * @author Joshua Parker <josh@joshuaparker.blog>
 */
$app->before('POST|PUT|DELETE|OPTIONS', '/cronjob/', function () use ($app) {
    header('Content-Type: application/json');
    $app->res->_format('json', 404);
    exit();
});

$app->get('/cronjob/master/', function () use ($qudb) {
    $prepare = $qudb->prepare(
        "SELECT site_domain, site_path FROM {$qudb->base_prefix}site WHERE site_status <> ?",
        [
            'archive'
        ],
        ARRAY_A
    );

    $sites = $qudb->getResuts($prepare, ARRAY_A);
    foreach ($sites as $site) {
        $command = "//" . $site['site_domain'] . $site['site_path'] . 'cronjob/';
        $ch = curl_init($command);
        $rc = curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        $rc = curl_exec($ch);
        curl_close($ch);
    }
});

$app->get('/cronjob/', function () use ($app, $qudb, $nodeq, $opt) {
    if ($opt->read('cron_jobs') != (int) 1) {
        exit();
    }

    ActionFilterHook::getInstance()->{'doAction'}('ttcms_task_worker_cron');

    try {
        $tasks = $nodeq->table($qudb->prefix . 'tasks')
            ->where('tasks_enabled', '1');

        if ((int) $tasks->count() > 0) {
            $array = [];
            foreach ($tasks->get() as $task) {
                $array[] = (array) $task;
            }

            foreach ($array as $queue) {
                if (!function_exists($queue['task_callback'])) {
                    $delete = $nodeq->table($qudb->prefix . 'tasks');
                    $delete->begin();
                    try {
                        $delete
                            ->where('tasks_id', (int) escape($queue['tasks_id']))
                            ->delete();
                        $delete->commit();
                    } catch (Exception $e) {
                        $delete->rollback();
                        Cascade::getLogger('system_email')->{'alert'}(
                            sprintf(
                                'QUEUE: %s',
                                $e->getMessage()
                            ),
                            [
                                'Cron Router' => 'Task Callback'
                            ]
                        );
                    }
                }

                $task = new Queue($queue);
                $task->createItem($queue);

                $jobs_to_do = true;
                $start = microtime(true);

                try {
                    while ($jobs_to_do) {
                        $item = $task->claimItem();
                        $data = (new \TriTan\Common\Serializer())->{'unserialize'}($item['data']);

                        if ($item) {
                            Cascade::getLogger('info')->{'info'}(
                                sprintf(
                                    'QUEUESTATE[8190]: Processing item %s . . .',
                                    $data['pid']
                                ),
                                [
                                    'Cron Router' => 'Item'
                                ]
                            );
                            // Execute the job task in a different function.
                            if ($task->executeAction($data)) {
                                // Delete the item.
                                $task->deleteItem($item);

                                Cascade::getLogger('info')->{'info'}(
                                    sprintf(
                                        'QUEUESTATE[8190]: Item %s processed.',
                                        $data['pid']
                                    ),
                                    [
                                        'Cron Router' => 'Action Hook'
                                    ]
                                );
                            } else {
                                // Release the item to execute the job task again later.
                                $task->releaseItem($item);

                                Cascade::getLogger('info')->{'info'}(
                                    sprintf(
                                        'QUEUESTATE[8190]: Item %s NOT processed.',
                                        $data['pid']
                                    ),
                                    [
                                        'Cron Router' => 'Release Item'
                                    ]
                                );

                                $jobs_to_do = false;

                                Cascade::getLogger('info')->{'info'}(
                                    'QUEUESTATE[8190]: Queue not completed. Item not executed.',
                                    [
                                        'Cron Router' => 'Release Item'
                                    ]
                                );
                            }
                        } else {
                            $jobs_to_do = false;
                            $time_elapsed = microtime(true) - $start;
                            $number_of_items = $task->numberOfItems();
                            if ($number_of_items == 0) {
                                Cascade::getLogger('info')->{'info'}(
                                    sprintf(
                                        'QUEUESTATE[8190]: Queue completed in %s seconds.',
                                        $time_elapsed
                                    ),
                                    [
                                        'Cron Router' => '# of Items == 0'
                                    ]
                                );
                            } else {
                                Cascade::getLogger('info')->{'info'}(
                                    sprintf(
                                        'QUEUESTATE[8190]: Queue not completed, there are %s items left.',
                                        $number_of_items
                                    ),
                                    [
                                        'Cron Router' => '# of Items'
                                    ]
                                );
                            }
                        }
                    }
                } catch (Exception $e) {
                    if ($queue['debug']) {
                        Cascade::getLogger('error')->{'error'}(
                            sprintf(
                                'QUEUESTATE[%s]: %s',
                                $e->getCode(),
                                $e->getMessage()
                            ),
                            [
                                'Cron Router' => 'Claim Queue Item'
                            ]
                        );

                        Cascade::getLogger('system_email')->{'alert'}(
                            sprintf(
                                'QUEUESTATE[%s]: %s',
                                $e->getCode(),
                                $e->getMessage()
                            ),
                            [
                                'Cron Router' => 'Claim Queue Item'
                            ]
                        );
                    }
                }
            }
        }
    } catch (Exception $e) {
        Cascade::getLogger('system_email')->{'alert'}(
            sprintf(
                'NODEQSTATE[%s]: %s',
                $e->getCode(),
                $e->getMessage()
            ),
            [
                'Cron Router' => 'Master'
            ]
        );
    }

    ttcms_nodeq_login_details();
    ttcms_nodeq_reset_password();
});
