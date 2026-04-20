<?php

namespace App\Controllers;

use App\Middleware\LoggingMiddleware;
use App\StateContainerInitializers\RedisConnectionPoolInitializer;
use Sidalex\SwooleApp\Classes\Controllers\AbstractController;
use Sidalex\SwooleApp\Classes\Controllers\Route;
use Sidalex\SwooleApp\Classes\Middleware\Middleware;
use Swoole\Database\RedisPool;

#[Route(uri: "/redis/set/{key}", method: 'POST')]
#[Middleware(LoggingMiddleware::class, [])]
class RedisSetter extends AbstractController
{
    public function execute(): \Swoole\Http\Response
    {
        /**
         * @var RedisPool $redisPool
         */
        $redisPool = $this->application->getStateContainer()->getContainer(RedisConnectionPoolInitializer::class);
        $key = $this->uri_params['key'];
        $body = $this->request->getContent();
        try {
            $redis = $redisPool->get();
            $redis->set($key, $body);
            $this->response->header('Content-Type', 'application/json');
            $this->response->status(201);
            $this->response->end();
        } catch (\RedisException $e) {
            $this->response->status(500);
            $this->response->header('Content-Type', 'application/json');
        } finally {
            $redisPool->put($redis);
        }

        return $this->response;
    }
}
