<?php

use Illuminate\Support\Facades\Route;

Route::get('/api-test', function() {
    return redirect('api/test');
});
Route::get('/', function () {
    return view('welcome');
});
