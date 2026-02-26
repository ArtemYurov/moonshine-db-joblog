# AGENTS.md

> Project map for AI agents. Keep this file up-to-date as the project evolves.

## Project Overview
Laravel package for job queue logging with MoonShine admin panel integration. Tracks job lifecycle, steps, progress, and errors.

## Tech Stack
- **Language:** PHP 8.2+
- **Framework:** Laravel 11.x / 12.x (package)
- **Admin Panel:** MoonShine 4.x
- **Testing:** PHPUnit 10.5+ / 11.0+

## Project Structure
```
src/
├── Commands/               # Artisan commands (cleanup, truncate)
├── Enums/                  # JobLogStatus enum
├── Horizon/                # Horizon integration (tag resolver, Redis repository)
├── Logger/                 # Core logging (JobLogger, DatabaseHandler, JobLoggerStep)
├── Middleware/              # Job middleware (LoggableExceptionAttempts)
├── Models/                 # Eloquent models (JobLog, JobLogStep, JobLogRecord)
├── MoonShine/
│   ├── Pages/              # Custom MoonShine pages
│   ├── Resources/          # MoonShine CRUD resources
│   └── Traits/             # MoonShine helper traits
├── Support/                # Utility classes (JobClassDiscovery, SimpleCliDumper)
├── Traits/                 # Integration traits (Loggable, JobLoggerMethods)
└── JobLogServiceProvider.php  # Service provider (auto-discovered)

config/
└── joblog.php              # Package configuration

database/
└── migrations/             # Three migration files (job_logs, job_log_steps, job_log_records)

lang/
├── en/joblog.php           # English translations
└── ru/joblog.php           # Russian translations

tests/
├── Fixtures/               # Test fixtures (fake jobs, models)
└── *Test.php               # PHPUnit test files
```

## Key Entry Points
| File | Purpose |
|------|---------|
| `src/JobLogServiceProvider.php` | Service provider — registers bindings, migrations, config, commands |
| `src/Traits/Loggable.php` | Main trait users add to their job classes |
| `src/Logger/JobLogger.php` | Core logger that manages job log lifecycle |
| `config/joblog.php` | Package configuration (cleanup, console output, Horizon) |
| `composer.json` | Package definition and dependencies |

## Documentation
| Document | Path | Description |
|----------|------|-------------|
| README (EN) | README.md | English documentation |
| README (RU) | README.ru.md | Russian documentation |

## AI Context Files
| File | Purpose |
|------|---------|
| AGENTS.md | This file — project structure map |
| .ai-factory/DESCRIPTION.md | Project specification and tech stack |
| .ai-factory/ARCHITECTURE.md | Architecture decisions and guidelines |
| CLAUDE.md | Agent instructions and preferences |
