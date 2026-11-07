<?php

declare(strict_types=1);

use Yef\Admin;
use Yiisoft\Router\Group;
use Yiisoft\Router\Route;

return [
    Group::create('/admin')
        ->routes(
            Route::get('/')
                ->action(Admin\Dashboard\Action::class)
                ->name('admin.dashboard'),
        ),
];
