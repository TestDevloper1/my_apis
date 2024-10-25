<?php

use App\Http\Controllers\AppApi\DownloaderController as ApiController;
use App\Http\Middleware\Api\ApiMiddleware as ApiMiddleware;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('403');
});

Route::get('scrape', [ApiController::class, 'scrape']);
Route::get('twitterscrape', [ApiController::class, 'twitterscrape']);
Route::get('facebookscrape', [ApiController::class, 'facebookscrape']);
Route::get('youtubescrape', [ApiController::class, 'youtubescrape']);
Route::get('tiktok', [ApiController::class, 'tiktok']);
Route::get('test', [ApiController::class, 'test']);


Route::middleware([ApiMiddleware::class])->group(function () {

});

/**************** Routing For Clear Cache Start *****************/
Route::any('/clear', function () {
    Artisan::call('route:cache');
    Artisan::call('config:cache');
    Artisan::call('cache:clear');
    Artisan::call('view:clear');
    Artisan::call('optimize:clear');
    return 'Cache Cleared';
});
/**************** Routing For Clear Cache End *****************/
