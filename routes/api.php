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

// Protected routes (dengan JWT)
Route::middleware(['jwt.custom.auth'])->group(function () {
    // User info & logout
    Route::get('/user', [UserController::class, 'user']);
    Route::post('/logout', [UserController::class, 'logout']);

    // Checklist View
    Route::get('/checklists', [ChecklistController::class, 'index']);
    Route::get('/checklists/completed', [ChecklistController::class, 'completedChecklists']);
    Route::get('/checklists/today', [ChecklistController::class, 'todayChecklists']);
    Route::get('/checklists/weekly', [ChecklistController::class, 'weeklyChecklists']);


    // Checklist CRUD
    Route::post('/checklists', [ChecklistController::class, 'store']);
    Route::get('/checklists/{id}', [ChecklistController::class, 'show']);
    Route::put('/checklists/{id}', [ChecklistController::class, 'update']);
    Route::delete('/checklists/{id}', [ChecklistController::class, 'destroy']);
    Route::patch('/checklists/{id}/restore', [ChecklistController::class, 'restore']);

    // Checklist Completion
    Route::post('/checklists/{id}/complete', [ChecklistController::class, 'markAsComplete']);
    Route::post('/checklists/{id}/uncomplete', [ChecklistController::class, 'unmarkAsComplete']);
});
