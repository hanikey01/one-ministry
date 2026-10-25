<script>
(() => {
    const form = document.querySelector('[data-page-block-editor]');
    if (!form) return;

    const canvas = form.querySelector('[data-canvas]');
    const inspector = form.querySelector('[data-inspector]');
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
    const objectAiUrl = form.dataset.objectAiEndpoint || '';
    const aiAvailable = form.dataset.aiAvailable === 'true';
    const id = () => `editor-${crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random().toString(16).slice(2)}`}`;
    const clone = (value) => JSON.parse(JSON.stringify(value));
    const escape = (value) => String(value ?? '').replace(/[&<>'"]/g, (character) => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[character]));
    const names = { hero:'Hero', heading:'Heading', paragraph:'Paragraph', button:'Button', feature_list:'Feature list', testimonial:'Testimonial', statistics:'Statistics', image:'Image', gallery:'Gallery', video_embed:'Video embed', audio:'Audio', file:'File download', media_text:'Media & text', list:'List', accordion:'Accordion', spacer:'Spacer', quote:'Quote', call_to_action:'Call to action', service_times:'Service times', events:'Events', groups:'Groups', locations:'Locations', carousel:'Carousel', giving:'Giving', prayer:'Prayer', sermon_spotlight:'Sermon spotlight', announcements:'Announcements', next_steps:'Next steps', newsletter:'Newsletter', welcome:'Welcome', custom:'Content card', live_service:'Live service', books:'Books & resources', volunteer:'Volunteer', divider:'Divider' };
    const defaults = { hero:{eyebrow:'WELCOME',title:'A place to belong',body:'Share the invitation people should see first.',button_label:'Plan your visit',button_url:''}, heading:{text:'A meaningful heading'}, paragraph:{text:'Write a clear paragraph that helps people understand your ministry.'}, button:{label:'Learn more',url:''}, feature_list:{title:'What you can expect',items:'A warm welcome\nHelpful people\nA clear next step'}, testimonial:{quote:'A short story of encouragement and connection.',name:'Name',role:'Community member'}, statistics:{title:'Our ministry at a glance',stat_one_value:'1',stat_one_label:'Community',stat_two_value:'2',stat_two_label:'Locations',stat_three_value:'3',stat_three_label:'Ways to connect'}, image:{url:'',alt:'',caption:''}, gallery:{images:'',columns:'three',caption:''}, video_embed:{url:'',title:'A message to watch',caption:''}, audio:{url:'',title:'Listen now',caption:''}, file:{url:'',label:'Download resource',description:''}, media_text:{media_url:'',media_alt:'',placement:'left',title:'A message beside your media',body:'Use this layout to combine a visual with a clear invitation.',button_label:'Learn more',button_url:''}, list:{title:'What to expect',items:'A warm welcome\nHelpful information\nA clear next step',ordered:'no'}, accordion:{title:'Frequently asked questions',question_one:'What should I expect?',answer_one:'Add a helpful, reassuring answer.',question_two:'Where do I park?',answer_two:'Share the practical details visitors need.',question_three:'Is there something for children?',answer_three:'Explain the ministry options available.'}, spacer:{height:'medium'}, quote:{text:'A short quote or testimony.',attribution:''}, call_to_action:{title:'Take your next step',body:'Give people a clear way to respond.',button_label:'Get connected',button_url:''}, service_times:{title:'Join us this week',body:'Add a helpful invitation alongside your service times.'}, events:{title:'What is happening next',body:'Share upcoming events and ways to get involved.'}, groups:{title:'Find your community',body:'Invite people into groups and meaningful connection.'}, locations:{title:'Find us',body:'Help people find a location and plan their visit.',button_label:'View locations',button_url:'/locations'}, giving:{title:'Support the ministry',body:'Make it simple to give safely and confidently.',button_label:'Give now',button_url:'/give'}, prayer:{title:'How can we pray for you?',body:'Share a prayer request with our ministry team.',button_label:'Request prayer',button_url:''}, announcements:{title:'Latest announcements',body:'Share the news people need to know.'}, next_steps:{title:'Take your next step',body:'Help people find a clear next step.'}, newsletter:{title:'Stay connected',body:'Invite people to receive ministry updates.'}, carousel:{eyebrow:'FEATURED',title:'A featured moment',body:'Use short slides to highlight a message, event, or next step.',slides:'A practical welcome for every visitor\nA simple way to take your next step',button_label:'Learn more',button_url:''}, sermon_spotlight:{title:'Featured teaching',body:'Highlight a message that helps people take a next step.',button_label:'Watch message',button_url:''}, welcome:{title:'There is room for you',body:'Offer a warm, personal welcome to everyone visiting this page.'}, custom:{title:'Content card',body:'Add a focused message that fits this page.',button_label:'Learn more',button_url:''}, live_service:{title:'Join us online',body:'Watch and participate in our live service.',button_label:'Watch now',button_url:''}, books:{title:'Books and resources',body:'Explore trusted resources for your journey.',button_label:'Browse resources',button_url:''}, volunteer:{title:'Serve with purpose',body:'Find a place to use your gifts and serve.',button_label:'Start serving',button_url:''}, divider:{} };
    const column = () => ({ id:id(), settings:{}, responsive:{}, elements:[] });
    const section = () => ({ id:id(), type:'section', settings:{ padding:'regular', content_width:'standard', column_gap:'regular', grid_preset:'one' }, responsive:{}, columns:[column()] });
    const gridSection = (count = 3) => ({ ...section(), settings:{ padding:'regular', content_width:'standard', column_gap:'regular', grid_preset:['one','two','three','four'][count - 1] || 'three' }, columns:Array.from({length:count}, () => column()) });
    const element = (type) => ({ id:id(), type, content:clone(defaults[type] || {}), settings:{}, responsive:{} });
    const fromFlat = (blocks) => ({ version:2, sections:(blocks || []).map((block) => ({ id:id(), type:'section', settings:{}, responsive:{}, columns:[{...column(), elements:[elementFromFlat(block)]}] })) });
    const elementFromFlat = (block) => ({ id:id(), type:block?.type || 'paragraph', content:clone(block?.data || defaults.paragraph), settings:{}, responsive:{} });
    let documentState;
    try { documentState = JSON.parse(form.dataset.blocks || '{}'); } catch { documentState = {}; }
    if (documentState?.version !== 2 || !Array.isArray(documentState.sections)) documentState = fromFlat(Array.isArray(documentState) ? documentState : []);
    if (!documentState.sections.length) documentState.sections.push(section());
    let selected = { kind:'section', section:0, column:0, element:0 };
    let breakpoint = 'desktop';
    let history = [];
    let future = [];
    const currentSection = () => documentState.sections[selected.section];
    const currentColumn = () => currentSection()?.columns[selected.column];
    const currentElement = () => currentColumn()?.elements[selected.element];
    const save = () => { output.value = JSON.stringify(documentState); };
    const snapshot = () => { history.push(JSON.stringify(documentState)); future = []; syncHistory(); };
    const syncHistory = () => { undo.disabled = !history.length; redo.disabled = !future.length; };
    const openInspector = () => {
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
    const select = (kind, sectionIndex, columnIndex = 0, elementIndex = 0) => { selected = {kind, section:sectionIndex, column:columnIndex, element:elementIndex}; render(); };
    const elementPreview = (item) => { const content = item.content || {}; if (item.type === 'hero') return `<div class="page-canvas-hero"><small>${escape(content.eyebrow)}</small><h1>${escape(content.title)}</h1><p>${escape(content.body)}</p><b>${escape(content.button_label)}</b></div>`; if (item.type === 'heading') return `<h2>${escape(content.text)}</h2>`; if (item.type === 'paragraph') return `<p>${escape(content.text)}</p>`; if (item.type === 'image') return content.url ? `<img src="${escape(content.url)}" alt="${escape(content.alt)}">` : '<div class="page-canvas-image-placeholder">Image</div>'; if (item.type === 'button') return `<div class="page-canvas-cta"><b>${escape(content.label)}</b></div>`; if (item.type === 'testimonial') return `<blockquote class="page-canvas-element-summary">“${escape(content.quote)}”<span>${escape(content.name)}</span></blockquote>`; if (item.type === 'statistics') return `<div class="page-canvas-module"><small>STATISTICS</small><h2>${escape(content.title)}</h2><p>${escape(content.stat_one_value)} · ${escape(content.stat_two_value)} · ${escape(content.stat_three_value)}</p></div>`; if (item.type === 'call_to_action') return `<div class="page-canvas-cta"><h2>${escape(content.title)}</h2><p>${escape(content.body)}</p><b>${escape(content.button_label)}</b></div>`; if (['service_times','events','groups'].includes(item.type)) return `<div class="page-canvas-module"><small>${escape(names[item.type]).toUpperCase()}</small><h2>${escape(content.title)}</h2><p>${escape(content.body)}</p></div>`; return `<div class="page-canvas-element-summary"><strong>${escape(names[item.type] || item.type)}</strong><span>${escape(content.title || content.text || content.label || 'Configure this element')}</span></div>`; };
    const structureButtons = (kind) => `<div class="page-structure-actions" aria-label="${kind} actions"><button type="button" class="page-structure-icon" data-editor-action="move-up" aria-label="Move up" title="Move up"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" aria-hidden="true"><path d="m6 14 6-6 6 6"/></svg></button><button type="button" class="page-structure-icon" data-editor-action="move-down" aria-label="Move down" title="Move down"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" aria-hidden="true"><path d="m6 10 6 6 6-6"/></svg></button><button type="button" class="page-structure-icon" data-editor-action="duplicate" aria-label="Duplicate" title="Duplicate"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" aria-hidden="true"><rect x="8" y="8" width="10" height="10" rx="1.5"/><path d="M6 16H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1"/></svg></button><button type="button" class="page-structure-icon is-danger" data-editor-action="delete" aria-label="Delete" title="Delete"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" aria-hidden="true"><path d="M5 7h14M10 11v6M14 11v6M9 7V5h6v2M7 7l1 13h8l1-13"/></svg></button><button type="button" class="page-structure-icon" data-editor-action="edit" aria-label="Edit" title="Edit"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" aria-hidden="true"><path d="m4 16-.7 4.7L8 20l10.8-10.8a2.1 2.1 0 0 0-3-3Z"/><path d="m14.5 7.5 2 2"/></svg></button></div>`;
    const renderCanvas = () => {
        canvas.innerHTML = documentState.sections.map((item, sectionIndex) => `<section class="page-canvas-section-object ${selected.kind==='section'&&selected.section===sectionIndex?'is-selected':''}" data-select-kind="section" data-section="${sectionIndex}"><header class="page-canvas-object-header"><span>Section ${sectionIndex + 1}</span><strong>${escape(item.settings?.title || 'Section')}</strong>${selected.kind==='section'&&selected.section===sectionIndex ? structureButtons('section') : ''}</header><div class="page-canvas-structure-columns">${item.columns.map((itemColumn, columnIndex) => `<div class="page-canvas-column-object ${selected.kind==='column'&&selected.section===sectionIndex&&selected.column===columnIndex?'is-selected':''}" data-select-kind="column" data-section="${sectionIndex}" data-column="${columnIndex}"><header><span>Column ${columnIndex + 1}</span>${selected.kind==='column'&&selected.section===sectionIndex&&selected.column===columnIndex ? structureButtons('column') : ''}</header><div class="page-canvas-column-elements">${itemColumn.elements.map((itemElement, elementIndex) => `<article class="page-canvas-element-object ${selected.kind==='element'&&selected.section===sectionIndex&&selected.column===columnIndex&&selected.element===elementIndex?'is-selected':''}" data-select-kind="element" data-section="${sectionIndex}" data-column="${columnIndex}" data-element="${elementIndex}"><header><span>${escape(names[itemElement.type] || itemElement.type)}</span>${selected.kind==='element'&&selected.section===sectionIndex&&selected.column===columnIndex&&selected.element===elementIndex ? structureButtons('element') : ''}</header><div class="page-canvas-block-content">${elementPreview(itemElement)}</div></article>`).join('')}<button type="button" class="page-canvas-insert" data-editor-action="add-element" data-section="${sectionIndex}" data-column="${columnIndex}">+ Add element</button></div></div>`).join('')}<button type="button" class="page-canvas-insert" data-editor-action="add-column" data-section="${sectionIndex}">+ Add column</button></div></section>`).join('') + '<button type="button" class="page-canvas-insert page-canvas-add-section" data-editor-action="add-section">+ Add section</button>';
    };
    const input = (key, label, value, textarea = false) => `<label>${label}${textarea ? `<textarea data-editor-field="${key}">${escape(value)}</textarea>` : `<input data-editor-field="${key}" value="${escape(value)}">`}</label>`;
    const selectField = (key, label, value, options) => `<label>${label}<select data-editor-field="${key}">${options.map(([optionValue, optionLabel]) => `<option value="${optionValue}" ${value===optionValue?'selected':''}>${optionLabel}</option>`).join('')}</select></label>`;
    const mediaTypeFor = (type, key) => key === 'background_image' || key === 'media_url' ? ['image'] : type === 'image' || type === 'gallery' ? ['image'] : type === 'video_embed' ? ['video'] : type === 'audio' ? ['audio'] : type === 'file' ? ['file'] : [];
    const mediaField = (key, label, value, type, bucket = 'content') => `<label>${label}<div class="page-media-field">${key === 'images' ? `<textarea data-editor-field="${key}" data-editor-bucket="${bucket}" placeholder="Choose one or more ministry images">${escape(value)}</textarea>` : `<input data-editor-field="${key}" data-editor-bucket="${bucket}" value="${escape(value)}" placeholder="Choose ministry media or paste an approved URL">`}<button class="page-media-picker-trigger" type="button" data-media-picker="${key}" data-media-bucket="${bucket}" data-media-types="${key === 'images' ? 'image' : mediaTypeFor(type,key).join(',')}">Choose from media library</button></div></label>`;
    const responsiveField = (object, key, label, options) => {
        const value = object.responsive?.[breakpoint]?.[key] || '';
        const inherited = breakpoint === 'desktop' ? 'Base layout value' : `Inherited from ${breakpoint === 'tablet' ? 'desktop' : 'tablet or desktop'}`;
        return `<label>${label}<select data-responsive-field="${key}"><option value="">${inherited}</option>${options.map(([optionValue, optionLabel]) => `<option value="${optionValue}" ${value===optionValue?'selected':''}>${optionLabel}</option>`).join('')}</select><small class="page-responsive-state">${value ? `Override for ${breakpoint}` : inherited}</small></label>`;
    };
    const responsiveControls = (object) => {
        const common = [['visibility','Visibility',[['visible','Visible'],['hidden','Hidden']]], ['alignment','Alignment',[['left','Left'],['center','Centre'],['right','Right'],['stretch','Stretch']]]];
        const fields = selected.kind === 'section'
            ? [...common, ['column_count','Columns',[['1','One'],['2','Two'],['3','Three'],['4','Four']]], ['stack','Column flow',[['inline','Keep columns inline'],['stack','Stack columns']]], ['padding','Spacing',[['compact','Compact'],['regular','Regular'],['spacious','Spacious']]], ['column_gap','Column gap',[['compact','Compact'],['regular','Regular'],['spacious','Spacious']]]]
            : selected.kind === 'column'
                ? [...common, ['width','Column width',[['full','Full'],['half','Half'],['third','One third']]], ['order','Order',[['1','First'],['2','Second'],['3','Third'],['4','Fourth']]], ['gap','Element gap',[['compact','Compact'],['regular','Regular'],['spacious','Spacious']]]]
                : [...common, ['order','Order',[['1','First'],['2','Second'],['3','Third'],['4','Fourth']]]];
        return `<section class="page-responsive-controls"><div class="page-responsive-heading"><strong>Responsive</strong><div class="page-breakpoint-tabs" role="tablist">${['desktop','tablet','mobile'].map((item) => `<button type="button" data-breakpoint="${item}" class="${breakpoint===item?'is-active':''}" aria-selected="${breakpoint===item}">${item[0].toUpperCase()+item.slice(1)}</button>`).join('')}</div></div><div class="page-object-control-grid">${fields.map(([key,label,options]) => responsiveField(object, key, label, options)).join('')}</div></section>`;
    };
    const objectAiControls = () => `<section class="page-object-ai"><strong>AI assist</strong><p>AI receives the full page for context and can suggest copy changes only for this selected ${selected.kind}.</p><textarea data-object-ai-instruction rows="3" minlength="10" maxlength="1500" placeholder="Describe what you want changed…" ${aiAvailable ? '' : 'disabled'}></textarea><button class="button" type="button" data-object-ai-ask ${aiAvailable ? '' : 'disabled'}>${aiAvailable ? 'Suggest changes' : 'AI route unavailable'}</button><p class="page-object-ai-status" data-object-ai-status role="status" aria-live="polite">${aiAvailable ? '' : 'An administrator must verify an AI page-editor route first.'}</p></section>`;
    let mediaTarget = null;
    const mediaDialog = document.createElement('div');
    mediaDialog.className = 'page-media-dialog';
    mediaDialog.hidden = true;
    mediaDialog.innerHTML = `<button type="button" class="page-media-backdrop" data-close-media aria-label="Close media library"></button><section role="dialog" aria-modal="true" aria-label="Choose ministry media"><header><div><p>MEDIA LIBRARY</p><h2>Choose media</h2><span>Select existing ministry media or upload a compatible file.</span></div><button type="button" data-close-media aria-label="Close">×</button></header><div class="page-media-upload"><label class="page-media-upload-button">Upload media<input type="file" data-page-media-upload></label><small data-media-upload-status></small></div><div class="page-media-list" data-page-media-list></div></section>`;
    document.body.append(mediaDialog);
    const compatibleMedia = () => media.filter((asset) => !mediaTarget?.types?.length || mediaTarget.types.includes(asset.type));
    const renderMedia = () => {
        const list = mediaDialog.querySelector('[data-page-media-list]');
        const assets = compatibleMedia();
        list.innerHTML = assets.length ? assets.map((asset, index) => `<button type="button" class="page-media-option" data-media-option="${index}"><span class="page-media-thumb">${asset.type === 'image' ? `<img src="${escape(asset.url)}" alt="">` : escape(asset.type.slice(0,3).toUpperCase())}</span><strong>${escape(asset.label)}</strong><span>${escape(asset.type)}</span></button>`).join('') : '<p class="page-media-empty">No compatible media is available yet.</p>';
        list.querySelectorAll('[data-media-option]').forEach((button) => button.addEventListener('click', () => { const asset = assets[Number(button.dataset.mediaOption)]; const item = selectedObject(); if (!asset || !item || !mediaTarget) return; const bucket = mediaTarget.bucket === 'settings' ? (item.settings ||= {}) : (item.content ||= {}); bucket[mediaTarget.key] = mediaTarget.key === 'images' ? [...new Set(String(bucket.images || '').split(/\n+/).filter(Boolean).concat(asset.url))].join('\n') : asset.url; save(); mediaDialog.hidden = true; render(); }));
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
        const appearanceControls = () => `<section class="page-object-appearance"><strong>Appearance</strong><div class="page-object-control-grid">${selectField('background', 'Background', object.settings?.background || 'transparent', [['transparent','Transparent'],['canvas','Canvas'],['surface','Surface'],['surface_muted','Muted surface'],['action','Action surface'],['brand','Brand surface']])}${mediaField('background_image', 'Background image', object.settings?.background_image || '', object.type || 'section', 'settings')}${selectField('background_overlay', 'Image overlay', object.settings?.background_overlay || 'none', [['none','None'],['soft','Soft'],['strong','Strong']])}${selectField('border', 'Border', object.settings?.border || 'none', [['none','None'],['subtle','Subtle'],['strong','Strong']])}${selectField('radius', 'Corner radius', object.settings?.radius || 'none', [['none','Square'],['small','Small'],['medium','Medium'],['large','Large']])}${selectField('shadow', 'Shadow', object.settings?.shadow || 'none', [['none','None'],['small','Small'],['medium','Medium']])}</div></section>`;
        if (selected.kind === 'section') content = input('title', 'Section title', object.settings?.title || '') + input('body', 'Introduction', object.settings?.body || '', true) + selectField('content_width', 'Content width', object.settings?.content_width || 'standard', [['full','Full width'],['wide','Wide'],['standard','Standard'],['narrow','Narrow']]) + selectField('grid_preset', 'Grid preset', object.settings?.grid_preset || (object.columns.length === 1 ? 'one' : object.columns.length === 2 ? 'two' : object.columns.length === 4 ? 'four' : 'three'), [['one','One column'],['two','Two columns'],['three','Three columns'],['four','Four columns']]) + selectField('padding', 'Section spacing', object.settings?.padding || 'regular', [['compact','Compact'],['regular','Regular'],['spacious','Spacious']]);
        if (selected.kind === 'column') content = selectField('alignment', 'Content alignment', object.settings?.alignment || 'left', [['left','Left'],['center','Centre'],['right','Right']]);
        if (selected.kind === 'element') content = Object.entries(object.content || {}).map(([key, value]) => (['media_url','images'].includes(key) || (key === 'url' && object.type !== 'button')) ? mediaField(key, key === 'media_url' ? 'Media' : key === 'images' ? 'Gallery images' : 'Media source', value, object.type) : input(key, key.replaceAll('_',' ').replace(/\b\w/g, (letter) => letter.toUpperCase()), value, ['body','text','caption','description','items','answer_one','answer_two','answer_three','slides','quote'].includes(key))).join('');
        inspector.innerHTML = `<div class="page-inspector-selected"><span>${escape(heading)}</span><strong>${selected.kind === 'element' ? 'Element settings' : selected.kind[0].toUpperCase() + selected.kind.slice(1) + ' settings'}</strong></div><div class="page-inspector-tabs" role="tablist" aria-label="Editor mode"><button type="button" class="is-active" aria-selected="true" data-inspector-mode="manual">Manual</button><button type="button" aria-selected="false" data-inspector-mode="ai">AI assist</button></div><div class="page-inspector-mode" data-inspector-mode-panel="manual"><div class="page-object-tabs" role="tablist" aria-label="Block settings"><button type="button" class="is-active" aria-selected="true" data-inspector-tab="content">Content</button><button type="button" aria-selected="false" data-inspector-tab="style">Style</button><button type="button" aria-selected="false" data-inspector-tab="advanced">Advanced</button></div><section class="page-object-tab-panel" data-inspector-panel="content"><div class="page-object-control-grid">${content}</div></section><section class="page-object-tab-panel" data-inspector-panel="style" hidden>${appearanceControls()}</section><section class="page-object-tab-panel" data-inspector-panel="advanced" hidden>${responsiveControls(object)}</section></div><div class="page-inspector-mode" data-inspector-mode-panel="ai" hidden>${objectAiControls()}</div>`;
        window.initializeEnhancedSelects?.(inspector);
    };
    const render = () => { save(); renderCanvas(); renderInspector(); syncHistory(); };
    const mutate = (callback) => { snapshot(); callback(); render(); };
    const selectedObject = () => selected.kind === 'section' ? currentSection() : selected.kind === 'column' ? currentColumn() : currentElement();
    const applyObjectAiEdits = (edits) => mutate(() => Object.entries(edits || {}).forEach(([target, fields]) => {
        const match = /^c(\d+)e(\d+)$/.exec(target);
        if (!match || !fields || typeof fields !== 'object') return;
        const item = documentState.sections[selected.section]?.columns[Number(match[1])]?.elements[Number(match[2])];
        if (!item) return;
        Object.entries(fields).forEach(([field, value]) => { if (typeof value === 'string' && Object.hasOwn(item.content || {}, field)) item.content[field] = value; });
    }));
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
            const title = document.createElement('strong'); title.textContent = 'Suggested copy changes';
            const list = document.createElement('dl'); Object.values(payload.data.edits || {}).forEach((fields) => Object.entries(fields).forEach(([field, value]) => { const term = document.createElement('dt'); term.textContent = field.replaceAll('_', ' '); const detail = document.createElement('dd'); detail.textContent = value; list.append(term, detail); }));
            const actions = document.createElement('div'); actions.className = 'ai-page-proposal-actions';
            const apply = document.createElement('button'); apply.type = 'button'; apply.className = 'button'; apply.textContent = 'Apply suggestion'; apply.addEventListener('click', () => { applyObjectAiEdits(payload.data.edits); });
            const discard = document.createElement('button'); discard.type = 'button'; discard.className = 'button button-secondary'; discard.textContent = 'Discard'; discard.addEventListener('click', () => { proposal.remove(); status.textContent = 'Suggestion discarded. The selection is unchanged.'; });
            actions.append(apply, discard); proposal.append(title, list, actions); controls.append(proposal);
            status.textContent = `Suggestion ready from ${payload.data.provider} · ${payload.data.model}${payload.data.fallback_used ? ' (fallback model)' : ''}. Review it before applying.`;
        } catch (error) { status.textContent = error.message || 'This selection could not be revised. Try again.'; } finally { button.disabled = false; }
    };
    const deleteSelected = () => mutate(() => { if (selected.kind === 'section') { if (documentState.sections.length === 1) return; documentState.sections.splice(selected.section, 1); selected.section = Math.max(0, selected.section - 1); selected.kind = 'section'; } else if (selected.kind === 'column') { const item = currentSection(); if (item.columns.length === 1) return; item.columns.splice(selected.column, 1); selected.column = Math.max(0, selected.column - 1); } else { const item = currentColumn(); item.elements.splice(selected.element, 1); selected.element = Math.max(0, selected.element - 1); } });
    const duplicateSelected = () => mutate(() => { if (selected.kind === 'section') { const item = clone(currentSection()); item.id = id(); documentState.sections.splice(selected.section + 1, 0, item); selected.section++; } else if (selected.kind === 'column') { const item = clone(currentColumn()); item.id = id(); currentSection().columns.splice(selected.column + 1, 0, item); selected.column++; } else { const item = clone(currentElement()); item.id = id(); currentColumn().elements.splice(selected.element + 1, 0, item); selected.element++; } });
    const moveSelected = (direction) => mutate(() => { const list = selected.kind === 'section' ? documentState.sections : selected.kind === 'column' ? currentSection().columns : currentColumn().elements; const index = selected.kind === 'section' ? selected.section : selected.kind === 'column' ? selected.column : selected.element; const next = index + direction; if (next < 0 || next >= list.length) return; [list[index], list[next]] = [list[next], list[index]]; if (selected.kind === 'section') selected.section = next; else if (selected.kind === 'column') selected.column = next; else selected.element = next; });
    canvas.addEventListener('click', (event) => { const action = event.target.closest('[data-editor-action]'); if (action) { event.stopPropagation(); const sectionIndex = Number(action.dataset.section); const columnIndex = Number(action.dataset.column); if (action.dataset.editorAction === 'add-section') return mutate(() => { documentState.sections.push(section()); select('section', documentState.sections.length - 1); }); if (action.dataset.editorAction === 'add-column') return mutate(() => { documentState.sections[sectionIndex].columns.push(column()); select('column', sectionIndex, documentState.sections[sectionIndex].columns.length - 1); }); if (action.dataset.editorAction === 'add-element') { selected = {kind:'column', section:sectionIndex, column:columnIndex, element:0}; form.querySelector('[data-block-library-open]')?.click(); return; } if (action.dataset.editorAction === 'delete') return deleteSelected(); if (action.dataset.editorAction === 'duplicate') return duplicateSelected(); if (action.dataset.editorAction === 'move-up') return moveSelected(-1); if (action.dataset.editorAction === 'move-down') return moveSelected(1); }
        const target = event.target.closest('[data-select-kind]'); if (target) select(target.dataset.selectKind, Number(target.dataset.section), Number(target.dataset.column || 0), Number(target.dataset.element || 0));
    });
    form.addEventListener('click', (event) => { if (event.target.closest('[data-inspector-close]')) { event.preventDefault(); event.stopPropagation(); closeInspector(); } });
    libraryTrigger?.addEventListener('click', (event) => { event.preventDefault(); event.stopPropagation(); openLibrary(); });
    libraryBackdrop?.addEventListener('click', (event) => { event.preventDefault(); closeLibrary(); });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeInspector(); });
    form.querySelectorAll('[data-add]').forEach((button) => button.addEventListener('click', () => { const type = button.dataset.add; if (type === 'section') return mutate(() => { documentState.sections.push(section()); selected = {kind:'section',section:documentState.sections.length - 1,column:0,element:0}; }); if (type === 'grid') return mutate(() => { documentState.sections.push(gridSection(Number(button.dataset.gridCount || 3))); selected = {kind:'section',section:documentState.sections.length - 1,column:0,element:0}; }); const target = currentColumn() || documentState.sections[0].columns[0]; mutate(() => { target.elements.push(element(type)); selected = {kind:'element',section:selected.section,column:selected.column,element:target.elements.length - 1}; }); }));
    canvas.addEventListener('click', (event) => {
        const edit = event.target.closest('[data-editor-action="edit"]');
        if (edit) { event.preventDefault(); event.stopPropagation(); openInspector(); }
    });
    form.querySelectorAll('[data-add]').forEach((button) => button.addEventListener('click', openInspector));
    inspector.addEventListener('input', (event) => { const key = event.target.dataset.editorField; if (!key) return; const object = selected.kind === 'section' ? currentSection() : selected.kind === 'column' ? currentColumn() : currentElement(); const bucket = event.target.dataset.editorBucket === 'settings' ? (object.settings ||= {}) : selected.kind === 'element' ? object.content : object.settings; if (selected.kind === 'section' && key === 'grid_preset') { const count = {one:1,two:2,three:3,four:4}[event.target.value] || 1; snapshot(); while (object.columns.length < count) object.columns.push(column()); if (object.columns.length > count) object.columns.splice(count); } bucket[key] = event.target.value; save(); renderCanvas(); });
    inspector.addEventListener('change', (event) => { const responsiveKey = event.target.dataset.responsiveField; if (responsiveKey) { const object = selected.kind === 'section' ? currentSection() : selected.kind === 'column' ? currentColumn() : currentElement(); object.responsive ||= {}; object.responsive[breakpoint] ||= {}; if (event.target.value) object.responsive[breakpoint][responsiveKey] = event.target.value; else delete object.responsive[breakpoint][responsiveKey]; if (!Object.keys(object.responsive[breakpoint]).length) delete object.responsive[breakpoint]; save(); renderInspector(); return; } if (event.target.dataset.editorField) event.target.dispatchEvent(new Event('input', { bubbles:true })); });
    inspector.addEventListener('click', (event) => { const mode = event.target.closest('[data-inspector-mode]'); if (mode) { inspector.querySelectorAll('[data-inspector-mode]').forEach((button) => { const active = button === mode; button.classList.toggle('is-active', active); button.setAttribute('aria-selected', active ? 'true' : 'false'); }); inspector.querySelectorAll('[data-inspector-mode-panel]').forEach((panel) => { panel.hidden = panel.dataset.inspectorModePanel !== mode.dataset.inspectorMode; }); return; } const tab = event.target.closest('[data-inspector-tab]'); if (tab) { inspector.querySelectorAll('[data-inspector-tab]').forEach((button) => { const active = button === tab; button.classList.toggle('is-active', active); button.setAttribute('aria-selected', active ? 'true' : 'false'); }); inspector.querySelectorAll('[data-inspector-panel]').forEach((panel) => { panel.hidden = panel.dataset.inspectorPanel !== tab.dataset.inspectorTab; }); return; } const aiAsk = event.target.closest('[data-object-ai-ask]'); if (aiAsk) { requestObjectAi(aiAsk); return; } const picker = event.target.closest('[data-media-picker]'); if (picker) { openMedia(picker.dataset.mediaPicker, picker.dataset.mediaTypes ? picker.dataset.mediaTypes.split(',') : [], picker.dataset.mediaBucket || 'content'); return; } const breakpointButton = event.target.closest('[data-breakpoint]'); if (breakpointButton) { breakpoint = breakpointButton.dataset.breakpoint; renderInspector(); return; } const action = event.target.closest('[data-editor-action]'); if (!action) return; if (action.dataset.editorAction === 'delete') deleteSelected(); if (action.dataset.editorAction === 'duplicate') duplicateSelected(); if (action.dataset.editorAction === 'move-up') moveSelected(-1); if (action.dataset.editorAction === 'move-down') moveSelected(1); });
    undo.addEventListener('click', () => { if (!history.length) return; future.push(JSON.stringify(documentState)); documentState = JSON.parse(history.pop()); render(); });
    redo.addEventListener('click', () => { if (!future.length) return; history.push(JSON.stringify(documentState)); documentState = JSON.parse(future.pop()); render(); });
    form.addEventListener('ai-page-draft', (event) => { const proposal = event.detail; const plannedDocument = proposal?.document; if (plannedDocument?.version !== 2 || !Array.isArray(plannedDocument.sections) || !plannedDocument.sections.length) return; mutate(() => { documentState = clone(plannedDocument); selected = {kind:'section',section:0,column:0,element:0}; }); });
    form.addEventListener('submit', save);
    window.oneMinistryPageEditor = { get document(){ return documentState; }, get selected(){ return selected; }, addElement(type){ const target = currentColumn(); if (!target || !defaults[type]) return; mutate(() => target.elements.push(element(type))); }, save };
    render();
})();
</script>
