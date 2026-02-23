<?php

declare(strict_types=1);

namespace ArtemYurov\JobLog\MoonShine\Resources;

use MoonShine\Support\Enums\Action;
use ArtemYurov\JobLog\Enums\JobLogStatus;
use ArtemYurov\JobLog\Models\JobLogStep;
use ArtemYurov\JobLog\MoonShine\Traits\JobLogErrorFormatter;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Fields\Relationships\HasMany;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\Support\ListOf;
use MoonShine\UI\Fields\Date;
use MoonShine\UI\Fields\Enum;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Preview;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;

/**
 * @extends ModelResource<JobLogStep>
 */
class JobLogStepResource extends ModelResource
{
    use JobLogErrorFormatter;

    protected string $model = JobLogStep::class;

    public function getTitle(): string
    {
        return __('joblog::joblog.resource.job_log_steps');
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
            Text::make(__('joblog::joblog.step.step_key'), 'step_key'),
            Text::make(__('joblog::joblog.step.step_name'), 'step_name'),
            Text::make(__('joblog::joblog.step.custom_status'), 'custom_status'),
            Date::make(__('joblog::joblog.step.created_at'), 'created_at')->withTime()->sortable(),
            Date::make(__('joblog::joblog.step.updated_at'), 'updated_at')->withTime()->sortable(),

            Textarea::make(__('joblog::joblog.step.last_error'), 'last_error', fn($item) => $this->formatLastError($item->getLastErrorRecord())),
            Number::make('%', 'progress'),
            Enum::make(__('joblog::joblog.field.status'), 'status')->attach(JobLogStatus::class)->bold(),
            Number::make('', 'runtime_seconds', fn($item) => $item->runtime_seconds ? "{$item->runtime_seconds}\u{00A0}s" : '')->align('right'),
        ];
    }

    /**
     * @return list<FieldContract>
     */
    protected function detailFields(): iterable
    {
        return [
            ID::make(),
            Text::make(__('joblog::joblog.step.step_key'), 'step_key'),
            Text::make(__('joblog::joblog.step.step_name'), 'step_name'),
            Enum::make(__('joblog::joblog.field.status'), 'status')->attach(JobLogStatus::class),
            Text::make(__('joblog::joblog.step.custom_status'), 'custom_status'),
            Number::make(__('joblog::joblog.field.progress'), 'progress'),
            Preview::make(__('joblog::joblog.step.data'), 'data', fn($item) =>
                $item->data
                    ? '<pre style="white-space: pre-wrap; word-wrap: break-word; max-height: 600px; overflow-y: auto; background: #1e1e2e; color: #cdd6f4; padding: 1rem; border-radius: 0.5rem; font-size: 12px;">' .
                      htmlspecialchars(json_encode($item->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) .
                      '</pre>'
                    : ''
            ),
            Date::make(__('joblog::joblog.step.created_at'), 'created_at')->withTime(),
            Date::make(__('joblog::joblog.step.updated_at'), 'updated_at')->withTime(),
            HasMany::make(__('joblog::joblog.step.records'), 'records', resource: JobLogRecordResource::class)->searchable(false)->withoutModals(),
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
