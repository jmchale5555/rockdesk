    </main>
    <footer class="container">
        <small><?= APP_NAME ?></small>
    </footer>
    <script defer src="<?= ROOT ?>/assets/js/trix-2-0-8.umd.min.js"></script>
    <script defer src="<?= ROOT ?>/assets/js/alpine-3-15-11.min.js"></script>
    <script defer src="<?= ROOT ?>/assets/js/htmx-2-0-10.min.js"></script>
    <script>
        document.addEventListener('trix-initialize', function (event) {
            const toolbar = event.target.toolbarElement;
            const attachButton = toolbar ? toolbar.querySelector('.trix-button--icon-attach') : null;

            if (attachButton) {
                attachButton.title = 'Browse for image';
                attachButton.setAttribute('aria-label', 'Browse for image');
            }
        });

        document.addEventListener('trix-file-accept', function (event) {
            const file = event.file;
            const editor = event.target;

            if (!editor.dataset.uploadUrl || !file.type.match(/^image\/(jpeg|png|webp)$/) || file.size > 5242880) {
                event.preventDefault();
            }
        });

        document.addEventListener('trix-attachment-add', function (event) {
            const attachment = event.attachment;
            const editor = event.target;

            if (!attachment.file || !editor.dataset.uploadUrl) {
                return;
            }

            const form = editor.closest('form');
            const csrf = form ? form.querySelector('input[name="csrf_token"]') : null;
            const data = new FormData();
            data.append('attachment', attachment.file);
            if (csrf) {
                data.append('csrf_token', csrf.value);
            }

            fetch(editor.dataset.uploadUrl, {
                method: 'POST',
                body: data,
                headers: {
                    'Accept': 'application/json'
                }
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Upload failed');
                    }
                    return response.json();
                })
                .then(function (data) {
                    attachment.setAttributes({
                        url: data.url,
                        href: data.href
                    });
                })
                .catch(function () {
                    attachment.remove();
                    alert('Image upload failed. Use JPG, PNG, or WebP images up to 5 MB.');
                });
        });

        document.addEventListener('submit', function (event) {
            const form = event.target.closest('[data-message-composer]');
            if (!form) {
                return;
            }

            const status = form.querySelector('[data-message-status]');
            const body = form.querySelector('[data-message-body]');
            const editor = form.querySelector('[data-message-editor]');
            const privateNote = form.querySelector('[data-message-private]');
            const error = form.querySelector('[data-message-error]');
            const resolved = status && status.value === 'resolved';
            const plainText = body ? body.value.replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim() : '';
            let message = '';

            if (resolved && privateNote && privateNote.checked) {
                message = 'Resolution messages cannot be private. Uncheck private note to resolve this ticket.';
            } else if (resolved && plainText === '') {
                message = 'Resolution text is required when resolving a ticket.';
            }

            if (message !== '') {
                event.preventDefault();
                if (editor) {
                    editor.classList.add('is-invalid');
                }
                if (error) {
                    error.textContent = message;
                    error.hidden = false;
                }
            }
        });

        document.addEventListener('change', function (event) {
            const form = event.target.closest('[data-message-composer]');
            if (!form) {
                return;
            }

            const status = form.querySelector('[data-message-status]');
            const privateNote = form.querySelector('[data-message-private]');
            const help = form.querySelector('[data-resolution-public-help]');
            const isResolved = status && status.value === 'resolved';

            if (help) {
                help.hidden = !isResolved;
            }

            if (!isResolved || (privateNote && !privateNote.checked)) {
                const editor = form.querySelector('[data-message-editor]');
                const error = form.querySelector('[data-message-error]');
                if (editor) {
                    editor.classList.remove('is-invalid');
                }
                if (error) {
                    error.hidden = true;
                }
            }
        });
    </script>
</body>
</html>
