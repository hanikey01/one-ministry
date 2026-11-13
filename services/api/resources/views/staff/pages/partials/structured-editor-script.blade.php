<script>
(() => {
    const form = document.querySelector('[data-page-block-editor]');
    if (!form) return;
    const persistedDocumentVersion = @json($page->updated_at ?? null);
    if (persistedDocumentVersion) { const versionField=document.createElement('input'); versionField.type='hidden'; versionField.name='document_version'; versionField.value=persistedDocumentVersion; form.append(versionField); }

    const canvas = form.querySelector('[data-canvas]');
    const inspector = form.querySelector('[data-inspector]');
    const navigator = form.querySelector('[data-page-navigator]');
    const floatingNavigator = form.querySelector('[data-floating-navigator]');
    const floatingNavigatorTree = form.querySelector('[data-floating-page-navigator]');
    const floatingNavigatorTrigger = form.querySelector('[data-floating-navigator-open]');
    const inspectorPanel = form.querySelector('.page-block-inspector');
    const inspectorBackdrop = form.querySelector('.page-block-inspector-backdrop');
    const libraryPanel = form.querySelector('.page-block-library');
    const libraryBackdrop = form.querySelector('.page-block-library-backdrop');
    const libraryTrigger = form.querySelector('[data-block-library-open]');
    const output = form.querySelector('[data-page-block-input]');
    const undo = form.querySelector('[data-undo]');
    const redo = form.querySelector('[data-redo]');
    const media = [];
    const mediaCatalogUrl = @json(route('staff.pages.media'));
    const mediaUploadUrl = @json(route('staff.media-library.upload'));
    let elementCatalog = {};
    try { elementCatalog = JSON.parse(form.dataset.elementCatalog || '{}'); } catch { elementCatalog = {}; }
    const objectAiUrl = form.dataset.objectAiEndpoint || '';
    const aiAvailable = form.dataset.aiAvailable === 'true';
    const id = () => `editor-${crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random().toString(16).slice(2)}`}`;
    const clone = (value) => JSON.parse(JSON.stringify(value));
    const objectValue = (value) => value && typeof value === 'object' && !Array.isArray(value) ? value : {};
    const prepareDocument = (document) => {
        document.sections = (Array.isArray(document.sections) ? document.sections : []).map((section) => {
            section.settings = objectValue(section.settings);
            section.responsive = objectValue(section.responsive);
            Object.keys(section.responsive).forEach((breakpoint) => { section.responsive[breakpoint] = objectValue(section.responsive[breakpoint]); });
            section.columns = (Array.isArray(section.columns) ? section.columns : []).map((column) => {
                column.settings = objectValue(column.settings);
                column.responsive = objectValue(column.responsive);
                Object.keys(column.responsive).forEach((breakpoint) => { column.responsive[breakpoint] = objectValue(column.responsive[breakpoint]); });
                column.elements = (Array.isArray(column.elements) ? column.elements : []).map((item) => {
                    item.content = objectValue(item.content);
                    item.settings = objectValue(item.settings);
                    item.responsive = objectValue(item.responsive);
                    Object.keys(item.responsive).forEach((breakpoint) => { item.responsive[breakpoint] = objectValue(item.responsive[breakpoint]); });
                    return item;
                });
                return column;
            });
            return section;
        });
        return document;
    };
    const escape = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[character]));
    const names = Object.fromEntries(Object.entries(elementCatalog).map(([type, definition]) => [type, definition.label || type.replaceAll('_', ' ')]));
    const defaults = Object.fromEntries(Object.entries(elementCatalog).map(([type, definition]) => [type, objectValue(definition.defaults)]));
    const column = () => ({ id:id(), settings:{}, responsive:{}, elements:[] });
    const firstLibraryGroup = libraryPanel?.querySelector('.page-block-library-group');
    Object.entries(elementCatalog).forEach(([type, definition]) => {
        let button = libraryPanel?.querySelector(`[data-add="${type}"]`);
        if (!button && firstLibraryGroup) {
            button = document.createElement('button');
            button.type = 'button';
            button.dataset.add = type;
            firstLibraryGroup.append(button);
        }
        if (button) button.textContent = definition.label || names[type];
    });
    libraryPanel?.querySelectorAll('[data-add]').forEach((button) => {
        if (!['grid', 'section'].includes(button.dataset.add) && !Object.hasOwn(elementCatalog, button.dataset.add)) button.remove();
    });
    const section = () => ({ id:id(), type:'section', settings:{ padding:'none', content_width:'standard', column_gap:'regular', grid_preset:'one' }, responsive:{}, columns:[column()] });
    const gridSection = (count = 3) => ({ ...section(), settings:{ padding:'none', content_width:'standard', column_gap:'regular', grid_preset:['one','two','three','four'][count - 1] || 'three' }, columns:Array.from({length:count}, () => column()) });
    const element = (type) => ({ id:id(), type, content:clone(defaults[type] || {}), settings:{}, responsive:{} });
    let documentState;
    try { documentState = JSON.parse(form.dataset.blocks || '{}'); } catch { documentState = {}; }
    if (documentState?.version !== 2 || !Array.isArray(documentState.sections)) documentState = { version:2, sections:[] };
    documentState = prepareDocument(documentState);
    if (!documentState.sections.length) documentState.sections.push(section());
    let savedDocument = JSON.stringify(documentState);
    const saveState = document.createElement('span');
    saveState.className = 'page-editor-save-state';
    saveState.setAttribute('role', 'status');
    saveState.textContent = 'Saved';
    form.querySelector('.page-editor-actions > div')?.append(saveState);
    let selected = { kind:'section', section:0, column:0, element:0 };
    const collapsedNavigatorNodes = new Set();
    let selectionActive = false;
    let breakpoint = 'desktop';
    let inspectorTab = 'content';
    let history = [];
    let future = [];
    let submittedDocument = null;
    const currentSection = () => documentState.sections[selected.section];
    const currentColumn = () => currentSection()?.columns[selected.column];
    const currentElement = () => currentColumn()?.elements[selected.element];
    const save = (notifyPreview = true) => {
        output.value = JSON.stringify(documentState);
        const dirty = output.value !== savedDocument;
        form.dataset.dirty = dirty ? 'true' : 'false';
        saveState.textContent = dirty ? 'Unsaved changes' : 'Saved';
        if (notifyPreview) form.dispatchEvent(new CustomEvent('page-editor-document-change'));
    };
    const snapshot = () => { history.push(JSON.stringify(documentState)); future = []; syncHistory(); };
    const syncHistory = () => { undo.disabled = !history.length; redo.disabled = !future.length; };
    const closeFloatingNavigator = () => {
        floatingNavigator?.classList.remove('is-open');
        floatingNavigator?.setAttribute('aria-hidden', 'true');
        floatingNavigatorTrigger?.setAttribute('aria-expanded', 'false');
    };
    const openInspector = () => {
        closeFloatingNavigator();
        libraryPanel?.classList.remove('is-open');
        libraryBackdrop?.classList.remove('is-open');
        document.body.classList.remove('page-library-is-open');
        inspectorPanel?.classList.add('is-open');
        inspectorPanel?.setAttribute('aria-hidden', 'false');
        form.querySelectorAll('.page-block-inspector-backdrop').forEach((backdrop) => backdrop.classList.add('is-open'));
        document.body.classList.add('page-inspector-is-open');
        libraryTrigger?.setAttribute('hidden', '');
    };
    const closeInspector = () => {
        inspectorPanel?.classList.remove('is-open');
        inspectorPanel?.setAttribute('aria-hidden', 'true');
        document.querySelectorAll('.page-block-inspector-backdrop').forEach((backdrop) => backdrop.classList.remove('is-open'));
        document.body.classList.remove('page-inspector-is-open');
        libraryPanel?.classList.remove('is-open');
        libraryBackdrop?.classList.remove('is-open');
        document.body.classList.remove('page-library-is-open');
        libraryTrigger?.removeAttribute('hidden');
    };
    const openLibrary = () => {
        inspectorPanel?.classList.remove('is-open');
        inspectorBackdrop?.classList.remove('is-open');
        inspectorPanel?.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('page-inspector-is-open');
        libraryPanel?.classList.add('is-open');
        libraryBackdrop?.classList.add('is-open');
        document.body.classList.add('page-library-is-open');
        libraryTrigger?.setAttribute('hidden', '');
    };
    const closeLibrary = () => {
        libraryPanel?.classList.remove('is-open');
        libraryBackdrop?.classList.remove('is-open');
        document.body.classList.remove('page-library-is-open');
        libraryTrigger?.removeAttribute('hidden');
    };
    const select = (kind, sectionIndex, columnIndex = 0, elementIndex = 0) => { selected = {kind, section:sectionIndex, column:columnIndex, element:elementIndex}; selectionActive = true; inspectorTab = 'content'; render(); };
    const renderNavigator = () => {
        if (!navigator) return;
        navigator.innerHTML = documentState.sections.map((sectionItem, sectionIndex) => `<div><button type="button" data-navigator-kind="section" data-section="${sectionIndex}">Section ${sectionIndex + 1}${sectionItem.settings?.title ? ` · ${escape(sectionItem.settings.title)}` : ''}</button>${sectionItem.columns.map((columnItem, columnIndex) => `<div><button type="button" data-navigator-kind="column" data-section="${sectionIndex}" data-column="${columnIndex}">Column ${columnIndex + 1}</button>${columnItem.elements.map((elementItem, elementIndex) => `<button type="button" data-navigator-kind="element" data-section="${sectionIndex}" data-column="${columnIndex}" data-element="${elementIndex}">${escape(names[elementItem.type] || elementItem.type)}</button>`).join('')}</div>`).join('')}</div>`).join('');
    };
    const renderFloatingNavigator = () => {
        if (!floatingNavigatorTree) return;
        const node = (kind, label, attributes, level, icon, key) => { const expandable = kind !== 'element'; const collapsed = expandable && collapsedNavigatorNodes.has(key); return `<button type="button" class="page-editor-floating-navigator-node is-${kind}" data-floating-navigator-kind="${kind}" data-level="${level}" ${expandable ? `data-floating-navigator-toggle="${key}" aria-expanded="${collapsed ? 'false' : 'true'}"` : ''} ${attributes}><span class="page-editor-floating-navigator-chevron${expandable ? ' is-toggle' : ''}" aria-hidden="true">${expandable ? (collapsed ? '▸' : '▾') : '·'}</span><span class="page-editor-floating-navigator-icon" aria-hidden="true">${icon}</span><span class="page-editor-floating-navigator-label">${escape(label)}</span></button>`; };
        floatingNavigatorTree.innerHTML = documentState.sections.map((sectionItem, sectionIndex) => {
            const sectionKey = `section:${sectionIndex}`;
            const sectionCollapsed = collapsedNavigatorNodes.has(sectionKey);
            const columns = sectionCollapsed ? '' : sectionItem.columns.map((columnItem, columnIndex) => {
                const columnKey = `column:${sectionIndex}:${columnIndex}`;
                const columnCollapsed = collapsedNavigatorNodes.has(columnKey);
                const elements = columnCollapsed ? '' : columnItem.elements.map((elementItem, elementIndex) => node('element', names[elementItem.type] || elementItem.type, `data-section="${sectionIndex}" data-column="${columnIndex}" data-element="${elementIndex}"`, 2, elementItem.type === 'heading' ? 'T' : '≡', '')).join('');
                return `<div>${node('column', `Column ${columnIndex + 1}`, `data-section="${sectionIndex}" data-column="${columnIndex}"`, 1, '▦', columnKey)}${elements}</div>`;
            }).join('');
            return `<div>${node('section', `Section ${sectionIndex + 1}${sectionItem.settings?.title ? ` · ${sectionItem.settings.title}` : ''}`, `data-section="${sectionIndex}"`, 0, '▧', sectionKey)}${columns}</div>`;
        }).join('');
        floatingNavigatorTree.querySelectorAll('[data-floating-navigator-kind]').forEach((button) => {
            const kind = button.dataset.floatingNavigatorKind;
            const active = selected.kind === kind && selected.section === Number(button.dataset.section) && (kind === 'section' || selected.column === Number(button.dataset.column)) && (kind !== 'element' || selected.element === Number(button.dataset.element));
            button.classList.toggle('is-selected', active);
            button.setAttribute('aria-current', active ? 'true' : 'false');
        });
    };
    const presetSpacing = { none:'0', compact:'4px', regular:'8px', spacious:'24px' };
    const inheritedValue = (object, key, fallback = '') => {
        const values = objectValue(object?.responsive);
        return values[breakpoint]?.[key] ?? (breakpoint === 'mobile' ? values.tablet?.[key] ?? values.desktop?.[key] : breakpoint === 'tablet' ? values.desktop?.[key] : undefined) ?? fallback;
    };
    const liveCanvasStyles = () => {
        canvas.querySelectorAll('.page-canvas-section-object').forEach((node, sectionIndex) => {
            const section = documentState.sections[sectionIndex];
            const settings = objectValue(section?.settings);
            const padding = inheritedValue(section, 'padding', settings.padding || 'none');
            const margin = inheritedValue(section, 'margin', settings.margin || 'none');
            const columns = inheritedValue(section, 'column_count', String(section.columns.length));
            const stack = inheritedValue(section, 'stack', 'inline');
            node.style.setProperty('--editor-section-width', settings.content_width === 'full' ? '100%' : settings.content_width === 'wide' ? '1240px' : settings.content_width === 'narrow' ? '760px' : settings.content_width === 'custom' ? (settings.content_max_width || '1120px') : '1120px');
            node.style.setProperty('--editor-section-padding', presetSpacing[padding] || padding || '0');
            node.style.setProperty('--editor-section-margin', presetSpacing[margin] || margin || '0');
            node.style.setProperty('--editor-section-columns', stack === 'stack' ? '1' : columns || String(section.columns.length));
            node.style.setProperty('--editor-section-gap', presetSpacing[settings.column_gap || 'regular'] || '8px');
            if (settings.min_height) node.style.minHeight = settings.min_height;
            if (settings.background_color) node.style.backgroundColor = settings.background_color;
            if (settings.border_width) node.style.borderWidth = settings.border_width;
            if (settings.border_style) node.style.borderStyle = settings.border_style;
            if (settings.border_color) node.style.borderColor = settings.border_color;
            node.querySelectorAll('.page-canvas-column-object').forEach((columnNode, columnIndex) => {
                const column = section.columns[columnIndex];
                const columnSettings = objectValue(column?.settings);
                const width = inheritedValue(column, 'width', 'full');
                const customWidth = inheritedValue(column, 'custom_width', columnSettings.custom_width || '100%');
                columnNode.style.width = width === 'half' ? '50%' : width === 'third' ? '33.333%' : width === 'custom' ? customWidth : '100%';
                columnNode.style.minHeight = columnSettings.min_height || '';
                columnNode.style.gap = presetSpacing[columnSettings.element_gap || inheritedValue(column, 'gap', 'regular')] || columnSettings.element_gap || '8px';
                columnNode.querySelectorAll('.page-canvas-element-object').forEach((elementNode, elementIndex) => {
                    const element = column.elements[elementIndex];
                    const elementSettings = objectValue(element?.settings);
                    elementNode.style.textAlign = inheritedValue(element, 'alignment', 'left');
                    elementNode.style.order = inheritedValue(element, 'order', 'initial');
                    elementNode.style.fontSize = inheritedValue(element, 'font_size', elementSettings.font_size || '');
                    elementNode.style.fontWeight = elementSettings.font_weight || '';
                    elementNode.style.lineHeight = inheritedValue(element, 'line_height', elementSettings.line_height || '');
                    elementNode.style.letterSpacing = inheritedValue(element, 'letter_spacing', elementSettings.letter_spacing || '');
                    if (elementSettings.text_color) elementNode.style.color = elementSettings.text_color;
                    if (elementSettings.background_color) elementNode.style.backgroundColor = elementSettings.background_color;
                    if (elementSettings.border_width) elementNode.style.borderWidth = elementSettings.border_width;
                    if (elementSettings.border_style) elementNode.style.borderStyle = elementSettings.border_style;
                    if (elementSettings.border_color) elementNode.style.borderColor = elementSettings.border_color;
                    elementNode.hidden = inheritedValue(element, 'visibility', 'visible') === 'hidden';
                });
            });
        });
        canvas.querySelectorAll('.page-canvas-section-object, .page-canvas-column-object, .page-canvas-element-object').forEach((node) => {
            node.draggable = true;
            node.addEventListener('dragstart', (event) => {
                const target = event.currentTarget;
                event.stopPropagation();
                target.classList.add('is-dragging');
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', JSON.stringify({
                    kind: target.dataset.selectKind,
                    section: Number(target.dataset.section),
                    column: Number(target.dataset.column || 0),
                    element: Number(target.dataset.element || 0),
                }));
            });
            node.addEventListener('dragend', (event) => event.currentTarget.classList.remove('is-dragging'));
            node.addEventListener('dragover', (event) => { event.preventDefault(); event.currentTarget.classList.add('is-drop-target'); });
            node.addEventListener('dragleave', (event) => event.currentTarget.classList.remove('is-drop-target'));
            node.addEventListener('drop', (event) => {
                event.preventDefault();
                const target = event.currentTarget;
                target.classList.remove('is-drop-target');
                let source;
                try { source = JSON.parse(event.dataTransfer.getData('text/plain')); } catch { return; }
                if (source.kind !== 'element' || target.dataset.selectKind !== 'column') return;
                if (source.section !== Number(target.dataset.section) || source.column === Number(target.dataset.column)) return;
                const section = documentState.sections[source.section];
                const from = section?.columns[source.column];
                const to = section?.columns[Number(target.dataset.column)];
                if (!from || !to || !from.elements[source.element]) return;
                mutate(() => {
                    const [moved] = from.elements.splice(source.element, 1);
                    to.elements.push(moved);
                    selected = { kind:'element', section:source.section, column:Number(target.dataset.column), element:to.elements.length - 1 };
                    selectionActive = true;
                });
            });
        });
    };
    const input = (key, label, value, textarea = false, bucket = 'content') => `<label>${label}${textarea ? `<textarea data-editor-field="${key}" data-editor-bucket="${bucket}">${escape(value)}</textarea>` : `<input data-editor-field="${key}" data-editor-bucket="${bucket}" value="${escape(value)}">`}</label>`;
    const selectField = (key, label, value, options, bucket = 'content') => `<label>${label}<select data-editor-field="${key}" data-editor-bucket="${bucket}">${options.map(([optionValue, optionLabel]) => `<option value="${optionValue}" ${value===optionValue?'selected':''}>${optionLabel}</option>`).join('')}</select></label>`;
    const mediaTypeFor = (type, key) => key === 'background_image' || key === 'media_url' ? ['image'] : ['image', 'image_card', 'gallery'].includes(type) ? ['image'] : type === 'video_embed' ? ['video'] : type === 'audio' ? ['audio'] : type === 'file' ? ['file'] : [];
    const mediaField = (key, label, value, type, bucket = 'content') => `<label>${label}<div class="page-media-field">${key === 'images' ? `<textarea data-editor-field="${key}" data-editor-bucket="${bucket}" placeholder="Choose one or more ministry images">${escape(value)}</textarea>` : `<input data-editor-field="${key}" data-editor-bucket="${bucket}" value="${escape(value)}" placeholder="Choose ministry media or paste an approved URL">`}<button class="page-media-picker-trigger" type="button" data-media-picker="${key}" data-media-bucket="${bucket}" data-media-types="${key === 'images' ? 'image' : mediaTypeFor(type,key).join(',')}">Choose from media library</button></div></label>`;
    const inheritedResponsiveValue = (object, key) => {
        const responsive = object.responsive || {};
        const sources = breakpoint === 'mobile' ? ['tablet', 'desktop'] : breakpoint === 'tablet' ? ['desktop'] : [];
        for (const source of sources) if (responsive[source]?.[key]) return { source, value:responsive[source][key] };
        return null;
    };
    const responsiveField = (object, key, label, options) => {
        const value = object.responsive?.[breakpoint]?.[key] || '';
        const inheritedValue = inheritedResponsiveValue(object, key);
        const inherited = breakpoint === 'desktop' ? 'Base layout value' : inheritedValue ? `Inherited from ${inheritedValue.source}: ${inheritedValue.value}` : `Inherited from ${breakpoint === 'tablet' ? 'desktop' : 'tablet or desktop'}`;
        return `<label>${label}<select data-responsive-field="${key}"><option value="">${inherited}</option>${options.map(([optionValue, optionLabel]) => `<option value="${optionValue}" ${value===optionValue?'selected':''}>${optionLabel}</option>`).join('')}</select><small class="page-responsive-state">${value ? `Override for ${breakpoint}` : inherited}</small></label>`;
    };
    const responsiveDimensionField = (object, key, label) => {
        const value = object.responsive?.[breakpoint]?.[key] || '';
        const inheritedValue = inheritedResponsiveValue(object, key);
        const inherited = breakpoint === 'desktop' ? `Uses the ${selected.kind}'s base ${label.toLowerCase()}` : inheritedValue ? `Inherited from ${inheritedValue.source}: ${inheritedValue.value}` : `Inherited from ${breakpoint === 'tablet' ? 'desktop' : 'tablet or desktop'}`;
        return `<label>${label}<input data-responsive-dimension="${key}" value="${escape(value)}" placeholder="Inherit"><small class="page-responsive-state">${value ? `Override for ${breakpoint}` : inherited}</small></label>`;
    };
    const spacingUnits = [['px','px'],['%','%'],['em','em'],['rem','rem'],['vw','vw'],['vh','vh']];
    const unitOf = (value, fallback = 'px') => String(value || '').match(/[a-z%]+$/i)?.[0] || fallback;
    const numericOf = (value) => String(value || '').replace(/[a-z%]+$/i, '');
    const unitSelect = (unit) => `<select data-spacing-unit>${spacingUnits.map(([value,label]) => `<option value="${value}" ${unit===value?'selected':''}>${label}</option>`).join('')}</select>`;
    const spacingField = (object, key, label) => { const value = object.responsive?.[breakpoint]?.[key]; const sides = typeof value === 'object' && value ? value : {}; const unit = unitOf(Object.values(sides)[0]); return `<fieldset class="page-spacing-field"><legend>${label}</legend><div class="page-spacing-unit">${unitSelect(unit)}</div><div>${['top','right','bottom','left'].map((side) => `<label>${side}<input inputmode="decimal" placeholder="inherit" data-spacing-field="${key}" data-spacing-side="${side}" value="${escape(numericOf(sides[side]))}"></label>`).join('')}</div></fieldset>`; };
    const responsiveControls = (object) => {
        const common = [['visibility','Visibility',[['visible','Visible'],['hidden','Hidden']]], ['padding','Padding',[['none','None'],['compact','Compact'],['regular','Regular'],['spacious','Spacious']]], ['margin','Margin',[['none','None'],['compact','Compact'],['regular','Regular'],['spacious','Spacious']]]];
        const fields = selected.kind === 'section'
            ? [...common, ['column_count','Columns',[['1','One'],['2','Two'],['3','Three'],['4','Four']]], ['stack','Column flow',[['inline','Keep columns inline'],['stack','Stack columns']]], ['column_gap','Column gap',[['none','None'],['compact','Compact'],['regular','Regular'],['spacious','Spacious']]]]
            : selected.kind === 'column'
                ? common
                : [...common, ['order','Order',[['1','First'],['2','Second'],['3','Third'],['4','Fourth']]]];
        return `<section class="page-responsive-controls"><div class="page-responsive-heading"><strong>Responsive</strong><div class="page-breakpoint-tabs" role="tablist">${['desktop','tablet','mobile'].map((item) => `<button type="button" data-breakpoint="${item}" class="${breakpoint===item?'is-active':''}" aria-selected="${breakpoint===item}">${item[0].toUpperCase()+item.slice(1)}</button>`).join('')}</div></div><div class="page-object-control-grid">${fields.map(([key,label,options]) => ['padding','margin'].includes(key) ? spacingField(object, key, label) : responsiveField(object, key, label, options)).join('')}</div></section>`;
    };
    const moveElementControl = () => {
        const columns = currentSection()?.columns || [];
        if (selected.kind !== 'element' || columns.length < 2) return '';
        return `<section class="page-object-move"><strong>Move element</strong><label>Destination column<select data-move-element-to>${columns.map((item, index) => `<option value="${index}" ${index===selected.column?'selected':''}>Column ${index + 1}${index===selected.column?' (current)':''}</option>`).join('')}</select></label><small>Move this element to another column in the selected section.</small></section>`;
    };
    const objectAiControls = () => `<section class="page-object-ai"><strong>AI assist</strong><p>AI receives the full page for context and can redesign this selected ${selected.kind}, including its content, layout, style, spacing, responsive settings, and permitted children.</p><textarea data-object-ai-instruction rows="3" minlength="10" maxlength="1500" placeholder="Describe the design or content change you want…" ${aiAvailable ? '' : 'disabled'}></textarea><button class="button" type="button" data-object-ai-ask ${aiAvailable ? '' : 'disabled'}>${aiAvailable ? 'Design this selection' : 'AI route unavailable'}</button><p class="page-object-ai-status" data-object-ai-status role="status" aria-live="polite">${aiAvailable ? '' : 'An administrator must verify an AI page-editor route first.'}</p></section>`;
    let mediaTarget = null;
    const mediaDialog = document.createElement('div');
    mediaDialog.className = 'page-media-dialog';
    mediaDialog.hidden = true;
    mediaDialog.innerHTML = `<button type="button" class="page-media-backdrop" data-close-media aria-label="Close media library"></button><section role="dialog" aria-modal="true" aria-label="Choose ministry media"><header><div><p>MEDIA LIBRARY</p><h2>Choose media</h2><span>Select existing ministry media or upload a compatible file.</span></div><button type="button" class="page-media-close" data-close-media aria-label="Close">×</button></header><div class="page-media-upload"><label class="page-media-upload-button">Upload media<input type="file" data-page-media-upload></label><small data-media-upload-status></small></div><div class="page-media-list" data-page-media-list></div></section>`;
    (document.querySelector('.workspace-shell') || document.body).append(mediaDialog);
    const compatibleMedia = () => media.filter((asset) => !mediaTarget?.types?.length || mediaTarget.types.includes(asset.type));
    const renderMedia = () => {
        const list = mediaDialog.querySelector('[data-page-media-list]');
        const assets = compatibleMedia();
        list.innerHTML = assets.length ? assets.map((asset, index) => `<button type="button" class="page-media-option" data-media-option="${index}"><span class="page-media-thumb">${asset.type === 'image' ? `<img src="${escape(asset.url)}" alt="">` : escape(asset.type.slice(0,3).toUpperCase())}</span><strong>${escape(asset.label)}</strong><span>${escape(asset.type)}</span></button>`).join('') : '<p class="page-media-empty">No compatible media is available yet.</p>';
        list.querySelectorAll('[data-media-option]').forEach((button) => button.addEventListener('click', () => { const asset = assets[Number(button.dataset.mediaOption)]; const item = selectedObject(); if (!asset || !item || !mediaTarget) return; const bucket = mediaTarget.bucket === 'settings' ? (item.settings = objectValue(item.settings)) : (item.content = objectValue(item.content)); bucket[mediaTarget.key] = mediaTarget.key === 'images' ? [...new Set(String(bucket.images || '').split(/\n+/).filter(Boolean).concat(asset.url))].join('\n') : asset.url; save(); mediaDialog.hidden = true; render(); }));
    };
    const acceptFor = (types) => {
        const values = [];
        if (types.includes('image')) values.push('image/*');
        if (types.includes('video')) values.push('video/*');
        if (types.includes('audio')) values.push('audio/*');
        if (types.includes('file')) values.push('.pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt');
        return values.join(',');
    };
    let mediaCatalogLoaded = false;
    const loadMediaCatalog = async () => {
        if (mediaCatalogLoaded) return;
        const status = mediaDialog.querySelector('[data-media-upload-status]');
        status.textContent = 'Loading ministry media…';
        try {
            const response = await fetch(mediaCatalogUrl, { headers:{ 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest' } });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || 'Media library could not be loaded.');
            media.splice(0, media.length, ...(payload.assets || []));
            mediaCatalogLoaded = true;
            status.textContent = '';
        } catch (error) { status.textContent = error.message || 'Media library could not be loaded.'; }
    };
    const openMedia = async (key, types, bucket = 'content') => { mediaTarget = { key, types, bucket }; mediaDialog.querySelector('[data-page-media-upload]').accept = acceptFor(types); mediaDialog.hidden = false; renderMedia(); await loadMediaCatalog(); renderMedia(); };
    mediaDialog.addEventListener('click', (event) => { if (event.target.closest('[data-close-media]')) mediaDialog.hidden = true; });
    mediaDialog.querySelector('[data-page-media-upload]').addEventListener('change', async (event) => {
        const upload = event.target;
        if (!upload.files?.length) return;
        const status = mediaDialog.querySelector('[data-media-upload-status]');
        status.textContent = 'Uploading…';
        const data = new FormData(); [...upload.files].forEach((file) => data.append('file[]', file));
        try {
            const response = await fetch(mediaUploadUrl, { method:'POST', body:data, headers:{ 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest', 'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]')?.content || '' } });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || 'Upload failed.');
            (payload.assets || []).forEach((asset) => media.unshift({ url:asset.url, label:asset.title, type:['image','video','audio'].includes(asset.kind) ? asset.kind : 'file' }));
            status.textContent = payload.message || 'Upload complete.';
            renderMedia();
        } catch (error) { status.textContent = error.message || 'Upload failed.'; }
        upload.value = '';
    });
    const renderInspector = () => {
        const object = selected.kind === 'section' ? currentSection() : selected.kind === 'column' ? currentColumn() : currentElement();
        if (!object) { inspector.innerHTML = '<div class="page-inspector-empty">Choose a section, column, or element.</div>'; return; }
        const heading = selected.kind === 'element' ? names[object.type] || object.type : selected.kind[0].toUpperCase() + selected.kind.slice(1);
        let content = '';
        const colorField = (key, label, value) => { const valid = /^#[0-9a-f]{6}$/i.test(String(value || '')); return `<label>${label}<input type="color" data-editor-field="${key}" data-editor-bucket="settings" value="${valid ? value : '#ffffff'}"></label>`; };
        const styleNumberField = (key, label, value) => input(key, label, value || '', false, 'settings');
        const radiusField = () => { const corners = [['top_left','Top left'],['top_right','Top right'],['bottom_right','Bottom right'],['bottom_left','Bottom left']]; const unit = unitOf(object.settings?.radius_top_left); return `<fieldset class="page-spacing-field page-radius-field"><legend>Corner radius</legend><div class="page-spacing-unit">${unitSelect(unit)}</div><div>${corners.map(([corner,label]) => `<label>${label}<input inputmode="decimal" placeholder="0" data-radius-field="radius_${corner}" value="${escape(numericOf(object.settings?.[`radius_${corner}`]))}"></label>`).join('')}</div></fieldset>`; };
        const typographyControls = () => selected.kind === 'element' ? `<section class="page-style-details"><strong>Typography</strong><div class="page-object-control-grid">${selectField('font_family','Typeface',object.settings?.font_family || 'inherit',[['inherit','Application sans'],['system','System sans'],['verdana','Verdana'],['trebuchet','Trebuchet MS'],['georgia','Georgia'],['palatino','Palatino']],'settings')}${responsiveDimensionField(object,'font_size','Font size')}${selectField('font_weight','Font weight',object.settings?.font_weight || '400',[['400','Regular'],['500','Medium'],['600','Semibold'],['700','Bold'],['800','Extra bold']],'settings')}${responsiveDimensionField(object,'line_height','Line height')}${responsiveDimensionField(object,'letter_spacing','Letter spacing')}${selectField('text_transform','Text transform',object.settings?.text_transform || 'none',[['none','None'],['uppercase','Uppercase'],['lowercase','Lowercase'],['capitalize','Capitalize']],'settings')}${colorField('text_color','Text color',object.settings?.text_color || '')}</div></section>` : '';
        const buttonStyleControls = () => selected.kind === 'element' && (object.type === 'button' || Object.hasOwn(object.content || {}, 'button_label')) ? `<section class="page-style-details"><strong>Button design</strong><div class="page-object-control-grid">${colorField('button_background','Background',object.settings?.button_background || '')}${colorField('button_hover_background','Hover background',object.settings?.button_hover_background || '')}${colorField('button_text_color','Text color',object.settings?.button_text_color || '')}${colorField('button_border_color','Border color',object.settings?.button_border_color || '')}${styleNumberField('button_border_width','Border width',object.settings?.button_border_width)}${styleNumberField('button_radius','Corner radius',object.settings?.button_radius)}${styleNumberField('button_padding_x','Horizontal padding',object.settings?.button_padding_x)}${styleNumberField('button_padding_y','Vertical padding',object.settings?.button_padding_y)}${selectField('button_width','Button width',object.settings?.button_width || 'auto',[['auto','Fit content'],['full','Full width']],'settings')}</div></section>` : '';
        const styleDetailControls = () => `<section class="page-style-details"><strong>Detailed style</strong><datalist id="page-style-units"><option value="0px"><option value="1%"><option value="1em"><option value="1rem"><option value="1vw"><option value="1vh"></datalist><div class="page-object-control-grid">${colorField('background_color','Background color',object.settings?.background_color || '')}${colorField('overlay_color','Image overlay color',object.settings?.overlay_color || '')}${input('overlay_opacity','Overlay opacity (0–100)',object.settings?.overlay_opacity || '',false,'settings')}${colorField('border_color','Border color',object.settings?.border_color || '')}${selectField('border_style','Border style',object.settings?.border_style || 'solid',[['none','None'],['solid','Solid'],['dashed','Dashed'],['dotted','Dotted']],'settings')}${styleNumberField('border_width','Border width',object.settings?.border_width)}${radiusField()}${styleNumberField('shadow_x','Shadow X',object.settings?.shadow_x)}${styleNumberField('shadow_y','Shadow Y',object.settings?.shadow_y)}${styleNumberField('shadow_blur','Shadow blur',object.settings?.shadow_blur)}${styleNumberField('shadow_spread','Shadow spread',object.settings?.shadow_spread)}${colorField('shadow_color','Shadow color',object.settings?.shadow_color || '')}</div></section>${typographyControls()}${buttonStyleControls()}`;
        const appearanceControls = () => {
            const alignmentOptions = selected.kind === 'element'
                ? [['left','Left'],['center','Centre'],['right','Right']]
                : [['left','Left'],['center','Centre'],['right','Right'],['stretch','Stretch']];
            const backgroundControls = selected.kind === 'section' ? `${selectField('background_position','Background position',object.settings?.background_position || 'center_center',[['left_top','Left top'],['center_top','Centre top'],['right_top','Right top'],['left_center','Left centre'],['center_center','Centre centre'],['right_center','Right centre'],['left_bottom','Left bottom'],['center_bottom','Centre bottom'],['right_bottom','Right bottom']],'settings')}${selectField('background_size','Background size',object.settings?.background_size || 'cover',[['auto','Auto'],['cover','Cover'],['contain','Contain']],'settings')}${selectField('background_repeat','Background repeat',object.settings?.background_repeat || 'no_repeat',[['no_repeat','No repeat'],['repeat','Repeat'],['repeat_x','Repeat horizontally'],['repeat_y','Repeat vertically']],'settings')}${selectField('background_attachment','Background attachment',object.settings?.background_attachment || 'scroll',[['scroll','Scroll'],['fixed','Fixed']],'settings')}` : '';
            return `<section class="page-object-appearance"><div class="page-responsive-heading"><strong>Appearance</strong><div class="page-breakpoint-tabs" role="tablist">${['desktop','tablet','mobile'].map((item) => `<button type="button" data-breakpoint="${item}" class="${breakpoint===item?'is-active':''}" aria-selected="${breakpoint===item}">${item[0].toUpperCase()+item.slice(1)}</button>`).join('')}</div></div><div class="page-object-control-grid">${responsiveField(object, 'alignment', 'Alignment', alignmentOptions)}${mediaField('background_image', 'Background image', object.settings?.background_image || '', object.type || 'section', 'settings')}${backgroundControls}${selectField('overflow', 'Content overflow', object.settings?.overflow || 'visible', [['visible','Visible'],['hidden','Hidden']], 'settings')}</div>${styleDetailControls()}</section>`;
        };
        const customCodeControls = () => ['custom_code','html'].includes(object.type) ? `<section class="page-object-custom-code"><strong>Custom-code safety review</strong><p role="note"><strong>Preview warning:</strong> custom code runs only inside this element’s isolated sandbox. It cannot access the ministry application, submit forms, use storage, or make network requests. Keep the design responsive and keyboard operable. HTML, CSS, and JavaScript are each limited to 50,000 characters.</p>${input('css', 'Custom CSS', object.content?.css || '', true)}${input('javascript', 'Custom JavaScript', object.content?.javascript || '', true)}</section>` : '';
        const elementCustomControls = () => ['section','column','element'].includes(selected.kind) ? `<section class="page-object-custom-code"><strong>${selected.kind[0].toUpperCase() + selected.kind.slice(1)} identity and CSS</strong><p>Use these fields for scoped CSS only. Executable JavaScript is confined to the isolated Custom HTML / CSS / JavaScript element.</p>${input('custom_id', 'CSS ID', object.settings?.custom_id || '', false, 'settings')}${input('custom_class', 'CSS classes', object.settings?.custom_class || '', false, 'settings')}${!['custom_code','html'].includes(object.type) ? input('custom_css', 'Custom CSS', object.settings?.custom_css || '', true, 'settings') : ''}</section>` : '';
        if (selected.kind === 'section') content = input('title', 'Section title', object.settings?.title || '', false, 'settings') + input('body', 'Introduction', object.settings?.body || '', true, 'settings') + selectField('content_width', 'Content width', object.settings?.content_width || 'standard', [['full','Full width'],['wide','Wide'],['standard','Standard'],['narrow','Narrow'],['custom','Custom']], 'settings') + input('content_max_width','Custom content width',object.settings?.content_max_width || '',false,'settings') + input('min_height','Minimum height',object.settings?.min_height || '',false,'settings') + selectField('vertical_alignment','Vertical alignment',object.settings?.vertical_alignment || 'start',[['start','Top'],['center','Centre'],['end','Bottom'],['stretch','Stretch']],'settings') + input('row_gap','Row gap',object.settings?.row_gap || '',false,'settings') + selectField('grid_preset', 'Grid preset', object.settings?.grid_preset || (object.columns.length === 1 ? 'one' : object.columns.length === 2 ? 'two' : object.columns.length === 4 ? 'four' : 'three'), [['one','One column'],['two','Two columns'],['three','Three columns'],['four','Four columns']], 'settings');
        if (selected.kind === 'column') content = responsiveField(object, 'width', 'Column width', [['full','Full'],['half','Half'],['third','One third'],['custom','Custom']]) + responsiveDimensionField(object,'custom_width','Custom width') + input('min_height','Minimum height',object.settings?.min_height || '',false,'settings') + selectField('vertical_alignment','Vertical alignment',object.settings?.vertical_alignment || 'start',[['start','Top'],['center','Centre'],['end','Bottom'],['stretch','Stretch']],'settings') + input('element_gap','Element gap',object.settings?.element_gap || '',false,'settings') + responsiveField(object, 'order', 'Column order', [['1','First'],['2','Second'],['3','Third'],['4','Fourth']]) + responsiveField(object, 'gap', 'Element gap preset', [['none','None'],['compact','Compact'],['regular','Regular'],['spacious','Spacious']]);
        if (selected.kind === 'element') content = (elementCatalog[object.type]?.fields || Object.keys(object.content || {})).map((key) => {
            const value = object.content?.[key] ?? '';
            const fieldDefinition = elementCatalog[object.type]?.field_definitions?.[key] || {};
            const label = fieldDefinition.label || key.replaceAll('_',' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
            if (['custom_code','html'].includes(object.type) && ['css','javascript'].includes(key)) return '';
            if (['custom_code','html'].includes(object.type)) return input(key, label, value, true);
            if (fieldDefinition.media) return mediaField(key, key === 'media_url' ? 'Media' : key === 'images' ? 'Gallery images' : 'Media source', value, object.type);
            if (Array.isArray(fieldDefinition.options) && fieldDefinition.options.length) return selectField(key, label, value, fieldDefinition.options);
            return input(key, label, value, fieldDefinition.control === 'textarea');
        }).join('');
        inspector.innerHTML = `<div class="page-inspector-selected"><span>${escape(heading)}</span><strong>${selected.kind === 'element' ? 'Element settings' : selected.kind[0].toUpperCase() + selected.kind.slice(1) + ' settings'}</strong></div><div class="page-object-tabs" role="tablist" aria-label="Block settings"><button type="button" class="${inspectorTab === 'content' ? 'is-active' : ''}" aria-selected="${inspectorTab === 'content'}" data-inspector-tab="content">Content</button><button type="button" class="${inspectorTab === 'style' ? 'is-active' : ''}" aria-selected="${inspectorTab === 'style'}" data-inspector-tab="style">Style</button><button type="button" class="${inspectorTab === 'advanced' ? 'is-active' : ''}" aria-selected="${inspectorTab === 'advanced'}" data-inspector-tab="advanced">Advanced</button></div><section class="page-object-tab-panel" data-inspector-panel="content" ${inspectorTab === 'content' ? '' : 'hidden'}><div class="page-object-control-grid">${content}</div>${moveElementControl()}${objectAiControls()}</section><section class="page-object-tab-panel" data-inspector-panel="style" ${inspectorTab === 'style' ? '' : 'hidden'}>${appearanceControls()}</section><section class="page-object-tab-panel" data-inspector-panel="advanced" ${inspectorTab === 'advanced' ? '' : 'hidden'}>${responsiveControls(object)}${elementCustomControls()}${customCodeControls()}</section>`;
        window.initializeEnhancedSelects?.(inspector);
    };
    const render = () => { renderNavigator(); renderFloatingNavigator(); liveCanvasStyles(); renderInspector(); syncHistory(); };
    const mutate = (callback) => { snapshot(); callback(); save(); render(); };
    const selectedObject = () => selected.kind === 'section' ? currentSection() : selected.kind === 'column' ? currentColumn() : currentElement();
    const applyObjectAiProposal = (proposal, target) => mutate(() => {
        const replacement = clone(proposal);
        if (target.kind === 'section') documentState.sections[target.section] = replacement;
        else if (target.kind === 'column') documentState.sections[target.section].columns[target.column] = replacement;
        else documentState.sections[target.section].columns[target.column].elements[target.element] = replacement;
        selected = {...target};
        selectionActive = true;
    });
    const reviewValue = (value, key = '') => {
        const text = typeof value === 'object' && value !== null ? JSON.stringify(value) : String(value ?? '');
        if (['html','css','javascript','custom_css'].includes(key)) return text ? `Custom code changed (${text.length} characters)` : 'Removed';
        return text.length > 180 ? `${text.slice(0, 177)}…` : (text || 'Empty');
    };
    const objectTree = (root, rootKind) => {
        const items = [];
        const addElement = (item, path) => items.push({path, kind:'element', object:item});
        if (rootKind === 'element') addElement(root, names[root.type] || root.type || 'Element');
        else if (rootKind === 'column') {
            items.push({path:'Column', kind:'column', object:root});
            (root.elements || []).forEach((item, index) => addElement(item, `Element ${index + 1} · ${names[item.type] || item.type}`));
        } else {
            items.push({path:'Section', kind:'section', object:root});
            (root.columns || []).forEach((columnItem, columnIndex) => {
                items.push({path:`Column ${columnIndex + 1}`, kind:'column', object:columnItem});
                (columnItem.elements || []).forEach((item, elementIndex) => addElement(item, `Column ${columnIndex + 1} · Element ${elementIndex + 1} · ${names[item.type] || item.type}`));
            });
        }
        return items;
    };
    const changedEntries = (beforeRoot, afterRoot, rootKind, bucket) => {
        const before = objectTree(beforeRoot, rootKind);
        const after = objectTree(afterRoot, rootKind);
        const entries = [];
        const total = Math.max(before.length, after.length);
        for (let index = 0; index < total; index++) {
            const oldItem = before[index]; const newItem = after[index];
            if (!oldItem || !newItem) continue;
            const oldValues = objectValue(oldItem.object?.[bucket]); const newValues = objectValue(newItem.object?.[bucket]);
            new Set([...Object.keys(oldValues), ...Object.keys(newValues)]).forEach((key) => {
                if (JSON.stringify(oldValues[key] ?? '') === JSON.stringify(newValues[key] ?? '')) return;
                entries.push({label:`${newItem.path} · ${key.replaceAll('_',' ')}`, before:reviewValue(oldValues[key], key), after:reviewValue(newValues[key], key)});
            });
        }
        return entries;
    };
    const structureEntries = (before, after, kind) => {
        const entries = [];
        if (kind === 'element' && before.type !== after.type) entries.push({label:'Element type', before:names[before.type] || before.type, after:names[after.type] || after.type});
        const oldTree = objectTree(before, kind); const newTree = objectTree(after, kind);
        const oldElements = oldTree.filter((item) => item.kind === 'element').map((item) => item.object.type);
        const newElements = newTree.filter((item) => item.kind === 'element').map((item) => item.object.type);
        if (kind === 'section' && (before.columns || []).length !== (after.columns || []).length) entries.push({label:'Columns', before:String((before.columns || []).length), after:String((after.columns || []).length)});
        if (oldElements.length !== newElements.length) entries.push({label:'Elements', before:String(oldElements.length), after:String(newElements.length)});
        const typeDifference = (left, right) => { const remaining = right.reduce((counts, type) => ({...counts, [type]:(counts[type] || 0) + 1}), {}); return left.filter((type) => { if (remaining[type]) { remaining[type]--; return false; } return true; }); };
        const added = typeDifference(newElements, oldElements); const removed = typeDifference(oldElements, newElements);
        if (added.length) entries.push({label:'Added elements', before:'None', after:added.map((type) => names[type] || type).join(', ')});
        if (removed.length) entries.push({label:'Removed elements', before:removed.map((type) => names[type] || type).join(', '), after:'Removed'});
        if (JSON.stringify(oldElements) !== JSON.stringify(newElements)) entries.push({label:'Element sequence', before:oldElements.map((type) => names[type] || type).join(' → ') || 'None', after:newElements.map((type) => names[type] || type).join(' → ') || 'None'});
        return entries;
    };
    const protectedUrlEntries = (before, after, kind) => {
        const collect = (root) => objectTree(root, kind).flatMap((item) => Object.entries({...objectValue(item.object.content), ...objectValue(item.object.settings)})
            .filter(([key, value]) => typeof value === 'string' && value && (key === 'url' || key === 'media_url' || key === 'images' || key === 'background_image' || key.endsWith('_url')))
            .map(([key, value]) => ({label:`${item.path} · ${key.replaceAll('_',' ')}`, value})));
        const oldUrls = collect(before); const newUrls = collect(after);
        return oldUrls.map((item) => {
            const retained = newUrls.some((candidate) => candidate.value === item.value);
            return {label:item.label, before:reviewValue(item.value), after:retained ? 'Preserved' : 'Removed'};
        });
    };
    const appendReviewSection = (container, title, entries) => {
        if (!entries.length) return;
        const sectionNode = document.createElement('section'); sectionNode.className = 'page-object-ai-review-group';
        const heading = document.createElement('h4'); heading.textContent = `${title} (${entries.length})`;
        const list = document.createElement('dl');
        entries.slice(0, 30).forEach((entry) => { const term = document.createElement('dt'); term.textContent = entry.label; const detail = document.createElement('dd'); const before = document.createElement('span'); before.textContent = entry.before; const arrow = document.createElement('b'); arrow.textContent = '→'; const after = document.createElement('span'); after.textContent = entry.after; detail.append(before, arrow, after); list.append(term, detail); });
        if (entries.length > 30) { const note = document.createElement('p'); note.textContent = `${entries.length - 30} additional changes are included.`; sectionNode.append(heading, list, note); }
        else sectionNode.append(heading, list);
        container.append(sectionNode);
    };
    const buildObjectAiReview = (before, after, kind) => {
        const review = document.createElement('div'); review.className = 'page-object-ai-review';
        appendReviewSection(review, 'Structure', structureEntries(before, after, kind));
        appendReviewSection(review, 'Content', changedEntries(before, after, kind, 'content'));
        appendReviewSection(review, 'Style and advanced settings', changedEntries(before, after, kind, 'settings'));
        appendReviewSection(review, 'Responsive behavior', changedEntries(before, after, kind, 'responsive'));
        appendReviewSection(review, 'Protected links and media', protectedUrlEntries(before, after, kind));
        if (!review.children.length) { const empty = document.createElement('p'); empty.textContent = 'AI returned no visible changes for this selection.'; review.append(empty); }
        return review;
    };
    const requestObjectAi = async (button) => {
        const controls = button.closest('[data-object-ai-controls], .page-object-ai');
        const instruction = controls?.querySelector('[data-object-ai-instruction]');
        const status = controls?.querySelector('[data-object-ai-status]');
        const brief = instruction?.value.trim() || '';
        if (brief.length < 10) { status.textContent = 'Describe the change in at least 10 characters.'; instruction?.focus(); return; }
        const signature = JSON.stringify(selectedObject());
        const target = { kind:selected.kind, section:selected.section, column:selected.column, element:selected.element };
        button.disabled = true; status.textContent = 'Reviewing the selected scope with page context…';
        try {
            const response = await fetch(objectAiUrl, { method:'POST', headers:{ Accept:'application/json', 'Content-Type':'application/json', 'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]')?.content || '' }, body:JSON.stringify({ title:form.querySelector('[name=title]')?.value.trim() || 'Untitled page', instruction:brief, page_purpose:form.querySelector('[data-ai-brief]')?.value.trim() || '', ...target, document:JSON.stringify(documentState) }) });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload?.error?.message || payload?.message || 'This selection could not be revised. Try again.');
            if (JSON.stringify(selectedObject()) !== signature || selected.kind !== target.kind || selected.section !== target.section || selected.column !== target.column || selected.element !== target.element) { status.textContent = 'This selection changed while AI was working. Request a fresh suggestion.'; return; }
            controls.querySelector('[data-object-ai-proposal]')?.remove();
            const proposal = document.createElement('div'); proposal.className = 'page-object-ai-proposal'; proposal.dataset.objectAiProposal = '';
            if (!payload?.data?.proposal || typeof payload.data.proposal !== 'object') throw new Error('AI returned an invalid design proposal. Try again.');
            const title = document.createElement('strong'); title.textContent = `Proposed ${target.kind} design`;
            const summary = buildObjectAiReview(JSON.parse(signature), payload.data.proposal, target.kind);
            const actions = document.createElement('div'); actions.className = 'ai-page-proposal-actions';
            const apply = document.createElement('button'); apply.type = 'button'; apply.className = 'button'; apply.textContent = 'Apply design'; apply.addEventListener('click', () => { applyObjectAiProposal(payload.data.proposal, target); });
            const discard = document.createElement('button'); discard.type = 'button'; discard.className = 'button button-secondary'; discard.textContent = 'Discard'; discard.addEventListener('click', () => { proposal.remove(); status.textContent = 'Suggestion discarded. The selection is unchanged.'; });
            const another = document.createElement('button'); another.type = 'button'; another.className = 'button button-secondary'; another.textContent = 'Request another version'; another.addEventListener('click', () => { proposal.remove(); requestObjectAi(button); });
            actions.append(apply, another, discard); proposal.append(title, summary, actions); controls.append(proposal);
            status.textContent = `Design ready from ${payload.data.provider} · ${payload.data.model}${payload.data.fallback_used ? ' (fallback model)' : ''}. Review the complete replacement before applying.`;
        } catch (error) { status.textContent = error.message || 'This selection could not be revised. Try again.'; } finally { button.disabled = false; }
    };
    const deleteSelected = () => {
        const object = selectedObject();
        const children = selected.kind === 'section' ? (object?.columns || []).reduce((count, column) => count + 1 + (column.elements?.length || 0), 0) : selected.kind === 'column' ? (object?.elements?.length || 0) : 0;
        if (selected.kind === 'section' && documentState.sections.length === 1) return;
        if (selected.kind === 'column' && currentSection().columns.length === 1) return;
        if (children > 1 && !window.confirm(`Delete this ${selected.kind} and its ${children} child items? You can undo this change before saving.`)) return;
        mutate(() => { if (selected.kind === 'section') { documentState.sections.splice(selected.section, 1); selected.section = Math.max(0, selected.section - 1); selected.kind = 'section'; } else if (selected.kind === 'column') { currentSection().columns.splice(selected.column, 1); selected.column = Math.max(0, selected.column - 1); } else { currentColumn().elements.splice(selected.element, 1); selected.element = Math.max(0, selected.element - 1); } });
    };
    const duplicateSelected = () => mutate(() => { if (selected.kind === 'section') { const item = clone(currentSection()); item.id = id(); documentState.sections.splice(selected.section + 1, 0, item); selected.section++; } else if (selected.kind === 'column') { const item = clone(currentColumn()); item.id = id(); currentSection().columns.splice(selected.column + 1, 0, item); selected.column++; } else { const item = clone(currentElement()); item.id = id(); currentColumn().elements.splice(selected.element + 1, 0, item); selected.element++; } });
    const moveSelected = (direction) => mutate(() => { const list = selected.kind === 'section' ? documentState.sections : selected.kind === 'column' ? currentSection().columns : currentColumn().elements; const index = selected.kind === 'section' ? selected.section : selected.kind === 'column' ? selected.column : selected.element; const next = index + direction; if (next < 0 || next >= list.length) return; [list[index], list[next]] = [list[next], list[index]]; if (selected.kind === 'section') selected.section = next; else if (selected.kind === 'column') selected.column = next; else selected.element = next; });
    const performEditorAction = (name, sectionIndex = selected.section, columnIndex = selected.column) => {
        if (name === 'add-section') return mutate(() => { documentState.sections.push(section()); select('section', documentState.sections.length - 1); });
        if (name === 'add-column') return mutate(() => { documentState.sections[sectionIndex].columns.push(column()); select('column', sectionIndex, documentState.sections[sectionIndex].columns.length - 1); });
        if (name === 'add-element') { selectionActive = true; selected = {kind:'column', section:sectionIndex, column:columnIndex, element:0}; form.querySelector('[data-block-library-open]')?.click(); return; }
        if (name === 'delete') return deleteSelected();
        if (name === 'duplicate') return duplicateSelected();
        if (name === 'move-up') return moveSelected(-1);
        if (name === 'move-down') return moveSelected(1);
        if (name === 'edit') return openInspector();
    };
    canvas.addEventListener('click', (event) => { const canvasSize = event.target.closest('[data-canvas-size]'); if (canvasSize) { event.preventDefault(); event.stopPropagation(); const size = canvasSize.dataset.canvasSize; canvas.dataset.preview = size; canvas.parentElement?.dataset && (canvas.parentElement.dataset.preview = size); canvas.closest('[data-canvas-toolbar]')?.querySelectorAll('[data-canvas-size]').forEach((button) => { const active = button === canvasSize; button.classList.toggle('is-active', active); button.setAttribute('aria-pressed', active ? 'true' : 'false'); }); requestAnimationFrame(syncCanvasPreview); return; } const action = event.target.closest('[data-editor-action]'); if (action) { event.stopPropagation(); const sectionIndex = Number.isFinite(Number(action.dataset.section)) ? Number(action.dataset.section) : selected.section; const columnIndex = Number.isFinite(Number(action.dataset.column)) ? Number(action.dataset.column) : selected.column; if (action.dataset.editorAction === 'add-section') return mutate(() => { documentState.sections.push(section()); select('section', documentState.sections.length - 1); }); if (action.dataset.editorAction === 'add-column') return mutate(() => { documentState.sections[sectionIndex].columns.push(column()); select('column', sectionIndex, documentState.sections[sectionIndex].columns.length - 1); }); if (action.dataset.editorAction === 'add-element') { selectionActive = true; selected = {kind:'column', section:sectionIndex,column:columnIndex,element:0}; form.querySelector('[data-block-library-open]')?.click(); return; } if (action.dataset.editorAction === 'delete') return deleteSelected(); if (action.dataset.editorAction === 'duplicate') return duplicateSelected(); if (action.dataset.editorAction === 'move-up') return moveSelected(-1); if (action.dataset.editorAction === 'move-down') return moveSelected(1); }
        const target = event.target.closest('[data-select-kind]'); if (target) select(target.dataset.selectKind, Number(target.dataset.section), Number(target.dataset.column || 0), Number(target.dataset.element || 0));
    });
    canvas.addEventListener('keydown', (event) => { if (!['Enter', ' '].includes(event.key)) return; const target = event.target.closest('[data-select-kind]'); if (!target) return; event.preventDefault(); select(target.dataset.selectKind, Number(target.dataset.section), Number(target.dataset.column || 0), Number(target.dataset.element || 0)); });
    canvas?.closest('.page-block-canvas-wrap')?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-canvas-size]');
        if (!button) return;
        event.preventDefault();
        event.stopPropagation();
        const size = button.dataset.canvasSize;
        canvas.dataset.preview = size;
        canvas?.closest('.page-block-canvas-wrap')?.setAttribute('data-preview', size);
        canvas?.closest('.page-block-canvas-wrap')?.querySelectorAll('[data-canvas-size]').forEach((item) => {
            const active = item === button;
            item.classList.toggle('is-active', active);
            item.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    });
    form.addEventListener('click', (event) => { if (event.target.closest('[data-inspector-close]')) { event.preventDefault(); event.stopPropagation(); closeInspector(); } });
    navigator?.addEventListener('click', (event) => { const target = event.target.closest('[data-navigator-kind]'); if (!target) return; select(target.dataset.navigatorKind, Number(target.dataset.section), Number(target.dataset.column || 0), Number(target.dataset.element || 0)); openInspector(); });
    floatingNavigatorTrigger?.addEventListener('click', () => {
        const open = !floatingNavigator?.classList.contains('is-open');
        if (!open) return closeFloatingNavigator();
        floatingNavigator?.classList.add('is-open');
        floatingNavigator?.setAttribute('aria-hidden', 'false');
        floatingNavigatorTrigger.setAttribute('aria-expanded', 'true');
    });
    form.querySelector('[data-floating-navigator-close]')?.addEventListener('click', closeFloatingNavigator);
    floatingNavigatorTree?.addEventListener('click', (event) => {
        const target = event.target.closest('[data-floating-navigator-kind]');
        if (!target) return;
        const toggle = event.target.closest('.page-editor-floating-navigator-chevron.is-toggle');
        if (toggle) {
            const key = target.dataset.floatingNavigatorToggle;
            if (key) {
                collapsedNavigatorNodes.has(key) ? collapsedNavigatorNodes.delete(key) : collapsedNavigatorNodes.add(key);
                renderFloatingNavigator();
            }
            event.preventDefault();
            event.stopPropagation();
            return;
        }
        select(target.dataset.floatingNavigatorKind, Number(target.dataset.section), Number(target.dataset.column || 0), Number(target.dataset.element || 0));
        openInspector();
    });
    libraryTrigger?.addEventListener('click', (event) => { event.preventDefault(); event.stopPropagation(); openLibrary(); });
    libraryBackdrop?.addEventListener('click', (event) => { event.preventDefault(); closeLibrary(); });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') { closeFloatingNavigator(); closeInspector(); } });
    form.querySelectorAll('[data-add]').forEach((button) => button.addEventListener('click', () => { const type = button.dataset.add; if (type === 'section') return mutate(() => { documentState.sections.push(section()); selectionActive = true; selected = {kind:'section',section:documentState.sections.length - 1,column:0,element:0}; }); if (type === 'grid') return mutate(() => { documentState.sections.push(gridSection(Number(button.dataset.gridCount || 3))); selectionActive = true; selected = {kind:'section',section:documentState.sections.length - 1,column:0,element:0}; }); const target = currentColumn() || documentState.sections[0].columns[0]; mutate(() => { target.elements.push(element(type)); selectionActive = true; selected = {kind:'element',section:selected.section,column:selected.column,element:target.elements.length - 1}; }); }));
    form.querySelectorAll('[data-add]').forEach((button) => button.addEventListener('click', openInspector));
    inspector.addEventListener('change', (event) => {
        const key = event.target.dataset.responsiveDimension;
        if (!key) return;
        const object = selectedObject();
        object.responsive = objectValue(object.responsive);
        object.responsive[breakpoint] = objectValue(object.responsive[breakpoint]);
        const value = event.target.value.trim();
        if (value) object.responsive[breakpoint][key] = value;
        else delete object.responsive[breakpoint][key];
        if (!Object.keys(object.responsive[breakpoint]).length) delete object.responsive[breakpoint];
        save();
        renderInspector();
    });
    const updateEditorField = (target) => {
        const key = target.dataset.editorField;
        if (!key) return;
        const object = selectedObject();
        if (!object) return;
        const bucket = target.dataset.editorBucket === 'settings' ? (object.settings = objectValue(object.settings)) : selected.kind === 'element' ? (object.content = objectValue(object.content)) : (object.settings = objectValue(object.settings));
        if ((bucket[key] ?? '') === target.value) return;
        if (selected.kind === 'section' && key === 'grid_preset') {
            const count = {one:1,two:2,three:3,four:4}[target.value] || 1;
            snapshot();
            while (object.columns.length < count) object.columns.push(column());
            if (object.columns.length > count) object.columns.splice(count);
        }
        if (target.value === '' && target.dataset.editorBucket === 'settings') delete bucket[key];
        else bucket[key] = target.value;
        save();
    };
    inspector.addEventListener('input', (event) => updateEditorField(event.target));
    inspector.addEventListener('change', (event) => { const moveTarget = event.target.dataset.moveElementTo; if (moveTarget !== undefined) { const targetColumn = Number(moveTarget); const section = currentSection(); if (!Number.isInteger(targetColumn) || !section?.columns[targetColumn] || targetColumn === selected.column) return; mutate(() => { const source = currentColumn(); const [moved] = source.elements.splice(selected.element, 1); if (!moved) return; section.columns[targetColumn].elements.push(moved); selected.column = targetColumn; selected.element = section.columns[targetColumn].elements.length - 1; }); return; } const spacingKey = event.target.dataset.spacingField; if (spacingKey) { const object = selected.kind === 'section' ? currentSection() : selected.kind === 'column' ? currentColumn() : currentElement(); const unit = event.target.closest('fieldset')?.querySelector('[data-spacing-unit]')?.value || 'px'; object.responsive ||= {}; object.responsive[breakpoint] ||= {}; const box = object.responsive[breakpoint][spacingKey] && typeof object.responsive[breakpoint][spacingKey] === 'object' ? object.responsive[breakpoint][spacingKey] : {}; const value = event.target.value.trim(); box[event.target.dataset.spacingSide] = value === '' ? '' : `${value}${unit}`; if (Object.values(box).every((sideValue) => !sideValue)) delete object.responsive[breakpoint][spacingKey]; else object.responsive[breakpoint][spacingKey] = box; if (!Object.keys(object.responsive[breakpoint]).length) delete object.responsive[breakpoint]; save(); return; } const responsiveKey = event.target.dataset.responsiveField; if (responsiveKey) { const object = selected.kind === 'section' ? currentSection() : selected.kind === 'column' ? currentColumn() : currentElement(); object.responsive ||= {}; object.responsive[breakpoint] ||= {}; if (event.target.value) object.responsive[breakpoint][responsiveKey] = event.target.value; else delete object.responsive[breakpoint][responsiveKey]; if (!Object.keys(object.responsive[breakpoint]).length) delete object.responsive[breakpoint]; save(); renderInspector(); return; } if (event.target.dataset.editorField) event.target.dispatchEvent(new Event('input', { bubbles:true })); });
    inspector.addEventListener('click', (event) => { const tab = event.target.closest('[data-inspector-tab]'); if (tab) { inspectorTab = tab.dataset.inspectorTab; inspector.querySelectorAll('[data-inspector-tab]').forEach((button) => { const active = button === tab; button.classList.toggle('is-active', active); button.setAttribute('aria-selected', active ? 'true' : 'false'); }); inspector.querySelectorAll('[data-inspector-panel]').forEach((panel) => { panel.hidden = panel.dataset.inspectorPanel !== inspectorTab; }); return; } const reset = event.target.closest('[data-responsive-reset]'); if (reset) { const object = selectedObject(); const key = reset.dataset.responsiveReset; if (!object?.responsive?.[breakpoint]) return; delete object.responsive[breakpoint][key]; if (!Object.keys(object.responsive[breakpoint]).length) delete object.responsive[breakpoint]; save(); renderInspector(); liveCanvasStyles(); return; } const aiAsk = event.target.closest('[data-object-ai-ask]'); if (aiAsk) { requestObjectAi(aiAsk); return; } const picker = event.target.closest('[data-media-picker]'); if (picker) { openMedia(picker.dataset.mediaPicker, picker.dataset.mediaTypes ? picker.dataset.mediaTypes.split(',') : [], picker.dataset.mediaBucket || 'content'); return; } const breakpointButton = event.target.closest('[data-breakpoint]'); if (breakpointButton) { breakpoint = breakpointButton.dataset.breakpoint; renderInspector(); liveCanvasStyles(); return; } const action = event.target.closest('[data-editor-action]'); if (!action) return; if (action.dataset.editorAction === 'delete') deleteSelected(); if (action.dataset.editorAction === 'duplicate') duplicateSelected(); if (action.dataset.editorAction === 'move-up') moveSelected(-1); if (action.dataset.editorAction === 'move-down') moveSelected(1); });
    inspector.addEventListener('input', (event) => { const radiusKey = event.target.dataset.radiusField; if (!radiusKey) return; const object = selected.kind === 'section' ? currentSection() : selected.kind === 'column' ? currentColumn() : currentElement(); const unit = event.target.closest('fieldset')?.querySelector('[data-spacing-unit]')?.value || 'px'; object.settings ||= {}; object.settings[radiusKey] = event.target.value.trim() ? `${event.target.value.trim()}${unit}` : ''; if (!object.settings[radiusKey]) delete object.settings[radiusKey]; save(); });
    inspector.addEventListener('change', (event) => { const unit = event.target.dataset.spacingUnit; if (!unit) return; const fieldset = event.target.closest('fieldset'); const object = selected.kind === 'section' ? currentSection() : selected.kind === 'column' ? currentColumn() : currentElement(); const radius = fieldset?.classList.contains('page-radius-field'); if (radius) { object.settings ||= {}; fieldset.querySelectorAll('[data-radius-field]').forEach((input) => { const value = numericOf(input.value).trim(); if (value === '') delete object.settings[input.dataset.radiusField]; else object.settings[input.dataset.radiusField] = `${value}${unit}`; }); } else { const key = fieldset?.querySelector('[data-spacing-field]')?.dataset.spacingField; object.responsive ||= {}; object.responsive[breakpoint] ||= {}; const box = object.responsive[breakpoint][key] && typeof object.responsive[breakpoint][key] === 'object' ? object.responsive[breakpoint][key] : {}; fieldset.querySelectorAll('[data-spacing-field]').forEach((input) => { const value = numericOf(input.value).trim(); if (value === '') delete box[input.dataset.spacingSide]; else box[input.dataset.spacingSide] = `${value}${unit}`; }); if (Object.keys(box).length) object.responsive[breakpoint][key] = box; else delete object.responsive[breakpoint][key]; if (!Object.keys(object.responsive[breakpoint]).length) delete object.responsive[breakpoint]; } save(); });
    undo.addEventListener('click', () => { if (!history.length) return; future.push(JSON.stringify(documentState)); documentState = JSON.parse(history.pop()); render(); });
    redo.addEventListener('click', () => { if (!future.length) return; history.push(JSON.stringify(documentState)); documentState = JSON.parse(future.pop()); render(); });
    form.addEventListener('ai-page-draft', (event) => { const proposal = event.detail; const plannedDocument = proposal?.document; if (plannedDocument?.version !== 2 || !Array.isArray(plannedDocument.sections) || !plannedDocument.sections.length) return; mutate(() => { documentState = clone(plannedDocument); selectionActive = true; selected = {kind:'section',section:0,column:0,element:0}; const title=form.querySelector('[name="title"]'); const seoTitle=form.querySelector('[name="seo_title"]'); const seoDescription=form.querySelector('[name="seo_description"]'); if(title&&proposal.title) title.value=proposal.title; if(seoTitle&&proposal.seo_title) seoTitle.value=proposal.seo_title; if(seoDescription&&proposal.seo_description) seoDescription.value=proposal.seo_description; }); });
    form.addEventListener('submit', () => {
        save();
        submittedDocument = output.value;
    }, { capture:true });
    form.addEventListener('ministry:async-success', (event) => {
        const stored = event.detail?.document;
        if (!stored || stored.version !== 2 || !Array.isArray(stored.sections)) {
            saveState.textContent = 'Save could not be verified';
            form.dataset.dirty = 'true';
            return;
        }
        const normalised = JSON.stringify(stored);
        const unchangedDuringRequest = submittedDocument !== null && JSON.stringify(documentState) === submittedDocument;
        savedDocument = normalised;
        if (unchangedDuringRequest) {
            documentState = clone(stored);
            output.value = normalised;
            render();
        }
        submittedDocument = null;
        save();
        saveState.textContent = form.dataset.dirty === 'true' ? 'New changes not saved' : event.detail?.status === 'published' ? 'Published' : 'Saved';
    });
    window.addEventListener('beforeunload', (event) => {
        if (form.dataset.dirty !== 'true') return;
        event.preventDefault();
        event.returnValue = '';
    });
    window.oneMinistryPageEditor = {
        get document(){ return documentState; },
        get selected(){ return selected; },
        select(kind, sectionIndex, columnIndex = 0, elementIndex = 0){ select(kind, sectionIndex, columnIndex, elementIndex); },
        action(name){ performEditorAction(name); },
        updateInline(sectionIndex, columnIndex, elementIndex, field, value){
            const item = documentState.sections[sectionIndex]?.columns[columnIndex]?.elements[elementIndex];
            if (!item || !Object.hasOwn(item.content || {}, field)) return;
            mutate(() => { item.content[field] = value; selected = { kind:'element', section:sectionIndex, column:columnIndex, element:elementIndex }; selectionActive = true; });
        },
        moveElement(fromSection, fromColumn, fromElement, toSection, toColumn, toElement){
            const source = documentState.sections[fromSection]?.columns[fromColumn];
            const destination = documentState.sections[toSection]?.columns[toColumn];
            if (!source || !destination || !source.elements[fromElement]) return;
            mutate(() => {
                const [moved] = source.elements.splice(fromElement, 1);
                const destinationIndex = source === destination && fromElement < toElement ? toElement - 1 : toElement;
                destination.elements.splice(Math.max(0, destinationIndex), 0, moved);
                selected = { kind:'element', section:toSection, column:toColumn, element:Math.max(0, destinationIndex) };
                selectionActive = true;
            });
        },
        addElement(type){ const target = currentColumn(); if (!target || !defaults[type]) return; mutate(() => target.elements.push(element(type))); },
        save,
    };
    save(false);
    render();
})();
</script>
