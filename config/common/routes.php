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
            Route::post('/pages/preview')->action(Admin\Pages\PreviewAction::class)->name('admin.pages.preview'),
            Route::methods(['GET', 'POST'], '/pages/{id}/edit')
                ->action(Admin\Pages\EditAction::class)
                ->name('admin.pages.edit'),
            Route::post('/pages/{id}/delete')->action(Admin\Pages\DeleteAction::class)->name('admin.pages.delete'),
            Route::get('/modules')->action(Admin\Modules\ListAction::class)->name('admin.modules'),
            Route::methods(['GET', 'POST'], '/modules/install')
                ->action(Admin\Modules\InstallFormAction::class)
                ->name('admin.modules.install'),
            Route::get('/modules/offer/{token}')->action(Admin\Modules\OfferAction::class)->name('admin.modules.offer'),
            // Confirming asks for the password again, so guessing it is limited like signing in.
            Route::post('/modules/offer/{token}')
                ->middleware(LimitRequestsMiddleware::class)
                ->action(Admin\Modules\OfferAction::class)
                ->name('admin.modules.offer.confirm'),
            Route::post('/modules/check-updates')->action(Admin\Modules\CheckUpdatesAction::class)->name('admin.modules.check_updates'),
            Route::post('/modules/{name}/update')->action(Admin\Modules\PrepareUpdateAction::class)->name('admin.modules.update'),
            Route::post('/modules/{name}/remove')->action(Admin\Modules\RemoveAction::class)->name('admin.modules.remove'),
            Route::post('/modules/{name}/enable')->action(Admin\Modules\EnableAction::class)->name('admin.modules.enable'),
            Route::post('/modules/{name}/disable')->action(Admin\Modules\DisableAction::class)->name('admin.modules.disable'),
            Route::post('/language')->action(Admin\Language\Action::class)->name('admin.language'),
            Route::get('/apply')->action(Admin\Apply\ScreenAction::class)->name('admin.apply'),
            Route::post('/apply/step')->action(Admin\Apply\StepAction::class)->name('admin.apply.step'),
        ),
];
