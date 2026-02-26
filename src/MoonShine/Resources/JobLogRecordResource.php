<?php

declare(strict_types=1);

namespace ArtemYurov\JobLog\MoonShine\Resources;

use MoonShine\Support\Enums\Action;
use ArtemYurov\JobLog\Models\JobLogRecord;
use ArtemYurov\JobLog\MoonShine\Traits\JobLogErrorFormatter;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Textarea;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Support\ListOf;

/**
 * @extends ModelResource<JobLogRecord>
 */
class JobLogRecordResource extends ModelResource
{
    use JobLogErrorFormatter;

    protected string $model = JobLogRecord::class;

    public function getTitle(): string
    {
        return __('joblog::joblog.resource.job_log_records');
    }

    protected function activeActions(): ListOf
    {
        return new ListOf(Action::class, [Action::VIEW]);
    }

    /**
     * @return list<FieldContract>
     */
    protected function indexFields(): iterable
    {
        return [
            ID::make()->sortable(),
            Text::make(__('joblog::joblog.record.step_key'), 'step_key'),
            Text::make(__('joblog::joblog.record.level'), 'level'),
            Textarea::make(__('joblog::joblog.record.message'), 'message', fn($item) => $this->formatMessage($item->message)),
            Date::make(__('joblog::joblog.record.created_at'), 'created_at')->withTime()->sortable(),
        ];
    }

    /**
     * @return list<FieldContract>
     */
    protected function detailFields(): iterable
    {
        return [
            ID::make(),
            Text::make(__('joblog::joblog.record.step_key'), 'step_key'),
            Text::make(__('joblog::joblog.record.level'), 'level'),
            Textarea::make(__('joblog::joblog.record.message'), 'message'),
            Textarea::make(__('joblog::joblog.record.context'), 'context'),
            Textarea::make(__('joblog::joblog.record.trace'), 'trace'),
            Date::make(__('joblog::joblog.record.created_at'), 'created_at')->withTime(),
        ];
    }

    /**
     * @return array<string, string[]|string>
     */
    protected function rules(mixed $item): array
    {
        return [];
    }
}
