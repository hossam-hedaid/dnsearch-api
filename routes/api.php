<?php

use App\Http\Controllers\DropcatchExtractorController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('dropcatch-extractor', [DropcatchExtractorController::class, 'store']);
Route::get('test', function (Request $request) {
    return "test";
});
