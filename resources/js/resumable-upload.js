function attach(form, { submit, fields, restore, progress }) {
    if (!globalThis.crypto?.subtle || !globalThis.crypto?.randomUUID) return null;

    const root = form.dataset.transferUrl;
    const workflow = form.dataset.transferWorkflow;
    const key = 'gomovie-upload:' + form.dataset.transferOwner + ':' + workflow;
    const panel = form.querySelector('[data-transfer-controls]');
    const notice = panel.querySelector('[role="status"]');
    const pause = panel.querySelector('[data-transfer-pause]');
    const cancel = panel.querySelector('[data-transfer-cancel]');
    const submitLabel = submit.textContent;
    const submitInitiallyDisabled = submit.disabled;
    let draft = null;
    let operation = null;
    let saving = false;

    try {
        draft = JSON.parse(sessionStorage.getItem(key));
    } catch { /* Upload can still pause and resume in this page. */ }

    function remember(value) {
        draft = value;
        try {
            if (value) sessionStorage.setItem(key, JSON.stringify(value));
            else sessionStorage.removeItem(key);
        } catch { /* In-memory state still works. */ }
    }

    function render(text = '') {
        panel.hidden = !draft;
        if (draft) {
            notice.textContent = text || `Saved upload: ${draft.name}. Select the same file to resume.`;
        }
        pause.hidden = !operation || saving;
        cancel.hidden = !!operation;
        submit.textContent = draft && !operation ? 'Resume upload' : submitLabel;
    }

    async function request(url, method, body, signal, contentType) {
        const headers = {
            Accept: 'application/json',
            'X-CSRF-TOKEN': form.elements._token.value,
        };
        if (contentType) headers['Content-Type'] = contentType;
        const controller = new AbortController();
        const abort = () => controller.abort();
        signal?.addEventListener('abort', abort, { once: true });
        if (signal?.aborted) abort();
        const timer = setTimeout(() => controller.abort(), saving ? 900000 : 120000);
        let response;
        try {
            response = await fetch(url, {
                method, headers, body, signal: controller.signal, credentials: 'same-origin',
            });
        } finally {
            clearTimeout(timer);
            signal?.removeEventListener('abort', abort);
        }
        if (response.redirected) throw new Error('Your sign-in expired. Refresh this page and sign in again.');
        const data = response.status === 204 ? {} : await response.json().catch(() => ({}));
        if (!response.ok) {
            const message = {
                413: 'The server rejected an upload chunk. Check the web server upload limit.',
                419: 'Your sign-in expired. Refresh this page and sign in again.',
                429: 'Too many upload requests. Wait a minute, then resume.',
            }[response.status] || Object.values(data.errors || {}).flat()[0]
                || data.message || 'The upload request failed. Resume to try again.';
            throw Object.assign(new Error(message), { status: response.status });
        }
        return data;
    }

    async function digest(blob) {
        const hash = await crypto.subtle.digest('SHA-256', await blob.arrayBuffer());
        return Array.from(new Uint8Array(hash), byte => byte.toString(16).padStart(2, '0')).join('');
    }

    pause.addEventListener('click', () => operation?.abort());
    cancel.addEventListener('click', async () => {
        if (!draft || operation) return;
        cancel.disabled = true;
        submit.disabled = true;
        try {
            if ((await request(root + '/' + draft.id, 'GET')).completed) {
                render('The upload is already saved. Resume to open it.');
                return;
            }
            await request(root + '/' + draft.id, 'DELETE');
            remember(null);
            render();
            progress(null);
        } catch (error) {
            if (error.status === 404) {
                remember(null);
                render();
                progress(null);
            } else {
                render(error.message || 'Could not cancel the upload. Try again.');
            }
        } finally {
            cancel.disabled = false;
            submit.disabled = submitInitiallyDisabled;
        }
    });

    if (draft) restore(draft.fields);
    render();

    return {
        get active() { return !!operation || cancel.disabled; },
        shouldUse(file) { return !!draft || file.size > Number(form.dataset.directUploadBytes); },
        async send(file, payload) {
            if (draft && (file.name !== draft.name || file.size !== draft.size || file.lastModified !== draft.lastModified)) {
                throw new Error('Select the same file to resume, or cancel this transfer first.');
            }
            const values = Object.fromEntries(fields.map(field => [field, payload.get(field)]));
            remember({
                id: draft?.id || crypto.randomUUID(),
                initialized: draft?.initialized || false,
                name: file.name, size: file.size, lastModified: file.lastModified, fields: values,
            });
            operation = new AbortController();
            const signal = operation.signal;
            render('Connecting to upload storage…');

            try {
                const url = root + '/' + draft.id;
                const upload = draft.initialized
                    ? await request(url, 'GET', undefined, signal)
                    : await request(root, 'POST', JSON.stringify({
                        id: draft.id, workflow, filename: file.name, size_bytes: file.size,
                    }), signal, 'application/json');
                remember({ ...draft, initialized: true });
                let received = upload.received_bytes;

                if (!upload.completed) {
                    if (received) render('Checking the saved part of your video…');
                    for (let i = 0; i < upload.chunk_hashes.length; i++) {
                        if (signal.aborted) throw new DOMException('Paused', 'AbortError');
                        const start = i * upload.chunk_bytes;
                        const chunk = file.slice(start, Math.min(start + upload.chunk_bytes, received));
                        if (await digest(chunk) !== upload.chunk_hashes[i]) {
                            throw new Error('This file differs from the saved upload. Select the original file, or cancel this transfer.');
                        }
                    }

                    render('Uploading video…');
                    progress(Math.floor(received / file.size * 100));
                    while (received < file.size) {
                        const chunk = file.slice(received, received + upload.chunk_bytes);
                        const saved = await request(url + '?offset=' + received, 'PUT', chunk, signal, 'application/octet-stream');
                        received = saved.received_bytes;
                        progress(Math.floor(received / file.size * 100));
                    }
                }

                saving = true;
                render('Saving your video…');
                payload.delete('video');
                payload.set('transfer_id', draft.id);
                const result = await request(form.action, 'POST', payload, signal);
                if (result.ok !== true || !Number(result.upload_id)) {
                    throw new Error('The upload could not be confirmed. Resume to check its status.');
                }
                remember(null);
                return result;
            } catch (error) {
                if (signal.aborted) {
                    render('Upload paused. Resume when you are ready.');
                    return null;
                }
                if (error.status === 404 && !saving) {
                    remember(null);
                    progress(null);
                    throw new Error('The saved transfer is unavailable or expired. Start the upload again.');
                }
                render('Upload interrupted. Resume to continue.');
                if (error instanceof TypeError || error.name === 'AbortError') {
                    throw new Error('Connection interrupted. Resume to continue from the saved progress.');
                }
                throw error;
            } finally {
                const text = notice.textContent;
                operation = null;
                saving = false;
                render(text);
            }
        },
    };
}

window.GomovieResumableUpload = { attach };
