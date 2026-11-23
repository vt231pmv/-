<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\CreditTypeController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\TestController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test', [TestController::class, 'test']);

// Lab 2 CRUD routes for Product
Route::get('/products', [ProductController::class, 'getProducts']);
Route::get('/products/{id}', [ProductController::class, 'getProductItem']);
Route::post('/products', [ProductController::class, 'createProduct']);
Route::match(['put', 'patch'], '/products/{id}', [ProductController::class, 'updateProduct']);
Route::delete('/products/{id}', [ProductController::class, 'deleteProduct']);

// Lab 3 Banking Credit System API + Lab 5 JWT Auth & RBAC
Route::prefix('api')->group(function () {
    // Auth routes (public)
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login',    [AuthController::class, 'login']);

        Route::middleware('jwt.auth')->group(function () {
            Route::get('/me',       [AuthController::class, 'me']);
            Route::post('/logout',  [AuthController::class, 'logout']);
            Route::post('/refresh', [AuthController::class, 'refresh']);
        });
    });

    // Protected API routes
    Route::middleware('jwt.auth')->group(function () {
        // 1. Clients
        Route::get('/clients',         [ClientController::class, 'index']);
        Route::get('/clients/{id}',    [ClientController::class, 'show']);
        Route::middleware('role:manager,admin')->group(function () {
            Route::post('/clients',       [ClientController::class, 'store']);
            Route::match(['put', 'patch'], '/clients/{id}', [ClientController::class, 'update']);
        });
        Route::middleware('role:admin')->group(function () {
            Route::delete('/clients/{id}', [ClientController::class, 'destroy']);
        });

        // 2. Bank Accounts
        Route::get('/bank-accounts',         [BankAccountController::class, 'index']);
        Route::get('/bank-accounts/{id}',    [BankAccountController::class, 'show']);
        Route::middleware('role:manager,admin')->group(function () {
            Route::post('/bank-accounts',       [BankAccountController::class, 'store']);
            Route::match(['put', 'patch'], '/bank-accounts/{id}', [BankAccountController::class, 'update']);
        });
        Route::middleware('role:admin')->group(function () {
            Route::delete('/bank-accounts/{id}', [BankAccountController::class, 'destroy']);
        });

        // 3. Credit Types
        Route::get('/credit-types',         [CreditTypeController::class, 'index']);
        Route::get('/credit-types/{id}',    [CreditTypeController::class, 'show']);
        Route::middleware('role:manager,admin')->group(function () {
            Route::post('/credit-types',       [CreditTypeController::class, 'store']);
            Route::match(['put', 'patch'], '/credit-types/{id}', [CreditTypeController::class, 'update']);
        });
        Route::middleware('role:admin')->group(function () {
            Route::delete('/credit-types/{id}', [CreditTypeController::class, 'destroy']);
        });

        // 4. Credits
        Route::get('/credits',         [CreditController::class, 'index']);
        Route::get('/credits/{id}',    [CreditController::class, 'show']);
        Route::middleware('role:manager,admin')->group(function () {
            Route::post('/credits',       [CreditController::class, 'store']);
            Route::match(['put', 'patch'], '/credits/{id}', [CreditController::class, 'update']);
        });
        Route::middleware('role:admin')->group(function () {
            Route::delete('/credits/{id}', [CreditController::class, 'destroy']);
        });

        // 5. Payments
        Route::get('/payments',         [PaymentController::class, 'index']);
        Route::get('/payments/{id}',    [PaymentController::class, 'show']);
        Route::middleware('role:manager,admin')->group(function () {
            Route::post('/payments',       [PaymentController::class, 'store']);
            Route::match(['put', 'patch'], '/payments/{id}', [PaymentController::class, 'update']);
        });
        Route::middleware('role:admin')->group(function () {
            Route::delete('/payments/{id}', [PaymentController::class, 'destroy']);
        });
    });
});
