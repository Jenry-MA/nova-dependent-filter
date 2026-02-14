<?php

namespace DevJM\DependentFilter\Nova\Filters;

use Closure;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

class DependentFilter extends Filter
{
    public $component = 'dependent-select-filter';

    protected ?string $modelClass = null;

    protected ?string $column = null;

    protected string $labelColumn = 'name';

    protected string $valueColumn = 'id';

    protected array $dependencies = [];

    protected ?Closure $scopeCallback = null;

    /**
     * Create a new dependent filter instance.
     *
     * Usage:
     *   $client = DependentFilter::make('Client Filter', Client::class, 'client_id');
     *
     *   $project = DependentFilter::make('Project Filter', Project::class, 'project_id')
     *       ->dependsOn($client, foreignKey: 'client_id');
     *
     *   $user = DependentFilter::make('User Filter', User::class, 'user_id')
     *       ->dependsOn($project, relationship: 'projects')
     *       ->scope(fn($q) => $q->whereHas('roles')->where('is_active', 1));
     */
    public static function make(...$arguments): static
    {
        [$name, $model, $column] = $arguments;

        $instance = new static();
        $instance->name = $name;
        $instance->modelClass = $model;
        $instance->column = $column;

        return $instance;
    }

    /**
     * Get the key for the filter.
     */
    public function key(): string
    {
        return 'dependent-filter-' . $this->column;
    }

    /**
     * Declare a parent filter dependency.
     *
     * For a simple foreign key:
     *   ->dependsOn($clientFilter, foreignKey: 'client_id')
     *
     * For a belongsToMany / hasMany relationship:
     *   ->dependsOn($projectFilter, relationship: 'projects')
     */
    public function dependsOn(Filter $parent, ?string $foreignKey = null, ?string $relationship = null): static
    {
        $this->dependencies[] = [
            'parent' => $parent,
            'foreignKey' => $foreignKey,
            'relationship' => $relationship,
        ];

        return $this;
    }

    /**
     * Add a base query scope (applied to both initial and dependent options).
     *
     *   ->scope(fn($query, $request) => $query->where('is_active', 1))
     */
    public function scope(Closure $callback): static
    {
        $this->scopeCallback = $callback;

        return $this;
    }

    /**
     * Set the column used as label in the dropdown (default: 'name').
     */
    public function label(string $column): static
    {
        $this->labelColumn = $column;

        return $this;
    }

    /**
     * Set the column used as value in the dropdown (default: 'id').
     */
    public function value(string $column): static
    {
        $this->valueColumn = $column;

        return $this;
    }

    /**
     * Apply the filter to the given query.
     */
    public function apply(NovaRequest $request, $query, $value)
    {
        return $query->where($this->column, $value);
    }

    /**
     * Get the filter's available options (initial load — all options).
     */
    public function options(NovaRequest $request)
    {
        return $this->buildBaseQuery($request)
            ->pluck($this->valueColumn, $this->labelColumn);
    }

    /**
     * Get options filtered by parent filter values.
     * Called by the API controller when a parent filter changes.
     */
    public function getFilteredOptions(NovaRequest $request, array $parentValues): array
    {
        $query = $this->buildBaseQuery($request);

        foreach ($this->dependencies as $dep) {
            $parentKey = $dep['parent']->key();
            $parentValue = $parentValues[$parentKey] ?? null;

            if (empty($parentValue)) {
                continue;
            }

            if ($dep['relationship']) {
                $query->whereHas($dep['relationship'], function ($q) use ($parentValue) {
                    $q->where($q->getModel()->getTable() . '.id', $parentValue);
                });
            } elseif ($dep['foreignKey']) {
                $query->where($dep['foreignKey'], $parentValue);
            }
        }

        return $query->pluck($this->valueColumn, $this->labelColumn)->toArray();
    }

    /**
     * Build the base query with scope applied.
     */
    protected function buildBaseQuery(NovaRequest $request)
    {
        $query = ($this->modelClass)::query();

        if ($this->scopeCallback) {
            ($this->scopeCallback)($query, $request);
        }

        return $query->orderBy($this->labelColumn);
    }

    /**
     * Prepare the filter for JSON serialization.
     */
    public function jsonSerialize(): array
    {
        $dependsOnMap = [];

        foreach ($this->dependencies as $dep) {
            $key = $dep['parent']->key();
            $dependsOnMap[$key] = $key;
        }

        return array_merge(parent::jsonSerialize(), [
            'dependsOn' => $dependsOnMap,
        ]);
    }
}
