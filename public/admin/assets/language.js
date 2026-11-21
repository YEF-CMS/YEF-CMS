// Switches the language of the admin as soon as another one is picked. Without this script the form has a button.
(function () {
    'use strict';

    var select = document.getElementById('language');
    if (select) {
        select.addEventListener('change', function () {
            select.form.submit();
        });
    }
}());
