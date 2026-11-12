/* Task Tracker - progressive enhancement only.
   Everything here (confirm dialogs, inline validation, character counters,
   auto-submit status dropdowns) makes the UI nicer. The server independently
   enforces the same rules, so the app is correct with JavaScript disabled. */
(function () {
    'use strict';

    // Mirror of the server-side whitelists in TaskRepository.
    var STATUSES = ['Pending', 'In Progress', 'Completed'];
    var PRIORITIES = ['Low', 'Medium', 'High'];
    var MAX_TITLE = 150;
    var MAX_DESCRIPTION = 2000;

    /**
     * Show a Bootstrap modal asking for confirmation.
     * Resolves true when the user confirms, false when dismissed.
     * Falls back to window.confirm if Bootstrap is unavailable (e.g. offline CDN).
     */
    function confirmDialog(message, title) {
        if (typeof window.bootstrap === 'undefined' || !window.bootstrap.Modal) {
            return Promise.resolve(window.confirm(message));
        }

        var host = document.createElement('div');
        // Static markup only - the dynamic text below is set with textContent,
        // so a task title can never be interpreted as HTML here.
        host.innerHTML =
            '<div class="modal fade" tabindex="-1">' +
            '  <div class="modal-dialog modal-dialog-centered modal-sm">' +
            '    <div class="modal-content">' +
            '      <div class="modal-header"><h5 class="modal-title"></h5>' +
            '        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>' +
            '      <div class="modal-body"></div>' +
            '      <div class="modal-footer">' +
            '        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>' +
            '        <button type="button" class="btn btn-danger" data-yes>Yes, delete</button>' +
            '      </div>' +
            '    </div>' +
            '  </div>' +
            '</div>';
        document.body.appendChild(host);

        var element = host.firstElementChild;
        element.querySelector('.modal-title').textContent = title || 'Please confirm';
        element.querySelector('.modal-body').textContent = message;

        var modal = new window.bootstrap.Modal(element);
        var accepted = false;
        var resolve;
        var promise = new Promise(function (r) { resolve = r; });

        element.querySelector('[data-yes]').addEventListener('click', function () {
            accepted = true;
            modal.hide();
        });
        // Bootstrap removes its backdrop asynchronously - clean up afterwards.
        element.addEventListener('hidden.bs.modal', function () {
            host.remove();
            resolve(accepted);
        });

        modal.show();
        return promise;
    }

    // Any form carrying data-confirm asks before submitting.
    document.addEventListener('submit', function (event) {
        var form = event.target;
        var message = form.dataset.confirm;
        if (!message || form.dataset.confirmed === '1') {
            return;
        }
        event.preventDefault();
        confirmDialog(message).then(function (ok) {
            if (ok) {
                form.dataset.confirmed = '1';
                form.submit();
            }
        });
    });

    // ---------------------------------------------------- field validation --
    function fieldError(name, message) {
        var control = document.getElementById(name);
        var feedback = document.querySelector('[data-error-for="' + name + '"]');
        if (!control) {
            return;
        }
        if (message) {
            control.classList.add('is-invalid');
            if (feedback) {
                feedback.classList.remove('d-none');
                feedback.textContent = message;
            }
        } else {
            control.classList.remove('is-invalid');
            if (feedback) {
                feedback.classList.add('d-none');
            }
        }
    }

    function validDate(value) {
        if (!value) {
            return true; // optional
        }
        var parts = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
        if (!parts) {
            return false;
        }
        var date = new Date(value + 'T00:00:00');
        return !isNaN(date.getTime()) && date.getFullYear() === Number(parts[1])
            && date.getMonth() + 1 === Number(parts[2])
            && date.getDate() === Number(parts[3]);
    }

    function validateTaskForm(form) {
        var errors = 0;

        var title = form.title.value.trim();
        if (title === '') {
            fieldError('title', 'Please give the task a title.');
            errors++;
        } else if (title.length > MAX_TITLE) {
            fieldError('title', 'Title must be ' + MAX_TITLE + ' characters or fewer (currently ' + title.length + ').');
            errors++;
        } else {
            fieldError('title', '');
        }

        var description = form.description.value.trim();
        if (description.length > MAX_DESCRIPTION) {
            fieldError('description', 'Description must be ' + MAX_DESCRIPTION + ' characters or fewer (currently ' + description.length + ').');
            errors++;
        } else {
            fieldError('description', '');
        }

        fieldError('status', STATUSES.indexOf(form.status.value) === -1 ? 'Please choose a valid status.' : '');
        if (STATUSES.indexOf(form.status.value) === -1) {
            errors++;
        }

        fieldError('priority', form.priority.value && PRIORITIES.indexOf(form.priority.value) === -1
            ? 'Please choose a valid priority.' : '');
        if (form.priority.value && PRIORITIES.indexOf(form.priority.value) === -1) {
            errors++;
        }

        if (!validDate(form.due_date.value)) {
            fieldError('due_date', 'Due date must be a real date in YYYY-MM-DD format.');
            errors++;
        } else {
            fieldError('due_date', '');
        }

        return errors === 0;
    }

    document.querySelectorAll('form[data-validate="task"]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!validateTaskForm(form)) {
                event.preventDefault();
                var firstInvalid = form.querySelector('.is-invalid');
                if (firstInvalid) {
                    firstInvalid.focus();
                }
            }
        });

        // Live character counters.
        [['title', MAX_TITLE], ['description', MAX_DESCRIPTION]].forEach(function (pair) {
            var control = form.elements[pair[0]];
            var counter = form.querySelector('[data-counter-for="' + pair[0] + '"]');
            if (!control || !counter) {
                return;
            }
            var update = function () {
                counter.textContent = String(control.value.length);
                counter.closest('.form-text').classList.toggle('text-danger', control.value.length > pair[1]);
            };
            control.addEventListener('input', update);
            update();
        });
    });

    // ------------------------------------------------- registration form ---
    document.querySelectorAll('form[data-validate="register"]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var errors = 0;
            var name = form.name.value.trim();
            var email = form.email.value.trim();
            var password = form.password.value;
            var confirm = form.password_confirm.value;

            fieldError('name', (name === '' || name.length > 100)
                ? 'Please enter a name between 1 and 100 characters.' : '');
            if (name === '' || name.length > 100) { errors++; }

            fieldError('email', /^[^\s@]+@[^\s@.]+\.[^\s@]{2,}$/.test(email) ? '' : 'Please enter a valid email address.');
            if (!/^[^\s@]+@[^\s@.]+\.[^\s@]{2,}$/.test(email)) { errors++; }

            fieldError('password', (password.length < 8 || password.length > 200)
                ? 'Password must be between 8 and 200 characters.' : '');
            if (password.length < 8 || password.length > 200) { errors++; }

            fieldError('password_confirm', password === confirm ? '' : 'The two passwords do not match.');
            if (password !== confirm) { errors++; }

            if (errors > 0) {
                event.preventDefault();
                var firstInvalid = form.querySelector('.is-invalid');
                if (firstInvalid) { firstInvalid.focus(); }
            }
        });
    });

    // ------------------------------------------------ auto-submit dropdown --
    // The "Set" button is the no-JavaScript fallback; hide it when JS is running.
    document.querySelectorAll('[data-autosubmit]').forEach(function (select) {
        var fallback = select.parentElement.querySelector('[data-no-js]');
        if (fallback) {
            fallback.classList.add('d-none');
        }
        select.addEventListener('change', function () {
            select.closest('form').submit();
        });
    });
})();
