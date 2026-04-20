<?php

declare(strict_types=1);

namespace App\Events;

use Sidalex\SwooleApp\Application;
use Swoole\Http\Request;
use Swoole\Http\Response;

class RequestLogger
{
    public function handle(Application $app, Request $request, Response $response): void
    {
        $uri = $request->server['request_uri'] ?? '/';
        $method = $request->server['request_method'] ?? 'UNKNOWN';
        $remoteAddr = $request->server['remote_addr'] ?? 'unknown';

        $config = $app->getConfig();
        $debug = $config->getConfigFromKey('APP_DEBUG');

        if ($debug) {
            error_log("[RequestLogger] {$method} {$uri} from {$remoteAddr}");
        }

        // Log to response header in debug mode
        if ($debug) {
            $response->header('X-Debug-Request-Logged', date('Y-m-d H:i:s'));
        }
    }
}
