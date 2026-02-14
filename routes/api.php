<?php

use Illuminate\Support\Facades\Route;
use JenryMA\DependentFilter\Http\Controllers\DependentFilterController;

/*
|--------------------------------------------------------------------------
| Tool API Routes
|--------------------------------------------------------------------------
|
| Here is where you may register API routes for your tool. These routes
| are loaded by the ServiceProvider of your tool. You're free to add
| as many additional routes to this file as your tool may require.
|
*/

// Dependent Filter Routes
Route::get('/dependent-filter-options', DependentFilterController::class);
