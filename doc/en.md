**AppSwoole Example**

# SwooleApp: A Minimalist Guide to Building Web Applications

## Step 1: What is SwooleApp and Why Do We Need It?

SwooleApp is a PHP framework built on top of **Swoole** (an asynchronous engine for PHP). Unlike traditional PHP, where each request starts PHP from scratch, SwooleApp runs as a **long-lived server process**.

### Key Difference from Regular PHP:
- **Regular PHP (Apache/Nginx + PHP-FPM)**: Each request → new PHP process → load all files → execute → terminate
- **SwooleApp**: Server starts once → loads code into memory → processes requests in long-lived processes

### Advantages:
- **High Performance**: No overhead of loading PHP for each request
- **In-Memory State**: Data can be cached between requests
- **Asynchronicity**: Background tasks do not block the main thread

## Step 2: Project Structure

The project has the following structure:
```
├── server.php          # Entry point
├── config.json         # Configuration
├── src/
│   ├── Controllers/    # HTTP request handlers
│   ├── Middleware/     # Middleware
│   ├── Tasks/          # Background tasks
│   ├── CyclicJobs/     # Cyclic tasks
│   └── StateContainerInitializers/ # Resource initialization
```

## Step 3: Entry Point - server.php

```php
<?php
declare(strict_types=1);
require_once "./vendor/autoload.php";

use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;
use Swoole\Constant;

// 1. Load configuration
$config = json_decode(file_get_contents('./config.json'));

// 2. Create HTTP server on port 9501
$http = new Server("0.0.0.0", 9501);

// 3. Configure number of processes
$http->set([
    Constant::OPTION_WORKER_NUM => 2,                    // 2 main workers
    Constant::OPTION_TASK_WORKER_NUM => (swoole_cpu_num()) * 10, // Background workers
]);

// 4. Create application
$app = new \Sidalex\SwooleApp\Application($config);

// 5. Event handlers
$http->on("start", function (Server $http) use ($app) {
    echo "Swoole HTTP server is started.\n";
    $app->initCyclicJobs($http); // Start cyclic tasks
});

$http->on("request", function (Request $request, Response $response) use ($app, $http) {
    $app->execute($request, $response, $http); // Process HTTP requests
});

$http->on('task', function (Server $server, $taskId, $reactorId, $data) use ($app) {
    return $app->taskExecute($server, $taskId, $reactorId, $data); // Process background tasks
});

// 6. Start the server
$http->start();
```

**What happens here:**
- The server starts once and runs continuously
- 2 main Workers are created to handle HTTP requests
- Task Workers are created for background tasks (count = CPU cores × 10)
- Cyclic tasks are initialized on start

## Step 4: Configuration - config.json

```json
{
    "controllers": ["App\\Controllers"],
    "REDIS": {
        "HOST": "127.0.0.1",
        "PORT": 6379,
        "PASSWORD": "",
        "DB_NUMBER": 0,
        "TIMEOUT": 1,
        "POOL_SIZE": 10
    },
    "StateContainerInitiation": [
        "App\\StateContainerInitializers\\RedisConnectionPoolInitializer"
    ],
    "CyclicJobs": [
        "App\\CyclicJobs\\CyclicJob"
    ],
    "globalMiddlewares": [],
    "APP_DEBUG": true
}
```
Configuration can also be added via environment variables, which override or add values to config.json. More details here:

[environment variables](https://github.com/SwooleApp/SwooleApp?tab=readme-ov-file#config)

**Explanation:**
- `controllers`: namespace Where to look for controller classes
- `REDIS`: Redis connection settings
- `StateContainerInitiation`: Classes for initializing shared resources
- `CyclicJobs`: Cyclic tasks to run
- `globalMiddlewares`: Global Middleware

## Step 5: State Container and Connection Pools

### What is a State Container?
In SwooleApp, Workers (processes) are long-lived and can store data in memory between requests. The State Container is a place to store shared resources.

### Example: RedisConnectionPoolInitializer

```php
// src/StateContainerInitializers/RedisConnectionPoolInitializer.php
class RedisConnectionPoolInitializer extends AbstractContainerInitiator {
    public function init(\Sidalex\SwooleApp\Application $param): void {
        $redisConfig = $param->getConfig()->getConfigFromKey('REDIS');
        
        // Create a pool of 10 Redis connections
        $this->result = new RedisPool(
            (new RedisConfig)
                ->withHost($redisConfig->HOST)
                ->withPort($redisConfig->PORT)
                ->withAuth($redisConfig->PASSWORD ?? '')
                ->withDbIndex($redisConfig->DB_NUMBER ?? 0)
                ->withTimeout($redisConfig->TIMEOUT ?? 1),
            $redisConfig->POOL_SIZE ?? 10
        );
        
        $this->key = RedisConnectionPoolInitializer::class;
    }
}
```

**Why do we need a connection pool?**
Without a pool: Each request creates a new Redis connection (slow)
With a pool: Connections are created once on startup and reused

## Step 6: Controllers and Routing

### Basic controller:

```php
// src/Controllers/Controller.php
#[Route(uri: "/", method: 'GET')]  // Handles GET request to "/"
#[Middleware(LoggingMiddleware::class,[])]  // Applies Middleware
class Controller extends AbstractController {
    public function execute(): \Swoole\Http\Response {
        $this->response->end('Hello World');  // Sends response
        return $this->response;
    }
}
```

**How routing works:**
- The `#[Route]` attribute defines the URL and HTTP method
- The controller must extend `AbstractController`
- The `execute()` method is called when the route matches

### Controller with parameters:

```php
// src/Controllers/RedisGetter.php
#[Route(uri: "/redis/get/{key}", method: 'GET')]  // {key} - path parameter
class RedisGetter extends AbstractController {
    public function execute(): \Swoole\Http\Response {
        // Get Redis pool from State Container
        $redisPool = $this->application->getStateContainer()
            ->getContainer(RedisConnectionPoolInitializer::class);
        
        // Parameter from URL: /redis/get/mykey → $key = "mykey"
        $key = $this->uri_params['key'];
        
        // Get a connection from the pool
        $redis = $redisPool->get();
        
        try {
            $data = $redis->get($key);  // Read from Redis
            $this->response->status(200);
            $this->response->end($data);
        } finally {
            $redisPool->put($redis);  // Return connection to the pool
        }
        
        return $this->response;
    }
}
```

**Important:** Always return connections to the pool!

## Step 7: Middleware

Middleware is code that runs BEFORE and AFTER the controller.

```php
// src/Middleware/LoggingMiddleware.php
class LoggingMiddleware extends AbstractMiddleware {
    public function process($request, $response, $application, $next) {
        $startTime = microtime(true);
        
        // Code executed BEFORE the controller
        echo "Request: {$request->getMethod()} {$request->server['request_uri']}\n";
        
        // Pass control to the next Middleware or controller
        $result = $next($request, $response);
        
        // Code executed AFTER the controller
        $endTime = microtime(true);
        echo "Response time: " . ($endTime - $startTime) . "s\n";
        
        return $result;
    }
}
```

**Execution chain:**
```
Request → Middleware1 → Middleware2 → Controller → Middleware2 → Middleware1 → Response
```

## Step 8: Task Workers (Background Tasks)

Task Workers perform long or blocking operations without blocking HTTP Workers.

For high performance in Swoole, it is important that the main runtime workers handling requests do not have blocking operations; these are moved to Tasks.

### Creating a task:

```php
// src/Tasks/TasksExample.php
class TasksExample extends AbstractTaskExecutor {
    public function execute(): TaskResulted {
        // $this->dataStorage contains data from the controller
        print "task data incoming: " . var_export($this->dataStorage, true);
        
        sleep(3);  // Simulate a long operation
        
        return new TaskResulted(['success' => 'ok']);
    }
}
```

### Starting a task from a controller:

```php
// src/Controllers/TestTaskController.php
#[Route(uri: "/test-task", method: 'POST')]
class TestTaskController extends AbstractController {
    public function execute(): \Swoole\Http\Response {
        $body = $this->request->getContent();
        
        // Create a task
        $task = new BasicTaskData(TasksExample::class, ['data' => $body]);
        
        // Start the task in the background (wait for result for 10 seconds)
        $result = $this->server->taskwait($task, 10);
        
        if ($result->getResult()) {
            $this->response->end("Task executed successfully");
        } else {
            $this->response->status(500);
            $this->response->end("Task execution failed");
        }
        
        return $this->response;
    }
}
```

**Two ways to start tasks:**
- `task()` — asynchronously, don't wait for result
- `taskwait()` — synchronously, wait for result with timeout

## Step 9: Cyclic Jobs

Cyclic Jobs are a built-in cron-like feature.

```php
// src/CyclicJobs/CyclicJob.php
class CyclicJob extends AbstractCyclicJob {
    protected float $timeSleep = 10;  // Execute every 10 seconds
    
    public function runJob(): void {
        $date = date('Y-m-d H:i:s');
        // Write to log
        file_put_contents('Job.log', "Cyclic Job date now {$date} \n", FILE_APPEND);
    }
}
```

**How they work:**
- Tasks run in separate coroutines (lightweight threads)
- Execute at a specified interval
- Have access to the State Container

## Step 10: Request Lifecycle

When a client makes a request `GET /redis/get/mykey`:

1. **server.php** receives the request
2. Calls `$app->execute($request, $response, $http)`
3. **Application** looks for a suitable controller
4. **RoutesCollectionBuilder** finds `RedisGetter` via the `/redis/get/{key}` route
5. An instance of `RedisGetter` is created
6. The Middleware chain is executed
7. `RedisGetter::execute()` is called
8. The controller gets a connection from the Redis pool
9. Performs the `GET mykey` operation
10. Returns the connection to the pool
11. The response passes through Middleware in reverse order
12. The response is sent to the client

## Step 11: Important Features of SwooleApp

### State between requests:
```php
// THIS WORKS IN SwooleApp (but not in regular PHP):
class CounterController extends AbstractController {
    private static $count = 0;  // Persists between requests!
    
    public function execute(): \Swoole\Http\Response {
        self::$count++;
        $this->response->end("Count: " . self::$count);
        return $this->response;
    }
}
// Request 1: "Count: 1"
// Request 2: "Count: 2" (same Worker) 
```

### But be careful:
```php
// NOT ALLOWED (data will be visible to all users):
private static $userData = [];  // Shared for everyone!
```

### Different Workers — Different Data:
If you have 2 Workers, `CounterController::$count` will be different in each Worker.

## Step 12: Running and Testing

1. **Run docker-compose**
```shell
docker-compose up -d --build
```
2. **View logs**

```bash
docker logs -f swoole-app
```

3. **Test:**
```bash
# Test controller src/Controllers/Controller.php
curl http://localhost:9501/

# Write to Redis src/Controllers/RedisSetter.php
curl -X POST http://localhost:9501/redis/set/mykey -d "myvalue"
# Read from Redis src/Controllers/RedisGetter.php
curl http://localhost:9501/redis/get/mykey

# Background task src/Controllers/TestTaskController.php
curl -X POST http://localhost:9501/test-task -d '{"test": "data"}'
```

## Summary: Key Concepts of SwooleApp

1. **Long-lived processes** — server starts once
2. **State Container** — storing shared resources in memory
3. **Connection pools** — reusing DB/Redis connections
4. **Task Workers** — background execution of long operations
5. **Cyclic Jobs** — built-in task scheduler
6. **Middleware** — cross-cutting functionality
7. **Attribute routing** — declarative route definition

## When to Use SwooleApp?

✅ **Suitable for:**
- High-load APIs
- Real-time applications
- Services with background tasks
- Swoole documentation: https://wiki.swoole.com/ru/#/
    - SwooleAPP documentation: https://github.com/SwooleApp/SwooleApp