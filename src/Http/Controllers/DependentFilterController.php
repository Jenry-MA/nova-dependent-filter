<?php

namespace DevJM\DependentFilter\Http\Controllers;

use DevJM\DependentFilter\Nova\Filters\DependentFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Nova;

class DependentFilterController extends Controller
{
    public function __invoke(NovaRequest $request): JsonResponse
    {
        $resourceKey = $request->input('resource');
        $filterKey = $request->input('filter');

        $resourceClass = Nova::resourceForKey($resourceKey);

        if (! $resourceClass) {
            return response()->json([], 404);
        }

        $resource = new $resourceClass($resourceClass::newModel());
        $filters = $resource->filters($request);

        /** @var DependentFilter|null $filter */
        $filter = collect($filters)->first(
            fn ($f) => $f instanceof DependentFilter && $f->key() === $filterKey
        );

        if (! $filter) {
            return response()->json([], 404);
        }

        $parentValues = $request->except(['resource', 'filter']);

        $options = $filter->getFilteredOptions($request, $parentValues);

        $formattedOptions = collect($options)->map(function ($value, $label) {
            if (is_array($value)) {
                return array_merge(['label' => $label], $value);
            } elseif (is_string($label)) {
                return ['label' => $label, 'value' => $value];
            }

            return ['label' => $value, 'value' => $value];
        })->values()->all();

        return response()->json($formattedOptions);
    }
}
