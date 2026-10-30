let closeOpenEnhancedSelect = null;
let enhancedSelectIndex = 0;
let pageCleanups = [];
let initializedPageUrl = null;
let livewireNavigationPending = false;

const cleanupPage = () => {
    closeOpenEnhancedSelect?.();
    pageCleanups.splice(0).forEach((cleanup) => {
        try { cleanup(); } catch (_) { /* A departed page may already have released this resource. */ }
    });
};

const addPageListener = (target, eventName, listener, options) => {
    if (!target) return;
    target.addEventListener(eventName, listener, options);
    pageCleanups.push(() => target.removeEventListener(eventName, listener, options));
};

// Blade pages can register document/window listeners through this bridge. They
// are removed before Livewire replaces the page, preventing stale handlers.
window.MinistryPage = { addListener: addPageListener };

const setPageInterval = (callback, delay) => {
    const timer = window.setInterval(callback, delay);
    pageCleanups.push(() => window.clearInterval(timer));
    return timer;
};

const initializePage = (force = false) => {
    const pageUrl = window.location.href;
    cleanupPage();
    initializedPageUrl = pageUrl;

    document.querySelectorAll('.page-quick-edit').forEach((details) => {
        const form = details.querySelector('form');
        const close = details.querySelector('[data-quick-edit-close]');
        if (!form) return;
        addPageListener(close, 'click', (event) => {
            event.preventDefault();
            details.open = false;
        });
        addPageListener(details, 'click', (event) => {
            if (event.target.closest('summary, form')) return;
            details.open = false;
        });
        addPageListener(document, 'click', (event) => {
            if (details.open && !details.contains(event.target)) details.open = false;
        });
        addPageListener(document, 'keydown', (event) => {
            if (event.key === 'Escape' && details.open) {
                details.open = false;
                details.querySelector('summary')?.focus();
            }
        });
    });

    const library = document.querySelector('.page-block-library');
    const libraryOpens = document.querySelectorAll('[data-block-library-open]');
    const libraryClosers = document.querySelectorAll('[data-block-library-close]');
    if (library && !library.querySelector('[data-block-library-close]')) {
        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'page-block-library-close';
        close.dataset.blockLibraryClose = '';
        close.setAttribute('aria-label', 'Close elements and sections');
        close.title = 'Close elements and sections';
        close.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17"></path></svg>';
        library.querySelector('header')?.append(close);
        addPageListener(close, 'click', () => setLibraryOpen(false));
    }
    const setLibraryOpen = (open) => {
        if (!library) return;
        library.classList.toggle('is-open', open);
        document.body.classList.toggle('page-library-is-open', open);
        if (open) library.querySelector('[data-add]')?.focus();
    };
    libraryOpens.forEach((opener) => addPageListener(opener, 'click', () => setLibraryOpen(true)));
    libraryClosers.forEach((closer) => addPageListener(closer, 'click', () => setLibraryOpen(false)));
    addPageListener(document, 'keydown', (event) => { if (event.key === 'Escape') setLibraryOpen(false); });
    addPageListener(library, 'click', (event) => {
        const addButton = event.target.closest('[data-add]');
        if (!addButton) return;
        window.oneMinistryPageEditor?.addBlock?.(addButton.dataset.add);
        setLibraryOpen(false);
    });
    const canvasWrap = document.querySelector('.page-block-canvas-wrap');
    const floatingLibraryOpen = document.querySelector('.page-block-library-trigger');
    if (canvasWrap && floatingLibraryOpen && floatingLibraryOpen.parentElement !== canvasWrap) canvasWrap.prepend(floatingLibraryOpen);
document.querySelectorAll('[data-zoom-meeting]').forEach((room) => {
    const joinButton = room.querySelector('[data-zoom-join]');
    const status = room.querySelector('[data-zoom-status]');
    const stage = room.querySelector('[data-zoom-stage]');
    const help = room.querySelector('[data-zoom-help]');
    const lobbyList = room.querySelector('[data-lobby-list]');
    const lobbyEmpty = room.querySelector('[data-lobby-empty]');
    let client;
    let waitingTimer;
    let isJoining = false;
    pageCleanups.push(() => {
        if (waitingTimer) window.clearInterval(waitingTimer);
        if (client) void Promise.resolve(client.leave?.(true)).catch(() => {});
    });

    const setStatus = (message) => { if (status) status.textContent = message; };
    const join = async () => {
        if (isJoining) return;
        isJoining = true;
        joinButton.disabled = true;
        setStatus('Checking room access…');
        try {
            const response = await fetch(room.dataset.grantUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json', 'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({ display_name: room.dataset.displayName }),
            });
            const body = await response.json();
            if (!response.ok) throw new Error(body.message ?? 'The room could not be opened.');
            if (body.data.status === 'waiting') {
                setStatus('Waiting for host admission');
                if (help) help.textContent = 'The host has been notified. This page does not count as attendance.';
                joinButton.disabled = true;
                waitingTimer = setPageInterval(async () => {
                    try {
                        const response = await fetch(room.dataset.waitingUrl, { headers: { Accept: 'application/json' } });
                        if ([401, 403, 404].includes(response.status)) {
                            window.clearInterval(waitingTimer);
                            setStatus('Meeting access unavailable');
                            if (help) help.textContent = response.status === 401 ? 'Your sign-in has expired. Sign in again to continue.' : 'This meeting or its access has changed. Contact the host if you still need to join.';
                            return;
                        }
                        if (!response.ok) return;
                        const { data } = await response.json();
                        if (data.admission_status === 'admitted') {
                            window.clearInterval(waitingTimer);
                            isJoining = false;
                            await join();
                        } else if (data.admission_status === 'declined' || !['scheduled', 'live'].includes(data.meeting_status)) {
                            window.clearInterval(waitingTimer);
                            const declined = data.admission_status === 'declined';
                            setStatus(declined ? 'Admission declined' : 'Meeting unavailable');
                            if (help) help.textContent = declined ? 'The host declined this request. Contact the meeting host if you believe this was a mistake.' : 'This meeting is no longer open. Return to your member dashboard for other gatherings.';
                        }
                    } catch (_) {
                        // Keep the waiting page open and retry after a brief connection interruption.
                    }
                }, 3000);
                return;
            }

            setStatus('Connecting securely…');
            // The Video SDK is large and this shared bundle loads on every route.
            // Keep it out of ordinary page navigation and fetch it only after the
            // server has issued a valid join grant.
            const { default: ZoomVideo } = await import('@zoom/videosdk');
            client = ZoomVideo.createClient();
            await client.init('en-US', 'Global', { patchJsMedia: true });
            await client.join(body.data.grant.session_name, body.data.grant.token, room.dataset.displayName, body.data.grant.session_passcode);
            stage?.replaceChildren();
            const message = document.createElement('div');
            message.className = 'meeting-stage-empty';
            const title = document.createElement('strong');
            title.textContent = 'You are in the meeting';
            const detail = document.createElement('p');
            detail.textContent = 'Your camera and microphone are off until you turn them on.';
            message.append(title, detail);
            stage?.append(message);
            setStatus('Connected');
            joinButton.textContent = 'Connected';
            joinButton.disabled = true;
            addPageListener(window, 'beforeunload', () => client?.leave?.(true), { once: true });
        } catch (error) {
            if (client) {
                try { await client.leave(true); } catch (_) { /* The provider session may not have opened. */ }
            }
            client = undefined;
            setStatus('Unable to join');
            if (help) help.textContent = error.message || 'Check the room configuration and try again.';
            joinButton.disabled = false;
        } finally {
            isJoining = false;
        }
    };

    joinButton?.addEventListener('click', join);

    if (room.dataset.lobbyUrl && lobbyList) {
        const refreshLobby = async () => {
            try {
                const response = await fetch(room.dataset.lobbyUrl, { headers: { Accept: 'application/json' } });
                if (!response.ok) return;
                const { data } = await response.json();
                lobbyList.replaceChildren(...data.map((person) => {
                    const item = document.createElement('li');
                    const name = document.createElement('span');
                    name.textContent = person.display_name;
                    const actions = document.createElement('div');
                    for (const [decision, label] of [['admit', 'Admit'], ['decline', 'Decline']]) {
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'button-secondary';
                        button.textContent = label;
                        button.addEventListener('click', async () => {
                            button.disabled = true;
                            try {
                                await fetch(`${room.dataset.lobbyDecisionUrl}/${person.id}`, {
                                    method: 'POST',
                                    headers: {
                                        Accept: 'application/json', 'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                                    },
                                    body: JSON.stringify({ decision }),
                                }).then(async (result) => { if (!result.ok) throw new Error('Could not update this lobby request. Refresh the list and try again.'); });
                                await refreshLobby();
                            } catch (error) {
                                button.disabled = false;
                                if (help) help.textContent = error.message;
                            }
                        });
                        actions.append(button);
                    }
                    item.append(name, actions);
                    return item;
                }));
                if (lobbyEmpty) lobbyEmpty.hidden = data.length > 0;
            } catch (_) {
                // The room itself remains usable while the lobby refresh retries.
            }
        };
        refreshLobby();
        setPageInterval(refreshLobby, 5000);
    }
});

document.querySelectorAll('[data-worship-shell]').forEach((shell) => {
    const toggle = shell.querySelector('[data-worship-theme-toggle]');
    if (!toggle) return;
    const storageKey = 'ministry-theme';
    const legacyStorageKey = 'ministry-public-theme';
    const setTheme = (theme) => {
        const dark = theme === 'dark';
        shell.classList.toggle('worship-theme-dark', dark);
        toggle.setAttribute('aria-pressed', String(dark));
        toggle.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
        const label = toggle.querySelector('.workspace-theme-label');
        if (label) label.textContent = dark ? 'Dark' : 'Light';
    };
    const savedTheme = window.localStorage.getItem(storageKey) ?? window.localStorage.getItem(legacyStorageKey);
    if (savedTheme) window.localStorage.setItem(storageKey, savedTheme);
    setTheme(savedTheme === 'dark' ? 'dark' : 'light');
    toggle.addEventListener('click', () => {
        const theme = shell.classList.contains('worship-theme-dark') ? 'light' : 'dark';
        window.localStorage.setItem(storageKey, theme);
        setTheme(theme);
    });
    addPageListener(window, 'storage', (event) => { if (event.key === storageKey) setTheme(event.newValue === 'dark' ? 'dark' : 'light'); });
});

const carousel = document.querySelector('[data-home-carousel]');
if (carousel) {
    const slides = [...carousel.querySelectorAll('.homepage-slide')];
    let active = 0;
    const show = (index) => {
        active = (index + slides.length) % slides.length;
        slides.forEach((slide, position) => { slide.hidden = position !== active; });
    };
    carousel.querySelector('[data-carousel-previous]')?.addEventListener('click', () => show(active - 1));
    carousel.querySelector('[data-carousel-next]')?.addEventListener('click', () => show(active + 1));
}

document.querySelectorAll('[data-homepage-order]').forEach((form) => {
    const list = form.parentElement?.querySelector('[data-order-list]');
    const inputs = form.querySelector('[data-order-inputs]');
    if (!list || !inputs) return;
    const sync = () => {
        inputs.replaceChildren(...[...list.querySelectorAll('[data-order-item]')].map((item) => {
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = 'order[]'; input.value = item.dataset.orderId;
            return input;
        }));
    };
    list.querySelectorAll('[data-order-item]').forEach((item) => {
        item.addEventListener('dragstart', () => item.classList.add('is-dragging'));
        item.addEventListener('dragend', () => { item.classList.remove('is-dragging'); sync(); });
        item.addEventListener('dragover', (event) => {
            event.preventDefault();
            const dragging = list.querySelector('.is-dragging');
            if (dragging && dragging !== item) list.insertBefore(dragging, item);
        });
    });
    sync();
});


const serviceRoom = document.querySelector('[data-service-room]');
if (serviceRoom) {
    const poll = async () => {
        try {
            const response = await fetch(serviceRoom.dataset.statusUrl, { headers: { Accept: 'application/json' } });
            if (!response.ok) return;
            const { data } = await response.json();
            if (data.state !== serviceRoom.dataset.state || data.media?.state !== serviceRoom.dataset.mediaState) window.location.reload();
        } catch (_) {
            // The visible room remains usable during a temporary status request failure.
        }
    };
    serviceRoom.dataset.state = serviceRoom.querySelector('.worship-eyebrow')?.textContent?.includes('LIVE') ? 'live' : 'interrupted';
    serviceRoom.dataset.mediaState = serviceRoom.querySelector('.worship-player') ? 'available' : 'unavailable';
    setPageInterval(poll, 10000);
}

document.querySelectorAll('[data-workspace-shell]').forEach((shell) => {
    const sidebar = shell.querySelector('[data-workspace-sidebar]');
    const toggle = shell.querySelector('[data-workspace-toggle]');
    const backdrop = shell.querySelector('.workspace-backdrop');
    const closers = shell.querySelectorAll('[data-workspace-close]');
    const themeToggle = shell.querySelector('[data-workspace-theme-toggle]');
    const storageKey = 'ministry-theme';
    const legacyStorageKey = 'ministry-staff-theme';
    const chromeAlreadyReady = shell.dataset.workspaceChromeReady === 'true';

    const setNavigation = (open) => {
        shell.classList.toggle('navigation-open', open);
        sidebar?.classList.toggle('is-open', open);
        backdrop?.classList.toggle('is-open', open);
        toggle?.setAttribute('aria-expanded', String(open));
    };

    const setTheme = (theme) => {
        const dark = theme === 'dark';
        shell.classList.toggle('staff-theme-dark', dark);
        if (shell.dataset.workspaceSurface === 'member') shell.classList.toggle('member-theme-dark', dark);
        themeToggle?.setAttribute('aria-pressed', String(dark));
        themeToggle?.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
        const label = themeToggle?.querySelector('.workspace-theme-label');
        if (label) label.textContent = dark ? 'Dark' : 'Light';
    };

    const memberLegacyTheme = shell.dataset.workspaceSurface === 'member' ? window.localStorage.getItem('ministry-member-theme') : null;
    const savedTheme = window.localStorage.getItem(storageKey) ?? memberLegacyTheme ?? window.localStorage.getItem(legacyStorageKey);
    if (savedTheme) window.localStorage.setItem(storageKey, savedTheme);
    setTheme(savedTheme === 'dark' ? 'dark' : 'light');
    if (!chromeAlreadyReady) {
        shell.dataset.workspaceChromeReady = 'true';
        toggle?.addEventListener('click', () => setNavigation(!shell.classList.contains('navigation-open')));
        closers.forEach((button) => button.addEventListener('click', () => setNavigation(false)));
        sidebar?.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setNavigation(false)));
        addPageListener(window, 'keydown', (event) => { if (event.key === 'Escape') setNavigation(false); });
        themeToggle?.addEventListener('click', () => {
            const theme = shell.classList.contains('staff-theme-dark') ? 'light' : 'dark';
            window.localStorage.setItem(storageKey, theme);
            setTheme(theme);
        });
        addPageListener(window, 'storage', (event) => { if (event.key === storageKey) setTheme(event.newValue === 'dark' ? 'dark' : 'light'); });
    }

    shell.querySelectorAll('[data-service-edit-open]').forEach((trigger) => {
        trigger.addEventListener('click', () => document.getElementById(trigger.dataset.serviceEditOpen)?.showModal());
    });
    shell.querySelectorAll('[data-service-edit-dialog]').forEach((dialog) => {
        dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
        dialog.querySelectorAll('[data-service-edit-close]').forEach((button) => button.addEventListener('click', () => dialog.close()));
    });
    shell.querySelectorAll('[data-workspace-dialog-open]').forEach((trigger) => {
        trigger.addEventListener('click', () => document.getElementById(trigger.dataset.workspaceDialogOpen)?.showModal());
    });
    shell.querySelectorAll('[data-workspace-dialog]').forEach((dialog) => {
        dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
        dialog.querySelectorAll('[data-workspace-dialog-close]').forEach((button) => button.addEventListener('click', () => dialog.close()));
    });
    shell.querySelectorAll('.workspace-content .data-panel').forEach((panel) => {
        if (panel.matches('.module-panel, .service-schedule-panel, .security-sessions-panel, .responsive-records')) return;
        const table = panel.querySelector('table');
        const labels = [...(table?.querySelectorAll('thead th') ?? [])].map((header, index) => header.textContent.trim() || `Details ${index + 1}`);
        if (!table || labels.length === 0) return;
        panel.classList.add('mobile-record-table');
        table.querySelectorAll('tbody tr').forEach((row) => {
            const cells = [...row.querySelectorAll(':scope > td')];
            if (cells.length === 1 && cells[0].colSpan > 1) return;
            cells.forEach((cell, index) => cell.dataset.label = labels[index] || `Details ${index + 1}`);
        });
    });
});

const setupWizard = document.querySelector('[data-setup-wizard]');
if (setupWizard) {
    const form = setupWizard.querySelector('[data-setup-form]');
    const steps = [...setupWizard.querySelectorAll('[data-setup-step]')];
    const progress = [...setupWizard.querySelectorAll('[data-setup-progress]')];
    const next = setupWizard.querySelector('[data-setup-next]');
    const back = setupWizard.querySelector('[data-setup-back]');
    const finish = setupWizard.querySelector('[data-setup-finish]');
    const count = setupWizard.querySelector('[data-setup-count]');
    const validation = setupWizard.querySelector('[data-setup-validation]');
    let current = 0;

    const setupPresets = {
        neutral: { action: '#343A40', chrome: '#24262A' },
        slate: { action: '#315B96', chrome: '#1D2A3D' },
        warm: { action: '#6B5140', chrome: '#332A23' },
    };

    const updatePreview = () => {
        const name = form.querySelector('[name="name"]')?.value.trim();
        const presetKey = form.querySelector('[name="theme_preset"]:checked')?.value ?? 'neutral';
        const preset = setupPresets[presetKey] ?? setupPresets.neutral;
        setupWizard.style.setProperty('--brand', preset.action);
        setupWizard.style.setProperty('--accent', preset.action);
        setupWizard.style.setProperty('--sidebar', preset.chrome);
        const previewName = setupWizard.querySelector('[data-setup-preview-name]');
        const previewMark = setupWizard.querySelector('[data-setup-preview-mark]');
        if (previewName) previewName.textContent = name || 'Your ministry';
        if (previewMark) previewMark.textContent = (name || 'M').slice(0, 1).toUpperCase();
    };

    const showStep = (index) => {
        current = index;
        steps.forEach((step, position) => {
            const active = position === current;
            step.hidden = !active;
            step.classList.toggle('is-active', active);
        });
        progress.forEach((item, position) => {
            item.classList.toggle('is-active', position === current);
            item.classList.toggle('is-complete', position < current);
        });
        count.textContent = `Step ${current + 1} of ${steps.length}`;
        back.hidden = current === 0;
        next.hidden = current === steps.length - 1;
        finish.hidden = current !== steps.length - 1;
        validation.textContent = '';
        updatePreview();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const validateStep = () => {
        const fields = [...steps[current].querySelectorAll('input, select')].filter((field) => !field.disabled);
        const invalid = fields.find((field) => !field.checkValidity());
        if (!invalid) return true;
        validation.textContent = invalid.validationMessage || 'Complete the required fields to continue.';
        invalid.focus();
        return false;
    };

    next?.addEventListener('click', () => { if (validateStep()) showStep(current + 1); });
    back?.addEventListener('click', () => showStep(current - 1));
    form?.addEventListener('submit', (event) => { if (!validateStep()) event.preventDefault(); });
    form?.querySelectorAll('[name="name"], [name="theme_preset"]').forEach((field) => field.addEventListener('input', updatePreview));
    updatePreview();
}

document.querySelectorAll('[data-setup-timezone-picker]').forEach((picker) => {
    const trigger = picker.querySelector('.setup-timezone-trigger');
    const options = picker.querySelector('.setup-timezone-options');
    const search = picker.querySelector('[data-setup-timezone-search]');
    const value = picker.querySelector('input[name="timezone"]');
    const label = picker.querySelector('[data-setup-timezone-label]');
    const choices = [...picker.querySelectorAll('[data-timezone-option]')];
    const close = () => {
        picker.classList.remove('is-open');
        options.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
    };
    const open = () => {
        picker.classList.add('is-open');
        options.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        search?.focus();
    };
    trigger?.addEventListener('click', () => (picker.classList.contains('is-open') ? close() : open()));
    choices.forEach((choice) => choice.addEventListener('click', () => {
        value.value = choice.dataset.value;
        label.textContent = choice.dataset.value;
        choices.forEach((item) => item.setAttribute('aria-selected', String(item === choice)));
        close();
        trigger.focus();
    }));
    search?.addEventListener('input', () => {
        const term = search.value.trim().toLowerCase();
        choices.forEach((choice) => { choice.hidden = term !== '' && !choice.textContent.toLowerCase().includes(term); });
    });
    addPageListener(document, 'click', (event) => { if (!picker.contains(event.target)) close(); });
    picker.addEventListener('keydown', (event) => { if (event.key === 'Escape') { close(); trigger.focus(); } });
});

document.querySelectorAll('[data-page-editor-tabs]').forEach((tabs) => {
    const editor = tabs.closest('[data-page-block-editor]');
    const buttons = [...tabs.querySelectorAll('[data-page-editor-mode]')];
    const panels = [...editor?.querySelectorAll('[data-page-editor-panel]') ?? []];
    if (!editor || !buttons.length || !panels.length) return;

    const selectMode = (mode) => {
        buttons.forEach((button) => {
            const selected = button.dataset.pageEditorMode === mode;
            button.classList.toggle('is-active', selected);
            button.setAttribute('aria-selected', String(selected));
            button.tabIndex = selected ? 0 : -1;
        });
        panels.forEach((panel) => { panel.hidden = panel.dataset.pageEditorPanel !== mode; });
        editor.dataset.pageEditorMode = mode;
    };

    buttons.forEach((button) => button.addEventListener('click', () => selectMode(button.dataset.pageEditorMode)));
    selectMode('manual');
});

document.querySelectorAll('[data-ai-page-draft]').forEach((panel) => {
    const button = panel.querySelector('[data-ai-generate]');
    const status = panel.querySelector('[data-ai-status]');
    const editor = panel.closest('[data-page-block-editor]');
    if (!button || !status || !editor) return;

    button.addEventListener('click', async () => {
        const brief = panel.querySelector('[data-ai-brief]')?.value.trim() ?? '';
        if (brief.length < 20) {
            status.textContent = 'Add a brief of at least 20 characters.';
            panel.querySelector('[data-ai-brief]')?.focus();
            return;
        }

        button.disabled = true;
            status.textContent = 'Planning your page…';
        try {
            const response = await fetch(panel.dataset.endpoint, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({
                    brief,
                    title: editor.querySelector('[name="title"]')?.value.trim() ?? '',
                    public_details: panel.querySelector('[data-ai-public-details]')?.value.trim() ?? '',
                }),
            });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload?.error?.message ?? payload?.message ?? 'The page draft could not be generated. Try again.');
            const previousProposal = panel.querySelector('[data-ai-proposal]');
            previousProposal?.remove();
            const proposal = payload.data;
            const preview = document.createElement('section');
            preview.className = 'ai-page-proposal';
            preview.dataset.aiProposal = '';
            preview.tabIndex = -1;
            const heading = document.createElement('h3');
            heading.textContent = `Page plan ready: ${proposal.title}`;
            const explanation = document.createElement('p');
            explanation.textContent = 'Review the section plan below. Applying it replaces the current canvas; you can undo the change before saving.';
            const outline = document.createElement('ol');
            const labels = { hero: 'Welcome section', heading: 'Heading', paragraph: 'Text', media_text: 'Image and text', image: 'Image', quote: 'Quote', list: 'List', accordion: 'Questions and answers', call_to_action: 'Closing invitation' };
            const plannedElements = proposal.document?.sections?.flatMap((section) => section.columns?.flatMap((column) => column.elements ?? []) ?? []) ?? [];
            plannedElements.forEach((block) => {
                const item = document.createElement('li');
                const data = block.content ?? {};
                const title = data.title || data.text || data.label || data.eyebrow || data.image_hint || data.alt || '';
                item.textContent = [labels[block.type] ?? 'Section', title].filter(Boolean).join(' · ');
                outline.append(item);
            });
            const actions = document.createElement('div');
            actions.className = 'ai-page-proposal-actions';
            const apply = document.createElement('button');
            apply.className = 'button';
            apply.type = 'button';
            apply.textContent = 'Apply page plan';
            const discard = document.createElement('button');
            discard.className = 'button button-secondary';
            discard.type = 'button';
            discard.textContent = 'Discard';
            apply.addEventListener('click', () => {
                editor.dispatchEvent(new CustomEvent('ai-page-draft', { detail: proposal }));
                preview.remove();
                status.textContent = 'Page plan applied. Review the content, choose images and button destinations, then save as a draft.';
                editor.querySelector('[data-canvas]')?.focus({ preventScroll: false });
            });
            discard.addEventListener('click', () => {
                preview.remove();
                status.textContent = 'Page plan discarded. Your current page is unchanged.';
                button.focus();
            });
            actions.append(apply, discard);
            preview.append(heading, explanation, outline, actions);
            panel.append(preview);
            preview.focus();
            const route = `${payload.data.provider} · ${payload.data.model}`;
            const contentSectionCount = Math.max(0, (proposal.document?.sections?.length ?? 0) - 2);
            status.textContent = `Page plan prepared with ${contentSectionCount} content sections by ${route}${proposal.fallback_used ? ' (fallback model)' : ''}. Review it before applying.`;
        } catch (error) {
            status.textContent = error instanceof Error ? error.message : 'The page draft could not be generated. Try again.';
        } finally {
            button.disabled = false;
        }
    });
});

document.querySelectorAll('[data-page-block-editor][data-section-ai-endpoint]').forEach((form) => {
    const inspector = form.querySelector('[data-inspector]');
    const canvas = form.querySelector('[data-canvas]');
    const blocksInput = form.querySelector('[data-page-block-input]');
    const titleInput = form.querySelector('[name="title"]');
    const aiPanel = form.querySelector('[data-ai-page-draft]');
    if (!inspector || !canvas || !blocksInput || !titleInput || !aiPanel) return;

    const inspectorTabs = inspector.closest('.page-block-inspector')?.querySelector('[data-inspector-tabs]');
    const manualTab = inspectorTabs?.querySelector('[data-inspector-tab="manual"]');
    const aiTab = inspectorTabs?.querySelector('[data-inspector-tab="ai"]');
    const setInspectorMode = (mode) => {
        const aiControls = inspector.querySelector('[data-section-ai-controls]');
        const hasSelection = Boolean(canvas.querySelector('.page-canvas-block.is-selected'));
        if (inspectorTabs) inspectorTabs.hidden = !hasSelection;
        inspector.closest('.page-block-inspector')?.classList.toggle('is-ai-mode', mode === 'ai' && hasSelection);
        if (manualTab) { manualTab.classList.toggle('is-active', mode === 'manual'); manualTab.setAttribute('aria-selected', mode === 'manual' ? 'true' : 'false'); }
        if (aiTab) { aiTab.classList.toggle('is-active', mode === 'ai'); aiTab.setAttribute('aria-selected', mode === 'ai' ? 'true' : 'false'); }
        if (inspector) {
            const inspectorActions = inspector.querySelector('.page-inspector-actions');
            [...inspector.children].forEach((child) => {
                child.hidden = mode === 'ai' && child !== aiControls && child !== inspectorActions;
            });
        }
        if (aiControls) aiControls.hidden = mode !== 'ai';
    };
    if (manualTab && !manualTab.dataset.bound) {
        manualTab.dataset.bound = 'true';
        manualTab.addEventListener('click', () => setInspectorMode('manual'));
    }
    if (aiTab && !aiTab.dataset.bound) {
        aiTab.dataset.bound = 'true';
        aiTab.addEventListener('click', () => setInspectorMode('ai'));
    }

    const installSectionAssistant = () => {
        if (inspector.querySelector('[data-section-ai-controls]')) return;
        const actions = inspector.querySelector('.page-inspector-actions');
        const selected = canvas.querySelector('.page-canvas-block.is-selected');
        if (!actions || !selected) { setInspectorMode('manual'); return; }
        const index = Number(selected.dataset.index);
        let blocks;
        try { blocks = JSON.parse(blocksInput.value || '[]'); } catch { return; }
        const block = blocks[index];
        if (!block?.data || typeof block.data !== 'object' || !Object.keys(block.data).length) return;
        const originalBlock = JSON.stringify(block);

        const section = document.createElement('section');
        section.className = 'page-section-ai';
        section.dataset.sectionAiControls = '';
        const heading = document.createElement('strong');
        heading.textContent = 'Edit this section with AI';
        const description = document.createElement('p');
        description.textContent = 'The ministry AI receives this full page draft for context. It can propose changes to this section only.';
        const instruction = document.createElement('textarea');
        instruction.dataset.sectionAiInstruction = '';
        instruction.rows = 3;
        instruction.maxLength = 1500;
        instruction.minLength = 10;
        instruction.placeholder = 'Describe what you want changed in this section…';
        const ask = document.createElement('button');
        ask.type = 'button';
        ask.className = 'button';
        ask.dataset.sectionAiAsk = '';
        ask.textContent = 'Suggest changes';
        const aiUnavailable = aiPanel.querySelector('[data-ai-generate]')?.disabled;
        if (aiUnavailable) ask.disabled = true;
        const status = document.createElement('p');
        status.className = 'page-section-ai-status';
        status.dataset.sectionAiStatus = '';
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');
        if (aiUnavailable) status.textContent = 'AI editing is unavailable until an administrator verifies a model route in AI model settings.';
        section.append(heading, description, instruction, ask, status);
        actions.after(section);
        setInspectorMode('manual');

        ask.addEventListener('click', async () => {
            const brief = instruction.value.trim();
            if (brief.length < 10) {
                status.textContent = 'Describe the change in at least 10 characters.';
                instruction.focus();
                return;
            }
            ask.disabled = true;
            status.textContent = 'Reviewing the full page context…';
            try {
                const response = await fetch(form.dataset.sectionAiEndpoint, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                    body: JSON.stringify({
                        title: titleInput.value,
                        instruction: brief,
                        page_purpose: form.querySelector('[data-ai-brief]')?.value.trim() ?? '',
                        selected_index: index,
                        blocks: blocksInput.value,
                    }),
                });
                const payload = await response.json();
                if (!response.ok) throw new Error(payload?.error?.message ?? payload?.message ?? 'This section could not be revised. Try again.');
                let latestBlocks;
                try { latestBlocks = JSON.parse(blocksInput.value || '[]'); } catch { latestBlocks = []; }
                if (canvas.querySelector('.page-canvas-block.is-selected')?.dataset.index !== String(index)
                    || JSON.stringify(latestBlocks[index]) !== originalBlock) {
                    status.textContent = 'This section changed while AI was working. Review the current version and request a fresh suggestion.';
                    return;
                }
                const proposal = payload.data;
                const preview = document.createElement('div');
                preview.className = 'page-section-ai-proposal';
                const title = document.createElement('strong');
                title.textContent = 'Suggested section update';
                const list = document.createElement('dl');
                Object.entries(proposal.data ?? {}).forEach(([key, value]) => {
                    const term = document.createElement('dt');
                    term.textContent = key.replaceAll('_', ' ');
                    const detail = document.createElement('dd');
                    detail.textContent = value;
                    list.append(term, detail);
                });
                const actions = document.createElement('div');
                actions.className = 'ai-page-proposal-actions';
                const apply = document.createElement('button');
                apply.type = 'button';
                apply.className = 'button';
                apply.textContent = 'Apply to this section';
                apply.addEventListener('click', () => {
                    form.dispatchEvent(new CustomEvent('ai-section-revision', { detail: { index, data: proposal.data } }));
                    status.textContent = 'Suggestion applied to this section. Review the page, then save it as a draft.';
                    section.querySelector('.page-section-ai-proposal')?.remove();
                });
                const discard = document.createElement('button');
                discard.type = 'button';
                discard.className = 'button button-secondary';
                discard.textContent = 'Discard';
                discard.addEventListener('click', () => {
                    preview.remove();
                    status.textContent = 'Suggestion discarded. The section is unchanged.';
                });
                actions.append(apply, discard);
                preview.append(title, list, actions);
                section.querySelector('.page-section-ai-proposal')?.remove();
                section.append(preview);
                const route = `${proposal.provider} · ${proposal.model}`;
                status.textContent = `Suggestion ready from ${route}${proposal.fallback_used ? ' (fallback model)' : ''}. Review it before applying.`;
            } catch (error) {
                status.textContent = error instanceof Error ? error.message : 'This section could not be revised. Try again.';
            } finally {
                ask.disabled = false;
            }
        });
    };

    new MutationObserver(installSectionAssistant).observe(inspector, { childList: true });
    installSectionAssistant();
});

document.querySelectorAll('[data-page-block-editor]').forEach((form) => {
    const frame = form.querySelector('[data-public-preview]');
    const status = form.querySelector('[data-public-preview-status]');
    const blocksInput = form.querySelector('[data-page-block-input]');
    const titleInput = form.querySelector('[name="title"]');
    if (!frame || !status || !blocksInput || !titleInput) return;

    let timer;
    let requestId = 0;
    let activeRequest;
    let selectedTarget;
    const previewViewport = frame.closest('.page-editor-preview-viewport');
    const syncPreviewHeight = () => {
        const previewDocument = frame.contentDocument;
        if (!previewDocument) return;
        const documentElement = previewDocument.documentElement;
        const body = previewDocument.body;
        const height = Math.max(
            documentElement?.scrollHeight || 0,
            documentElement?.offsetHeight || 0,
            body?.scrollHeight || 0,
            body?.offsetHeight || 0,
            720,
        );
        frame.style.height = `${height}px`;
    };
    const installCanvasEditing = () => {
        const canvasDocument = frame.contentDocument;
        if (!canvasDocument) return;
        syncPreviewHeight();
        const previewResizeObserver = new ResizeObserver(syncPreviewHeight);
        previewResizeObserver.observe(canvasDocument.documentElement);
        if (canvasDocument.body) previewResizeObserver.observe(canvasDocument.body);
        const style = canvasDocument.createElement('style');
        style.textContent = `
            [data-page-editor-object] { cursor: pointer; }
            [data-page-editor-object]:hover { outline: 2px solid color-mix(in srgb, var(--action) 55%, transparent); outline-offset: -2px; }
            [data-page-editor-selected] { outline: 3px solid var(--action) !important; outline-offset: -3px !important; }
            [data-page-editor-object]:focus-visible { outline: 3px solid var(--action); outline-offset: -3px; }
            .page-editor-canvas-tools { position: absolute; z-index: 2147483000; display: flex; gap: 3px; padding: 4px; border-radius: 9px; background: var(--action); box-shadow: 0 8px 22px rgb(0 0 0 / 18%); }
            .page-editor-canvas-tools button { display: grid; width: 32px; height: 32px; place-items: center; border: 0; border-radius: 6px; background: transparent; color: var(--action-foreground); font: inherit; font-weight: 800; cursor: pointer; }
            .page-editor-canvas-tools button:hover, .page-editor-canvas-tools button:focus-visible { background: color-mix(in srgb, var(--surface) 20%, transparent); outline: none; }
        `;
        canvasDocument.head.append(style);
        const tools = canvasDocument.createElement('div');
        tools.className = 'page-editor-canvas-tools';
        tools.hidden = true;
        tools.innerHTML = '<button type="button" data-canvas-action="move-up" aria-label="Move up" title="Move up">↑</button><button type="button" data-canvas-action="move-down" aria-label="Move down" title="Move down">↓</button><button type="button" data-canvas-action="duplicate" aria-label="Duplicate" title="Duplicate">⧉</button><button type="button" data-canvas-action="delete" aria-label="Delete" title="Delete">×</button>';
        canvasDocument.body.append(tools);
        canvasDocument.querySelectorAll('.public-page-structured-section').forEach((section, sectionIndex) => {
            section.dataset.pageEditorObject = 'section';
            section.dataset.pageEditorSection = String(sectionIndex);
            section.tabIndex = 0;
            section.querySelectorAll(':scope > .public-page-structured-columns > .public-page-structured-column').forEach((column, columnIndex) => {
                column.dataset.pageEditorObject = 'column';
                column.dataset.pageEditorSection = String(sectionIndex);
                column.dataset.pageEditorColumn = String(columnIndex);
                column.tabIndex = 0;
                column.querySelectorAll(':scope > .public-page-element').forEach((element, elementIndex) => {
                    element.dataset.pageEditorObject = 'element';
                    element.dataset.pageEditorSection = String(sectionIndex);
                    element.dataset.pageEditorColumn = String(columnIndex);
                    element.dataset.pageEditorElement = String(elementIndex);
                    element.tabIndex = 0;
                    element.draggable = true;
                });
            });
        });
        const currentSelection = window.oneMinistryPageEditor?.selected;
        if (currentSelection) {
            const selector = `[data-page-editor-object="${currentSelection.kind}"][data-page-editor-section="${currentSelection.section}"]${currentSelection.kind !== 'section' ? `[data-page-editor-column="${currentSelection.column}"]` : ''}${currentSelection.kind === 'element' ? `[data-page-editor-element="${currentSelection.element}"]` : ''}`;
            selectedTarget = canvasDocument.querySelector(selector);
            selectedTarget?.setAttribute('data-page-editor-selected', '');
        }
        const selectCanvasObject = (target) => {
            if (!target) return;
            selectedTarget?.removeAttribute('data-page-editor-selected');
            selectedTarget = target;
            selectedTarget.setAttribute('data-page-editor-selected', '');
            const rect = target.getBoundingClientRect();
            tools.hidden = false;
            tools.style.left = `${Math.max(8, rect.left + canvasDocument.defaultView.scrollX)}px`;
            tools.style.top = `${Math.max(8, rect.top + canvasDocument.defaultView.scrollY - 42)}px`;
            window.oneMinistryPageEditor?.select?.(
                target.dataset.pageEditorObject,
                Number(target.dataset.pageEditorSection || 0),
                Number(target.dataset.pageEditorColumn || 0),
                Number(target.dataset.pageEditorElement || 0),
            );
        };
        tools.addEventListener('click', (event) => {
            const button = event.target.closest('[data-canvas-action]');
            if (!button) return;
            event.preventDefault();
            event.stopPropagation();
            window.oneMinistryPageEditor?.action?.(button.dataset.canvasAction);
        });
        canvasDocument.addEventListener('click', (event) => {
            const target = event.target.closest('[data-page-editor-object]');
            if (!target) return;
            event.preventDefault();
            event.stopPropagation();
            selectCanvasObject(target);
        }, true);
        canvasDocument.addEventListener('keydown', (event) => {
            if (!['Enter', ' '].includes(event.key)) return;
            const target = event.target.closest('[data-page-editor-object]');
            if (!target) return;
            event.preventDefault();
            selectCanvasObject(target);
        });
        const inlineField = (element, target) => {
            const model = window.oneMinistryPageEditor?.document?.sections?.[Number(element.dataset.pageEditorSection)]?.columns?.[Number(element.dataset.pageEditorColumn)]?.elements?.[Number(element.dataset.pageEditorElement)];
            if (!model) return null;
            const tag = target.tagName.toLowerCase();
            if (model.type === 'heading' && /^h[1-6]$/.test(tag)) return 'text';
            if (model.type === 'paragraph' && tag === 'p') return 'text';
            if (model.type === 'button' && ['a', 'button'].includes(tag)) return 'label';
            if (model.type === 'quote' && tag === 'blockquote') return 'text';
            if (/^h[1-6]$/.test(tag) && Object.hasOwn(model.content || {}, 'title')) return 'title';
            if (tag === 'p' && Object.hasOwn(model.content || {}, 'body')) return 'body';
            if (['a', 'button'].includes(tag) && Object.hasOwn(model.content || {}, 'button_label')) return 'button_label';
            return null;
        };
        canvasDocument.addEventListener('dblclick', (event) => {
            const element = event.target.closest('[data-page-editor-object="element"]');
            const field = element ? inlineField(element, event.target) : null;
            if (!element || !field) return;
            event.preventDefault();
            event.stopPropagation();
            event.target.contentEditable = 'true';
            event.target.focus();
            const range = canvasDocument.createRange();
            range.selectNodeContents(event.target);
            const selection = frame.contentWindow?.getSelection();
            selection?.removeAllRanges();
            selection?.addRange(range);
            event.target.addEventListener('blur', () => {
                event.target.contentEditable = 'false';
                window.oneMinistryPageEditor?.updateInline?.(
                    Number(element.dataset.pageEditorSection),
                    Number(element.dataset.pageEditorColumn),
                    Number(element.dataset.pageEditorElement),
                    field,
                    event.target.textContent.trim(),
                );
            }, { once: true });
        }, true);
        let dragged;
        canvasDocument.addEventListener('dragstart', (event) => {
            dragged = event.target.closest('[data-page-editor-object="element"]');
            if (!dragged) event.preventDefault();
        });
        canvasDocument.addEventListener('dragover', (event) => {
            if (dragged && event.target.closest('[data-page-editor-object="element"]')) event.preventDefault();
        });
        canvasDocument.addEventListener('drop', (event) => {
            const destination = event.target.closest('[data-page-editor-object="element"]');
            if (!dragged || !destination || dragged === destination) return;
            event.preventDefault();
            window.oneMinistryPageEditor?.moveElement?.(
                Number(dragged.dataset.pageEditorSection),
                Number(dragged.dataset.pageEditorColumn),
                Number(dragged.dataset.pageEditorElement),
                Number(destination.dataset.pageEditorSection),
                Number(destination.dataset.pageEditorColumn),
                Number(destination.dataset.pageEditorElement),
            );
            dragged = null;
        });
    };
    const renderPublicPreview = async () => {
        const currentRequest = ++requestId;
        activeRequest?.abort();
        activeRequest = new AbortController();
        status.textContent = 'Updating the public page preview…';
        try {
            const response = await fetch(form.dataset.previewEndpoint, {
                method: 'POST',
                headers: {
                    Accept: 'text/html',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({ title: titleInput.value || 'Untitled page', blocks: blocksInput.value }),
                signal: activeRequest.signal,
            });
            if (!response.ok) throw new Error('The public preview could not be updated. Your draft edits are still here.');
            const html = await response.text();
            if (currentRequest !== requestId) return;
            const previewDocument = new DOMParser().parseFromString(html, 'text/html');
            frame.srcdoc = `<!doctype html>${previewDocument.documentElement.outerHTML}`;
            frame.addEventListener('load', () => {
                installCanvasEditing();
                syncPreviewHeight();
                status.textContent = 'Canvas is live. Select any section, column or element to edit it.';
            }, { once: true });
        } catch (error) {
            if (error instanceof DOMException && error.name === 'AbortError') return;
            status.textContent = error instanceof Error ? error.message : 'The public preview could not be updated.';
        }
    };
    const schedulePreview = () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(renderPublicPreview, 450);
    };

    form.addEventListener('input', schedulePreview);
    form.addEventListener('change', schedulePreview);
    form.addEventListener('click', (event) => {
        if (event.target.closest('[data-add], [data-remove], [data-duplicate], [data-move], [data-undo], [data-redo]')) {
            window.setTimeout(schedulePreview, 0);
        }
    });
    form.addEventListener('ai-page-draft', schedulePreview);
    form.addEventListener('ai-section-revision', schedulePreview);
    form.addEventListener('page-editor-document-change', schedulePreview);
    form.querySelectorAll('[data-public-preview-size]').forEach((button) => button.addEventListener('click', () => {
        const size = button.dataset.publicPreviewSize;
        frame.dataset.preview = size;
        if (previewViewport) previewViewport.dataset.preview = size;
        form.querySelectorAll('[data-public-preview-size]').forEach((item) => {
            const active = item === button;
            item.classList.toggle('is-active', active);
            item.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }));
    renderPublicPreview();
});

const initializeEnhancedSelects = (root = document) => {
const enhancedSelects = [...root.querySelectorAll('select:not([data-native-select])')];
enhancedSelects.forEach((select) => {
    if (select.closest('.enhanced-select')) return;
    const index = enhancedSelectIndex++;
    const wrapper = document.createElement('div');
    wrapper.className = 'enhanced-select';
    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'enhanced-select-trigger';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    trigger.setAttribute('aria-controls', `enhanced-select-${index}`);
    const text = document.createElement('span');
    const chevron = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    chevron.setAttribute('class', 'ui-icon'); chevron.setAttribute('width', '17'); chevron.setAttribute('height', '17'); chevron.setAttribute('viewBox', '0 0 24 24'); chevron.setAttribute('fill', 'none'); chevron.setAttribute('stroke', 'currentColor'); chevron.setAttribute('stroke-width', '1.8'); chevron.setAttribute('aria-hidden', 'true');
    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path'); path.setAttribute('d', 'm6 9 6 6 6-6'); path.setAttribute('stroke-linecap', 'round'); path.setAttribute('stroke-linejoin', 'round'); chevron.append(path);
    trigger.append(text, chevron);
    const options = document.createElement('div');
    options.className = `enhanced-select-options${select.closest('.school-form') ? ' course-select-options' : ''}`; options.hidden = true;
    const optionList = document.createElement('div');
    optionList.id = `enhanced-select-${index}`;
    optionList.setAttribute('role', 'listbox');
    const searchable = select.hasAttribute('data-searchable-select');
    const remoteUrl = select.dataset.remoteOptions;
    let searchInput = null;
    let searchCount = null;
    let noResults = null;
    let remoteTimer;
    let remoteRequestId = 0;
    let remoteLoaded = false;
    const choices = [];

    if (searchable) {
        options.classList.add('has-search');
        const searchPanel = document.createElement('div');
        searchPanel.className = 'enhanced-select-search';
        searchInput = document.createElement('input');
        searchInput.type = 'search';
        searchInput.autocomplete = 'off';
        searchInput.placeholder = 'Search options';
        searchInput.setAttribute('aria-label', `Search ${select.closest('label')?.childNodes[0]?.textContent?.trim() || 'models'}`);
        searchInput.setAttribute('aria-controls', optionList.id);
        searchCount = document.createElement('span');
        searchCount.className = 'enhanced-select-search-count';
        searchCount.setAttribute('aria-live', 'polite');
        searchPanel.append(searchInput, searchCount);
        noResults = document.createElement('p');
        noResults.className = 'enhanced-select-no-results';
        noResults.textContent = 'No options match. Try another search.';
        noResults.setAttribute('role', 'status');
        noResults.hidden = true;
        options.append(searchPanel, noResults);
    }
    options.append(optionList);

    const sync = () => {
        const selected = select.options[select.selectedIndex];
        text.textContent = selected?.textContent ?? 'Choose an option';
        choices.forEach((option) => option.setAttribute('aria-selected', String(option.dataset.value === select.value)));
    };
    const addChoice = (option) => {
        const choice = document.createElement('button'); choice.type = 'button'; choice.setAttribute('role', 'option'); choice.dataset.value = option.value; choice.dataset.searchText = option.dataset.searchText ?? option.textContent; choice.textContent = option.textContent; choice.disabled = option.disabled;
        choice.addEventListener('click', () => { select.value = option.value; select.dispatchEvent(new Event('change', { bubbles: true })); sync(); close(); trigger.focus(); }); optionList.append(choice); choices.push(choice);
    };
    const filterChoices = () => {
        if (!searchInput) return;
        const query = searchInput.value.trim().toLocaleLowerCase();
        const visibleCount = choices.reduce((count, choice) => {
            const matches = !query || choice.dataset.searchText.toLocaleLowerCase().includes(query);
            choice.hidden = !matches;
            return count + Number(matches && !choice.disabled);
        }, 0);
        if (!remoteUrl) searchCount.textContent = `${visibleCount} ${visibleCount === 1 ? 'option' : 'options'}`;
        noResults.hidden = visibleCount !== 0;
    };
    const renderChoices = () => {
        choices.splice(0, choices.length);
        optionList.replaceChildren();
        [...select.options].forEach(addChoice);
        filterChoices();
        sync();
    };
    const loadRemoteChoices = async (query = '') => {
        if (!remoteUrl) return;
        const requestId = ++remoteRequestId;
        if (searchCount) searchCount.textContent = query ? 'Searching…' : 'Loading models…';
        if (noResults) noResults.hidden = true;
        try {
            const url = new URL(remoteUrl, window.location.href);
            if (query) url.searchParams.set('q', query);
            const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) throw new Error('Model choices could not be loaded. Try again.');
            const result = await response.json();
            if (requestId !== remoteRequestId) return;
            const data = result.data ?? {};
            const models = Array.isArray(data.models) ? data.models : [];
            const currentValue = select.value;
            const currentOption = [...select.options].find((option) => option.value === currentValue && currentValue);
            const fragment = document.createDocumentFragment();
            const placeholder = select.options[0]?.cloneNode(true);
            const seen = new Set();
            if (placeholder) { fragment.append(placeholder); seen.add(placeholder.value); }
            if (currentOption && !seen.has(currentOption.value)) { fragment.append(currentOption.cloneNode(true)); seen.add(currentOption.value); }
            models.forEach((model) => {
                const value = `${model.provider_key}|${model.model_id}`;
                if (seen.has(value)) return;
                const option = document.createElement('option');
                option.value = value;
                option.textContent = model.label;
                option.dataset.searchText = model.search_text;
                fragment.append(option);
                seen.add(value);
            });
            select.replaceChildren(fragment);
            if (currentValue && seen.has(currentValue)) select.value = currentValue;
            renderChoices();
            if (searchCount) searchCount.textContent = `${models.length}${data.has_more ? '+' : ''} ${models.length === 1 ? 'model' : 'models'}`;
            if (noResults) noResults.hidden = models.length !== 0;
            remoteLoaded = true;
        } catch (error) {
            if (requestId !== remoteRequestId) return;
            if (searchCount) searchCount.textContent = '';
            if (noResults) {
                noResults.textContent = error instanceof Error ? error.message : 'Model choices could not be loaded. Try again.';
                noResults.hidden = false;
            }
        }
    };
    searchInput?.addEventListener('input', () => {
        if (!remoteUrl) { filterChoices(); return; }
        remoteLoaded = false;
        window.clearTimeout(remoteTimer);
        ++remoteRequestId;
        if (searchCount) searchCount.textContent = 'Searching…';
        if (noResults) noResults.hidden = true;
        remoteTimer = window.setTimeout(() => loadRemoteChoices(searchInput.value.trim()), 220);
    });
    // Render the menu at document level so any card, table, or scroll container can contain its own content without clipping the open menu.
    const positionOptions = () => {
        if (!wrapper.classList.contains('is-open')) return;
        const viewport = window.visualViewport;
        const viewportWidth = viewport?.width ?? window.innerWidth;
        const viewportHeight = viewport?.height ?? window.innerHeight;
        const edge = 12;
        const gap = 7;
        const triggerBounds = trigger.getBoundingClientRect();
        const availableWidth = Math.max(120, viewportWidth - edge * 2);
        const menuWidth = Math.min(triggerBounds.width, availableWidth);

        options.style.position = 'fixed';
        options.style.top = '0px';
        options.style.bottom = 'auto';
        options.style.left = `${Math.max(edge, Math.min(triggerBounds.left, viewportWidth - edge - Math.min(triggerBounds.width, availableWidth)))}px`;
        options.style.right = 'auto';
        // Searchable model catalogs should stay aligned with their field; long model names wrap inside it.
        options.style.boxSizing = 'border-box';
        options.style.width = searchInput ? `${menuWidth}px` : 'max-content';
        options.style.minWidth = `${menuWidth}px`;
        options.style.maxWidth = `${searchInput ? menuWidth : availableWidth}px`;
        options.style.maxHeight = `${Math.min(260, Math.max(80, viewportHeight - edge * 2))}px`;
        options.style.transform = 'none';

        const renderedMenuWidth = options.getBoundingClientRect().width;
        const left = Math.max(edge, Math.min(triggerBounds.left, viewportWidth - edge - renderedMenuWidth));
        options.style.left = `${left}px`;

        const spaceBelow = Math.max(0, viewportHeight - triggerBounds.bottom - edge - gap);
        const spaceAbove = Math.max(0, triggerBounds.top - edge - gap);
        const naturalHeight = options.scrollHeight;
        const openAbove = spaceBelow < Math.min(naturalHeight, 160) && spaceAbove > spaceBelow;
        const availableHeight = openAbove ? spaceAbove : spaceBelow;
        options.style.maxHeight = `${Math.max(80, Math.min(260, availableHeight))}px`;
        options.style.top = openAbove ? 'auto' : `${triggerBounds.bottom + gap}px`;
        options.style.bottom = openAbove ? `${viewportHeight - triggerBounds.top + gap}px` : 'auto';
    };
    const close = () => {
        wrapper.classList.remove('is-open');
        options.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
        if (searchInput) {
            searchInput.value = '';
            if (remoteUrl) {
                remoteLoaded = false;
                window.clearTimeout(remoteTimer);
                ++remoteRequestId;
                if (noResults) { noResults.textContent = 'No options match. Try another search.'; noResults.hidden = true; }
            }
            filterChoices();
        }
        options.classList.remove('is-portaled');
        delete options.dataset.themeMode;
        if (options.parentElement !== wrapper) wrapper.append(options);
        ['position', 'top', 'bottom', 'left', 'right', 'width', 'min-width', 'max-width', 'max-height', 'box-sizing', 'transform', 'background-color', 'color', 'border-color', 'font-family', '--surface', '--surface-ink', '--ink', '--muted', '--brand', '--line-strong'].forEach((property) => options.style.removeProperty(property));
        document.removeEventListener('scroll', positionOptions, true);
        window.removeEventListener('resize', positionOptions);
        window.visualViewport?.removeEventListener('resize', positionOptions);
        window.visualViewport?.removeEventListener('scroll', positionOptions);
        if (closeOpenEnhancedSelect === close) closeOpenEnhancedSelect = null;
    };
    const open = () => {
        if (wrapper.classList.contains('is-open')) return;
        closeOpenEnhancedSelect?.();
        const inheritedStyles = getComputedStyle(wrapper);
        options.dataset.themeMode = wrapper.closest('.staff-theme-dark, .member-theme-dark, .public-theme-dark, .worship-theme-dark') ? 'dark' : 'light';
        ['--surface', '--surface-ink', '--ink', '--muted', '--brand', '--line-strong'].forEach((property) => {
            const value = inheritedStyles.getPropertyValue(property).trim();
            if (value) options.style.setProperty(property, value);
        });
        const menuStyles = getComputedStyle(options);
        options.style.backgroundColor = menuStyles.backgroundColor;
        options.style.color = menuStyles.color;
        options.style.borderColor = menuStyles.borderColor;
        options.style.fontFamily = menuStyles.fontFamily;
        (wrapper.closest('dialog') ?? document.body).append(options);
        options.classList.add('is-portaled');
        wrapper.classList.add('is-open');
        options.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        closeOpenEnhancedSelect = close;
        positionOptions();
        addPageListener(document, 'scroll', positionOptions, true);
        addPageListener(window, 'resize', positionOptions);
        addPageListener(window.visualViewport, 'resize', positionOptions);
        addPageListener(window.visualViewport, 'scroll', positionOptions);
        if (searchInput) {
            searchInput.focus();
            if (remoteUrl && !remoteLoaded) loadRemoteChoices(searchInput.value.trim());
        }
        else (options.querySelector('[aria-selected="true"]:not(:disabled)') ?? options.querySelector('button:not(:disabled)'))?.focus();
    };
    trigger.addEventListener('click', () => wrapper.classList.contains('is-open') ? close() : open());
    const handleMenuKeydown = (event) => {
        const visibleChoices = [...options.querySelectorAll('button:not(:disabled):not([hidden])')];
        const currentIndex = visibleChoices.indexOf(document.activeElement);
        if (event.key === 'Escape') { event.preventDefault(); close(); trigger.focus(); return; }
        if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
        event.preventDefault();
        if (visibleChoices.length === 0) return;
        const nextIndex = event.key === 'Home' ? 0 : event.key === 'End' ? visibleChoices.length - 1 : currentIndex === -1 ? (event.key === 'ArrowDown' ? 0 : visibleChoices.length - 1) : (currentIndex + (event.key === 'ArrowDown' ? 1 : -1) + visibleChoices.length) % visibleChoices.length;
        visibleChoices[nextIndex]?.focus();
    };
    options.addEventListener('keydown', handleMenuKeydown);
    trigger.addEventListener('keydown', (event) => { if (event.key === 'ArrowDown' || event.key === 'ArrowUp') { event.preventDefault(); open(); } });
    wrapper.addEventListener('keydown', (event) => { if (event.key === 'Escape') { close(); trigger.focus(); } });
    addPageListener(document, 'click', (event) => { if (!wrapper.contains(event.target) && !options.contains(event.target)) close(); });
    select.addEventListener('change', sync); select.before(wrapper); wrapper.append(select, trigger, options); renderChoices();
});
};
const localizeGivingSelects = (root = document) => {
    const regionNames = typeof Intl.DisplayNames === 'function' ? new Intl.DisplayNames([document.documentElement.lang || 'en'], { type: 'region' }) : null;
    const currencyNames = typeof Intl.DisplayNames === 'function' ? new Intl.DisplayNames([document.documentElement.lang || 'en'], { type: 'currency' }) : null;
    root.querySelectorAll('select[data-country-select] option[value]').forEach((option) => {
        if (option.value) option.textContent = `${regionNames?.of(option.value) || option.value} (${option.value})`;
    });
    root.querySelectorAll('select[data-currency-select] option[value]').forEach((option) => {
        if (option.value) option.textContent = `${option.value} — ${currencyNames?.of(option.value) || option.value}`;
    });
};
localizeGivingSelects();
initializeEnhancedSelects();
// Pages that replace fragments in place (for example the media library filters)
// can re-run the same select enhancement without a full navigation.
window.initializeEnhancedSelects = initializeEnhancedSelects;

document.querySelectorAll('[data-copy-text]').forEach((button) => button.addEventListener('click', async () => {
    const value = button.dataset.copyText ?? '';
    if (!value) return;
    try { await navigator.clipboard.writeText(value); }
    catch {
        const field = document.createElement('textarea');
        field.value = value; field.setAttribute('readonly', ''); field.style.position = 'fixed'; field.style.opacity = '0';
        document.body.append(field); field.select(); document.execCommand('copy'); field.remove();
    }
    const label = button.getAttribute('title');
    button.setAttribute('title', 'Copied');
    window.setTimeout(() => button.setAttribute('title', label || 'Copy placeholder'), 1200);
}));
document.addEventListener('ministry:enhance-selects', (event) => {
    const root = event.detail instanceof Element ? event.detail : document;
    localizeGivingSelects(root);
    initializeEnhancedSelects(root);
});

document.querySelectorAll('[data-provider-form]').forEach((form) => {
    form.querySelector('[data-provider-picker]')?.addEventListener('change', (event) => {
        form.action = `/api/v1/staff/giving/providers/${encodeURIComponent(event.target.value)}`;
    });
});
document.querySelectorAll('[data-open-dialog]').forEach((button) => button.addEventListener('click', () => document.getElementById(button.dataset.openDialog)?.showModal()));
document.querySelectorAll('.giving-dialog').forEach((dialog) => {
    dialog.querySelectorAll('[data-close-dialog]').forEach((button) => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
});
document.querySelectorAll('form[data-confirm]').forEach((form) => form.addEventListener('submit', (event) => {
    if (!window.confirm(form.dataset.confirm)) event.preventDefault();
}));

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const notificationStack = document.querySelector('[data-notification-stack]');
const notificationTimers = new Map();
const notificationRemovalTimers = new Map();
let notificationSequence = 0;
const notificationApi = {
    show(message, state = 'info', options = {}) {
        if (!notificationStack || !message) return;
        const id = options.id ?? `notification-${++notificationSequence}`;
        let toast = notificationStack.querySelector(`[data-notification-id="${CSS.escape(id)}"]`);
        clearTimeout(notificationRemovalTimers.get(id));
        notificationRemovalTimers.delete(id);
        if (!toast) {
            toast = document.createElement('div');
            toast.className = 'viewport-notification';
            toast.dataset.notificationId = id;
            const indicator = document.createElement('span');
            indicator.className = 'viewport-notification-indicator';
            indicator.setAttribute('aria-hidden', 'true');
            const copy = document.createElement('p');
            copy.className = 'viewport-notification-copy';
            const dismiss = document.createElement('button');
            dismiss.type = 'button';
            dismiss.className = 'viewport-notification-dismiss';
            dismiss.setAttribute('aria-label', 'Dismiss notification');
            const icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            icon.setAttribute('viewBox', '0 0 20 20');
            icon.setAttribute('aria-hidden', 'true');
            const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            path.setAttribute('d', 'm5 5 10 10M15 5 5 15');
            path.setAttribute('fill', 'none');
            path.setAttribute('stroke', 'currentColor');
            path.setAttribute('stroke-width', '1.7');
            path.setAttribute('stroke-linecap', 'round');
            icon.append(path);
            dismiss.append(icon);
            dismiss.addEventListener('click', () => this.dismiss(id));
            toast.append(indicator, copy, dismiss);
            notificationStack.append(toast);
        }
        clearTimeout(notificationTimers.get(id));
        toast.dataset.state = state;
        toast.setAttribute('role', state === 'error' || state === 'warning' ? 'alert' : 'status');
        toast.querySelector('.viewport-notification-copy').textContent = message;
        toast.classList.add('is-visible');
        if (state === 'pending' || options.duration === 0) {
            notificationTimers.delete(id);
        } else {
            const duration = options.duration ?? (state === 'error' || state === 'warning' ? 9000 : 6500);
            notificationTimers.set(id, window.setTimeout(() => this.dismiss(id), duration));
        }
        return id;
    },
    dismiss(id) {
        const toast = notificationStack?.querySelector(`[data-notification-id="${CSS.escape(id)}"]`);
        if (!toast) return;
        clearTimeout(notificationTimers.get(id));
        notificationTimers.delete(id);
        toast.classList.remove('is-visible');
        notificationRemovalTimers.set(id, window.setTimeout(() => {
            toast.remove();
            notificationRemovalTimers.delete(id);
        }, 220));
    },
};
window.MinistryNotifications = notificationApi;

const showAsyncFeedback = (message, state = 'success') => notificationApi.show(message, state, { id: 'async-form-feedback' });

document.querySelectorAll('.form-success, .form-errors, .workspace-notice, .member-notice').forEach((notice) => {
    if (notice.dataset.notificationHandled) return;
    notice.dataset.notificationHandled = 'true';
    const message = notice.textContent.trim();
    if (!message) return;
    const state = notice.matches('.form-errors, .workspace-notice-error, .member-notice-error, .is-error') || notice.getAttribute('role') === 'alert' ? 'error' : 'success';
    notificationApi.show(message, state);
});

const removeFieldErrors = (form) => {
    form.querySelectorAll('[data-async-field-error]').forEach((node) => node.remove());
    form.querySelectorAll('[aria-invalid="true"]').forEach((field) => field.removeAttribute('aria-invalid'));
};

const showFieldErrors = (form, errors = {}) => {
    Object.entries(errors).forEach(([key, messages]) => {
        const field = [...form.elements].find((element) => element.name === key || (key.startsWith('fallbacks.') && element.name === 'fallbacks[]'));
        if (!field) return;
        field.setAttribute('aria-invalid', 'true');
        const error = document.createElement('small');
        error.dataset.asyncFieldError = '';
        error.className = 'field-error';
        error.textContent = Array.isArray(messages) ? messages[0] : String(messages);
        field.insertAdjacentElement('afterend', error);
    });
};

const applyAiSettingsData = (data = {}) => {
    if (data.provider_key && data.provider) {
        const badge = document.querySelector(`[data-ai-provider-status="${CSS.escape(data.provider_key)}"]`);
        const count = document.querySelector(`[data-ai-provider-count="${CSS.escape(data.provider_key)}"]`);
        if (badge) badge.textContent = !data.provider.configured ? 'Not configured' : (!data.provider.enabled ? 'Disabled' : (data.provider.status === 'connected' ? 'Connected' : data.provider.status));
        if (count) count.textContent = String(data.provider.model_count ?? 0);
        const refresh = [...document.querySelectorAll('form[action*="catalog"]')].find((form) => form.action.includes(`/catalog/${data.provider_key}/refresh`))?.querySelector('button[type="submit"]');
        if (refresh) refresh.disabled = !data.provider.enabled || !data.provider.configured;
    }
    if (data.route) {
        const badge = document.querySelector('[data-ai-route-status]');
        if (badge) badge.textContent = data.route.status[0].toLocaleUpperCase() + data.route.status.slice(1);
        const help = document.querySelector('[data-ai-route-help]');
        if (help) help.textContent = data.route.status === 'verified' ? 'The route passed its live compatibility check.' : 'The route cannot be used until it passes the live check.';
        document.querySelectorAll('[data-ai-model-select]').forEach((select) => {
            const value = select.name === 'primary_model' ? data.route.primary : (select.name === 'fallbacks[]' ? (data.route.fallbacks?.[Number(select.dataset.fallbackIndex ?? 0)] ?? '') : '');
            if (value && [...select.options].some((option) => option.value === value)) select.value = value;
            select.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }
};

const applyAsyncFormData = (form, data = {}) => {
    if (form.dataset.asyncHandler === 'ai-settings') applyAiSettingsData(data);
    form.dispatchEvent(new CustomEvent('ministry:async-success', { bubbles: true, detail: data }));
    if (!data.redirect) return;
    if (window.Livewire?.navigate) {
        window.Livewire.navigate(data.redirect);
    } else {
        window.location.assign(data.redirect);
    }
};

document.querySelectorAll('form[data-async-form]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (form.dataset.submitting === 'true') return;
        form.dataset.submitting = 'true';
        removeFieldErrors(form);
        const pendingLabel = form.dataset.asyncPendingLabel ?? form.dataset.pendingLabel ?? 'Saving…';
        showAsyncFeedback(pendingLabel, 'pending');
        const submitter = event.submitter;
        const originalContent = submitter?.innerHTML;
        const formData = new FormData(form);
        if (submitter?.name) formData.append(submitter.name, submitter.value);
        if (submitter) {
            submitter.disabled = true;
            submitter.setAttribute('aria-busy', 'true');
            if (!submitter.classList.contains('page-editor-icon-button')) submitter.textContent = pendingLabel;
        }
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}) },
                credentials: 'same-origin',
            });
            const result = await response.json();
            if (!response.ok) {
                showFieldErrors(form, result.errors);
                throw new Error(result.message ?? 'This change could not be saved. Check the highlighted fields.');
            }
            applyAsyncFormData(form, result.data ?? {});
            showAsyncFeedback(result.message ?? 'Saved.', 'success');
            form.querySelector('input[name="api_key"]')?.value && (form.querySelector('input[name="api_key"]').value = '');
        } catch (error) {
            showAsyncFeedback(error instanceof Error ? error.message : 'A network error prevented this change from being saved.', 'error');
        } finally {
            delete form.dataset.submitting;
            if (submitter) {
                submitter.disabled = false;
                submitter.removeAttribute('aria-busy');
                submitter.innerHTML = originalContent ?? 'Save';
            }
        }
    });
});

};

document.addEventListener('livewire:navigating', () => { livewireNavigationPending = true; });
document.addEventListener('livewire:navigated', () => {
    livewireNavigationPending = false;
    initializePage(true);
});

initializePage();
