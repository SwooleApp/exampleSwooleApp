<?php

namespace App\Middleware;

use Sidalex\SwooleApp\Application;
use Sidalex\SwooleApp\Classes\Middleware\AbstractMiddleware;

class LoggingMiddleware extends AbstractMiddleware
{
    public function process(
        \Swoole\Http\Request $request,
        \Swoole\Http\Response $response,
        Application $application,
        callable $next
    ): \Swoole\Http\Response {
        $startTime = microtime(true);

        // Логируем начало запроса
        $this->logRequest($request);

        // Выполняем следующий Middleware или контроллер
        $result = $next($request, $response);

        // Логируем завершение запроса
        $endTime = microtime(true);
        $this->logResponse($request, $result, $endTime - $startTime);

        return $result;
    }

    private function logRequest(\Swoole\Http\Request $request): void
    {
        // В реальном приложении здесь было бы логирование в файл или систему
        echo "Request: {$request->getMethod()} {$request->server['request_uri']}\n";
    }

    private function logResponse(
        \Swoole\Http\Request $request,
        \Swoole\Http\Response $response,
        float $executionTime
    ): void {
        echo "Response: {$request->getMethod()} {$request->server['request_uri']} - " .
            sprintf("%.4f", $executionTime) . "s\n";
    }
}
