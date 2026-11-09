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

    'common.save' => 'Save',
    'common.cancel' => 'Cancel',
    'common.back' => 'Back',
    'common.close' => 'Close',

    'nav.pages' => 'Pages',

    'pages.title' => 'Pages',
    'pages.new' => 'New page',
    'pages.edit' => 'Edit page',
    'pages.delete' => 'Delete',
    'pages.empty' => 'The site has no pages yet.',
    'pages.of_type' => 'Type: {type}',
    'pages.apply_hint' => 'Saved changes appear on the public site after they are applied. For now run "php bin/yef yef:apply" on the server; a button for it will come to this screen.',
    'pages.no_types' => 'There are no page types to choose from: no module that adds a content type is enabled.',
    'pages.choose_type' => 'What kind of page is it?',
    'pages.type_unavailable' => 'Type unavailable',
    'pages.unavailable' => 'The module that provides the type of this page is not enabled, so the page cannot be edited. Its data is kept and it works again when the module is enabled. You can still delete it.',
    'pages.created' => 'The page is created.',
    'pages.saved' => 'The page is saved.',
    'pages.deleted' => 'The page is deleted.',
    'pages.column.title' => 'Title',
    'pages.column.address' => 'Address',
    'pages.column.type' => 'Type',
    'pages.column.status' => 'Status',
    'pages.column.updated' => 'Updated',
    'pages.status.published' => 'Published',
    'pages.status.draft' => 'Draft',
    'pages.field.title' => 'Title',
    'pages.field.address' => 'Address',
    'pages.field.address_hint' => 'Lowercase letters, digits and hyphens; use / for sections, for example blog/hello. Leave empty for the home page.',
    'pages.field.seo' => 'Search engines',
    'pages.field.meta_title' => 'Title for search engines',
    'pages.field.meta_description' => 'Description for search engines',
    'pages.field.published' => 'Published',
    'pages.field.published_hint' => 'Drafts are not shown on the public site.',
    'pages.delete_title' => 'Delete the page?',
    'pages.delete_question' => 'Delete the page "{title}"? This cannot be undone.',
    'pages.delete_note' => 'Its address stays on the public site until the changes are applied.',
    'pages.delete_confirm' => 'Delete page',
    'pages.error.title.empty' => 'Enter a title.',
    'pages.error.path.too_long' => 'The address is too long: at most {limit} characters.',
    'pages.error.path.bad_segment' => 'Use lowercase latin letters, digits and hyphens, separated by "/". No spaces, no slash at the start or end.',
    'pages.error.path.reserved' => 'This address starts with a name the system keeps for itself (admin, api, assets, data).',
    'pages.error.path.taken' => 'Another page already has this address.',
    'pages.error.invalid' => 'Something in the form is not valid.',

    'dashboard.title' => 'Dashboard',
    'dashboard.welcome' => 'Welcome, {login}.',
    'dashboard.pages_total' => 'Pages',
    'dashboard.pages_published' => 'Published',
    'dashboard.pages_drafts' => 'Drafts',
];
