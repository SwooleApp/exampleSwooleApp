<?php
declare(strict_types=1);
require_once "./vendor/autoload.php";
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;
use Swoole\Constant;
$config = json_decode(file_get_contents('./config.json'));
$http = new Server("0.0.0.0", 9501);
$http->set(
    [
        Constant::OPTION_WORKER_NUM => 2,
        Constant::OPTION_TASK_WORKER_NUM => (swoole_cpu_num()) * 10,
    ]
);

$app = new \Sidalex\SwooleApp\Application($config);
$http->on(
    "start",
    function (Server $http) use ($app) {
        echo "Swoole HTTP server is started.\n";
        $app->initCyclicJobs($http);
    }
);
$http->on(
    "request",
    function (Request $request, Response $response) use ($app,$http) {
        $app->execute($request, $response,$http);
    }
);
$http->on(
    'task',
    function (Server $server, $taskId, $reactorId, $data) use ($app) {
        return $app->taskExecute($server, $taskId, $reactorId, $data);
    }
);
$http->start();