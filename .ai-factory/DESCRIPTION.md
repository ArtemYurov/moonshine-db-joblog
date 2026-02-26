# Project: MoonShine DB JobLog

## Overview
A Laravel package (library) that provides job queue logging with MoonShine admin panel integration. Tracks queue job lifecycle in real-time: statuses, steps, progress, errors — all visible in the MoonShine admin.

## Core Features
- Automatic tracking of queue job lifecycle (queued → processing → processed/failed)
- Step-by-step progress with named steps
- PSR-3 compatible logging (emergency, alert, critical, error, warning, notice, info, debug)
- Polymorphic `related` relation — link any Eloquent model to a job
- Auto-detection of first Eloquent model from job constructor arguments
- Color-coded console output during `artisan` execution
- MoonShine admin resources with filters, query tags, and detail views
- Laravel Horizon integration (tag resolution, purge interception)
- Configurable cleanup schedule and job scan paths
- i18n support (EN, RU)

## Tech Stack
- **Language:** PHP 8.2+
- **Framework:** Laravel 11.x / 12.x (package/library type)
- **Admin Panel:** MoonShine 4.x
- **Testing:** PHPUnit 10.5+ / 11.0+
- **Logging:** Monolog 3.0+
- **Optional:** Laravel Horizon (tag resolution, purge interception)

## Architecture Notes
- PSR-4 autoloading under `ArtemYurov\JobLog\` namespace
- Service provider with auto-discovery (`JobLogServiceProvider`)
- Three database tables: `job_logs`, `job_log_steps`, `job_log_records`
- Trait-based integration (`Loggable` trait added to job classes)
- MoonShine resources for admin panel UI
- Monolog DatabaseHandler for persisting log records
- Horizon integration with tag resolver and custom Redis job repository

## Architecture
See `.ai-factory/ARCHITECTURE.md` for detailed architecture guidelines.
Pattern: Layered Architecture (Laravel Package)

## Non-Functional Requirements
- Logging: Configurable console output, database persistence via Monolog handler
- Error handling: Exception tracking with attempts support
- Cleanup: Configurable automatic cleanup of old records
- Compatibility: Laravel 11.x and 12.x, PHP 8.2+
