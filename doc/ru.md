**Пример AppSwoole**


# SwooleApp: Минималистичное руководство по созданию веб-приложений

## Шаг 1: Что такое SwooleApp и зачем он нужен?

SwooleApp — это PHP-фреймворк, построенный на базе **Swoole** (асинхронный движок для PHP). В отличие от традиционного PHP, где каждый запрос запускает PHP с нуля, SwooleApp работает как **долгоживущий серверный процесс**.

### Ключевое отличие от обычного PHP:
- **Обычный PHP (Apache/Nginx + PHP-FPM)**: Каждый запрос → новый процесс PHP → загрузка всех файлов → выполнение → завершение
- **SwooleApp**: Сервер запускается один раз → загружает код в память → обрабатывает запросы в долгоживущих процессах

### Преимущества:
- **Высокая производительность**: Нет накладных расходов на загрузку PHP для каждого запроса
- **Состояние в памяти**: Можно кэшировать данные между запросами
- **Асинхронность**: Фоновые задачи не блокируют основной поток

## Шаг 2: Структура проекта

Проект имеет следующую структуру:
```
├── server.php          # Точка входа
├── config.json         # Конфигурация
├── src/
│   ├── Controllers/    # Обработчики HTTP-запросов
│   ├── Middleware/     # Промежуточное ПО
│   ├── Tasks/          # Фоновые задачи
│   ├── CyclicJobs/     # Циклические задачи
│   └── StateContainerInitializers/ # Инициализация ресурсов
```

## Шаг 3: Точка входа - server.php

```php
<?php
declare(strict_types=1);
require_once "./vendor/autoload.php";

use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;
use Swoole\Constant;

// 1. Загружаем конфигурацию
$config = json_decode(file_get_contents('./config.json'));

// 2. Создаем HTTP-сервер на порту 9501
$http = new Server("0.0.0.0", 9501);

// 3. Настраиваем количество процессов
$http->set([
    Constant::OPTION_WORKER_NUM => 2,                    // 2 основных воркера
    Constant::OPTION_TASK_WORKER_NUM => (swoole_cpu_num()) * 10, // Фоновые воркеры
]);

// 4. Создаем приложение
$app = new \Sidalex\SwooleApp\Application($config);

// 5. Обработчики событий
$http->on("start", function (Server $http) use ($app) {
    echo "Swoole HTTP server is started.\n";
    $app->initCyclicJobs($http); // Запускаем циклические задачи
});

$http->on("request", function (Request $request, Response $response) use ($app, $http) {
    $app->execute($request, $response, $http); // Обработка HTTP-запросов
});

$http->on('task', function (Server $server, $taskId, $reactorId, $data) use ($app) {
    return $app->taskExecute($server, $taskId, $reactorId, $data); // Обработка фоновых задач
});

// 6. Запускаем сервер
$http->start();
```

**Что здесь происходит:**
- Сервер запускается один раз и работает постоянно
- Создается 2 основных Worker'а для обработки HTTP-запросов
- Создаются Task Worker'ы для фоновых задач (кол-во = CPU ядер × 10)
- При старте инициализируются циклические задачи

## Шаг 4: Конфигурация - config.json

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
Так же конфиг можно добавлять через переменные окружения,которые переписывают или добавляют значение к config.json, подробнее тут:

[переменные окружения](https://github.com/SwooleApp/SwooleApp?tab=readme-ov-file#%D0%BA%D0%BE%D0%BD%D1%84%D0%B8%D0%B3)


**Разъяснение:**
- `controllers`: неймспейс Где искать классы контроллеров
- `REDIS`: Настройки подключения к Redis
- `StateContainerInitiation`: Классы для инициализации общих ресурсов
- `CyclicJobs`: Циклические задачи для запуска
- `globalMiddlewares`: Глобальные Middleware

## Шаг 5: State Container и пулы соединений

### Что такое State Container?
В SwooleApp Worker'ы (процессы) живут долго и могут хранить данные в памяти между запросами. State Container — это место для хранения общих ресурсов.

### Пример: RedisConnectionPoolInitializer

```php
// src/StateContainerInitializers/RedisConnectionPoolInitializer.php
class RedisConnectionPoolInitializer extends AbstractContainerInitiator {
    public function init(\Sidalex\SwooleApp\Application $param): void {
        $redisConfig = $param->getConfig()->getConfigFromKey('REDIS');
        
        // Создаем пул из 10 соединений с Redis
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

**Зачем нужен пул соединений?**
Без пула: Каждый запрос создает новое соединение с Redis (медленно)
С пулом: Соединения создаются один раз при старте и переиспользуются

## Шаг 6: Контроллеры и маршрутизация

### Базовый контроллер:

```php
// src/Controllers/Controller.php
#[Route(uri: "/", method: 'GET')]  // Обрабатывает GET запрос на "/"
#[Middleware(LoggingMiddleware::class,[])]  // Применяет Middleware
class Controller extends AbstractController {
    public function execute(): \Swoole\Http\Response {
        $this->response->end('Hello World');  // Отправляет ответ
        return $this->response;
    }
}
```

**Как работает маршрутизация:**
- Атрибут `#[Route]` определяет URL и HTTP-метод
- Контроллер должен наследоваться от `AbstractController`
- Метод `execute()` вызывается при совпадении маршрута

### Контроллер с параметрами:

```php
// src/Controllers/RedisGetter.php
#[Route(uri: "/redis/get/{key}", method: 'GET')]  // {key} - параметр пути
class RedisGetter extends AbstractController {
    public function execute(): \Swoole\Http\Response {
        // Получаем Redis пул из State Container
        $redisPool = $this->application->getStateContainer()
            ->getContainer(RedisConnectionPoolInitializer::class);
        
        // Параметр из URL: /redis/get/mykey → $key = "mykey"
        $key = $this->uri_params['key'];
        
        // Берем соединение из пула
        $redis = $redisPool->get();
        
        try {
            $data = $redis->get($key);  // Читаем из Redis
            $this->response->status(200);
            $this->response->end($data);
        } finally {
            $redisPool->put($redis);  // Возвращаем соединение в пул
        }
        
        return $this->response;
    }
}
```

**Важно:** Всегда возвращайте соединения в пул!

## Шаг 7: Middleware (промежуточное ПО)

Middleware — это код, который выполняется ДО и ПОСЛЕ контроллера.

```php
// src/Middleware/LoggingMiddleware.php
class LoggingMiddleware extends AbstractMiddleware {
    public function process($request, $response, $application, $next) {
        $startTime = microtime(true);
        
        // Код, выполняемый ДО контроллера
        echo "Request: {$request->getMethod()} {$request->server['request_uri']}\n";
        
        // Передаем управление следующему Middleware или контроллеру
        $result = $next($request, $response);
        
        // Код, выполняемый ПОСЛЕ контроллера
        $endTime = microtime(true);
        echo "Response time: " . ($endTime - $startTime) . "s\n";
        
        return $result;
    }
}
```

**Цепочка выполнения:**
```
Запрос → Middleware1 → Middleware2 → Контроллер → Middleware2 → Middleware1 → Ответ
```

## Шаг 8: Task Workers (фоновые задачи)

Task Workers выполняют долгие или блокирующие операции, не блокируя HTTP Worker'ы.

Для высокой производительности Swoole важно что бы воркеры основного рантайма которые обрабатывают запросы не имели блокирующих операций их выносят в Tasks.

### Создание задачи:

```php
// src/Tasks/TasksExample.php
class TasksExample extends AbstractTaskExecutor {
    public function execute(): TaskResulted {
        // $this->dataStorage содержит данные из контроллера
        print "task data incoming: " . var_export($this->dataStorage, true);
        
        sleep(3);  // Имитация долгой операции
        
        return new TaskResulted(['success' => 'ok']);
    }
}
```

### Запуск задачи из контроллера:

```php
// src/Controllers/TestTaskController.php
#[Route(uri: "/test-task", method: 'POST')]
class TestTaskController extends AbstractController {
    public function execute(): \Swoole\Http\Response {
        $body = $this->request->getContent();
        
        // Создаем задачу
        $task = new BasicTaskData(TasksExample::class, ['data' => $body]);
        
        // Запускаем задачу в фоне (ждем результат 10 секунд)
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

**Два способа запуска задач:**
- `task()` — асинхронно, не ждем результат
- `taskwait()` — синхронно, ждем результат с таймаутом

## Шаг 9: Cyclic Jobs (циклические задачи)

Cyclic Jobs — это встроенный аналог cron.

```php
// src/CyclicJobs/CyclicJob.php
class CyclicJob extends AbstractCyclicJob {
    protected float $timeSleep = 10;  // Выполнять каждые 10 секунд
    
    public function runJob(): void {
        $date = date('Y-m-d H:i:s');
        // Записываем в лог
        file_put_contents('Job.log', "Cyclic Job date now {$date} \n", FILE_APPEND);
    }
}
```

**Как работают:**
- Задачи запускаются в отдельных корутинах (легких потоках)
- Выполняются с заданным интервалом
- Имеют доступ к State Container

## Шаг 10: Жизненный цикл запроса

Когда клиент делает запрос `GET /redis/get/mykey`:

1. **server.php** получает запрос
2. Вызывает `$app->execute($request, $response, $http)`
3. **Application** ищет подходящий контроллер
4. **RoutesCollectionBuilder** находит `RedisGetter` по маршруту `/redis/get/{key}`
5. Создается экземпляр `RedisGetter`
6. Выполняется цепочка Middleware
7. Вызывается `RedisGetter::execute()`
8. Контроллер берет соединение из Redis пула
9. Выполняет операцию `GET mykey`
10. Возвращает соединение в пул
11. Ответ проходит через Middleware в обратном порядке
12. Ответ отправляется клиенту

## Шаг 11: Важные особенности SwooleApp

### Состояние между запросами:
```php
// ЭТО РАБОТАЕТ В SwooleApp (но не в обычном PHP):
class CounterController extends AbstractController {
    private static $count = 0;  // Сохраняется между запросами!
    
    public function execute(): \Swoole\Http\Response {
        self::$count++;
        $this->response->end("Count: " . self::$count);
        return $this->response;
    }
}
// Запрос 1: "Count: 1"
// Запрос 2: "Count: 2" (тот же Worker) 
```

### Но будьте осторожны:
```php
// НЕДОПУСТИМО (данные будут видны всем пользователям):
private static $userData = [];  // Общий для всех!
```

### Разные Worker'ы — разные данные:
Если у вас 2 Worker'а, то `CounterController::$count` будет разным в каждом Worker'е.

## Шаг 12: Запуск и тестирование

1. **запустите docker-compose**
```shell
docker-compose up -d --build
```
2. **посмотреть логи**

```bash
docker logs -f swoole-app
```

3.**Протестируйте:**
```bash
# Тестовый контроллер src/Controllers/Controller.php
curl http://localhost:9501/

# запись в редис src/Controllers/RedisSetter.php
curl -X POST http://localhost:9501/redis/set/mykey -d "myvalue"
# чтение из редис src/Controllers/RedisGetter.php
curl http://localhost:9501/redis/get/mykey

# Фоновая задача src/Controllers/TestTaskController.php
curl -X POST http://localhost:9501/test-task -d '{"test": "data"}'
```

## Итог: Ключевые концепции SwooleApp

1. **Долгоживущие процессы** — сервер запускается один раз
2. **State Container** — хранение общих ресурсов в памяти
3. **Пул соединений** — переиспользование подключений к БД/Redis
4. **Task Workers** — фоновое выполнение долгих операций
5. **Cyclic Jobs** — встроенный планировщик задач
6. **Middleware** — сквозная функциональность
7. **Атрибутная маршрутизация** — декларативное объявление маршрутов

## Когда использовать SwooleApp?

✅ **Подходит для:**
- Высоконагруженных API
- Real-time приложений
- Сервисов с фоновыми задачами
- Документацию Swoole: https://wiki.swoole.com/ru/#/
    - Документация SwooleAPP: https://github.com/SwooleApp/SwooleApp