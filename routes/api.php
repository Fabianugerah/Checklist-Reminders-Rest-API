<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ChecklistController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::prefix('auth')->group(function () {
    // Public routes (tanpa JWT)
    Route::post('/register', [UserController::class, 'register']);
    Route::post('/login', [UserController::class, 'login']);
});

// Protected routes (wajib pakai JWT)
Route::middleware(['jwt.custom.auth'])->group(function () {
    // User info & logout
    Route::get('/user', [UserController::class, 'user']);
    Route::post('/logout', [UserController::class, 'logout']);

    // Checklist CRUD
    Route::get('/checklists', [ChecklistController::class, 'index']);
    Route::post('/checklists', [ChecklistController::class, 'store']);
    Route::get('/checklists/{id}', [ChecklistController::class, 'show']);
    Route::put('/checklists/{id}', [ChecklistController::class, 'update']);
    Route::delete('/checklists/{id}', [ChecklistController::class, 'destroy']);
    Route::post('/checklists/{id}/restore', [ChecklistController::class, 'restore']);


    // Checklist completion
    Route::post('/checklists/{id}/complete', [ChecklistController::class, 'markAsComplete']);
    Route::post('/checklists/{id}/uncomplete', [ChecklistController::class, 'unmarkAsComplete']);
    Route::post('/checklists/{id}/repeat-days/{day}/complete', [ChecklistController::class, 'markRepeatDayAsComplete']);
    Route::post('/checklists/{id}/repeat-days/{day}/uncomplete', [ChecklistController::class, 'unmarkRepeatDayAsComplete']);
});
