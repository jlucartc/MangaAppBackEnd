<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('insertMangas','Api@insertMangas')->name('insertMangas');

Route::post('insertChapters','Api@insertChapters')->name('insertChapters');

Route::post('checkUpdates','Api@checkUpdates')->name('checkUpdates');

Route::post('getUpdates','Api@getUpdates')->name('getUpdates');

Route::get('getMangaList','Api@getMangaList')->name('getMangaList');

Route::post('search','Api@search')->name('search');

Route::post('getManga','Api@getManga')->name('getManga');

Route::post('getPages','Api@getPages')->name('getPages');

Route::get('responseFormats','Api@getResponseFormats')->name('responseFormats');

Route::get('requestFormats','Api@getRequestFormats')->name('requestFormats');