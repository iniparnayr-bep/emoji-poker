<?php

use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\GameSessionController;
use App\Http\Controllers\Api\PlayerController;
use Illuminate\Support\Facades\Route;

// ── Session management ────────────────────────────────────────────────────────
Route::post('/sessions',                        [GameSessionController::class, 'create']);
Route::get('/sessions/stats',                   [GameSessionController::class, 'stats']);
Route::get('/sessions/{token}',                 [GameSessionController::class, 'show']);
Route::post('/sessions/{token}/join',           [GameSessionController::class, 'join']);
Route::put('/sessions/{token}/open',            [GameSessionController::class, 'setOpen']);
Route::put('/sessions/{token}/estimate',        [GameSessionController::class, 'estimate']);
Route::put('/sessions/{token}/settings',        [GameSessionController::class, 'updateSettings']);
Route::post('/sessions/{token}/kick',           [GameSessionController::class, 'kick']);
Route::put('/sessions/{token}/make-admin',      [GameSessionController::class, 'makeAdmin']);

// ── Player management ─────────────────────────────────────────────────────────
Route::get('/players/{playerToken}',            [PlayerController::class, 'show']);
Route::put('/players/{playerToken}',            [PlayerController::class, 'update']);
Route::put('/players/{playerToken}/leave',      [PlayerController::class, 'leave']);

// ── Chat, AI, images, emoji ───────────────────────────────────────────────────
Route::get('/sessions/{token}/messages',        [ChatController::class, 'index']);
Route::post('/sessions/{token}/chat',           [ChatController::class, 'send']);
Route::post('/sessions/{token}/images',         [ChatController::class, 'uploadImage']);
Route::post('/sessions/{token}/throw',          [ChatController::class, 'throwEmoji']);
Route::post('/sessions/{token}/shake',          [ChatController::class, 'shake']);
