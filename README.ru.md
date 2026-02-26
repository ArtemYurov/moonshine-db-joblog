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
- Настраиваемое расписание очистки и пути сканирования job-классов
- Поддержка i18n (EN, RU из коробки)

## Требования

- PHP 8.2+
- Laravel 11.x или 12.x
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
        'days' => 30,
        'schedule' => false, // false, 'daily', 'weekly', 'hourly'
        'time' => '03:00',
    ],

    // Вывод в консоль при выполнении artisan команд
    'console_output' => true,

    // Интеграция с Laravel Horizon
    'horizon' => [
        'enabled' => 'auto',       // 'auto', true или false
        'intercept_purge' => true,  // Предотвращает удаление Horizon отслеживаемых задач
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

## Локализация

Пакет поставляется с переводами EN и RU. Опубликуйте для кастомизации:

```bash
php artisan vendor:publish --tag=joblog-lang
```

Файлы будут размещены в `lang/vendor/joblog/`. Пространство имён переводов: `joblog::joblog`.

## Схема базы данных

### job_logs

| Колонка | Тип | Описание |
|---------|-----|----------|
| id | bigint | Первичный ключ |
| connection | string | Имя подключения очереди |
| queue | string | Имя очереди |
| job_uuid | uuid | UUID задачи Laravel |
| job_class | string | Полное имя класса задачи |
| related_type | string (nullable) | Тип полиморфной модели |
| related_id | bigint (nullable) | ID полиморфной модели |
| queued_at | timestamp(3) | Время постановки в очередь |
| started_at | timestamp(3) | Время начала обработки |
| finished_at | timestamp(3) | Время завершения |
| runtime_seconds | integer | Время выполнения в секундах |
| progress | tinyint | Прогресс 0–100 |
| status | string | queued / processing / processed / failed / interrupted |
| pid | integer (nullable) | ID процесса |
| args | json | Сериализованные аргументы конструктора |
| tags | json | Теги Horizon |
| data | json | Пользовательские данные ключ-значение |

### job_log_steps

| Колонка | Тип | Описание |
|---------|-----|----------|
| id | bigint | Первичный ключ |
| job_log_id | bigint (FK) | Родительский лог задачи |
| step_key | string | Идентификатор шага |
| step_name | string | Человекочитаемое имя шага |
| status | string | processing / processed / failed |
| custom_status | string (nullable) | Пользовательский текст статуса |
| progress | tinyint | Прогресс шага 0–100 |
| started_at | timestamp(3) | Время начала шага |
| finished_at | timestamp(3) | Время завершения шага |
| runtime_seconds | integer | Время выполнения шага |
| data | json | Данные шага |

### job_log_records

| Колонка | Тип | Описание |
|---------|-----|----------|
| id | bigint | Первичный ключ |
| job_log_id | bigint (FK) | Родительский лог задачи |
| step_key | string (nullable) | Ключ шага (null для записей уровня job) |
| level | string(20) | Уровень лога PSR-3 |
| message | text | Сообщение лога |
| context | json (nullable) | Дополнительный контекст |
| trace | longtext (nullable) | Стек вызовов исключения |
| created_at | timestamp(3) | Время записи |

## Лицензия

MIT
