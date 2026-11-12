/* Task Tracker - vanilla JS enhancements only.
   The app works without these; they add confirmation dialogs and, in the
   browser, the same length/required checks the server enforces. */
(function () {
    'use strict';

    /**
     * Show a Bootstrap modal asking for confirmation.
     * Resolves true when the user confirms, false when dismissed.
     * Falls back to window.confirm if Bootstrap is unavailable (e.g. offline CDN).
     */
    function confirmDialog(message, title) {
        if (typeof window.bootstrap === 'undefined' || !window.bootstrap.Modal) {
            return Promise.resolve(window.confirm(message));
        }

        const host = document.createElement('div');
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

        const element = host.firstElementChild;
        element.querySelector('.modal-title').textContent = title || 'Please confirm';
        element.querySelector('.modal-body').textContent = message;

        const modal = new window.bootstrap.Modal(element);
        let accepted = false;

        element.querySelector('[data-yes]').addEventListener('click', function () {
            accepted = true;
            modal.hide();
        });
        // Bootstrap removes its backdrop asynchronously - clean up afterwards.
        element.addEventListener('hidden.bs.modal', function () {
            host.remove();
            resolve(accepted);
        });

        let resolve;
        const promise = new Promise(function (r) { resolve = r; });
        modal.show();
        return promise;
    }

    // Any form carrying data-confirm asks before submitting.
    document.addEventListener('submit', function (event) {
        const form = event.target;
        const message = form.dataset.confirm;
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
})();
