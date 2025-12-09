<?php

namespace App\StateContainerInitializers;
use Sidalex\SwooleApp\Classes\Initiation\AbstractContainerInitiator;
use Swoole\Database\RedisConfig;
use Swoole\Database\RedisPool;

class RedisConnectionPoolInitializer extends AbstractContainerInitiator
{

    public function init(\Sidalex\SwooleApp\Application $param): void
    {
        $redisConfig=$param->getConfig()->getConfigFromKey('REDIS');

        $this->result = new RedisPool((new RedisConfig)
            ->withHost($redisConfig->HOST)
            ->withPort($redisConfig->PORT)
            ->withAuth($redisConfig->PASSWORD ?? '')
            ->withDbIndex($redisConfig->DB_NUMBER ?? 0)
            ->withTimeout($redisConfig->TIMEOUT ?? 1),$redisConfig->POOL_SIZE ?? 10);
        $this->key = RedisConnectionPoolInitializer::class;
    }
}
