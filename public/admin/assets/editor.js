// Turns every rich text field into a CKEditor 5 editor and lets the user pick how much the editor offers.
// The format is only a way of editing: what is saved is the HTML of the field, nothing remembers the format.
// Without this script the field stays a plain textarea.
(function () {
    'use strict';

    var CK = window.CKEDITOR;
    var bars = document.querySelectorAll('[data-yef-format-bar]');
    if (!CK || bars.length === 0) {
        return;
    }

    var RANK = {minimal: 0, standard: 1, full: 2};

    function plugins(names) {
        return names.map(function (name) {
            return CK[name];
        });
    }

    var MINIMAL = {
        plugins: ['Essentials', 'Paragraph', 'Bold', 'Italic', 'Underline', 'Link', 'AutoLink', 'List', 'Image',
            'ImageInsertViaUrl', 'ImageToolbar', 'ImageTextAlternative', 'AutoImage', 'PasteFromOffice'],
        toolbar: ['bold', 'italic', 'underline', '|', 'bulletedList', 'numberedList', '|', 'link', 'insertImageViaUrl'],
        extra: {image: {toolbar: ['imageTextAlternative']}}
    };

    var STANDARD = {
        plugins: MINIMAL.plugins.concat(['Heading', 'BlockQuote', 'Table', 'TableToolbar', 'SourceEditing', 'Autoformat']),
        toolbar: ['undo', 'redo', '|', 'heading', '|', 'bold', 'italic', 'underline', '|', 'link', '|',
            'bulletedList', 'numberedList', '|', 'blockQuote', 'insertTable', 'insertImageViaUrl', '|', 'sourceEditing'],
        extra: {
            image: {toolbar: ['imageTextAlternative']},
            table: {contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells']}
        }
    };

    var FULL = {
        plugins: ['Essentials', 'Paragraph', 'Heading', 'Bold', 'Italic', 'Underline', 'Strikethrough', 'Subscript',
            'Superscript', 'Code', 'RemoveFormat', 'Link', 'AutoLink', 'List', 'ListProperties', 'TodoList',
            'BlockQuote', 'CodeBlock', 'Table', 'TableToolbar', 'TableProperties', 'TableCellProperties',
            'TableColumnResize', 'Image', 'ImageToolbar', 'ImageCaption', 'ImageStyle', 'ImageResize',
            'ImageTextAlternative', 'ImageInsertViaUrl', 'AutoImage', 'FontFamily', 'FontSize', 'FontColor',
            'FontBackgroundColor', 'Highlight', 'Alignment', 'Indent', 'IndentBlock', 'HorizontalLine',
            'SpecialCharacters', 'SpecialCharactersEssentials', 'FindAndReplace', 'ShowBlocks', 'PageBreak',
            'Autoformat', 'PasteFromOffice', 'SourceEditing', 'GeneralHtmlSupport', 'HtmlEmbed'],
        toolbar: {
            items: ['undo', 'redo', '|', 'sourceEditing', 'showBlocks', 'findAndReplace', '|', 'heading', '|',
                'fontFamily', 'fontSize', 'fontColor', 'fontBackgroundColor', 'highlight', '|',
                'bold', 'italic', 'underline', 'strikethrough', 'subscript', 'superscript', 'code', 'removeFormat', '|',
                'link', 'insertImageViaUrl', 'insertTable', 'blockQuote', 'codeBlock', 'htmlEmbed',
                'horizontalLine', 'pageBreak', 'specialCharacters', '|',
                'alignment', '|', 'bulletedList', 'numberedList', 'todoList', 'outdent', 'indent'],
            shouldNotGroupWhenFull: true
        },
        extra: {
            image: {toolbar: ['toggleImageCaption', 'imageTextAlternative', '|', 'imageStyle:inline', 'imageStyle:block',
                'imageStyle:side']},
            table: {
                contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells', 'tableProperties', 'tableCellProperties']
            },
            // Nothing the user pasted or typed in the source is thrown away.
            htmlSupport: {allow: [{name: /.*/, attributes: true, classes: true, styles: true}]},
            htmlEmbed: {showPreviews: false}
        }
    };

    var PROFILES = {minimal: MINIMAL, standard: STANDARD, full: FULL};

    function configOf(format) {
        var profile = PROFILES[format];

        return Object.assign({
            licenseKey: 'GPL',
            plugins: plugins(profile.plugins),
            toolbar: profile.toolbar
        }, profile.extra);
    }

    function loadContentStyles(bar) {
        var site = document.createElement('link');
        site.rel = 'stylesheet';
        site.href = bar.dataset.siteCss;
        // The site owner's own styles are optional; the neutral ones stand in when there are none.
        site.onerror = function () {
            site.remove();
            var fallback = document.createElement('link');
            fallback.rel = 'stylesheet';
            fallback.href = bar.dataset.fallbackCss;
            document.head.appendChild(fallback);
        };
        document.head.appendChild(site);
    }

    function mount(bar) {
        var textarea = bar.parentNode.querySelector('textarea[data-yef-rich-text]');
        if (!textarea) {
            return;
        }

        var current = bar.dataset.default;
        var editor = null;
        // What a narrower format made of the text, so that going back to a wider one without editing gives it back whole.
        var narrowed = null;

        // The browser would refuse to submit a required field it cannot focus; the server checks the field anyway.
        textarea.removeAttribute('required');

        var select = document.createElement('select');
        select.className = 'form-select form-select-sm w-auto';
        select.id = 'format-' + textarea.id;
        ['minimal', 'standard', 'full'].forEach(function (format) {
            var option = document.createElement('option');
            option.value = format;
            option.textContent = bar.dataset[format];
            select.appendChild(option);
        });
        select.value = current;

        var label = document.createElement('label');
        label.className = 'form-label mb-0 me-2';
        label.htmlFor = select.id;
        label.textContent = bar.dataset.label;

        bar.appendChild(label);
        bar.appendChild(select);
        bar.classList.remove('d-none');
        bar.classList.add('d-flex', 'align-items-center');

        function create(format) {
            return CK.ClassicEditor.create(textarea, configOf(format)).then(function (created) {
                editor = created;
                current = format;
            });
        }

        select.addEventListener('change', function () {
            var next = select.value;
            var narrowing = RANK[next] < RANK[current];
            if (narrowing && !window.confirm(bar.dataset.confirm)) {
                select.value = current;
                return;
            }

            var before = editor.getData();
            var restore = !narrowing && narrowed !== null && before === narrowed.after && RANK[next] >= narrowed.from;
            var html = restore ? narrowed.html : before;
            var from = current;
            select.disabled = true;

            editor.destroy().then(function () {
                textarea.value = html;

                return create(next);
            }).then(function () {
                if (narrowing) {
                    narrowed = {html: html, after: editor.getData(), from: RANK[from]};
                } else if (restore) {
                    narrowed = null;
                }
                select.disabled = false;
            });
        });

        loadContentStyles(bar);
        create(current);
    }

    Array.prototype.forEach.call(bars, mount);
}());
