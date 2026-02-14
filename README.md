# Nova Dependent Filter

Cascading/dependent select filters for Laravel Nova 5.
When a user selects a value in a **parent** filter, the **child** filter automatically narrows its options.

## Demo

https://github.com/dev-jm/nova-dependent-filter/raw/main/docs/screenshots/example.mp4

In this example, selecting the client **INTLXS** automatically narrows the **Project** filter to only show projects that belong to that client. Then, selecting a project narrows the **User** filter to only show users assigned to that specific project. Clearing a parent filter resets its children back to showing all available options.

---

## Installation


Install via Composer:

```bash
composer require dev-jm/nova-dependent-filter
```

The package auto-registers its service provider via Laravel's package discovery.

---

## Quick Start

```php
use DevJM\DependentFilter\Nova\Filters\DependentFilter;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;

public function filters(NovaRequest $request)
{
    $client = DependentFilter::make('Client', Client::class, 'client_id');

    $project = DependentFilter::make('Project', Project::class, 'project_id')
        ->dependsOn($client, foreignKey: 'client_id');

    $user = DependentFilter::make('User', User::class, 'user_id')
        ->dependsOn($project, relationship: 'projects');

    return [$client, $project, $user];
}
```

That's it. Three lines, three cascading filters.

---

## How It Works

1. **Page loads** - all filters show their full list of options.
2. **User picks a Client** - the Project dropdown re-fetches and only shows projects belonging to that client.
3. **User picks a Project** - the User dropdown re-fetches and only shows users assigned to that project.
4. **User clears a parent** - child filters reset and go back to showing all options.

---

## API Reference

### `DependentFilter::make(name, model, column)`

Creates a new filter instance.

| Argument | Type     | Description                                              |
|----------|----------|----------------------------------------------------------|
| `name`   | `string` | Display name shown in the filter panel                   |
| `model`  | `string` | Eloquent model class used to fetch options               |
| `column` | `string` | Column on the **resource table** to filter by (e.g. `project_id`) |

```php
DependentFilter::make('Project', Project::class, 'project_id')
```

---

### `->dependsOn($parentFilter, foreignKey: ..., relationship: ...)`

Declares that this filter's options depend on another filter's selected value.
Use **one** of the two named arguments:

#### Option A: `foreignKey` (for belongsTo / direct column)

When the child model has a foreign key column pointing to the parent.

```
Client (id) <--- Project (client_id)
```

```php
$project = DependentFilter::make('Project', Project::class, 'project_id')
    ->dependsOn($client, foreignKey: 'client_id');
```

This generates: `Project::where('client_id', $selectedClientId)`

#### Option B: `relationship` (for belongsToMany / hasMany)

When the child model is connected to the parent through an Eloquent relationship.

```
Project <---> User  (pivot table: project_user)
```

```php
$user = DependentFilter::make('User', User::class, 'user_id')
    ->dependsOn($project, relationship: 'projects');
```

This generates: `User::whereHas('projects', fn($q) => $q->where('id', $selectedProjectId))`

> The `relationship` value must match the **Eloquent relationship method name** on the child model (e.g. `User::projects()`).

---

### `->scope(fn($query, $request) => ...)`

Adds a base query scope applied to **both** the initial options and the dependent (filtered) options.
Use this for permission checks, active status, or any global filtering.

```php
// Only show active users with roles
$user = DependentFilter::make('User', User::class, 'user_id')
    ->scope(fn ($query) => $query->whereHas('roles')->where('is_active', 1));

// Permission-based scoping
$project = DependentFilter::make('Project', Project::class, 'project_id')
    ->scope(function ($query, $request) {
        if ($request->user()->hasPermissionTo('viewAllProjects')) {
            // no restriction
        } else {
            $query->whereHas('users', fn ($q) => $q->where('users.id', $request->user()->id));
        }
    });
```

> The callback receives `($query, $request)`. The second argument `$request` is optional - omit it if you don't need it.

---

### `->label(column)` and `->value(column)`

Customize which model columns are used for the dropdown label and value.

| Method     | Default  | Description                          |
|------------|----------|--------------------------------------|
| `->label()`| `'name'` | Column shown as the dropdown text    |
| `->value()`| `'id'`   | Column sent as the selected value    |

```php
DependentFilter::make('Country', Country::class, 'country_code')
    ->label('full_name')
    ->value('iso_code');
```

---

## Examples

### Standalone filter (no dependencies)

Works like a regular Nova select filter - no cascading, just a cleaner syntax.

```php
DependentFilter::make('Status', Status::class, 'status_id')
```

### Two-level cascade

```php
$department = DependentFilter::make('Department', Department::class, 'department_id');

$employee = DependentFilter::make('Employee', Employee::class, 'employee_id')
    ->dependsOn($department, foreignKey: 'department_id')
    ->scope(fn ($q) => $q->where('is_active', true));

return [$department, $employee];
```

### Three-level cascade

```php
$country = DependentFilter::make('Country', Country::class, 'country_id');

$city = DependentFilter::make('City', City::class, 'city_id')
    ->dependsOn($country, foreignKey: 'country_id');

$office = DependentFilter::make('Office', Office::class, 'office_id')
    ->dependsOn($city, foreignKey: 'city_id');

return [$country, $city, $office];
```

### Conditional inclusion

```php
$client = DependentFilter::make('Client', Client::class, 'client_id');

$project = DependentFilter::make('Project', Project::class, 'project_id')
    ->dependsOn($client, foreignKey: 'client_id');

if ($request->user()->isClient()) {
    return [$project];
} else {
    return [$client, $project];
}
```

### Mixed with regular Nova filters

```php
return [
    (new StartsDateFilter())->setColumn('start'),
    (new EndsDateFilter())->setColumn('start'),
    $clientFilter,
    new TimeTrackingDepartmentFilter(),
    $projectFilter,
    $userFilter,
];
```

## License

MIT
