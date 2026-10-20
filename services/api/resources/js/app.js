import './bootstrap';
import ZoomVideo from '@zoom/videosdk';

document.querySelectorAll('[data-zoom-meeting]').forEach((room) => {
    const joinButton = room.querySelector('[data-zoom-join]');
    const status = room.querySelector('[data-zoom-status]');
    const stage = room.querySelector('[data-zoom-stage]');
    const help = room.querySelector('[data-zoom-help]');
    let client;

    const setStatus = (message) => { if (status) status.textContent = message; };
    joinButton?.addEventListener('click', async () => {
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
                return;
            }

            setStatus('Connecting securely…');
            client = ZoomVideo.createClient();
            await client.init('en-US', 'Global', { patchJsMedia: true });
            await client.join(body.data.grant.session_name, body.data.grant.token, room.dataset.displayName, body.data.grant.session_passcode);
            const stream = client.getMediaStream();
            stage?.replaceChildren();
            const message = document.createElement('div');
            message.className = 'meeting-stage-empty';
            message.innerHTML = '<strong>You are in the meeting</strong><p>Device controls, participant video, chat, screen sharing, and host controls are being added to this branded room.</p>';
            stage?.append(message);
            setStatus('Connected');
            joinButton.textContent = 'Connected';
            joinButton.disabled = true;
            window.addEventListener('beforeunload', () => client?.leave?.(true), { once: true });
            // Retain the stream reference for the next room-control slice. The SDK has
            // joined successfully before media controls are exposed to the participant.
            room.zoomMediaStream = stream;
        } catch (error) {
            setStatus('Unable to join');
            if (help) help.textContent = error.message || 'Check the room configuration and try again.';
            joinButton.disabled = false;
        }
    });
});

document.querySelectorAll('[data-public-shell]').forEach((shell) => {
    const menuButton = shell.querySelector('[data-public-menu]');
    const publicNavigation = shell.querySelector('[data-public-navigation]');
    const publicSidebar = shell.querySelector('[data-public-sidebar]');
    const backdrop = shell.querySelector('.workspace-backdrop');
    const closers = shell.querySelectorAll('[data-public-close]');
    const setNavigation = (open) => {
        shell.classList.toggle('navigation-open', open);
        publicSidebar?.classList.toggle('is-open', open);
        backdrop?.classList.toggle('is-open', open);
        menuButton?.setAttribute('aria-expanded', String(open));
    };

    menuButton?.addEventListener('click', () => setNavigation(!shell.classList.contains('navigation-open')));
    closers.forEach((button) => button.addEventListener('click', () => setNavigation(false)));
    publicNavigation?.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setNavigation(false)));
    window.addEventListener('keydown', (event) => { if (event.key === 'Escape') setNavigation(false); });
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
    window.setInterval(poll, 10000);
}

document.querySelectorAll('[data-workspace-shell]').forEach((shell) => {
    const sidebar = shell.querySelector('[data-workspace-sidebar]');
    const toggle = shell.querySelector('[data-workspace-toggle]');
    const closers = shell.querySelectorAll('[data-workspace-close]');
    const themeToggle = shell.querySelector('[data-workspace-theme-toggle]');
    const storageKey = 'ministry-theme';
    const legacyStorageKey = 'ministry-staff-theme';

    const setNavigation = (open) => {
        shell.classList.toggle('navigation-open', open);
        sidebar?.classList.toggle('is-open', open);
        toggle?.setAttribute('aria-expanded', String(open));
    };

    const setTheme = (theme) => {
        const dark = theme === 'dark';
        shell.classList.toggle('staff-theme-dark', dark);
        themeToggle?.setAttribute('aria-pressed', String(dark));
        themeToggle?.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
        const label = themeToggle?.querySelector('.workspace-theme-label');
        if (label) label.textContent = dark ? 'Dark' : 'Light';
    };

    const savedTheme = window.localStorage.getItem(storageKey) ?? window.localStorage.getItem(legacyStorageKey);
    if (savedTheme) window.localStorage.setItem(storageKey, savedTheme);
    setTheme(savedTheme === 'dark' ? 'dark' : 'light');
    toggle?.addEventListener('click', () => setNavigation(!shell.classList.contains('navigation-open')));
    closers.forEach((button) => button.addEventListener('click', () => setNavigation(false)));
    sidebar?.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setNavigation(false)));
    window.addEventListener('keydown', (event) => { if (event.key === 'Escape') setNavigation(false); });
    themeToggle?.addEventListener('click', () => {
        const theme = shell.classList.contains('staff-theme-dark') ? 'light' : 'dark';
        window.localStorage.setItem(storageKey, theme);
        setTheme(theme);
    });
    window.addEventListener('storage', (event) => { if (event.key === storageKey) setTheme(event.newValue === 'dark' ? 'dark' : 'light'); });
});

document.querySelectorAll('[data-member-shell]').forEach((shell) => {
    const sidebar = shell.querySelector('[data-member-sidebar]');
    const toggles = shell.querySelectorAll('[data-member-toggle]');
    const closers = shell.querySelectorAll('[data-member-close]');
    const themeToggle = shell.querySelector('[data-member-theme-toggle]');
    const storageKey = 'ministry-theme';
    const legacyStorageKey = 'ministry-member-theme';
    const setNavigation = (open) => {
        shell.classList.toggle('member-navigation-open', open);
        sidebar?.classList.toggle('is-open', open);
        toggles.forEach((toggle) => toggle.setAttribute('aria-expanded', String(open)));
    };
    const setTheme = (theme) => {
        const dark = theme === 'dark';
        shell.classList.toggle('member-theme-dark', dark);
        themeToggle?.setAttribute('aria-pressed', String(dark));
        themeToggle?.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
        const label = themeToggle?.querySelector('.member-theme-label');
        if (label) label.textContent = dark ? 'Dark' : 'Light';
    };

    const savedTheme = window.localStorage.getItem(storageKey) ?? window.localStorage.getItem(legacyStorageKey);
    if (savedTheme) window.localStorage.setItem(storageKey, savedTheme);
    setTheme(savedTheme === 'dark' ? 'dark' : 'light');
    toggles.forEach((toggle) => toggle.addEventListener('click', () => setNavigation(!shell.classList.contains('member-navigation-open'))));
    closers.forEach((button) => button.addEventListener('click', () => setNavigation(false)));
    sidebar?.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setNavigation(false)));
    window.addEventListener('keydown', (event) => { if (event.key === 'Escape') setNavigation(false); });
    themeToggle?.addEventListener('click', () => {
        const theme = shell.classList.contains('member-theme-dark') ? 'light' : 'dark';
        window.localStorage.setItem(storageKey, theme);
        setTheme(theme);
    });
    window.addEventListener('storage', (event) => { if (event.key === storageKey) setTheme(event.newValue === 'dark' ? 'dark' : 'light'); });

    const sectionLinks = [...shell.querySelectorAll('.member-navigation a[href^="#"]')];
    const sections = sectionLinks.map((link) => document.querySelector(link.getAttribute('href'))).filter(Boolean);
    if (sections.length && 'IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            const visible = entries.filter((entry) => entry.isIntersecting).sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
            if (!visible) return;
            sectionLinks.forEach((link) => link.toggleAttribute('aria-current', link.getAttribute('href') === `#${visible.target.id}`));
        }, { rootMargin: '-22% 0px -64% 0px', threshold: [0.01, 0.3, 0.6] });
        sections.forEach((section) => observer.observe(section));
    }

    shell.querySelectorAll('[name="birthday_collection_consent"]').forEach((consent) => {
        const form = consent.closest('form');
        const birthdayControls = form?.querySelectorAll('[name="birthday_month"], [name="birthday_day"], [name="birthday_staff_reminder"], [name="birthday_public_recognition"]') ?? [];
        const syncBirthdayControls = () => {
            birthdayControls.forEach((control) => {
                control.disabled = !consent.checked;
                control.closest('.member-preference')?.classList.toggle('is-disabled', !consent.checked);
            });
        };
        syncBirthdayControls();
        consent.addEventListener('change', syncBirthdayControls);
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

    const updatePreview = () => {
        const name = form.querySelector('[name="name"]')?.value.trim();
        const primary = form.querySelector('[data-setup-colour="primary"]')?.value ?? '#1F6B55';
        const accent = form.querySelector('[data-setup-colour="accent"]')?.value ?? '#D8AB5D';
        setupWizard.style.setProperty('--brand', primary);
        setupWizard.style.setProperty('--accent', accent);
        const previewName = setupWizard.querySelector('[data-setup-preview-name]');
        const previewMark = setupWizard.querySelector('[data-setup-preview-mark]');
        if (previewName) previewName.textContent = name || 'Your ministry';
        if (previewMark) previewMark.textContent = (name || 'M').slice(0, 1).toUpperCase();
        const primaryValue = setupWizard.querySelector('[data-setup-primary-value]');
        const accentValue = setupWizard.querySelector('[data-setup-accent-value]');
        if (primaryValue) primaryValue.textContent = primary.toUpperCase();
        if (accentValue) accentValue.textContent = accent.toUpperCase();
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
    form?.querySelectorAll('[name="name"], [data-setup-colour]').forEach((field) => field.addEventListener('input', updatePreview));
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
    document.addEventListener('click', (event) => { if (!picker.contains(event.target)) close(); });
    picker.addEventListener('keydown', (event) => { if (event.key === 'Escape') { close(); trigger.focus(); } });
});

const enhancedSelects = [...document.querySelectorAll('select:not([data-native-select])')];
enhancedSelects.forEach((select, index) => {
    if (select.closest('.enhanced-select')) return;
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
    options.className = 'enhanced-select-options'; options.id = `enhanced-select-${index}`; options.setAttribute('role', 'listbox'); options.hidden = true;
    const sync = () => {
        const selected = select.options[select.selectedIndex];
        text.textContent = selected?.textContent ?? 'Choose an option';
        [...options.children].forEach((option) => option.setAttribute('aria-selected', String(option.dataset.value === select.value)));
    };
    [...select.options].forEach((option) => {
        const choice = document.createElement('button'); choice.type = 'button'; choice.setAttribute('role', 'option'); choice.dataset.value = option.value; choice.textContent = option.textContent; choice.disabled = option.disabled;
        choice.addEventListener('click', () => { select.value = option.value; select.dispatchEvent(new Event('change', { bubbles: true })); sync(); close(); trigger.focus(); }); options.append(choice);
    });
    const positionOptions = () => {
        options.style.left = '50%'; options.style.right = 'auto'; options.style.transform = 'translateX(-50%)';
        const edge = 12;
        const bounds = options.getBoundingClientRect();
        if (bounds.left < edge) {
            options.style.left = '0'; options.style.transform = 'none';
        } else if (bounds.right > window.innerWidth - edge) {
            options.style.left = 'auto'; options.style.right = '0'; options.style.transform = 'none';
        }
    };
    const close = () => { wrapper.classList.remove('is-open'); options.hidden = true; trigger.setAttribute('aria-expanded', 'false'); options.style.removeProperty('left'); options.style.removeProperty('right'); options.style.removeProperty('transform'); };
    const open = () => { wrapper.classList.add('is-open'); options.hidden = false; trigger.setAttribute('aria-expanded', 'true'); positionOptions(); options.querySelector('[aria-selected="true"]')?.focus(); };
    trigger.addEventListener('click', () => wrapper.classList.contains('is-open') ? close() : open());
    wrapper.addEventListener('keydown', (event) => { if (event.key === 'Escape') { close(); trigger.focus(); } });
    document.addEventListener('click', (event) => { if (!wrapper.contains(event.target)) close(); });
    select.addEventListener('change', sync); select.before(wrapper); wrapper.append(select, trigger, options); sync();
    window.addEventListener('resize', () => { if (wrapper.classList.contains('is-open')) positionOptions(); });
});
