<?php
/**
 * Created by PhpStorm.
 * User: nisamudheen
 * Date: 9/5/17
 * Time: 6:05 PM
 */

Route::any('podio/hooks/{appId}', 'Phases\PodioDataApi\PodioController@mangeAppHook')->name('podio_hooks_app_id');
Route::get('storage/{appName}/{id}/{filename}','Phases\PodioDataApi\PodioController@showImage')->name('show_image');

Route::get('api_token/{email}','Phases\PodioDataApi\PodioController@showApiToken')->name('show_api_token');

/**
 * API calls
 */
Route::group(['prefix' => 'api', 'middleware' => 'auth:api'], function () {
    Route::any('/items/{appName}/{paginate?}', 'Phases\PodioDataApi\PodioController@getItems')->name('api_items_app_name');
});

/**
 * Initial setup routes
 */
Route::group(['prefix' => 'sync', 'middleware' => 'auth:api'], function () {
    Route::get('/apps', 'Phases\PodioDataApi\PodioController@syncPodioApps')->name('sync_apps');
    Route::get('/podio/api/credentials', 'Phases\PodioDataApi\PodioController@syncPodioAuthCredentials')->name('sync_podio_api_credentials');
    Route::get('/hooks','Phases\PodioDataApi\PodioController@syncPodioHooks')->name('sync_hooks');
    Route::get('/app/data', 'Phases\PodioDataApi\PodioController@syncAppData')->name('sync_app_data');
});