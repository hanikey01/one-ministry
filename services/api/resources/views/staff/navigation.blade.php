<x-layouts.workspace title="Navigation" :ministry-name="$settings->name" :user-name="$user->name" user-role="Staff workspace">
    <x-slot:navigation></x-slot:navigation>

    <div class="navigation-page-theme">

    <header class="workspace-page-heading staff-page-command navigation-heading">
        <div>
            <p>MINISTRY MENUS</p>
            <h1>Navigation</h1>
            <span>Choose what staff, members, and visitors see in their menus.</span>
        </div>
        <a class="button button-secondary" href="{{ route('staff.pages') }}">Manage pages</a>
    </header>

    @if(session('status'))<div class="workspace-notice workspace-notice-success">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="workspace-notice workspace-notice-error">{{ $errors->first() }}</div>@endif

    <nav class="navigation-audience-tabs" aria-label="Choose which menus to manage" role="tablist">
        <button type="button" role="tab" id="navigation-tab-staff" aria-controls="navigation-panel-staff" aria-selected="true" tabindex="0" data-navigation-tab="staff"><x-ui.icon name="access" :size="17"/><span>Staff</span></button>
        <button type="button" role="tab" id="navigation-tab-member" aria-controls="navigation-panel-member" aria-selected="false" tabindex="-1" data-navigation-tab="member"><x-ui.icon name="people" :size="17"/><span>Members</span></button>
        <button type="button" role="tab" id="navigation-tab-public" aria-controls="navigation-panel-public" aria-selected="false" tabindex="-1" data-navigation-tab="public"><x-ui.icon name="external" :size="17"/><span>Public website</span></button>
    </nav>

    <form method="POST" action="{{ route('staff.navigation.layouts.update') }}" class="navigation-layout-form">
        @csrf
        @method('PUT')

        @foreach(['staff' => 'Staff workspace', 'member' => 'Member community'] as $surface => $surfaceLabel)
            <section id="navigation-panel-{{ $surface }}" class="navigation-audience-panel" role="tabpanel" aria-labelledby="navigation-tab-{{ $surface }}" data-navigation-panel="{{ $surface }}" @if($surface !== 'staff') hidden @endif>
                <header class="navigation-audience-intro">
                    <div><p>{{ strtoupper($surface) }} MENUS</p><h2>{{ $surfaceLabel }}</h2><span>Choose destinations, then arrange them in the order people should see them. {{ $surface === 'staff' ? 'Staff links continue to follow permissions and enabled modules.' : 'Member links remain protected by sign-in and their assigned permissions.' }}</span></div>
                </header>

                <div class="navigation-layout-grid">
                    @php($sidebarSections = collect($menuOptions[$surface])->pluck('group')->filter()->unique()->values())
                    @foreach(['sidebar' => ['Sidebar', 'Choose destinations, order them, and optionally place them under a collapsible parent.'], 'mobile' => ['Mobile footer', 'Shown as five direct destinations at the bottom of mobile screens; nested links remain in the menu drawer.']] as $menuSlot => [$slotLabel, $slotDescription])
                        @php($maxSelections = $menuSlot === 'mobile' ? 5 : null)
                        @php($selectedEntries = collect($menuLayouts[$surface][$menuSlot] ?? []))
                        @php($selectedKeys = $selectedEntries->map(fn ($entry) => is_string($entry) ? $entry : ($entry['key'] ?? null))->filter()->values())
                        <section class="navigation-layout-card" data-navigation-selection data-surface="{{ $surface }}" data-menu-slot="{{ $menuSlot }}" data-max-selections="{{ $maxSelections }}">
                            <header class="navigation-layout-card-heading">
                                <div><p>{{ $slotLabel }}</p><span>{{ $slotDescription }}</span></div>
                                <span class="navigation-selection-count" data-navigation-selection-status role="status">{{ $selectedKeys->count() }} selected</span>
                            </header>

                            <div class="navigation-selected-list" data-navigation-selected-list aria-label="Selected {{ strtolower($slotLabel) }} destinations">
                                @foreach($selectedEntries as $entry)
                                    @php($key = is_string($entry) ? $entry : ($entry['key'] ?? null))
                                    @if($key && isset($menuOptions[$surface][$key]))
                                        @include('staff.navigation-layout-row', ['surface' => $surface, 'menuSlot' => $menuSlot, 'key' => $key, 'option' => $menuOptions[$surface][$key], 'configured' => $entry, 'selected' => true, 'position' => $loop->iteration, 'fixed' => $surface === 'staff' && $menuSlot === 'sidebar' && $key === 'staff.navigation', 'sidebarSections' => $sidebarSections])
                                    @endif
                                @endforeach
                                <p class="navigation-empty-selection" data-navigation-empty @if($selectedKeys->isNotEmpty()) hidden @endif>No destinations selected yet. Add one below.</p>
                            </div>

                            <details class="navigation-add-destinations">
                                <summary><span class="navigation-add-symbol">+</span><span><strong>Add destinations</strong><small><span data-navigation-available-count>{{ max(0, count($menuOptions[$surface]) - $selectedKeys->count()) }}</span> available</small></span><x-ui.icon name="chevron-down" :size="16"/></summary>
                                <div class="navigation-available-content">
                                    <label class="navigation-search"><x-ui.icon name="search" :size="16"/><input type="search" placeholder="Search destinations" aria-label="Search {{ strtolower($surfaceLabel) }} destinations" data-navigation-search></label>
                                    <div class="navigation-available-list" data-navigation-available-list>
                                        @foreach($menuOptions[$surface] as $key => $option)
                                            @unless($selectedKeys->contains($key))
                                                @include('staff.navigation-layout-row', ['surface' => $surface, 'menuSlot' => $menuSlot, 'key' => $key, 'option' => $option, 'configured' => null, 'selected' => false, 'position' => null, 'fixed' => false, 'sidebarSections' => $sidebarSections])
                                            @endunless
                                        @endforeach
                                    </div>
                                    <p class="navigation-no-search-results" data-navigation-no-results hidden>No matching destinations.</p>
                                </div>
                            </details>
                        </section>
                    @endforeach
                </div>

                @if($surface === 'staff')
                    <aside class="navigation-permission-note"><x-ui.icon name="security" :size="17"/><span><strong>Access stays protected</strong> Hiding a link only changes the menu. Each page still checks the person’s permissions and enabled modules.</span></aside>
                @endif
            </section>
        @endforeach

        <div class="navigation-save-bar" data-navigation-save-bar>
            <span>Changes apply after you save.</span>
            <button class="button" type="submit">Save menu changes</button>
        </div>
    </form>

    <section id="navigation-panel-public" class="navigation-audience-panel navigation-public-panel" role="tabpanel" aria-labelledby="navigation-tab-public" data-navigation-panel="public" hidden>
        <header class="navigation-audience-intro">
            <div><p>PUBLIC WEBSITE</p><h2>Visitor menus</h2><span>Choose the links visitors see on the public website. The first five enabled mobile links appear in the footer.</span></div>
        </header>

        <div class="navigation-layout-grid navigation-public-grid">
            <section class="navigation-layout-card navigation-public-list">
                @php($selectedPublicItems = $items->filter(fn ($item) => (bool) $item->is_enabled))
                @php($availablePublicItems = $items->reject(fn ($item) => (bool) $item->is_enabled))
                <header class="navigation-layout-card-heading"><div><p>Public menu</p><span>Select links, arrange their order, or place a link under a collapsible parent. Top-level links control sidebar sections and the mobile footer.</span></div><span class="navigation-selection-count" data-navigation-public-count>{{ $selectedPublicItems->count() }} of {{ $items->count() }} selected</span></header>
                <form id="public-navigation-settings" method="POST" action="{{ route('staff.navigation.reorder') }}" data-navigation-order-form>
                    @csrf @method('PUT')
                    <div data-navigation-order>
                        @forelse($selectedPublicItems as $item)
                            @include('staff.navigation-item', ['item' => $item, 'icons' => $icons, 'publicSections' => $publicSections])
                        @empty
                            <div class="empty-cell" data-public-list-empty>No links selected. Add one from the available links below.</div>
                        @endforelse
                    </div>
                    <details class="navigation-destination-library navigation-add-destinations navigation-public-available" data-public-available-disclosure @if($availablePublicItems->isEmpty()) hidden @endif>
                        <summary><span class="navigation-add-symbol">+</span><span><strong>Add existing links</strong><small><span data-public-available-count>{{ $availablePublicItems->count() }}</span> available</small></span><x-ui.icon name="chevron-down" :size="16"/></summary>
                        <div class="navigation-available-list" data-navigation-available>
                            @forelse($availablePublicItems as $item)
                                @include('staff.navigation-item', ['item' => $item, 'icons' => $icons, 'publicSections' => $publicSections])
                            @empty
                                <div class="empty-cell" data-public-available-empty>No saved links are available to add.</div>
                            @endforelse
                        </div>
                    </details>
                    <button class="button" type="submit">Save menu changes</button>
                </form>
            </section>

            <aside id="navigation-public-add" class="navigation-layout-card navigation-public-add">
                <header class="navigation-layout-card-heading"><div><p>Add destinations</p><span>Choose an existing page or create a custom link.</span></div></header>
                <p class="navigation-quick-add-status" role="status" aria-live="polite" data-navigation-quick-add-status></p>
                <details class="navigation-destination-library navigation-add-destinations">
                    <summary><span class="navigation-add-symbol">+</span><span><strong>Quick add a destination</strong><small>Use a public page, member page, or published custom page</small></span><x-ui.icon name="chevron-down" :size="16"/></summary>
                    <div class="navigation-destination-grid">
                        @foreach(collect($destinations)->groupBy('group') as $group => $links)
                            <section><h3>{{ $group }}</h3><div>
                                @foreach($links as $link)
                                    @unless($items->contains(fn ($item) => $item->url === $link['url'] && $item->parent_id === null))
                                        <form method="POST" action="{{ route('staff.navigation.store') }}" data-navigation-quick-add>
                                            @csrf
                                            <input type="hidden" name="label" value="{{ $link['label'] }}"><input type="hidden" name="url" value="{{ $link['url'] }}"><input type="hidden" name="icon" value="{{ $link['icon'] }}"><input type="hidden" name="required_feature" value="{{ $link['required_feature'] }}"><input type="hidden" name="visible_desktop" value="1"><input type="hidden" name="visible_mobile" value="1">
                                            <button type="button" data-navigation-quick-add-button><x-ui.icon :name="$link['icon']" :size="17"/><span><strong>{{ $link['label'] }}</strong><small>{{ $link['url'] }}</small></span><b>+</b></button>
                                        </form>
                                    @endunless
                                @endforeach
                            </div></section>
                        @endforeach
                    </div>
                </details>
                <form method="POST" action="{{ route('staff.navigation.store') }}" data-navigation-custom-add>
                    @csrf
                    @include('staff.navigation-fields', ['item' => null])
                    <button class="button" type="submit"><x-ui.icon name="add" :size="15"/>Add link</button>
                </form>
                @if($items->isNotEmpty())
                    <details class="navigation-public-details">
                        <summary><span><strong>Manage link details</strong><small>Change labels, destinations, visibility, or remove links</small></span><x-ui.icon name="chevron-down" :size="16"/></summary>
                        <div class="navigation-public-details-list">
                            @foreach($items as $item)
                                <details class="navigation-public-detail-item" data-dismiss-on-outside>
                                    <summary>{{ $item->label }} <span>{{ $item->url }}</span></summary>
                                    <form method="POST" action="{{ route('staff.navigation.update', $item->id) }}">@csrf @method('PUT') @include('staff.navigation-fields', ['item' => $item, 'showIcon' => false])<button class="button" type="submit">Save link details</button></form>
                                    <form method="POST" action="{{ route('staff.navigation.destroy', $item->id) }}">@csrf @method('DELETE')<button class="button button-secondary" type="submit">Remove link</button></form>
                                </details>
                            @endforeach
                        </div>
                    </details>
                @endif
            </aside>
        </div>
    </section>

    <template id="navigation-icon-options">
        @foreach($icons as $icon)
            <button class="icon-picker-option" type="button" aria-label="{{ str($icon)->headline() }}" title="{{ str($icon)->headline() }}" data-icon-picker-option data-icon="{{ $icon }}" data-icon-search="{{ str($icon)->headline() }}"><x-ui.icon :name="$icon" :size="16"/><span>{{ str($icon)->headline() }}</span></button>
        @endforeach
    </template>
    <script>
        (() => {
            const tabs = [...document.querySelectorAll('[data-navigation-tab]')];
            const panels = [...document.querySelectorAll('[data-navigation-panel]')];
            const saveBar = document.querySelector('[data-navigation-save-bar]');
            const refreshSelectTitle = (select) => {
                select.title = select.options[select.selectedIndex]?.text || '';
            };
            document.querySelectorAll('[data-navigation-group], .navigation-public-section select').forEach((select) => {
                refreshSelectTitle(select);
                select.addEventListener('change', () => refreshSelectTitle(select));
            });
            const activateTab = (name, focus = false) => {
                tabs.forEach((tab) => {
                    const active = tab.dataset.navigationTab === name;
                    tab.setAttribute('aria-selected', String(active));
                    tab.tabIndex = active ? 0 : -1;
                    if (active && focus) tab.focus();
                });
                panels.forEach((panel) => panel.hidden = panel.dataset.navigationPanel !== name);
                if (saveBar) saveBar.hidden = name === 'public';
            };

            tabs.forEach((tab, index) => {
                tab.addEventListener('click', () => activateTab(tab.dataset.navigationTab));
                tab.addEventListener('keydown', (event) => {
                    if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
                    event.preventDefault();
                    const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length;
                    activateTab(tabs[next].dataset.navigationTab, true);
                });
            });

            const closeIconPicker = (picker, restoreFocus = false) => {
                const trigger = picker.querySelector('[data-icon-picker-trigger]');
                const panel = picker.querySelector('[data-icon-picker-panel]') || document.getElementById(trigger?.getAttribute('aria-controls'));
                if (!trigger || !panel) return;
                panel.hidden = true;
                trigger.setAttribute('aria-expanded', 'false');
                if (panel.parentElement === document.body) picker.append(panel);
                if (restoreFocus) trigger.focus();
            };
            const positionIconPicker = (picker) => {
                const trigger = picker.querySelector('[data-icon-picker-trigger]');
                const panel = picker.querySelector('[data-icon-picker-panel]') || document.getElementById(trigger?.getAttribute('aria-controls'));
                if (!trigger || !panel || panel.hidden) return;
                const rect = trigger.getBoundingClientRect();
                const theme = getComputedStyle(picker);
                ['--surface', '--surface-muted', '--ink', '--muted', '--line', '--line-strong', '--accent'].forEach((token) => {
                    const value = theme.getPropertyValue(token).trim();
                    if (value) panel.style.setProperty(token, value);
                });
                const width = Math.min(320, window.innerWidth - 24);
                const left = Math.max(12, Math.min(rect.left, window.innerWidth - width - 12));
                const availableBelow = window.innerHeight - rect.bottom - 12;
                const availableAbove = rect.top - 12;
                const openBelow = availableBelow >= availableAbove;
                const availableSpace = openBelow ? availableBelow : availableAbove;
                const height = Math.min(320, window.innerHeight - 24, Math.max(160, availableSpace));
                panel.style.width = `${width}px`;
                panel.style.setProperty('max-height', `${height}px`, 'important');
                panel.style.left = `${left}px`;
                panel.style.top = `${openBelow ? rect.bottom + 6 : Math.max(12, rect.top - height - 6)}px`;
            };
            const bindIconPicker = (picker) => {
                if (picker.dataset.iconPickerReady === 'true') return;
                picker.dataset.iconPickerReady = 'true';
                const trigger = picker.querySelector('[data-icon-picker-trigger]');
                const panel = picker.querySelector('[data-icon-picker-panel]');
                const valueInput = picker.querySelector('[data-icon-picker-value]');
                const search = picker.querySelector('[data-icon-picker-search]');
                const empty = picker.querySelector('[data-icon-picker-empty]');
                if (!trigger || !panel || !valueInput) return;
                trigger.addEventListener('click', () => {
                    const opening = panel.hidden;
                    document.querySelectorAll('[data-icon-picker-panel]:not([hidden])').forEach((openPanel) => {
                        const owner = openPanel.closest('[data-icon-picker]') || document.querySelector(`[aria-controls="${CSS.escape(openPanel.id)}"]`)?.closest('[data-icon-picker]');
                        if (owner && owner !== picker) closeIconPicker(owner);
                    });
                    if (!opening) { closeIconPicker(picker); return; }
                    document.body.append(panel);
                    panel.hidden = false;
                    panel.dataset.owner = trigger.id;
                    trigger.setAttribute('aria-expanded', 'true');
                    const grid = panel.querySelector('[data-icon-picker-grid]');
                    if (!grid.querySelector('[data-icon-picker-option]')) grid.prepend(document.querySelector('#navigation-icon-options').content.cloneNode(true));
                    panel.querySelectorAll('[data-icon-picker-option]').forEach((option) => option.setAttribute('aria-pressed', String(option.dataset.icon === valueInput.value)));
                    positionIconPicker(picker);
                    search.value = '';
                    panel.querySelectorAll('[data-icon-picker-option]').forEach((option) => option.hidden = false);
                    if (empty) empty.hidden = true;
                    search.focus();
                });
                search?.addEventListener('input', () => {
                    const query = search.value.trim().toLocaleLowerCase();
                    let matches = 0;
                    panel.querySelectorAll('[data-icon-picker-option]').forEach((option) => {
                        option.hidden = !option.dataset.iconSearch.toLocaleLowerCase().includes(query);
                        if (!option.hidden) matches++;
                    });
                    if (empty) empty.hidden = matches > 0;
                });
                panel.addEventListener('click', (event) => {
                    const option = event.target.closest('[data-icon-picker-option]');
                    if (!option) return;
                    valueInput.value = option.dataset.icon;
                    panel.querySelectorAll('[data-icon-picker-option]').forEach((entry) => entry.setAttribute('aria-pressed', String(entry === option)));
                    const current = picker.querySelector('[data-icon-picker-current]');
                    current.replaceChildren(option.querySelector('svg').cloneNode(true));
                    picker.querySelector('[data-icon-picker-label]').textContent = option.dataset.iconSearch;
                    trigger.setAttribute('aria-label', `${trigger.getAttribute('aria-label').split(':')[0]}: ${option.dataset.iconSearch}`);
                    closeIconPicker(picker);
                    trigger.focus();
                });
                search?.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') { event.preventDefault(); closeIconPicker(picker, true); }
                });
            };
            document.querySelectorAll('[data-icon-picker]').forEach(bindIconPicker);
            const dismissIconPickers = (event) => {
                document.querySelectorAll('[data-icon-picker-panel]:not([hidden])').forEach((panel) => {
                    const owner = document.getElementById(panel.dataset.owner)?.closest('[data-icon-picker]') || document.querySelector(`[aria-controls="${CSS.escape(panel.id)}"]`)?.closest('[data-icon-picker]');
                    if (owner && !owner.contains(event.target) && !panel.contains(event.target)) closeIconPicker(owner);
                });
            };
            document.addEventListener('pointerdown', dismissIconPickers);
            window.MinistryPage?.addListener(window, 'resize', () => document.querySelectorAll('[data-icon-picker-panel]:not([hidden])').forEach((panel) => {
                const owner = document.querySelector(`[aria-controls="${CSS.escape(panel.id)}"]`)?.closest('[data-icon-picker]');
                if (owner) positionIconPicker(owner);
            }));
            window.MinistryPage?.addListener(window, 'scroll', () => document.querySelectorAll('[data-icon-picker-panel]:not([hidden])').forEach((panel) => {
                const owner = document.querySelector(`[aria-controls="${CSS.escape(panel.id)}"]`)?.closest('[data-icon-picker]');
                if (owner) positionIconPicker(owner);
            }), true);

            document.querySelectorAll('[data-navigation-selection]').forEach((card) => {
                const activeList = card.querySelector('[data-navigation-selected-list]');
                const availableList = card.querySelector('[data-navigation-available-list]');
                const count = card.querySelector('[data-navigation-selection-status]');
                const availableCount = card.querySelector('[data-navigation-available-count]');
                const emptyMessage = card.querySelector('[data-navigation-empty]');
                const noResults = card.querySelector('[data-navigation-no-results]');
                const max = Number(card.dataset.maxSelections);
                const rows = [...card.querySelectorAll('[data-navigation-row]')];

                const updateParentOptions = () => {
                    const selectedKeys = new Set([...activeList.querySelectorAll('[data-navigation-row]')].map((row) => row.dataset.navigationKey));
                    rows.forEach((row) => {
                        const parent = row.querySelector('[data-navigation-parent]');
                        if (!parent) return;
                        [...parent.options].forEach((option) => {
                            if (option.value) option.disabled = !selectedKeys.has(option.value);
                        });
                        if (parent.value && !selectedKeys.has(parent.value)) parent.value = '';
                        row.dataset.nested = String(Boolean(parent.value));
                    });
                };

                const updatePositions = () => {
                    const selectedRows = [...activeList.querySelectorAll('[data-navigation-row]')];
                    selectedRows.forEach((row, index) => {
                        row.querySelector('[data-navigation-position]').value = index + 1;
                        row.querySelector('[data-navigation-order-display]').textContent = String(index + 1).padStart(2, '0');
                        row.querySelector('[data-move-up]').disabled = index === 0;
                        row.querySelector('[data-move-down]').disabled = index === selectedRows.length - 1;
                    });
                    const selectedCount = selectedRows.length;
                    if (count) count.textContent = `${selectedCount} selected`;
                    if (availableCount) availableCount.textContent = String(Math.max(0, rows.length - selectedCount));
                    if (emptyMessage) emptyMessage.hidden = selectedCount > 0;
                    updateParentOptions();
                };

                const moveRow = (row, selected) => {
                    const checkbox = row.querySelector('[data-navigation-checkbox]');
                    row.dataset.selected = String(selected);
                    const sectionControl = row.querySelector('[data-navigation-section-control]');
                    if (sectionControl) sectionControl.hidden = !selected;
                    const sectionSelect = row.querySelector('[data-navigation-group]');
                    if (sectionSelect) sectionSelect.disabled = !selected;
                    const parentControl = row.querySelector('[data-navigation-parent-control]');
                    if (parentControl) parentControl.hidden = !selected;
                    const parentSelect = row.querySelector('[data-navigation-parent]');
                    if (parentSelect) parentSelect.disabled = !selected;
                    row.querySelector('[data-navigation-icon]').disabled = !selected;
                    row.querySelector('[data-icon-picker-trigger]').disabled = !selected;
                    row.querySelector('[data-navigation-position]').disabled = !selected;
                    row.querySelectorAll('[data-move-up], [data-move-down]').forEach((button) => button.hidden = !selected);
                    (selected ? activeList : availableList).append(row);
                    checkbox.checked = selected;
                    updatePositions();
                    filterAvailable(card);
                };

                const filterAvailable = (scope) => {
                    const query = scope.querySelector('[data-navigation-search]')?.value.trim().toLocaleLowerCase() ?? '';
                    const visibleRows = [...scope.querySelectorAll('[data-navigation-available-list] [data-navigation-row]')].filter((row) => {
                        const visible = row.dataset.searchText.includes(query);
                        row.hidden = !visible;
                        return visible;
                    });
                    if (noResults) noResults.hidden = visibleRows.length > 0;
                };

                rows.forEach((row) => {
                    const checkbox = row.querySelector('[data-navigation-checkbox]');
                    row.querySelector('[data-navigation-parent]')?.addEventListener('change', (event) => {
                        row.dataset.nested = String(Boolean(event.currentTarget.value));
                    });
                    checkbox.addEventListener('change', () => {
                        if (checkbox.checked && max > 0 && activeList.querySelectorAll('[data-navigation-row]').length >= max) {
                            checkbox.checked = false;
                            const summary = card.querySelector('.navigation-add-destinations summary strong');
                            if (summary) summary.textContent = `Maximum ${max} destinations selected`;
                            window.setTimeout(() => { if (summary) summary.textContent = 'Add destinations'; }, 2200);
                            return;
                        }
                        moveRow(row, checkbox.checked);
                    });
                    row.querySelector('[data-move-up]').addEventListener('click', () => {
                        const activeRows = [...activeList.querySelectorAll('[data-navigation-row]')];
                        const index = activeRows.indexOf(row);
                        if (index > 0) activeList.insertBefore(row, activeRows[index - 1]);
                        updatePositions();
                    });
                    row.querySelector('[data-move-down]').addEventListener('click', () => {
                        const activeRows = [...activeList.querySelectorAll('[data-navigation-row]')];
                        const index = activeRows.indexOf(row);
                        if (index >= 0 && index < activeRows.length - 1) activeList.insertBefore(activeRows[index + 1], row);
                        updatePositions();
                    });
                });

                card.querySelector('[data-navigation-search]')?.addEventListener('input', () => filterAvailable(card));
                updatePositions();
            });

            const publicList = document.querySelector('[data-navigation-order]');
            if (publicList) {
                let dragging = null;
                const availableList = document.querySelector('[data-navigation-available]');
                const status = document.querySelector('[data-navigation-quick-add-status]');
                const selectionCount = document.querySelector('[data-navigation-public-count]');
                const availableCount = document.querySelector('[data-public-available-count]');
                const availableDisclosure = document.querySelector('[data-public-available-disclosure]');
                const allPublicRows = () => [...publicList.querySelectorAll('[data-navigation-id]'), ...(availableList ? [...availableList.querySelectorAll('[data-navigation-id]')] : [])];
                const updateEmptyState = (list, selector, message, marker) => {
                    if (!list) return;
                    const empty = list.querySelector(selector);
                    if (list.querySelector('[data-navigation-id]')) empty?.remove();
                    else if (!empty) {
                        const placeholder = document.createElement('div');
                        placeholder.className = 'empty-cell';
                        placeholder.dataset[marker] = '';
                        placeholder.textContent = message;
                        list.append(placeholder);
                    }
                };
                const updateSelectionCount = () => {
                    const rows = allPublicRows();
                    const selected = rows.filter((row) => row.querySelector('[data-public-enabled]')?.checked).length;
                    const available = rows.length - selected;
                    if (selectionCount) selectionCount.textContent = `${selected} of ${rows.length} selected`;
                    if (availableCount) availableCount.textContent = String(available);
                    if (availableDisclosure) {
                        availableDisclosure.hidden = available === 0;
                        if (available === 0) availableDisclosure.open = false;
                    }
                };
                const bindPublicItem = (node) => {
                    const checkbox = node.querySelector('[data-public-enabled]');
                    const controls = node.querySelector('[data-public-controls]');
                    const syncEnabledState = () => {
                        const selected = checkbox?.checked ?? true;
                        node.dataset.selected = String(selected);
                        if (controls) controls.hidden = !selected;
                        const target = selected ? publicList : availableList;
                        if (target && node.parentElement !== target) target.append(node);
                        updateEmptyState(publicList, '[data-public-list-empty]', 'No links selected. Add one from the available links below.', 'publicListEmpty');
                        updateEmptyState(availableList, '[data-public-available-empty]', 'No saved links are available to add.', 'publicAvailableEmpty');
                        updateMoveButtons();
                        updateSelectionCount();
                    };
                    checkbox?.addEventListener('change', syncEnabledState);
                    node.querySelector('[data-public-move="up"]')?.addEventListener('click', () => {
                        const nodes = [...publicList.querySelectorAll('[data-navigation-id]')].filter((row) => row.querySelector('[data-public-enabled]')?.checked);
                        const index = nodes.indexOf(node);
                        if (index > 0) publicList.insertBefore(node, nodes[index - 1]);
                        updateMoveButtons();
                    });
                    node.querySelector('[data-public-move="down"]')?.addEventListener('click', () => {
                        const nodes = [...publicList.querySelectorAll('[data-navigation-id]')].filter((row) => row.querySelector('[data-public-enabled]')?.checked);
                        const index = nodes.indexOf(node);
                        if (index >= 0 && index < nodes.length - 1) publicList.insertBefore(nodes[index + 1], node);
                        updateMoveButtons();
                    });
                    node.addEventListener('dragstart', () => { dragging = node; node.classList.add('is-dragging'); });
                    node.addEventListener('dragover', (event) => event.preventDefault());
                    node.addEventListener('drop', (event) => {
                        event.preventDefault();
                        if (dragging && dragging !== node) publicList.insertBefore(dragging, node);
                        updateMoveButtons();
                    });
                    node.addEventListener('dragend', () => node.classList.remove('is-dragging'));
                };
                const updateMoveButtons = () => {
                    const nodes = allPublicRows();
                    const selectedNodes = [...publicList.querySelectorAll('[data-navigation-id]')];
                    nodes.forEach((node) => {
                        const up = node.querySelector('[data-public-move="up"]');
                        const down = node.querySelector('[data-public-move="down"]');
                        const selectedIndex = selectedNodes.indexOf(node);
                        if (up) up.disabled = selectedIndex <= 0;
                        if (down) down.disabled = selectedIndex < 0 || selectedIndex === selectedNodes.length - 1;
                        const display = node.querySelector('[data-navigation-order-display]');
                        if (display && selectedIndex >= 0) display.textContent = String(selectedIndex + 1).padStart(2, '0');
                    });
                    updateSelectionCount();
                };
                allPublicRows().forEach(bindPublicItem);
                updateMoveButtons();

                const appendPublicItem = (result) => {
                    const template = document.createElement('template');
                    template.innerHTML = result.html.trim();
                    const node = template.content.firstElementChild;
                    if (!node) throw new Error('The destination was saved, but the menu could not be updated here. Refresh this page to see it.');
                    publicList.querySelector('.empty-cell')?.remove();
                    publicList.append(node);
                    node.querySelectorAll('[data-icon-picker]').forEach(bindIconPicker);
                    bindPublicItem(node);
                    updateMoveButtons();
                    updateSelectionCount();
                    return node;
                };

                const submitNavigationLink = async (form) => {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        body: new FormData(form),
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });
                    const result = await response.json().catch(() => ({}));
                    if (!response.ok) {
                        const validationMessage = Object.values(result.errors ?? {}).flat()[0];
                        throw new Error(validationMessage || result.message || 'This destination could not be added.');
                    }
                    appendPublicItem(result);
                    return result;
                };

                document.querySelectorAll('[data-navigation-quick-add]').forEach((form) => {
                    const button = form.querySelector('[data-navigation-quick-add-button]');
                    button?.addEventListener('click', async () => {
                        if (button.disabled) return;
                        button.disabled = true;
                        form.classList.add('is-pending');
                        if (status) { status.classList.remove('is-error'); status.textContent = `Adding ${form.elements.label.value}…`; }
                        try {
                            const result = await submitNavigationLink(form);
                            const section = form.closest('.navigation-destination-grid section');
                            form.remove();
                            if (section && !section.querySelector('form')) section.remove();
                            if (status) { status.classList.remove('is-error'); status.textContent = result.message; }
                        } catch (error) {
                            button.disabled = false;
                            form.classList.remove('is-pending');
                            if (status) { status.classList.add('is-error'); status.textContent = error.message || 'This destination could not be added. Try again.'; }
                        }
                    });
                });

                const customForm = document.querySelector('[data-navigation-custom-add]');
                customForm?.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    const button = customForm.querySelector('button[type="submit"]');
                    if (button?.disabled) return;
                    if (button) button.disabled = true;
                    customForm.setAttribute('aria-busy', 'true');
                    if (status) { status.classList.remove('is-error'); status.textContent = 'Adding custom link…'; }
                    try {
                        const result = await submitNavigationLink(customForm);
                        customForm.reset();
                        const iconValue = customForm.querySelector('[data-icon-picker-value]');
                        const iconLabel = customForm.querySelector('[data-icon-picker-label]');
                        const iconCurrent = customForm.querySelector('[data-icon-picker-current]');
                        if (iconValue) iconValue.value = '';
                        if (iconLabel) iconLabel.textContent = 'Choose icon';
                        const defaultIcon = document.querySelector('#navigation-icon-options')?.content.querySelector('[data-icon="sparkles"] svg');
                        if (iconCurrent && defaultIcon) iconCurrent.replaceChildren(defaultIcon.cloneNode(true));
                        if (status) { status.classList.remove('is-error'); status.textContent = result.message; }
                    } catch (error) {
                        if (status) { status.classList.add('is-error'); status.textContent = error instanceof Error ? error.message : 'This destination could not be added. Try again.'; }
                    } finally {
                        if (button) button.disabled = false;
                        customForm.removeAttribute('aria-busy');
                    }
                });
            }
        })();
    </script>
    </div>
</x-layouts.workspace>
