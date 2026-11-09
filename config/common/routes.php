<?php

declare(strict_types=1);

use Yef\Admin;
use Yiisoft\Auth\Middleware\Authentication;
use Yiisoft\Router\Group;
use Yiisoft\Router\Route;
use Yiisoft\Yii\RateLimiter\LimitRequestsMiddleware;

// Addresses are relative to the admin entry point: "/login" is /admin/login, or /admin/index.php/login without rewriting.
return [
    Route::get('/login')->action(Admin\Login\Action::class)->name('admin.login'),
    Route::post('/login')
        ->middleware(LimitRequestsMiddleware::class)
        ->action(Admin\Login\Action::class)
        ->name('admin.login.submit'),
    Route::get('/setup')->action(Admin\Setup\Action::class)->name('admin.setup'),
    Route::post('/setup')
        ->middleware(LimitRequestsMiddleware::class)
        ->action(Admin\Setup\Action::class)
        ->name('admin.setup.submit'),
    Group::create()
        ->middleware(Authentication::class)
        ->routes(
            Route::get('/')->action(Admin\Dashboard\Action::class)->name('admin.dashboard'),
            Route::post('/logout')->action(Admin\Logout\Action::class)->name('admin.logout'),
            Route::get('/pages')->action(Admin\Pages\ListAction::class)->name('admin.pages'),
            Route::methods(['GET', 'POST'], '/pages/new')
                ->action(Admin\Pages\NewAction::class)
                ->name('admin.pages.new'),
            Route::methods(['GET', 'POST'], '/pages/{id}/edit')
                ->action(Admin\Pages\EditAction::class)
                ->name('admin.pages.edit'),
            Route::post('/pages/{id}/delete')->action(Admin\Pages\DeleteAction::class)->name('admin.pages.delete'),
        ),
];
