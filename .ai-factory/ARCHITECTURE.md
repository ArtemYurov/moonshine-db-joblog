# Architecture: Layered Architecture (Laravel Package)

## Overview
This project follows a Layered Architecture adapted for a Laravel package (library). Each layer has a clear responsibility and dependencies flow top-down. The package integrates into the host Laravel application via a service provider and trait-based API.

This pattern was chosen because the project is a focused utility package with limited scope (job queue logging), single-team development, and tight integration with Laravel conventions. Adding more complex patterns (Clean Architecture, DDD) would introduce unnecessary abstraction for this type of package.

## Decision Rationale
- **Project type:** Laravel package/library (not a standalone application)
- **Tech stack:** PHP 8.2+, Laravel 11/12, MoonShine 4.x
- **Key factor:** Focused scope — the package does one thing well (job logging), follows Laravel conventions, and integrates via traits and service provider

## Folder Structure
```
src/
├── Commands/                  # Presentation: Artisan CLI commands
│   ├── JobLogCleanupCommand.php
│   └── JobLogTruncateCommand.php
├── Enums/                     # Domain: Status enumerations
│   └── JobLogStatus.php
├── Horizon/                   # Infrastructure: Laravel Horizon integration
│   ├── HorizonTagResolver.php
│   ├── LoggableRedisJobRepository.php
│   ├── NullTagResolver.php
│   └── TagResolverInterface.php
├── Logger/                    # Business Logic: Core logging engine
│   ├── DatabaseHandler.php
│   ├── JobLogger.php
│   └── JobLoggerStep.php
├── Middleware/                 # Business Logic: Job middleware
│   └── LoggableExceptionAttempts.php
├── Models/                    # Data Access: Eloquent models
│   ├── JobLog.php
│   ├── JobLogRecord.php
│   └── JobLogStep.php
├── MoonShine/                 # Presentation: Admin panel UI
│   ├── Pages/
│   ├── Resources/
│   └── Traits/
├── Support/                   # Infrastructure: Utilities
│   ├── JobClassDiscovery.php
│   └── SimpleCliDumper.php
├── Traits/                    # Public API: Integration traits for user jobs
│   ├── Loggable.php
│   └── JobLoggerMethods.php
└── JobLogServiceProvider.php  # Composition Root: wires everything together

config/joblog.php              # Configuration
database/migrations/           # Database schema
lang/{en,ru}/joblog.php        # Translations
```

## Dependency Rules

### Layer dependencies (top-down):
```
Presentation (Commands, MoonShine)
      ↓
Business Logic (Logger, Middleware)
      ↓
Data Access (Models, Enums)
      ↓
Infrastructure (Horizon, Support)
```

- ✅ Commands → Logger (commands invoke logging operations)
- ✅ MoonShine Resources → Models (admin displays model data)
- ✅ Logger → Models (persists log data via Eloquent)
- ✅ Middleware → Logger (intercepts job exceptions, delegates to logger)
- ✅ Traits → Logger (public API delegates to core logger)
- ❌ Models → Logger (data layer must not depend on business logic)
- ❌ Models → MoonShine (data layer must not depend on presentation)
- ❌ Logger → Commands (business logic must not know about CLI)
- ❌ Logger → MoonShine (business logic must not know about admin UI)

## Layer/Module Communication
- **User → Package:** Via `Loggable` trait added to job classes
- **Package → Database:** Via Eloquent models and Monolog DatabaseHandler
- **Package → Console:** Via `SimpleCliDumper` for colored CLI output
- **Package → MoonShine:** Via MoonShine Resources reading Eloquent models
- **Package → Horizon:** Via `TagResolverInterface` abstraction (auto-detected)

## Key Principles

1. **Single entry point for users** — The `Loggable` trait is the only thing users add to their jobs. All complexity is hidden behind this trait.
2. **Service Provider as Composition Root** — `JobLogServiceProvider` registers all bindings, migrations, config, and commands. No other class should register services.
3. **Eloquent Models are data containers** — Models hold schema definitions, relationships, scopes, and casts. Business logic lives in `Logger/` classes.
4. **Infrastructure is swappable** — Horizon integration uses `TagResolverInterface` with `NullTagResolver` fallback. Console output is optional via config.
5. **Follow Laravel conventions** — Use Laravel naming (Models, Commands, Middleware), PSR-4 autoloading, config publishing, migration auto-loading.

## Code Examples

### Правильно: Trait делегирует логику в Logger
```php
// src/Traits/Loggable.php
trait Loggable
{
    public function logger(): JobLogger
    {
        // Trait предоставляет удобный API, но логика живёт в Logger
        return app(JobLogger::class)->forJob($this);
    }
}
```

### Правильно: ServiceProvider как Composition Root
```php
// src/JobLogServiceProvider.php
class JobLogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/joblog.php', 'joblog');

        // Привязка интерфейса к реализации (Horizon или Null)
        $this->app->singleton(TagResolverInterface::class, function () {
            return class_exists(\Laravel\Horizon\Horizon::class)
                ? new HorizonTagResolver()
                : new NullTagResolver();
        });
    }
}
```

### Правильно: Модель только описывает данные
```php
// src/Models/JobLog.php
class JobLog extends Model
{
    protected $casts = [
        'status' => JobLogStatus::class,
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function steps(): HasMany
    {
        return $this->hasMany(JobLogStep::class);
    }

    // Скоупы для фильтрации
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', JobLogStatus::Failed);
    }
}
```

## Anti-Patterns

- ❌ **Бизнес-логика в моделях** — Не добавляйте методы типа `$jobLog->markAsProcessing()` с побочными эффектами (console output, записи в другие таблицы). Такая логика принадлежит `JobLogger`.
- ❌ **Прямые запросы в MoonShine Resources** — Не пишите raw SQL или сложную бизнес-логику в ресурсах. Используйте скоупы моделей и отношения.
- ❌ **Зависимость от Horizon без проверки** — Всегда используйте `TagResolverInterface` и проверяйте наличие Horizon через `class_exists()`.
- ❌ **Хардкод конфигурации** — Все настраиваемые параметры должны быть в `config/joblog.php`, а не захардкожены в классах.
- ❌ **Нарушение PSR-4** — Namespace `ArtemYurov\JobLog\` должен соответствовать структуре папок `src/`. Не создавайте классы вне этой иерархии.
