// Applies the listed changes one page at a time, so no single request has to build the whole site.
(function () {
    'use strict';

    var root = document.getElementById('apply');
    if (!root) {
        return;
    }

    var button = root.querySelector('.start');
    var rows = Array.prototype.slice.call(root.querySelectorAll('tbody tr'));
    var bar = root.querySelector('.progress-bar');
    var progress = root.querySelector('.progress');
    var result = root.querySelector('.result');
    var data = root.dataset;

    function show(row, text, tone) {
        var cell = row.querySelector('.state');
        cell.textContent = text;
        cell.className = 'state text-' + tone;
    }

    function apply(row) {
        var body = new URLSearchParams();
        body.set('page', row.dataset.page);
        body.set('_csrf', data.csrf);

        return fetch(data.stepUrl, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: {'Accept': 'application/json'}
        }).then(function (response) {
            return response.ok ? response.json() : {outcome: 'failed'};
        }).catch(function () {
            return {outcome: 'failed'};
        });
    }

    function describe(outcome) {
        switch (outcome) {
            case 'built':
                return [data.textBuilt, 'success'];
            case 'removed':
                return [data.textRemoved, 'success'];
            case 'nothing_to_do':
            case 'skipped':
                return [data.textSkipped, 'body-secondary'];
            default:
                return [data.textFailed, 'danger'];
        }
    }

    async function run() {
        var failures = 0;
        button.disabled = true;
        progress.classList.remove('d-none');

        for (var index = 0; index < rows.length; index++) {
            show(rows[index], '…', 'body-secondary');
            var answer = await apply(rows[index]);
            var shown = describe(answer.outcome);
            show(rows[index], shown[0], shown[1]);
            if (answer.outcome === 'failed' || answer.outcome === 'invalid') {
                failures++;
            }
            bar.style.width = Math.round(((index + 1) / rows.length) * 100) + '%';
        }

        result.className = 'result mb-3 alert alert-' + (failures === 0 ? 'success' : 'danger');
        result.textContent = failures === 0 ? data.textDone : data.textDoneWithFailures;
        button.disabled = failures === 0;
    }

    button.addEventListener('click', function () {
        run();
    });
}());
