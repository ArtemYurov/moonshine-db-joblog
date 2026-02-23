<?php

declare(strict_types=1);

namespace ArtemYurov\JobLog\MoonShine\Pages;

use ArtemYurov\JobLog\Enums\JobLogStatus;
use ArtemYurov\JobLog\Models\JobLog;
use ArtemYurov\JobLog\Support\JobClassDiscovery;
use Illuminate\Database\Eloquent\Builder;
use MoonShine\AssetManager\InlineCss;
use MoonShine\Contracts\UI\ActionButtonContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FormBuilderContract;
use MoonShine\Crud\Forms\FiltersForm;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\Laravel\QueryTags\QueryTag;
use MoonShine\Support\ListOf;
use MoonShine\UI\Components\Layout\Div;
use MoonShine\UI\Fields\Select;

/**
 * Custom Index page for JobLog.
 *
 * Differences from the default IndexPage:
 * - Filters are displayed inline (not inside OffCanvas)
 * - "Filters" button is removed from the top-right panel
 * - Query tags and filters are defined here (not in the resource)
 */
class JobLogIndexPage extends IndexPage
{
    /**
     * Remove OffCanvas filter button — filters are now inline.
     */
    protected function topRightButtons(): ListOf
    {
        return new ListOf(ActionButtonContract::class, [
            ...$this->getResource()->getHandlers()->getButtons()->toArray(),
        ]);
    }

    protected function modifyListComponent(ComponentContract $component): ComponentContract
    {
        return $component->simple()->columnSelection();
    }

    protected function queryTags(): array
    {
        return [
            QueryTag::make(
                __('joblog::joblog.query_tag.queued'),
                fn(Builder $query) => $query->where('status', JobLogStatus::QUEUED)
            ),
            QueryTag::make(
                __('joblog::joblog.query_tag.processing'),
                fn(Builder $query) => $query->where('status', JobLogStatus::PROCESSING)
            ),
            QueryTag::make(
                __('joblog::joblog.query_tag.processed'),
                fn(Builder $query) => $query->where('status', JobLogStatus::PROCESSED)
            ),
            QueryTag::make(
                __('joblog::joblog.query_tag.failed'),
                fn(Builder $query) => $query->where('status', JobLogStatus::FAILED)
            ),
            QueryTag::make(
                __('joblog::joblog.query_tag.interrupted'),
                fn(Builder $query) => $query->where('status', JobLogStatus::INTERRUPTED)
            ),
            QueryTag::make(
                __('joblog::joblog.query_tag.last_24h'),
                fn(Builder $query) => $query->where('queued_at', '>=', now()->subDay())
            ),
        ];
    }

    /**
     * @return list<\MoonShine\Contracts\UI\FieldContract>
     */
    protected function filters(): iterable
    {
        return [
            Select::make(__('joblog::joblog.filter.job_class'), 'job_class')
                ->options($this->getJobClassOptions())
                ->searchable()
                ->nullable(),
            Select::make(__('joblog::joblog.filter.queue'), 'queue')
                ->options($this->getQueueOptions())
                ->searchable()
                ->nullable(),
            Select::make(__('joblog::joblog.filter.related_type'), 'related_type')
                ->options($this->getRelatedModelOptions())
                ->searchable()
                ->nullable(),
        ];
    }

    /**
     * Query tags + inline filters + table.
     *
     * @return list<ComponentContract>
     */
    protected function mainLayer(): array
    {
        return [
            ...$this->getQueryTagsButtons(),
            ...$this->getInlineFiltersForm(),
            ...$this->getItemsComponents(),
        ];
    }

    /**
     * @return list<ComponentContract>
     */
    private function getInlineFiltersForm(): array
    {
        if (! $this->hasFilters()) {
            return [];
        }

        /** @var FormBuilderContract $form */
        $form = $this->getCore()->getConfig()->getForm(
            'filters',
            FiltersForm::class,
            resource: $this->getResource(),
            core: $this->getCore()
        );

        // Single row: Job class + Queue + Related model + Buttons
        // Visible fields are wrapped in div.moonshine-field, hidden inputs are bare <input>.
        $form->class('joblog-filters');
        $form->addAssets([InlineCss::make(<<<'CSS'
            .joblog-filters.form.space-elements {
                display: grid;
                grid-template-columns: 2fr 1fr 1fr auto;
                gap: 4px 12px;
                align-items: end;
                margin-bottom: 0;
            }
            .joblog-filters.form.space-elements > * + * {
                margin-top: 0;
            }
            .joblog-filters > input[type="hidden"] {
                display: none;
            }
            .joblog-filters > .grid:last-child .mt-3 {
                margin-top: 0;
            }
            /* Reset button is hidden by default in async mode (OffCanvas).
               Force it visible since filters are inline now. */
            .joblog-filters .js-async-reset-button {
                display: inline-flex !important;
            }
            /* Pull column selection button into the filter row (wide screens only) */
            @media (min-width: 640px) {
                .js-table-builder-wrapper > .flex.items-center.justify-end {
                    margin-top: -55px;
                    margin-bottom: 20px;
                }
            }
        CSS)]);

        return [
            $form,
            Div::make()->class('my-4'),
        ];
    }

    private function getJobClassOptions(): array
    {
        return JobClassDiscovery::discover()
            ->mapWithKeys(fn($class) => [$class => class_basename($class)])
            ->toArray();
    }

    private function getQueueOptions(): array
    {
        return JobLog::select('queue')
            ->distinct()
            ->whereNotNull('queue')
            ->pluck('queue', 'queue')
            ->toArray();
    }

    private function getRelatedModelOptions(): array
    {
        return JobLog::select('related_type')
            ->distinct()
            ->whereNotNull('related_type')
            ->pluck('related_type')
            ->mapWithKeys(function ($className) {
                $baseName = class_basename($className);
                return [$className => $baseName];
            })
            ->toArray();
    }
}
