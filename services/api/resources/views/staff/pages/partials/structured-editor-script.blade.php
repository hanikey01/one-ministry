<script>
(() => {
    const form = document.querySelector('[data-page-block-editor]');
    if (!form) return;

    const canvas = form.querySelector('[data-canvas]');
    const canvasWrap = canvas?.closest('.page-block-canvas-wrap');
    const canvasStage = form.querySelector('[data-canvas-stage]');
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
    const names = { hero:'Hero', custom_code:'Custom code', heading:'Heading', paragraph:'Paragraph', rich_text:'Rich text', button:'Button', feature_list:'Feature list', testimonial:'Testimonial', statistics:'Statistics', alert:'Alert', progress:'Progress', table:'Table', icon:'Icon', icon_box:'Icon box', icon_list:'Icon list', image:'Image', image_card:'Image card', gallery:'Gallery', video_embed:'Video embed', audio:'Audio', file:'File download', media_text:'Media & text', list:'List', accordion:'Accordion', tabs:'Tabs', social_links:'Social links', contact_form:'Contact form', map_directions:'Map & directions', spacer:'Spacer', quote:'Quote', call_to_action:'Call to action', service_times:'Service times', events:'Events', groups:'Groups', locations:'Locations', carousel:'Carousel', giving:'Giving', prayer:'Prayer', sermon_spotlight:'Sermon spotlight', announcements:'Announcements', next_steps:'Next steps', newsletter:'Newsletter', welcome:'Welcome', custom:'Content card', live_service:'Live service', books:'Books & resources', volunteer:'Volunteer', divider:'Divider' };
    const defaults = { custom_code:{html:'<section class="custom-card"><h2>Custom content</h2><p>Add a bespoke design, then review it before publishing.</p></section>',css:'.custom-card { padding: 2rem; border-radius: 1rem; background: #f4f4f5; color: #18181b; }',javascript:'',fallback:'Custom content is available on this page.'}, hero:{eyebrow:'WELCOME',title:'A place to belong',body:'Share the invitation people should see first.',button_label:'Plan your visit',button_url:''}, heading:{text:'A meaningful heading'}, paragraph:{text:'Write a clear paragraph that helps people understand your ministry.'}, rich_text:{title:'Tell your story',text:'Use clear, readable paragraphs to help people understand what matters.'}, button:{label:'Learn more',url:''}, feature_list:{title:'What you can expect',items:'A warm welcome\nHelpful people\nA clear next step'}, testimonial:{quote:'A short story of encouragement and connection.',name:'Name',role:'Community member'}, statistics:{title:'Our ministry at a glance',stat_one_value:'1',stat_one_label:'Community',stat_two_value:'2',stat_two_label:'Locations',stat_three_value:'3',stat_three_label:'Ways to connect'}, alert:{tone:'info',title:'Helpful information',body:'Use this callout to share an important update.'}, progress:{title:'Progress update',label:'Completed',value:'60'}, table:{title:'Weekly schedule',headers:'Day|Time|Location',rows:'Sunday|10:00 AM|Main auditorium\nWednesday|7:00 PM|Community room'}, icon:{icon:'heart',label:'You are welcome here'}, icon_box:{icon:'people',title:'Find community',body:'Connect with people and take a next step.',button_label:'Learn more',button_url:''}, icon_list:{title:'What you will find',items:'A warm welcome\nPractical support\nA place to belong'}, image:{url:'',alt:'',caption:''}, image_card:{url:'',alt:'',title:'A meaningful image',body:'Pair a visual with a clear message.',button_label:'Learn more',button_url:''}, gallery:{images:'',columns:'three',caption:''}, video_embed:{url:'',title:'A message to watch',caption:''}, audio:{url:'',title:'Listen now',caption:''}, file:{url:'',label:'Download resource',description:''}, media_text:{media_url:'',media_alt:'',placement:'left',title:'A message beside your media',body:'Use this layout to combine a visual with a clear invitation.',button_label:'Learn more',button_url:''}, list:{title:'What to expect',items:'A warm welcome\nHelpful information\nA clear next step',ordered:'no'}, accordion:{title:'Frequently asked questions',question_one:'What should I expect?',answer_one:'Add a helpful, reassuring answer.',question_two:'Where do I park?',answer_two:'Share the practical details visitors need.',question_three:'Is there something for children?',answer_three:'Explain the ministry options available.'}, tabs:{title:'Explore more',tab_one_label:'First step',tab_one_content:'Share the first helpful detail.',tab_two_label:'What to expect',tab_two_content:'Share practical information for visitors.',tab_three_label:'Stay connected',tab_three_content:'Explain the next step.'}, social_links:{title:'Stay connected',facebook_url:'',instagram_url:'',youtube_url:''}, contact_form:{title:'Get in touch',body:'Share your details and our team can follow up.',submit_label:'Send request'}, map_directions:{title:'Find us',address:'Add the address visitors should use.',directions_url:'',button_label:'Get directions'}, spacer:{height:'medium'}, quote:{text:'A short quote or testimony.',attribution:''}, call_to_action:{title:'Take your next step',body:'Give people a clear way to respond.',button_label:'Get connected',button_url:''}, service_times:{title:'Join us this week',body:'Add a helpful invitation alongside your service times.'}, events:{title:'What is happening next',body:'Share upcoming events and ways to get involved.'}, groups:{title:'Find your community',body:'Invite people into groups and meaningful connection.'}, locations:{title:'Find us',body:'Help people find a location and plan your visit.',button_label:'View locations',button_url:'/locations'}, giving:{title:'Support the ministry',body:'Make it simple to give safely and confidently.',button_label:'Give now',button_url:'/give'}, prayer:{title:'How can we pray for you?',body:'Share a prayer request with our ministry team.',button_label:'Request prayer',button_url:''}, announcements:{title:'Latest announcements',body:'Share the news people need to know.'}, next_steps:{title:'Take your next step',body:'Help people find a clear next step.'}, newsletter:{title:'Stay connected',body:'Invite people to receive ministry updates.'}, carousel:{eyebrow:'FEATURED',title:'A featured moment',body:'Use short slides to highlight a message, event, or next step.',slides:'A practical welcome for every visitor\nA simple way to take your next step',button_label:'Learn more',button_url:''}, sermon_spotlight:{title:'Featured teaching',body:'Highlight a message that helps people take a next step.',button_label:'Watch message',button_url:''}, welcome:{title:'There is room for you',body:'Offer a warm, personal welcome to everyone visiting this page.'}, custom:{title:'Content card',body:'Add a focused message that fits this page.',button_label:'Learn more',button_url:''}, live_service:{title:'Join us online',body:'Watch and participate in our live service.',button_label:'Watch now',button_url:''}, books:{title:'Books and resources',body:'Explore trusted resources for your journey.',button_label:'Browse resources',button_url:''}, volunteer:{title:'Serve with purpose',body:'Find a place to use your gifts and serve.',button_label:'Start serving',button_url:''}, divider:{} };
    names.html = 'HTML';
    defaults.html = { html:'<section><h2>Custom content</h2><p>Add your HTML here.</p></section>', css:'', javascript:'' };
    const column = () => ({ id:id(), settings:{}, responsive:{}, elements:[] });
    const customCodeLibraryButton = document.createElement('button');
    customCodeLibraryButton.type = 'button';
    customCodeLibraryButton.textContent = 'HTML';
    customCodeLibraryButton.addEventListener('click', () => {
        const target = currentColumn() || documentState.sections[0]?.columns[0];
        if (!target) return;
        mutate(() => { target.elements.push(element('html')); selectionActive = true; selected = {kind:'element',section:selected.section,column:selected.column,element:target.elements.length - 1}; });
        openInspector();
    });
    libraryPanel?.querySelector('.page-block-library-group')?.append(customCodeLibraryButton);
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
    let selectionActive = false;
    let breakpoint = 'desktop';
    let inspectorTab = 'content';
    let history = [];
    let future = [];
    const syncCanvasPreview = () => {
        if (!canvas || !canvasWrap || !canvasStage) return;
        const previewWidth = { desktop:1180, tablet:768, mobile:390 }[canvas.dataset.preview || 'desktop'] || 1180;
        const availableWidth = Math.max(0, canvasWrap.clientWidth - 24);
        const scale = previewWidth > availableWidth && availableWidth > 0 ? availableWidth / previewWidth : 1;
        canvasWrap.classList.toggle('is-scaled-preview', scale < 1);
        canvasWrap.style.setProperty('--canvas-preview-scale', String(scale));
        canvasStage.style.height = `${Math.ceil(canvas.offsetHeight * scale)}px`;
    };
    const currentSection = () => documentState.sections[selected.section];
    const currentColumn = () => currentSection()?.columns[selected.column];
    const currentElement = () => currentColumn()?.elements[selected.element];
    const save = () => { output.value = JSON.stringify(documentState); };
    const snapshot = () => { history.push(JSON.stringify(documentState)); future = []; syncHistory(); };
    const syncHistory = () => { undo.disabled = !history.length; redo.disabled = !future.length; };
    const openInspector = () => {
        selectionPopover.hidden = true;
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
        requestAnimationFrame(renderSelectionPopover);
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
    const select = (kind, sectionIndex, columnIndex = 0, elementIndex = 0) => { selected = {kind, section:sectionIndex, column:columnIndex, element:elementIndex}; selectionActive = true; render(); };
    const previewLines = (value, limit = 3) => String(value || '').split(/\r?\n/).map((line) => line.trim()).filter(Boolean).slice(0, limit);
    const previewAction = (label) => label ? `<b>${escape(label)}</b>` : '';
    const modulePreview = (type, content) => `<div class="page-canvas-module"><small>${escape(names[type] || type).toUpperCase()}</small><h2>${escape(content.title || '')}</h2><p>${escape(content.body || '')}</p>${previewAction(content.button_label)}</div>`;
    const elementPreview = (item) => {
        const content = item.content || {};
        if (item.type === 'hero') return `<div class="page-canvas-hero"><small>${escape(content.eyebrow)}</small><h1>${escape(content.title)}</h1><p>${escape(content.body)}</p>${previewAction(content.button_label)}</div>`;
        if (item.type === 'heading') return `<h2>${escape(content.text)}</h2>`;
        if (item.type === 'paragraph') return `<p>${escape(content.text)}</p>`;
        if (item.type === 'rich_text') return `<div class="page-canvas-element-summary"><strong>${escape(content.title)}</strong><p>${escape(content.text)}</p></div>`;
        if (item.type === 'button') return `<div class="page-canvas-cta">${previewAction(content.label)}</div>`;
        if (item.type === 'feature_list' || item.type === 'list') return `<div class="page-canvas-element-summary"><strong>${escape(content.title)}</strong><ul>${previewLines(content.items).map((line) => `<li>${escape(line)}</li>`).join('')}</ul></div>`;
        if (item.type === 'testimonial') return `<blockquote class="page-canvas-element-summary">“${escape(content.quote)}”<span>${escape(content.name)}${content.role ? ` · ${escape(content.role)}` : ''}</span></blockquote>`;
        if (item.type === 'statistics') return `<div class="page-canvas-module"><small>STATISTICS</small><h2>${escape(content.title)}</h2><p>${escape(content.stat_one_value)} · ${escape(content.stat_two_value)} · ${escape(content.stat_three_value)}</p></div>`;
        if (['custom_code','html'].includes(item.type)) return `<div class="page-canvas-element-summary"><strong>${item.type === 'html' ? 'HTML' : 'Custom code'}</strong><span>${content.html ? 'HTML ready' : 'Add HTML'}${item.type === 'custom_code' ? ` · ${content.css ? 'CSS ready' : 'No CSS'} · ${content.javascript ? 'JavaScript ready' : 'No JavaScript'}` : ''}</span></div>`;
        if (item.type === 'alert') return `<div class="page-canvas-element-summary"><strong>${escape(content.title)}</strong><p>${escape(content.body)}</p><span>${escape(content.tone || 'info')} alert</span></div>`;
        if (item.type === 'progress') return `<div class="page-canvas-element-summary"><strong>${escape(content.title)}</strong><span>${escape(content.label)} · ${escape(content.value)}%</span><progress max="100" value="${escape(content.value)}"></progress></div>`;
        if (item.type === 'table') return `<div class="page-canvas-element-summary"><strong>${escape(content.title)}</strong><span>${previewLines(content.headers, 1)[0] || 'Table headings'} · ${previewLines(content.rows).length} row${previewLines(content.rows).length === 1 ? '' : 's'}</span></div>`;
        if (item.type === 'icon') return `<div class="page-canvas-element-summary"><strong>${escape(content.icon)}</strong><span>${escape(content.label)}</span></div>`;
        if (item.type === 'icon_box') return `<div class="page-canvas-module"><small>${escape(content.icon).toUpperCase()}</small><h2>${escape(content.title)}</h2><p>${escape(content.body)}</p>${previewAction(content.button_label)}</div>`;
        if (item.type === 'icon_list') return `<div class="page-canvas-element-summary"><strong>${escape(content.title)}</strong><ul>${previewLines(content.items).map((line) => `<li>✓ ${escape(line)}</li>`).join('')}</ul></div>`;
        if (item.type === 'image') return content.url ? `<img src="${escape(content.url)}" alt="${escape(content.alt)}">` : '<div class="page-canvas-image-placeholder">Image</div>';
        if (item.type === 'image_card') return `<div class="page-canvas-cta"><small>${content.url ? 'IMAGE SELECTED' : 'IMAGE CARD'}</small><h2>${escape(content.title)}</h2><p>${escape(content.body)}</p>${previewAction(content.button_label)}</div>`;
        if (item.type === 'gallery') { const images = previewLines(content.images, 4); return `<div class="page-canvas-element-summary"><strong>Gallery</strong><span>${images.length ? `${images.length} selected image${images.length === 1 ? '' : 's'}` : 'Choose up to 12 images'}</span></div>`; }
        if (item.type === 'video_embed') return `<div class="page-canvas-element-summary"><strong>${escape(content.title || 'Video')}</strong><span>${content.url ? 'Video source selected' : 'Choose a supported video'}</span></div>`;
        if (item.type === 'audio') return `<div class="page-canvas-element-summary"><strong>${escape(content.title || 'Audio')}</strong><span>${content.url ? 'Audio source selected' : 'Choose an audio file'}</span></div>`;
        if (item.type === 'file') return `<div class="page-canvas-element-summary"><strong>${escape(content.label || 'Download resource')}</strong><span>${escape(content.description || (content.url ? 'File selected' : 'Choose a file'))}</span></div>`;
        if (item.type === 'media_text') return `<div class="page-canvas-cta"><small>${content.media_url ? 'MEDIA SELECTED' : 'MEDIA'}</small><h2>${escape(content.title)}</h2><p>${escape(content.body)}</p>${previewAction(content.button_label)}</div>`;
        if (item.type === 'accordion') return `<div class="page-canvas-element-summary"><strong>${escape(content.title)}</strong><span>${escape(content.question_one)}</span><span>${escape(content.question_two)}</span><span>${escape(content.question_three)}</span></div>`;
        if (item.type === 'tabs') return `<div class="page-canvas-element-summary"><strong>${escape(content.title)}</strong><span>${escape(content.tab_one_label)} · ${escape(content.tab_two_label)} · ${escape(content.tab_three_label)}</span><p>${escape(content.tab_one_content)}</p></div>`;
        if (item.type === 'social_links') return `<div class="page-canvas-element-summary"><strong>${escape(content.title)}</strong><span>${['Facebook', 'Instagram', 'YouTube'].filter((label, index) => [content.facebook_url, content.instagram_url, content.youtube_url][index]).join(' · ') || 'Add social destinations'}</span></div>`;
        if (item.type === 'contact_form') return `<div class="page-canvas-module"><small>CONTACT FORM</small><h2>${escape(content.title)}</h2><p>${escape(content.body)}</p>${previewAction(content.submit_label)}</div>`;
        if (item.type === 'map_directions') return `<div class="page-canvas-module"><small>MAP & DIRECTIONS</small><h2>${escape(content.title)}</h2><p>${escape(content.address)}</p>${previewAction(content.button_label)}</div>`;
        if (item.type === 'spacer') return `<div class="page-canvas-element-summary"><strong>Spacer</strong><span>${escape(content.height || 'medium')} spacing</span></div>`;
        if (item.type === 'quote') return `<blockquote class="page-canvas-element-summary">“${escape(content.text)}”<span>${escape(content.attribution)}</span></blockquote>`;
        if (item.type === 'divider') return '<hr class="public-page-divider">';
        if (item.type === 'call_to_action') return `<div class="page-canvas-cta"><h2>${escape(content.title)}</h2><p>${escape(content.body)}</p>${previewAction(content.button_label)}</div>`;
        if (item.type === 'carousel') return `<div class="page-canvas-module"><small>${escape(content.eyebrow || 'FEATURED')}</small><h2>${escape(content.title)}</h2><p>${escape(content.body)}</p><span>${previewLines(content.slides).length || 0} slide${previewLines(content.slides).length === 1 ? '' : 's'}</span>${previewAction(content.button_label)}</div>`;
        if (['service_times','events','groups','locations','giving','prayer','sermon_spotlight','announcements','next_steps','newsletter','welcome','custom','live_service','books','volunteer'].includes(item.type)) return modulePreview(item.type, content);
        return `<div class="page-canvas-element-summary"><strong>${escape(names[item.type] || item.type)}</strong><span>${escape(content.title || content.text || content.label || 'Configure this element')}</span></div>`;
    };
    const structureButtons = (kind) => `<div class="page-structure-actions" aria-label="${kind} actions">${kind === 'section' ? '<button type="button" class="page-structure-icon" data-editor-action="add-column" aria-label="Add column" title="Add column"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg></button>' : ''}<button type="button" class="page-structure-icon" data-editor-action="move-up" aria-label="Move up" title="Move up"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" aria-hidden="true"><path d="m6 14 6-6 6 6"/></svg></button><button type="button" class="page-structure-icon" data-editor-action="move-down" aria-label="Move down" title="Move down"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" aria-hidden="true"><path d="m6 10 6 6 6-6"/></svg></button><button type="button" class="page-structure-icon" data-editor-action="duplicate" aria-label="Duplicate" title="Duplicate"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" aria-hidden="true"><rect x="8" y="8" width="10" height="10" rx="1.5"/><path d="M6 16H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1"/></svg></button><button type="button" class="page-structure-icon is-danger" data-editor-action="delete" aria-label="Delete" title="Delete"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" aria-hidden="true"><path d="M5 7h14M10 11v6M14 11v6M9 7V5h6v2M7 7l1 13h8l1-13"/></svg></button><button type="button" class="page-structure-icon" data-editor-action="edit" aria-label="Edit" title="Edit"><svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" aria-hidden="true"><path d="m4 16-.7 4.7L8 20l10.8-10.8a2.1 2.1 0 0 0-3-3Z"/><path d="m14.5 7.5 2 2"/></svg></button></div>`;
    const selectionPopover = document.createElement('div');
    selectionPopover.className = 'page-canvas-action-overlay';
    selectionPopover.hidden = true;
    canvasWrap?.append(selectionPopover);
    const renderSelectionPopover = () => {
        if (!selectionActive) { selectionPopover.hidden = true; return; }
        const target = canvas.querySelector(':is(.page-canvas-section-object, .page-canvas-column-object, .page-canvas-element-object).is-selected');
        if (!target) { selectionPopover.hidden = true; return; }
        selectionPopover.innerHTML = structureButtons(selected.kind);
        selectionPopover.hidden = false;
        const targetBox = target.getBoundingClientRect();
        const wrapBox = canvasWrap.getBoundingClientRect();
        const width = selectionPopover.offsetWidth;
        selectionPopover.style.left = `${Math.max(8, Math.min(targetBox.right - wrapBox.left - width - 8, wrapBox.width - width - 8))}px`;
        selectionPopover.style.top = `${Math.max(8, targetBox.top - wrapBox.top + 5)}px`;
    };
    const renderCanvas = () => {
        canvas.innerHTML = documentState.sections.map((item, sectionIndex) => `<section class="page-canvas-section-object ${selected.kind==='section'&&selected.section===sectionIndex?'is-selected':''}" data-select-kind="section" data-section="${sectionIndex}" tabindex="0" aria-label="Select section ${sectionIndex + 1}"><header class="page-canvas-object-header"><span>Section ${sectionIndex + 1}</span><strong>${escape(item.settings?.title || 'Section')}</strong></header><div class="page-canvas-structure-columns">${item.columns.map((itemColumn, columnIndex) => `<div class="page-canvas-column-object ${selected.kind==='column'&&selected.section===sectionIndex&&selected.column===columnIndex?'is-selected':''}" data-select-kind="column" data-section="${sectionIndex}" data-column="${columnIndex}" tabindex="0" aria-label="Select column ${columnIndex + 1}"><header><span>Column ${columnIndex + 1}</span></header><div class="page-canvas-column-elements">${itemColumn.elements.map((itemElement, elementIndex) => `<article class="page-canvas-element-object ${selected.kind==='element'&&selected.section===sectionIndex&&selected.column===columnIndex&&selected.element===elementIndex?'is-selected':''}" data-select-kind="element" data-section="${sectionIndex}" data-column="${columnIndex}" data-element="${elementIndex}" tabindex="0" aria-label="Select ${escape(names[itemElement.type] || itemElement.type)} element"><header><span>${escape(names[itemElement.type] || itemElement.type)}</span></header><div class="page-canvas-block-content">${elementPreview(itemElement)}</div></article>`).join('')}<button type="button" class="page-canvas-insert" data-editor-action="add-element" data-section="${sectionIndex}" data-column="${columnIndex}">+ Add element</button></div></div>`).join('')}</div></section>`).join('') + '<button type="button" class="page-canvas-insert page-canvas-add-section" data-editor-action="add-section">+ Add section</button>';
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
    const spacingUnits = [['px','px'],['%','%'],['em','em'],['rem','rem'],['vw','vw'],['vh','vh']];
    const unitOf = (value, fallback = 'px') => String(value || '').match(/[a-z%]+$/i)?.[0] || fallback;
    const numericOf = (value) => String(value || '').replace(/[a-z%]+$/i, '');
    const unitSelect = (unit) => `<select data-spacing-unit>${spacingUnits.map(([value,label]) => `<option value="${value}" ${unit===value?'selected':''}>${label}</option>`).join('')}</select>`;
    const spacingField = (object, key, label) => { const value = object.responsive?.[breakpoint]?.[key]; const sides = typeof value === 'object' && value ? value : {}; const unit = unitOf(Object.values(sides)[0]); return `<fieldset class="page-spacing-field"><legend>${label}</legend><div class="page-spacing-unit">${unitSelect(unit)}</div><div>${['top','right','bottom','left'].map((side) => `<label>${side}<input inputmode="decimal" placeholder="inherit" data-spacing-field="${key}" data-spacing-side="${side}" value="${escape(numericOf(sides[side]))}"></label>`).join('')}</div></fieldset>`; };
    const responsiveControls = (object) => {
        const common = [['visibility','Visibility',[['visible','Visible'],['hidden','Hidden']]], ['alignment','Alignment',[['left','Left'],['center','Centre'],['right','Right'],['stretch','Stretch']]], ['padding','Padding',[['compact','Compact'],['regular','Regular'],['spacious','Spacious']]], ['margin','Margin',[['none','None'],['compact','Compact'],['regular','Regular'],['spacious','Spacious']]]];
        const fields = selected.kind === 'section'
            ? [...common, ['column_count','Columns',[['1','One'],['2','Two'],['3','Three'],['4','Four']]], ['stack','Column flow',[['inline','Keep columns inline'],['stack','Stack columns']]], ['column_gap','Column gap',[['compact','Compact'],['regular','Regular'],['spacious','Spacious']]]]
            : selected.kind === 'column'
                ? [...common, ['width','Column width',[['full','Full'],['half','Half'],['third','One third']]], ['order','Order',[['1','First'],['2','Second'],['3','Third'],['4','Fourth']]], ['gap','Element gap',[['compact','Compact'],['regular','Regular'],['spacious','Spacious']]]]
                : [...common, ['order','Order',[['1','First'],['2','Second'],['3','Third'],['4','Fourth']]]];
        return `<section class="page-responsive-controls"><div class="page-responsive-heading"><strong>Responsive</strong><div class="page-breakpoint-tabs" role="tablist">${['desktop','tablet','mobile'].map((item) => `<button type="button" data-breakpoint="${item}" class="${breakpoint===item?'is-active':''}" aria-selected="${breakpoint===item}">${item[0].toUpperCase()+item.slice(1)}</button>`).join('')}</div></div><div class="page-object-control-grid">${fields.map(([key,label,options]) => ['padding','margin'].includes(key) ? spacingField(object, key, label) : responsiveField(object, key, label, options)).join('')}</div></section>`;
    };
    const moveElementControl = () => {
        const columns = currentSection()?.columns || [];
        if (selected.kind !== 'element' || columns.length < 2) return '';
        return `<section class="page-object-move"><strong>Move element</strong><label>Destination column<select data-move-element-to>${columns.map((item, index) => `<option value="${index}" ${index===selected.column?'selected':''}>Column ${index + 1}${index===selected.column?' (current)':''}</option>`).join('')}</select></label><small>Move this element to another column in the selected section.</small></section>`;
    };
    const objectAiControls = () => `<section class="page-object-ai"><strong>AI assist</strong><p>AI receives the full page for context and can suggest copy changes only for this selected ${selected.kind}.</p><textarea data-object-ai-instruction rows="3" minlength="10" maxlength="1500" placeholder="Describe what you want changed…" ${aiAvailable ? '' : 'disabled'}></textarea><button class="button" type="button" data-object-ai-ask ${aiAvailable ? '' : 'disabled'}>${aiAvailable ? 'Suggest changes' : 'AI route unavailable'}</button><p class="page-object-ai-status" data-object-ai-status role="status" aria-live="polite">${aiAvailable ? '' : 'An administrator must verify an AI page-editor route first.'}</p></section>`;
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
        const colorField = (key, label, value) => { const valid = /^#[0-9a-f]{6}$/i.test(String(value || '')); return `<label>${label}<input type="color" data-editor-field="${key}" data-editor-bucket="settings" value="${valid ? value : '#ffffff'}"></label>`; };
        const styleNumberField = (key, label, value) => input(key, label, value || '', false, 'settings');
        const radiusField = () => { const sides = [['top_left','Top'],['top_right','Right'],['bottom_left','Left'],['bottom_right','Bottom']]; const unit = unitOf(object.settings?.radius_top_left); return `<fieldset class="page-spacing-field page-radius-field"><legend>Corner radius</legend><div class="page-spacing-unit">${unitSelect(unit)}</div><div>${sides.map(([side,label]) => `<label>${label}<input inputmode="decimal" placeholder="0" data-radius-field="radius_${side}" value="${escape(numericOf(object.settings?.[`radius_${side}`]))}"></label>`).join('')}</div></fieldset>`; };
        const styleDetailControls = () => `<section class="page-style-details"><strong>Detailed style</strong><datalist id="page-style-units"><option value="0px"><option value="1%"><option value="1em"><option value="1rem"><option value="1vw"><option value="1vh"></datalist><div class="page-object-control-grid">${colorField('background_color','Background color',object.settings?.background_color || '')}${colorField('border_color','Border color',object.settings?.border_color || '')}${selectField('border_style','Border style',object.settings?.border_style || 'solid',[['none','None'],['solid','Solid'],['dashed','Dashed'],['dotted','Dotted']],'settings')}${styleNumberField('border_width','Border width',object.settings?.border_width)}${radiusField()}${styleNumberField('shadow_x','Shadow X',object.settings?.shadow_x)}${styleNumberField('shadow_y','Shadow Y',object.settings?.shadow_y)}${styleNumberField('shadow_blur','Shadow blur',object.settings?.shadow_blur)}${styleNumberField('shadow_spread','Shadow spread',object.settings?.shadow_spread)}${colorField('shadow_color','Shadow color',object.settings?.shadow_color || '')}</div></section>`;
        const appearanceControls = () => `<section class="page-object-appearance"><strong>Appearance</strong><div class="page-object-control-grid">${mediaField('background_image', 'Background image', object.settings?.background_image || '', object.type || 'section', 'settings')}${selectField('overflow', 'Content overflow', object.settings?.overflow || 'visible', [['visible','Visible'],['hidden','Hidden']], 'settings')}</div>${styleDetailControls()}</section>`;
        const customCodeControls = () => ['custom_code','html'].includes(object.type) ? `<section class="page-object-custom-code"><strong>Custom CSS and JavaScript</strong><p>These fields run only inside this element’s isolated public sandbox. They cannot access the ministry application, forms, or network.</p>${input('css', 'Custom CSS', object.content?.css || '', true)}${input('javascript', 'Custom JavaScript', object.content?.javascript || '', true)}</section>` : '';
        const elementCustomControls = () => selected.kind === 'element' ? `<section class="page-object-custom-code"><strong>Element identity and code</strong><p>Use these fields to target this element in custom CSS. Code is reviewed before publishing.</p>${input('custom_id', 'CSS ID', object.settings?.custom_id || '', false, 'settings')}${input('custom_class', 'CSS classes', object.settings?.custom_class || '', false, 'settings')}${!['custom_code','html'].includes(object.type) ? `${input('custom_css', 'Custom CSS', object.settings?.custom_css || '', true, 'settings')}${input('custom_js', 'Custom JavaScript', object.settings?.custom_js || '', true, 'settings')}` : ''}</section>` : '';
        if (selected.kind === 'section') content = input('title', 'Section title', object.settings?.title || '') + input('body', 'Introduction', object.settings?.body || '', true) + selectField('content_width', 'Content width', object.settings?.content_width || 'standard', [['full','Full width'],['wide','Wide'],['standard','Standard'],['narrow','Narrow']]) + selectField('grid_preset', 'Grid preset', object.settings?.grid_preset || (object.columns.length === 1 ? 'one' : object.columns.length === 2 ? 'two' : object.columns.length === 4 ? 'four' : 'three'), [['one','One column'],['two','Two columns'],['three','Three columns'],['four','Four columns']]) + selectField('padding', 'Section spacing', object.settings?.padding || 'regular', [['compact','Compact'],['regular','Regular'],['spacious','Spacious']]);
        if (selected.kind === 'column') content = selectField('alignment', 'Content alignment', object.settings?.alignment || 'left', [['left','Left'],['center','Centre'],['right','Right']]);
        if (selected.kind === 'element') content = Object.entries(object.content || {}).map(([key, value]) => {
            const label = key.replaceAll('_',' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
            if (['custom_code','html'].includes(object.type) && ['css','javascript'].includes(key)) return '';
            if (['custom_code','html'].includes(object.type)) return input(key, label, value, true);
            if (['media_url','images'].includes(key) || (key === 'url' && object.type !== 'button')) return mediaField(key, key === 'media_url' ? 'Media' : key === 'images' ? 'Gallery images' : 'Media source', value, object.type);
            if (key === 'tone') return selectField(key, label, value, [['info','Information'],['success','Success'],['warning','Warning']]);
            if (key === 'icon') return selectField(key, label, value, [['heart','Heart'],['people','People'],['calendar','Calendar'],['location','Location'],['message','Message'],['book','Book'],['star','Star'],['check','Check'],['hand-heart','Care'],['globe','Globe']]);
            return input(key, label, value, ['body','text','caption','description','items','rows','answer_one','answer_two','answer_three','slides','quote','tab_one_content','tab_two_content','tab_three_content'].includes(key));
        }).join('');
        inspector.innerHTML = `<div class="page-inspector-selected"><span>${escape(heading)}</span><strong>${selected.kind === 'element' ? 'Element settings' : selected.kind[0].toUpperCase() + selected.kind.slice(1) + ' settings'}</strong></div><div class="page-object-tabs" role="tablist" aria-label="Block settings"><button type="button" class="${inspectorTab === 'content' ? 'is-active' : ''}" aria-selected="${inspectorTab === 'content'}" data-inspector-tab="content">Content</button><button type="button" class="${inspectorTab === 'style' ? 'is-active' : ''}" aria-selected="${inspectorTab === 'style'}" data-inspector-tab="style">Style</button><button type="button" class="${inspectorTab === 'advanced' ? 'is-active' : ''}" aria-selected="${inspectorTab === 'advanced'}" data-inspector-tab="advanced">Advanced</button></div><section class="page-object-tab-panel" data-inspector-panel="content" ${inspectorTab === 'content' ? '' : 'hidden'}><div class="page-object-control-grid">${content}</div>${moveElementControl()}${objectAiControls()}</section><section class="page-object-tab-panel" data-inspector-panel="style" ${inspectorTab === 'style' ? '' : 'hidden'}>${appearanceControls()}</section><section class="page-object-tab-panel" data-inspector-panel="advanced" ${inspectorTab === 'advanced' ? '' : 'hidden'}>${responsiveControls(object)}${elementCustomControls()}${customCodeControls()}</section>`;
        window.initializeEnhancedSelects?.(inspector);
    };
    const render = () => { save(); renderCanvas(); renderInspector(); syncHistory(); requestAnimationFrame(() => { syncCanvasPreview(); renderSelectionPopover(); }); };
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
    selectionPopover.addEventListener('click', (event) => {
        const action = event.target.closest('[data-editor-action]');
        if (!action) return;
        event.preventDefault();
        event.stopPropagation();
        performEditorAction(action.dataset.editorAction);
    });
    canvas.addEventListener('click', (event) => { const canvasSize = event.target.closest('[data-canvas-size]'); if (canvasSize) { event.preventDefault(); event.stopPropagation(); const size = canvasSize.dataset.canvasSize; canvas.dataset.preview = size; canvas.parentElement?.dataset && (canvas.parentElement.dataset.preview = size); canvas.closest('[data-canvas-toolbar]')?.querySelectorAll('[data-canvas-size]').forEach((button) => { const active = button === canvasSize; button.classList.toggle('is-active', active); button.setAttribute('aria-pressed', active ? 'true' : 'false'); }); requestAnimationFrame(syncCanvasPreview); return; } const action = event.target.closest('[data-editor-action]'); if (action) { event.stopPropagation(); const sectionIndex = Number.isFinite(Number(action.dataset.section)) ? Number(action.dataset.section) : selected.section; const columnIndex = Number.isFinite(Number(action.dataset.column)) ? Number(action.dataset.column) : selected.column; if (action.dataset.editorAction === 'add-section') return mutate(() => { documentState.sections.push(section()); select('section', documentState.sections.length - 1); }); if (action.dataset.editorAction === 'add-column') return mutate(() => { documentState.sections[sectionIndex].columns.push(column()); select('column', sectionIndex, documentState.sections[sectionIndex].columns.length - 1); }); if (action.dataset.editorAction === 'add-element') { selectionActive = true; selected = {kind:'column', section:sectionIndex,column:columnIndex,element:0}; form.querySelector('[data-block-library-open]')?.click(); return; } if (action.dataset.editorAction === 'delete') return deleteSelected(); if (action.dataset.editorAction === 'duplicate') return duplicateSelected(); if (action.dataset.editorAction === 'move-up') return moveSelected(-1); if (action.dataset.editorAction === 'move-down') return moveSelected(1); }
        const target = event.target.closest('[data-select-kind]'); if (target) select(target.dataset.selectKind, Number(target.dataset.section), Number(target.dataset.column || 0), Number(target.dataset.element || 0));
    });
    canvas.addEventListener('keydown', (event) => { if (!['Enter', ' '].includes(event.key)) return; const target = event.target.closest('[data-select-kind]'); if (!target) return; event.preventDefault(); select(target.dataset.selectKind, Number(target.dataset.section), Number(target.dataset.column || 0), Number(target.dataset.element || 0)); });
    canvasWrap.addEventListener('click', (event) => { const button = event.target.closest('[data-canvas-size]'); if (!button) return; event.preventDefault(); event.stopPropagation(); const size = button.dataset.canvasSize; canvas.dataset.preview = size; canvasWrap.dataset.preview = size; canvasWrap.querySelectorAll('[data-canvas-size]').forEach((item) => { const active = item === button; item.classList.toggle('is-active', active); item.setAttribute('aria-pressed', active ? 'true' : 'false'); }); requestAnimationFrame(syncCanvasPreview); });
    form.addEventListener('click', (event) => { if (event.target.closest('[data-inspector-close]')) { event.preventDefault(); event.stopPropagation(); closeInspector(); } });
    libraryTrigger?.addEventListener('click', (event) => { event.preventDefault(); event.stopPropagation(); openLibrary(); });
    libraryBackdrop?.addEventListener('click', (event) => { event.preventDefault(); closeLibrary(); });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeInspector(); });
    form.querySelectorAll('[data-add]').forEach((button) => button.addEventListener('click', () => { const type = button.dataset.add; if (type === 'section') return mutate(() => { documentState.sections.push(section()); selectionActive = true; selected = {kind:'section',section:documentState.sections.length - 1,column:0,element:0}; }); if (type === 'grid') return mutate(() => { documentState.sections.push(gridSection(Number(button.dataset.gridCount || 3))); selectionActive = true; selected = {kind:'section',section:documentState.sections.length - 1,column:0,element:0}; }); const target = currentColumn() || documentState.sections[0].columns[0]; mutate(() => { target.elements.push(element(type)); selectionActive = true; selected = {kind:'element',section:selected.section,column:selected.column,element:target.elements.length - 1}; }); }));
    form.querySelectorAll('[data-add]').forEach((button) => button.addEventListener('click', openInspector));
    inspector.addEventListener('input', (event) => { const key = event.target.dataset.editorField; if (!key) return; const object = selected.kind === 'section' ? currentSection() : selected.kind === 'column' ? currentColumn() : currentElement(); const bucket = event.target.dataset.editorBucket === 'settings' ? (object.settings ||= {}) : selected.kind === 'element' ? object.content : object.settings; if (selected.kind === 'section' && key === 'grid_preset') { const count = {one:1,two:2,three:3,four:4}[event.target.value] || 1; snapshot(); while (object.columns.length < count) object.columns.push(column()); if (object.columns.length > count) object.columns.splice(count); } bucket[key] = event.target.value; save(); renderCanvas(); });
    inspector.addEventListener('change', (event) => { const moveTarget = event.target.dataset.moveElementTo; if (moveTarget !== undefined) { const targetColumn = Number(moveTarget); const section = currentSection(); if (!Number.isInteger(targetColumn) || !section?.columns[targetColumn] || targetColumn === selected.column) return; mutate(() => { const source = currentColumn(); const [moved] = source.elements.splice(selected.element, 1); if (!moved) return; section.columns[targetColumn].elements.push(moved); selected.column = targetColumn; selected.element = section.columns[targetColumn].elements.length - 1; }); return; } const spacingKey = event.target.dataset.spacingField; if (spacingKey) { const object = selected.kind === 'section' ? currentSection() : selected.kind === 'column' ? currentColumn() : currentElement(); object.responsive ||= {}; object.responsive[breakpoint] ||= {}; const box = object.responsive[breakpoint][spacingKey] && typeof object.responsive[breakpoint][spacingKey] === 'object' ? object.responsive[breakpoint][spacingKey] : {}; box[event.target.dataset.spacingSide] = event.target.value.trim(); if (Object.values(box).every((value) => !value)) delete object.responsive[breakpoint][spacingKey]; else object.responsive[breakpoint][spacingKey] = box; if (!Object.keys(object.responsive[breakpoint]).length) delete object.responsive[breakpoint]; save(); return; } const responsiveKey = event.target.dataset.responsiveField; if (responsiveKey) { const object = selected.kind === 'section' ? currentSection() : selected.kind === 'column' ? currentColumn() : currentElement(); object.responsive ||= {}; object.responsive[breakpoint] ||= {}; if (event.target.value) object.responsive[breakpoint][responsiveKey] = event.target.value; else delete object.responsive[breakpoint][responsiveKey]; if (!Object.keys(object.responsive[breakpoint]).length) delete object.responsive[breakpoint]; save(); renderInspector(); return; } if (event.target.dataset.editorField) event.target.dispatchEvent(new Event('input', { bubbles:true })); });
    inspector.addEventListener('click', (event) => { const tab = event.target.closest('[data-inspector-tab]'); if (tab) { inspectorTab = tab.dataset.inspectorTab; inspector.querySelectorAll('[data-inspector-tab]').forEach((button) => { const active = button === tab; button.classList.toggle('is-active', active); button.setAttribute('aria-selected', active ? 'true' : 'false'); }); inspector.querySelectorAll('[data-inspector-panel]').forEach((panel) => { panel.hidden = panel.dataset.inspectorPanel !== inspectorTab; }); return; } const reset = event.target.closest('[data-responsive-reset]'); if (reset) { const object = selectedObject(); const key = reset.dataset.responsiveReset; if (!object?.responsive?.[breakpoint]) return; delete object.responsive[breakpoint][key]; if (!Object.keys(object.responsive[breakpoint]).length) delete object.responsive[breakpoint]; save(); renderInspector(); return; } const aiAsk = event.target.closest('[data-object-ai-ask]'); if (aiAsk) { requestObjectAi(aiAsk); return; } const picker = event.target.closest('[data-media-picker]'); if (picker) { openMedia(picker.dataset.mediaPicker, picker.dataset.mediaTypes ? picker.dataset.mediaTypes.split(',') : [], picker.dataset.mediaBucket || 'content'); return; } const breakpointButton = event.target.closest('[data-breakpoint]'); if (breakpointButton) { breakpoint = breakpointButton.dataset.breakpoint; renderInspector(); return; } const action = event.target.closest('[data-editor-action]'); if (!action) return; if (action.dataset.editorAction === 'delete') deleteSelected(); if (action.dataset.editorAction === 'duplicate') duplicateSelected(); if (action.dataset.editorAction === 'move-up') moveSelected(-1); if (action.dataset.editorAction === 'move-down') moveSelected(1); });
    if (window.ResizeObserver && canvasWrap) new ResizeObserver(() => requestAnimationFrame(syncCanvasPreview)).observe(canvasWrap);
    inspector.addEventListener('input', (event) => { const radiusKey = event.target.dataset.radiusField; if (!radiusKey) return; const object = selected.kind === 'section' ? currentSection() : selected.kind === 'column' ? currentColumn() : currentElement(); const unit = event.target.closest('fieldset')?.querySelector('[data-spacing-unit]')?.value || 'px'; object.settings ||= {}; object.settings[radiusKey] = event.target.value.trim() ? `${event.target.value.trim()}${unit}` : ''; if (!object.settings[radiusKey]) delete object.settings[radiusKey]; save(); });
    inspector.addEventListener('change', (event) => { const unit = event.target.dataset.spacingUnit; if (!unit) return; const fieldset = event.target.closest('fieldset'); const object = selected.kind === 'section' ? currentSection() : selected.kind === 'column' ? currentColumn() : currentElement(); const radius = fieldset?.classList.contains('page-radius-field'); if (radius) { object.settings ||= {}; fieldset.querySelectorAll('[data-radius-field]').forEach((input) => { const value = numericOf(input.value); if (value) object.settings[input.dataset.radiusField] = `${value}${unit}`; }); } else { const key = fieldset?.querySelector('[data-spacing-field]')?.dataset.spacingField; object.responsive ||= {}; object.responsive[breakpoint] ||= {}; const box = object.responsive[breakpoint][key] && typeof object.responsive[breakpoint][key] === 'object' ? object.responsive[breakpoint][key] : {}; fieldset.querySelectorAll('[data-spacing-field]').forEach((input) => { const value = numericOf(input.value).trim(); if (value) box[input.dataset.spacingSide] = `${value}${unit}`; }); if (Object.keys(box).length) object.responsive[breakpoint][key] = box; } save(); });
    undo.addEventListener('click', () => { if (!history.length) return; future.push(JSON.stringify(documentState)); documentState = JSON.parse(history.pop()); render(); });
    redo.addEventListener('click', () => { if (!future.length) return; history.push(JSON.stringify(documentState)); documentState = JSON.parse(future.pop()); render(); });
    form.addEventListener('ai-page-draft', (event) => { const proposal = event.detail; const plannedDocument = proposal?.document; if (plannedDocument?.version !== 2 || !Array.isArray(plannedDocument.sections) || !plannedDocument.sections.length) return; mutate(() => { documentState = clone(plannedDocument); selectionActive = true; selected = {kind:'section',section:0,column:0,element:0}; }); });
    form.addEventListener('submit', save);
    window.oneMinistryPageEditor = { get document(){ return documentState; }, get selected(){ return selected; }, addElement(type){ const target = currentColumn(); if (!target || !defaults[type]) return; mutate(() => target.elements.push(element(type))); }, save };
    render();
})();
</script>
