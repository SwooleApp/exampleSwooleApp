<?php

declare(strict_types=1);

namespace App\Events;

use Sidalex\SwooleApp\Application;
use Swoole\Http\Server;

class WorkerStopLogger
{
    public function handle(Application $app, Server $server, int $workerId): void
    {
        $type = $server->taskworker ? 'Task Worker' : 'HTTP Worker';
        echo "[{$type}] Worker #{$workerId} stopped at " . date('Y-m-d H:i:s') . "\n";

        $config = $app->getConfig();
        $debug = $config->getConfigFromKey('APP_DEBUG');
        if ($debug) {
            error_log("[WorkerStopLogger] Worker #{$workerId} stopped, type: {$type}");
        }
    }
}