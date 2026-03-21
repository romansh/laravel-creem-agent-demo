<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => 'Creem Simulator',
        'api_base' => '/api/v1',
        'stores' => array_keys(config('simulator.stores', [])),
    ]);
});

Route::get('/portal/{customer}', function (string $customer) {
    return response()->json([
        'customer_id' => $customer,
        'message' => 'Simulator billing portal placeholder',
    ]);
});

Route::get('/checkout/{session}', function (string $session) {
    return response()->json([
        'checkout_session' => $session,
        'message' => 'Simulator checkout placeholder',
    ]);
});
