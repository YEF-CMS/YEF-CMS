<?php

declare(strict_types=1);

/**
 * Texts of the admin in English, which is also what other languages fall back to.
 * Placeholders are written {like_this}.
 */
return [
    'app.name' => 'YEF CMS',

    'nav.dashboard' => 'Dashboard',
    'nav.logout' => 'Sign out',
    'nav.signed_in_as' => 'Signed in as {login}',

    'login.title' => 'Sign in',
    'login.login' => 'Login',
    'login.password' => 'Password',
    'login.submit' => 'Sign in',
    'login.failed' => 'The login or the password is wrong.',

    'setup.title' => 'Set up the site',
    'setup.intro' => 'The site has no users yet. Create the first one; they will be able to manage everything.',
    'setup.code' => 'Setup code',
    'setup.code_hint' => 'To prove you own this site, open the file {file} on the server (over FTP or in the hosting file manager) and copy the code from it.',
    'setup.password_hint' => 'At least {min} characters.',
    'setup.submit' => 'Create user and sign in',
    'setup.error.invalid_code' => 'The setup code is not right.',
    'setup.error.invalid_login' => 'The login must have 2 to 32 characters: letters, digits, dots, dashes and underscores.',

    'error.password_too_short' => 'The password must have at least {limit} characters.',
    'error.password_too_long' => 'The password must not be longer than {limit} bytes.',

    'dashboard.title' => 'Dashboard',
    'dashboard.welcome' => 'Welcome, {login}.',
    'dashboard.intro' => 'Pages, modules and publishing will appear here.',
];
