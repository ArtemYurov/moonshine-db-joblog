# MoonShine DB JobLog

[![PHP](https://img.shields.io/badge/PHP-8.2+-blue.svg)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-11.x|12.x-red.svg)](https://laravel.com)
[![MoonShine](https://img.shields.io/badge/MoonShine-4.x-purple.svg)](https://moonshine-laravel.com)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

**[English version (README.md)](README.md)**

Пакет логирования очередей с интеграцией в админ-панель [MoonShine](https://moonshine-laravel.com) для Laravel.

Отслеживайте queue jobs в реальном времени: статусы, шаги, прогресс, ошибки — всё видно в MoonShine админке.

## Возможности

- Автоматическое отслеживание жизненного цикла job (queued → processing → processed/failed)
- Пошаговый прогресс с именованными шагами
- PSR-3 совместимое логирование (emergency, alert, critical, error, warning, notice, info, debug)
- Полиморфная связь `related` — привязка любой Eloquent-модели к задаче
- Авто-определение первой Eloquent-модели из аргументов конструктора job
- Цветной вывод в консоль при выполнении через `artisan`
- MoonShine ресурсы с фильтрами, query tags и детальным просмотром
- Интеграция с Laravel Horizon (разрешение тегов, перехват purge)
- Middleware "без пересечений" — сериализация выполнения job по тегам через таблицу JobLog (без cache-локов), подключается внешним оркестратором
- Настраиваемое расписание очистки и пути сканирования job-классов
- Поддержка i18n (EN, RU из коробки)

## Требования

- PHP 8.2+
- Laravel 11.x, 12.x или 13.x
- MoonShine 4.x

## Установка

```bash
composer require artemyurov/moonshine-db-joblog
```

Пакет автоматически регистрирует сервис-провайдер. Запустите миграции:

```bash
php artisan migrate
```

Опционально опубликуйте конфиг:

```bash
php artisan vendor:publish --tag=joblog-config
```

### Локализация

Пакет поставляется с переводами EN и RU. Опубликуйте для кастомизации:

```bash
php artisan vendor:publish --tag=joblog-lang
```

Файлы будут размещены в `lang/vendor/joblog/`. Пространство имён переводов: `joblog::joblog`.

## Быстрый старт

### 1. Добавьте трейт Loggable в job

```php
use ArtemYurov\JobLog\Traits\Loggable;

class ProcessOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use Loggable;

    public function __construct(
        public readonly Order $order
    ) {}

    public function handle(): void
    {
        $this->log()->info('Начало обработки заказа');

        // Логика задачи...

        $this->log()->info('Заказ успешно обработан');
    }
}
```

Модель `Order` будет автоматически определена как `related` модель.

### 2. Определите шаги для сложных задач

```php
class ImportDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use Loggable;

    protected function steps(): array
    {
        return [
            'download'  => 'Загрузка данных',
            'validate'  => 'Валидация записей',
            'import'    => 'Импорт в базу данных',
            'cleanup'   => 'Очистка временных файлов',
        ];
    }

    public function handle(): void
    {
        $this->log()->step('download')->info('Загрузка...');
        // ... логика загрузки
        $this->log()->step('download')->processed();

        $this->log()->step('validate')->info('Валидация...');
        // ... логика валидации
        $this->log()->step('validate')->processed();

        $this->log()->step('import')->info('Импорт...');
        foreach ($records as $i => $record) {
            // ... логика импорта
            $this->log()->step('import')->setProgressFromCounts($i + 1, count($records));
        }
        $this->log()->step('import')->processed();

        $this->log()->step('cleanup')->info('Очистка...');
        // ... логика очистки
        $this->log()->step('cleanup')->processed();
    }
}
```

Прогресс автоматически рассчитывается по завершённым шагам (включено по умолчанию). Для отключения вызовите `$this->disableAutoStepProgress()` в вашем джобе.

### 3. Зарегистрируйте MoonShine ресурсы

```php
// В MoonShineLayout или MoonShineServiceProvider
use ArtemYurov\JobLog\MoonShine\Resources\JobLogResource;

MenuItem::make('Логи задач', JobLogResource::class),
```

## Использование

### Методы логирования (PSR-3)

```php
$this->log()->emergency('Система недоступна');
$this->log()->alert('Требуется немедленное действие');
$this->log()->critical('Критическое состояние');
$this->log()->error('Произошла ошибка', ['code' => 500]);
$this->log()->warning('Предупреждение');
$this->log()->notice('Важное уведомление');
$this->log()->info('Информационное сообщение');
$this->log()->debug('Детали отладки', ['query' => $sql]);
```

### Логирование исключений

```php
try {
    // рискованная операция
} catch (\Throwable $e) {
    $this->log()->exception($e, 'Пользовательское сообщение');
    $this->log()->step('import')->failed($e);
}
```

### Отслеживание прогресса

```php
// Установить точный прогресс (0-100)
$this->log()->progress(50);
$this->log()->step('import')->progress(75);

// Рассчитать из количества
$this->log()->step('import')->setProgressFromCounts($processed, $total);

// Инкремент
$this->log()->step('import')->incrementProgress(5);
```

### Управление статусами шагов

```php
$step = $this->log()->step('validate');

$step->start();       // алиас для processing()
$step->processing();  // статус PROCESSING
$step->processed();   // статус PROCESSED, прогресс 100%
$step->failed();      // статус FAILED

// Пользовательский статус (отображается рядом со стандартным)
$step->customStatus('Ожидание ответа API');
$step->customStatus('Лимит запросов', 'API вернул 429');
```

### Хранение данных

```php
// Сохранить данные ключ-значение на уровне job или step
$this->log()->addData(['total_records' => 1500]);
$this->log()->step('import')->addData(['skipped' => 3, 'errors' => 1]);

// Получить данные
$total = $this->log()->getData('total_records');
$allData = $this->log()->step('import')->getData();
```

### Явное указание related модели

По умолчанию первая Eloquent-модель в аргументах конструктора определяется автоматически. Переопределение:

```php
class SyncJob implements ShouldQueue
{
    use Loggable;

    public function __construct(
        public readonly Branch $branch,
        public readonly array $options
    ) {}

    // Явно определить related модель
    public function related(): Branch
    {
        return $this->branch;
    }
}
```

### Скрытие чувствительных аргументов

Аргументы конструктора автоматически сериализуются и сохраняются в БД. Используйте атрибут PHP 8.2 `#[\SensitiveParameter]` для маскировки чувствительных значений:

```php
class SendPaymentJob implements ShouldQueue
{
    use Loggable;

    public function __construct(
        public readonly Order $order,
        #[\SensitiveParameter] public readonly string $apiKey,
        #[\SensitiveParameter] public readonly string $secretToken,
    ) {}
}
```

В базе данных и интерфейсе MoonShine чувствительные аргументы будут сохранены как `********`.

### Предотвращение пересекающихся запусков (сериализация по тегам)

`ArtemYurov\JobLog\Middleware\JobLogWithoutOverlapping` сериализует job по их тегам JobLog.
«Занято» означает **живой процесс**, а не удерживаемый таймер: сосед блокирует запуск, только пока
существует его записанный `pid`. Теги берёт `TagResolver` (явный метод `tags()`, иначе
Eloquent-модели из свойств job). Пакет не подключает middleware сам — его включает оркестратор
([`moonshine-command-schedule-job`](https://github.com/ArtemYurov/moonshine-command-schedule-job))
в момент диспатча либо сама job в своём `middleware()`.

Покрываются два наложения:

- **Одно сообщение, два исполнения** — драйвер снова выдаёт ещё работающую job, когда истекает её
  `retry_after`. Оба исполнения пишут в одну строку `job_logs` (строка одна на uuid), поэтому
  переход в `PROCESSING` сохраняет первый живой `pid`; второе исполнение его видит и уступает.
- **Два сообщения, один ресурс** — разные uuid, одинаковые теги. Запуск уступает живому соседу,
  выигравшему тай-брейк по `(queued_at, uuid)`.

Ни лок, ни атомарность не нужны: каждое исполнение пишет свою строку `PROCESSING` **до** того, как
опрашивает соседей, поэтому промахнуться мимо друг друга они не могут.

Требуется **ext-posix**, и pid имеет смысл только в пределах **одного хоста**. Без расширения любой
записанный pid считается живым.

#### Сериализация или отбрасывание

Режим задаётся задержкой релиза, повторяя нативный
`Illuminate\Queue\Middleware\WithoutOverlapping`:

```php
new JobLogWithoutOverlapping(30);                   // сериализация: подождать 30 сек и повторить
(new JobLogWithoutOverlapping())->releaseAfter(30); // то же самое, fluent-стиль
(new JobLogWithoutOverlapping())->dontRelease();    // отбросить избыточный запуск
```

Оба исхода завершаются каноническими для Laravel статусами (без кастомного): возвращённый запуск
повторяется, пока сосед не исчезнет → `PROCESSED` или `FAILED`; отброшенный возвращается без
выполнения → `PROCESSED`.

> **Важно:** `release()` увеличивает `attempts()`, поэтому сериализуемая job **обязана** допускать
> повторы (`$tries > 1` или `retryUntil()`) — иначе первый же релиз израсходует её единственную
> попытку и она завершится с `MaxAttemptsExceeded`.

`expireAfter()` (по умолчанию 3 часа) — **не** TTL лока: он ограничивает, как долго строка в
`PROCESSING` может блокировать, чтобы потерянная служебная запись не заклинила тег навсегда. Если
собственный `timeout` job переживает это значение, порог поднимается до `timeout + 60s` — с
предупреждением в самой job, — поэтому он никогда не спишет ещё идущий запуск.

#### Обновление на 1.3

`new JobLogWithoutOverlapping(30)`, `releaseAfter()` и `dontRelease()` не изменились. Что стало иначе:

- **Два исполнения одного сообщения теперь ловятся** — раньше они делили одну строку и исключали
  друг друга как «себя», поэтому переотправленная job выполнялась дважды.
- **Упавшая job больше не блокирует свои теги** до ручной правки строки: её pid исчез, и следующая
  попытка проходит. Без ext-posix блокирует до истечения `expireAfter()`.
- **Возвращённая в очередь job получает `QUEUED` и пустой `pid`** вместо `PROCESSED` с
  `finished_at`. `JobReleasedAfterException` тоже обрабатывается — раньше строка навсегда застревала
  в `PROCESSING`.
- **`expireAfter()` сменил смысл** (TTL лока → порог протухания), дефолт вырос до 3 часов.
- `hasActiveOverlap()` удалён; тестовым двойникам следует подменять `findActiveOverlapByTags()`.

## Расширение ресурса

Создайте кастомный ресурс, наследующий `JobLogResource`, для доменно-специфичного форматирования:

```php
use ArtemYurov\JobLog\MoonShine\Resources\JobLogResource;

class MyJobLogResource extends JobLogResource
{
    protected function formatRelated(JobLog $item): string
    {
        if ($item->related instanceof Branch) {
            return $item->related->city ?? "Филиал #{$item->related->getKey()}";
        }

        return parent::formatRelated($item);
    }
}
```

## Конфигурация

```php
// config/joblog.php
return [
    // Очистка старых записей
    'cleanup' => [
        'days' => (int) env('JOBLOG_CLEANUP_DAYS', 30),
        'schedule' => env('JOBLOG_CLEANUP_SCHEDULE', false), // false, 'daily', 'weekly', 'hourly'
        'time' => env('JOBLOG_CLEANUP_TIME', '03:00'),
    ],

    // Вывод в консоль при выполнении artisan команд
    'console_output' => (bool) env('JOBLOG_CONSOLE_OUTPUT', true),

    // Интеграция с Laravel Horizon (определяется автоматически)
    'horizon' => [
        'intercept_purge' => (bool) env('JOBLOG_HORIZON_INTERCEPT_PURGE', true),
    ],

    // Пути для сканирования Loggable jobs (для выпадающего списка фильтра)
    'job_class_scan_paths' => [
        // По умолчанию — app/Jobs
    ],
];
```

## Artisan команды

```bash
# Очистка записей старше N дней (по умолчанию: 30)
php artisan joblog:cleanup
php artisan joblog:cleanup --days=7

# Полная очистка всех записей
php artisan joblog:truncate
```

## Лицензия

MIT
