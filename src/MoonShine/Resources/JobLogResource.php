<?php

declare(strict_types=1);

namespace ArtemYurov\JobLog\MoonShine\Resources;

use MoonShine\Support\Enums\Action;
use ArtemYurov\JobLog\Enums\JobLogStatus;
use ArtemYurov\JobLog\Models\JobLog;
use ArtemYurov\JobLog\MoonShine\Traits\JobLogErrorFormatter;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Fields\Relationships\HasMany;
use MoonShine\Laravel\Pages\Crud\DetailPage;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\Enums\SortDirection;
use MoonShine\Support\ListOf;
use ArtemYurov\JobLog\MoonShine\Pages\JobLogIndexPage;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Json;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;

/**
 * @extends ModelResource<JobLog>
 */
class JobLogResource extends ModelResource
{
    use JobLogErrorFormatter;

    protected string $model = JobLog::class;

    protected string $sortColumn = 'queued_at';

    protected SortDirection $sortDirection = SortDirection::DESC;
    protected bool $saveQueryState = true;

    protected array $with = ['latestStep', 'latestErrorRecord', 'related'];

    protected function pages(): array
    {
        return [
            JobLogIndexPage::class,
            DetailPage::class,
        ];
    }

    public function getTitle(): string
    {
        return __('joblog::joblog.resource.job_logs');
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
            Text::make(__('joblog::joblog.field.connection'), 'connection')->columnSelection(),
            Text::make(__('joblog::joblog.field.queue'), 'queue')->columnSelection(),
            Text::make(__('joblog::joblog.field.job_class'), 'job_class', formatted: fn($item) => class_basename($item->job_class))->columnSelection(),
            Text::make(__('joblog::joblog.field.related'), 'related', formatted: fn($item) => $this->formatRelated($item))->columnSelection(),
            Date::make(__('joblog::joblog.field.queued_at'), 'queued_at')->withTime()->changePreview(fn($value) => $this->formatDateTimeWithBreak($value))->sortable()->columnSelection(),
            Date::make(__('joblog::joblog.field.started_at'), 'started_at')->withTime()->changePreview(fn($value) => $this->formatDateTimeWithBreak($value))->sortable()->columnSelection(),
            Date::make(__('joblog::joblog.field.finished_at'), 'finished_at')->withTime()->changePreview(fn($value) => $this->formatDateTimeWithBreak($value))->sortable()->columnSelection(),
            Textarea::make(__('joblog::joblog.field.error'), formatted: fn(JobLog $item) => $this->formatLastError($item->latestErrorRecord, 100, ''))->columnSelection(),
            Text::make(__('joblog::joblog.field.current_step'), 'current_step', function($item) {
                return $item->latestStep ? $item->latestStep->step_key : '-';
            })->align('right')->columnSelection(),
            Number::make('', 'progress', fn($value) => $value->progress . '%')->align('right')->columnSelection(),
            Enum::make(__('joblog::joblog.field.status'), 'status')->attach(JobLogStatus::class)->sortable()->bold(),
            Number::make('', 'runtime_seconds', fn($item) => $item->runtime_seconds ? "{$item->runtime_seconds}\u{00A0}s" : '')->align('right')->columnSelection(),
        ];
    }

    /**
     * @return list<FieldContract>
     */
    protected function detailFields(): iterable
    {
        return [
            ID::make(),
            Text::make(__('joblog::joblog.field.connection'), 'connection'),
            Text::make(__('joblog::joblog.field.queue'), 'queue'),
            Text::make(__('joblog::joblog.field.job_uuid'), 'job_uuid'),
            Text::make(__('joblog::joblog.field.job_class'), 'job_class'),
            Textarea::make(__('joblog::joblog.field.args'), 'args'),
            Text::make(__('joblog::joblog.field.related'), 'related', formatted: fn($item) => $this->formatRelated($item)),
            Date::make(__('joblog::joblog.field.queued_at'), 'queued_at')->withTime(),
            Date::make(__('joblog::joblog.field.started_at'), 'started_at')->withTime(),
            Date::make(__('joblog::joblog.field.finished_at'), 'finished_at')->withTime(),
            Number::make(__('joblog::joblog.field.progress'), 'progress'),
            Enum::make(__('joblog::joblog.field.status'), 'status')->attach(JobLogStatus::class),
            Number::make(__('joblog::joblog.field.runtime'), 'runtime_seconds'),
            Json::make(__('joblog::joblog.field.data'), 'data')->keyValue(),
            HasMany::make(__('joblog::joblog.field.steps'), 'steps', resource: JobLogStepResource::class)->searchable(false)->withoutModals(),
            HasMany::make(__('joblog::joblog.field.records'), 'records', resource: JobLogRecordResource::class)->searchable(false)->withoutModals(),
            Textarea::make(__('joblog::joblog.field.last_error'), formatted: fn(JobLog $item) => $this->formatLastError($item->getLastErrorRecord(), 1000)),
        ];
    }

    /**
     * @return array<string, string[]|string>
     */
    protected function rules(mixed $item): array
    {
        return [];
    }

    protected function formatRelated(JobLog $item): string
    {
        if (!$item->related) {
            return '-';
        }

        $class = class_basename(get_class($item->related));
        $id = $item->related->getKey();

        return "{$class}:{$id}";
    }

    private function formatDateTimeWithBreak($value): string
    {
        return $value ? '<span style="white-space:nowrap">' . $value->format('Y-m-d') . '<br>' . $value->format('H:i:s') . '</span>' : '-';
    }
}
