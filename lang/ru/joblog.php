<?php

return [
    // Ресурсы — заголовки
    'resource' => [
        'job_logs' => 'Логи задач',
        'job_log_steps' => 'Шаги задач',
        'job_log_records' => 'Записи логов',
    ],

    // Статусы
    'status' => [
        'queued' => 'В очереди',
        'processing' => 'Выполняется',
        'processed' => 'Завершено',
        'failed' => 'Ошибка',
        'interrupted' => 'Прервано',
    ],

    // Поля — общие
    'field' => [
        'connection' => 'Подключение',
        'queue' => 'Очередь',
        'job_class' => 'Задача',
        'job_uuid' => 'UUID задачи',
        'related' => 'Связанная модель',
        'queued_at' => 'Очередь',
        'started_at' => 'Начало',
        'finished_at' => 'Завершение',
        'error' => 'Ошибка',
        'last_error' => 'Последняя ошибка',
        'current_step' => 'Этап',
        'status' => 'Статус',
        'progress' => 'Прогресс (%)',
        'runtime' => 'Время выполнения (сек)',
        'args' => 'Аргументы',
        'data' => 'Данные',
        'steps' => 'Шаги',
        'records' => 'Записи логов',
        'pid' => 'PID',
        'tags' => 'Теги',
    ],

    // Поля — шаги
    'step' => [
        'step_key' => 'Шаг',
        'step_name' => 'Имя',
        'custom_status' => 'Пользовательский статус',
        'created_at' => 'Создано',
        'updated_at' => 'Обновлено',
        'last_error' => 'Последняя ошибка',
        'records' => 'Записи логов шага',
        'data' => 'Данные',
    ],

    // Поля — записи логов
    'record' => [
        'step_key' => 'Шаг',
        'level' => 'Уровень',
        'message' => 'Сообщение',
        'context' => 'Контекст',
        'trace' => 'Стек вызовов',
        'created_at' => 'Создано',
    ],

    // Query tags
    'query_tag' => [
        'all' => 'Все',
        'queued' => 'В очереди',
        'processing' => 'В процессе',
        'processed' => 'Завершенные',
        'failed' => 'С ошибками',
        'interrupted' => 'Прерванные',
        'last_24h' => 'Последние 24 часа',
    ],

    // Фильтры
    'filter' => [
        'status' => 'Статус',
        'job_class' => 'Задача',
        'queue' => 'Очередь',
        'related_type' => 'Связанная модель',
    ],

    // Команды
    'command' => [
        'cleanup_description' => 'Очистка записей JobLog старше указанного количества дней',
        'cleanup_days_option' => 'Количество дней для хранения записей',
        'cleanup_start' => 'Начало очистки JobLog записей старше :days дней (до :date)',
        'cleanup_no_records' => 'Нет записей для удаления',
        'cleanup_found' => 'Найдено :count записей для удаления',
        'cleanup_success' => 'Успешно удалено :count записей JobLog',

        'truncate_description' => 'Полная очистка всех записей JobLog',
        'truncate_no_records' => 'Нет записей для удаления',
        'truncate_confirm' => 'Будет удалено :count записей JobLog. Продолжить?',
        'truncate_cancelled' => 'Операция отменена',
        'truncate_success' => 'Успешно выполнена полная очистка JobLog',
    ],

    // Прочее
    'signal_interrupted' => "Задача прервана сигналом :signal",
];
