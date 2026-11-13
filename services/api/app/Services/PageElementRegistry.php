<?php

namespace App\Services;

/** Authoritative element metadata for validation, editing, AI, and rendering. */
final class PageElementRegistry
{
    /** @return array<string,array<string,mixed>> */
    public function all(): array
    {
        $fields = [
            'hero'=>['eyebrow','title','body','button_label','button_url'], 'html'=>['reason','html','css','javascript','fallback'], 'custom_code'=>['reason','html','css','javascript','fallback'],
            'heading'=>['text'], 'paragraph'=>['text'], 'rich_text'=>['title','text'], 'button'=>['label','url'], 'feature_list'=>['title','items'],
            'testimonial'=>['quote','name','role'], 'statistics'=>['title','stat_one_value','stat_one_label','stat_two_value','stat_two_label','stat_three_value','stat_three_label'],
            'alert'=>['tone','title','body'], 'progress'=>['title','label','value'], 'table'=>['title','headers','rows'], 'icon'=>['icon','label'],
            'icon_box'=>['icon','title','body','button_label','button_url'], 'icon_list'=>['title','items'], 'image'=>['url','alt','caption','image_hint'],
            'image_card'=>['url','alt','title','body','button_label','button_url'], 'gallery'=>['images','columns','caption'], 'video_embed'=>['url','title','caption'],
            'audio'=>['url','title','caption'], 'file'=>['url','label','description'], 'media_text'=>['media_url','media_alt','placement','title','body','button_label','button_url'],
            'list'=>['title','items','ordered'], 'accordion'=>['title','question_one','answer_one','question_two','answer_two','question_three','answer_three'],
            'tabs'=>['title','tab_one_label','tab_one_content','tab_two_label','tab_two_content','tab_three_label','tab_three_content'],
            'social_links'=>['title','facebook_url','instagram_url','youtube_url'], 'contact_form'=>['title','body','submit_label'],
            'map_directions'=>['title','address','directions_url','button_label'], 'spacer'=>['height'], 'quote'=>['text','attribution'], 'divider'=>[],
            'call_to_action'=>['title','body','button_label','button_url'], 'service_times'=>['title','body'], 'events'=>['title','body'], 'departments'=>['title','body'],
            'locations'=>['title','body','button_label','button_url'], 'carousel'=>['eyebrow','title','body','slide_one_type','slide_one_media_url','slide_one_title','slide_one_body','slide_one_button_label','slide_one_button_url','slide_two_type','slide_two_media_url','slide_two_title','slide_two_body','slide_two_button_label','slide_two_button_url','slide_three_type','slide_three_media_url','slide_three_title','slide_three_body','slide_three_button_label','slide_three_button_url','slides','button_label','button_url'],
            'live_service'=>['title','body','button_label','button_url'], 'giving'=>['title','body','button_label','button_url'], 'prayer'=>['title','body','button_label','button_url'],
            'sermon_spotlight'=>['title','body','button_label','button_url'], 'books'=>['title','body','button_label','button_url'], 'announcements'=>['title','body'],
            'newsletter'=>['title','body'], 'next_steps'=>['title','body'], 'welcome'=>['title','body'],
            'custom'=>['title','body','button_label','button_url'],
        ];
        $labels = ['custom_code'=>'Custom HTML / CSS / JavaScript','html'=>'HTML','rich_text'=>'Rich text','feature_list'=>'Feature list','image_card'=>'Image card','video_embed'=>'Video embed','media_text'=>'Media & text','icon_box'=>'Icon box','icon_list'=>'Icon list','social_links'=>'Social links','contact_form'=>'Contact form','map_directions'=>'Map & directions','call_to_action'=>'Call to action','service_times'=>'Service times','live_service'=>'Live service','sermon_spotlight'=>'Sermon spotlight','next_steps'=>'Next steps','custom'=>'Content card','books'=>'Books & resources'];
        $categories = [
            'advanced'=>['html','custom_code'], 'text'=>['heading','paragraph','rich_text','quote','list','accordion','tabs','feature_list','testimonial','statistics','alert','progress','table'],
            'actions'=>['button','call_to_action'], 'media'=>['image','image_card','gallery','video_embed','audio','file','media_text','carousel'],
            'visual'=>['icon','icon_box','icon_list','divider','spacer'], 'connection'=>['contact_form','map_directions','social_links','newsletter','prayer','next_steps'],
            'ministry'=>['service_times','events','departments','locations','live_service','giving','sermon_spotlight','books','announcements','welcome','custom'],
        ];
        $features = ['live_service'=>'digital_church','giving'=>'giving','books'=>'books_library'];
        $catalog = [];
        foreach ($fields as $type => $fieldNames) {
            $category = array_key_first(array_filter($categories, fn (array $types): bool => in_array($type, $types, true))) ?: 'content';
            $defaults = []; foreach ($fieldNames as $field) $defaults[$field] = $this->defaultValue($type, $field);
            $settings = $this->settings(); $responsive = $this->responsive();
            $catalog[$type] = ['label'=>$labels[$type] ?? str($type)->replace('_',' ')->title()->toString(), 'category'=>$category,
                'fields'=>$fieldNames, 'field_definitions'=>$this->fieldDefinitions($type,$fieldNames), 'defaults'=>$defaults,
                'settings'=>$settings, 'setting_definitions'=>$this->controlDefinitions($settings),
                'responsive'=>$responsive, 'responsive_definitions'=>$this->controlDefinitions($responsive), 'media'=>$this->media($type),
                'validation'=>$this->elementValidation($type), 'accessibility'=>$this->accessibility($type, $category),
                'renderer'=>['view'=>'public.partials.page-element','case'=>$type],
                'inspector'=>['content'=>'field_definitions','style'=>$this->settings(),'responsive'=>$this->responsive()],
                'purpose'=>$this->purpose($type), 'use_cases'=>$this->useCases($type, $category),
                'factual_restrictions'=>$this->factualRestrictions($type), 'examples'=>$this->examples($type, $defaults),
                'custom_code'=>['allowed'=>in_array($type,['html','custom_code'],true),'fallback_required'=>in_array($type,['html','custom_code'],true),'reason_required'=>in_array($type,['html','custom_code'],true)],
                'ai_guidance'=>$this->guidance($type, $category), ...isset($features[$type]) ? ['feature'=>$features[$type]] : []];
        }
        return $catalog;
    }

    /** @param array<int,string> $enabledFeatures @return array<string,array<string,mixed>> */
    public function available(array $enabledFeatures): array { return array_filter($this->all(), fn (array $item): bool => !isset($item['feature']) || in_array($item['feature'],$enabledFeatures,true)); }
    /** @return array<int,string> */
    public function fields(string $type): array { return $this->all()[$type]['fields'] ?? []; }
    public function feature(string $type): ?string { return $this->all()[$type]['feature'] ?? null; }
    public function supports(string $type): bool { return isset($this->all()[$type]); }
    /** @return array<string,mixed> */
    public function definition(string $type): array { return $this->all()[$type] ?? []; }
    /** @return array<string,array{settings:array<int,string>,setting_definitions:array<string,array<string,mixed>>,responsive:array<int,string>,responsive_definitions:array<string,array<string,mixed>>}> */
    public function objectContracts(): array
    {
        $sectionSettings=['title','body','content_width','content_max_width','min_height','vertical_alignment','row_gap','column_gap','grid_preset','padding','margin','background','background_color','background_image','background_overlay','background_position','background_size','background_repeat','background_attachment','overlay_color','overlay_opacity','border','border_color','border_style','border_width','radius','radius_top_left','radius_top_right','radius_bottom_right','radius_bottom_left','shadow','shadow_x','shadow_y','shadow_blur','shadow_spread','shadow_color','overflow','custom_id','custom_class','custom_css'];
        $sectionResponsive=['visibility','alignment','padding','margin','column_count','stack','column_gap'];
        $columnSettings=['background_color','text_color','border_color','border_style','border_width','radius_top_left','radius_top_right','radius_bottom_right','radius_bottom_left','shadow_x','shadow_y','shadow_blur','shadow_spread','shadow_color','min_height','vertical_alignment','element_gap','overflow','custom_id','custom_class','custom_css'];
        $columnResponsive=['visibility','alignment','padding','margin','width','custom_width','order','gap'];
        return [
            'section'=>['settings'=>$sectionSettings,'setting_definitions'=>$this->controlDefinitions($sectionSettings),'responsive'=>$sectionResponsive,'responsive_definitions'=>$this->controlDefinitions($sectionResponsive)],
            'column'=>['settings'=>$columnSettings,'setting_definitions'=>$this->controlDefinitions($columnSettings),'responsive'=>$columnResponsive,'responsive_definitions'=>$this->controlDefinitions($columnResponsive)],
        ];
    }
    /** @return array<string,array{label:string,order:int}> */
    public function categories(): array { return ['text'=>['label'=>'Text and content','order'=>10],'actions'=>['label'=>'Actions','order'=>20],'media'=>['label'=>'Media','order'=>30],'visual'=>['label'=>'Visual and spacing','order'=>40],'connection'=>['label'=>'Connection','order'=>50],'ministry'=>['label'=>'Ministry','order'=>60],'advanced'=>['label'=>'Advanced','order'=>70],'content'=>['label'=>'Content','order'=>80]]; }

    private function defaultValue(string $type, string $field): string
    {
        $specific = [
            'hero'=>['eyebrow'=>'WELCOME','title'=>'A place to belong','body'=>'Share the invitation people should see first.','button_label'=>'Plan your visit'],
            'custom_code'=>['reason'=>'No native element supports this required bespoke interaction or layout.','html'=>'<section class="custom-card"><h2>Custom content</h2><p>Add a bespoke design, then review it before publishing.</p></section>','css'=>'.custom-card { padding: 2rem; border-radius: 1rem; background: #f4f4f5; color: #18181b; }','fallback'=>'Custom content is available on this page.'],
            'html'=>['reason'=>'No native element supports this required bespoke layout.','html'=>'<section><h2>Custom content</h2><p>Add your HTML here.</p></section>','fallback'=>'Custom content is available on this page.'],
            'heading'=>['text'=>'A meaningful heading'], 'paragraph'=>['text'=>'Write a clear paragraph that helps people understand your ministry.'],
            'alert'=>['tone'=>'info'], 'progress'=>['value'=>'60'], 'gallery'=>['columns'=>'three'], 'media_text'=>['placement'=>'left'], 'list'=>['ordered'=>'no'],
            'spacer'=>['height'=>'medium'], 'icon'=>['icon'=>'heart'], 'icon_box'=>['icon'=>'people'], 'locations'=>['button_url'=>'/locations'], 'giving'=>['button_url'=>'/give'],
        ];
        if (isset($specific[$type][$field])) return $specific[$type][$field];
        return match ($field) { 'title'=>'Add a clear title', 'body','text','description'=>'Add clear, welcoming content for this page.',
            'label','button_label','submit_label'=>'Learn more', 'items'=>"First point\nSecond point\nThird point", default=>'' };
    }

    /** @param array<int,string> $fields @return array<string,array<string,mixed>> */
    private function fieldDefinitions(string $type,array $fields): array
    {
        $long=['body','text','description','items','rows','slides','quote','reason','html','css','javascript','fallback','address']; $result=[];
        foreach($fields as $field) $result[$field]=['label'=>str($field)->replace('_',' ')->title()->toString(),
            'control'=>in_array($field,$long,true)||str_contains($field,'content')||str_contains($field,'answer')?'textarea':'text',
            'media'=>(in_array($field,['url','media_url','images'],true)||str_ends_with($field,'_media_url'))&&$type!=='button', 'options'=>$this->fieldOptions($field), 'validation'=>$this->fieldValidation($type,$field)];
        return $result;
    }
    /** @return array<int,string> */
    private function settings(): array { return ['background','background_color','background_image','background_overlay','background_position','background_size','background_repeat','background_attachment','overlay_color','overlay_opacity','text_color','border','border_color','border_style','border_width','radius','radius_top_left','radius_top_right','radius_bottom_right','radius_bottom_left','shadow','shadow_x','shadow_y','shadow_blur','shadow_spread','shadow_color','overflow','min_height','vertical_alignment','font_family','font_size','font_weight','line_height','letter_spacing','text_transform','button_background','button_hover_background','button_text_color','button_border_color','button_border_width','button_padding_x','button_padding_y','button_radius','button_width','custom_id','custom_class','custom_css']; }
    /** @return array<int,string> */
    private function responsive(): array { return ['visibility','alignment','padding','margin','order','font_size','line_height','letter_spacing']; }
    /** @return array<string,array<int,string>> */
    private function media(string $type): array { return ['types'=>match($type){'image','image_card','gallery','media_text'=>['image'],'video_embed'=>['video'],'audio'=>['audio'],'file'=>['file'],default=>[]}]; }
    /** @return array<string,mixed> */
    private function fieldValidation(string $type,string $field): array
    {
        $rule = ['type'=>'string','max'=>in_array($field,['body','text','items','images','rows','slides','reason','html','css','javascript','fallback'],true)?($field==='html'||$field==='css'||$field==='javascript'?50000:($field==='reason'?1000:12000)):500];
        if ($field==='tone') $rule['enum']=['info','success','warning'];
        if ($field==='columns') $rule['enum']=['two','three','four'];
        if ($field==='placement') $rule['enum']=['left','right'];
        if ($field==='ordered') $rule['enum']=['yes','no'];
        if ($field==='height') $rule['enum']=['small','medium','large'];
        if ($field==='icon') $rule['enum']=['heart','people','calendar','location','message','book','star','check','hand-heart','globe'];
        if ($field==='value') $rule += ['format'=>'integer','min'=>0,'max_value'=>100];
        if ($field==='button_url'||preg_match('/^slide_(one|two|three)_button_url$/', $field)===1||($type==='button'&&$field==='url')) $rule['format']='internal_or_http_url';
        elseif ($field==='images') $rule += ['format'=>'http_url_list','max_items'=>12];
        elseif ($field==='url'||$field==='media_url'||str_ends_with($field,'_url')) $rule['format']='http_url';
        return $rule;
    }
    /** @return array<int,array{0:string,1:string}> */
    private function fieldOptions(string $field): array
    {
        return match($field){
            'tone'=>[['info','Information'],['success','Success'],['warning','Warning']],
            'icon'=>[['heart','Heart'],['people','People'],['calendar','Calendar'],['location','Location'],['message','Message'],['book','Book'],['star','Star'],['check','Check'],['hand-heart','Care'],['globe','Globe']],
            'columns'=>[['two','Two'],['three','Three'],['four','Four']], 'placement'=>[['left','Media left'],['right','Media right']],
            'ordered'=>[['no','Bulleted'],['yes','Numbered']], 'height'=>[['small','Small'],['medium','Medium'],['large','Large']],
            'slide_one_type','slide_two_type','slide_three_type'=>[['flyer','Flyer'],['video','Video']], default=>[]};
    }
    /** @param array<int,string> $keys @return array<string,array<string,mixed>> */
    private function controlDefinitions(array $keys): array
    {
        $result=[];
        $enums = [
            'background'=>['canvas','surface','surface_muted','action','brand'],'background_position'=>['left_top','center_top','right_top','left_center','center_center','right_center','left_bottom','center_bottom','right_bottom'],
            'background_size'=>['auto','cover','contain'],'background_repeat'=>['no_repeat','repeat','repeat_x','repeat_y'],'background_attachment'=>['scroll','fixed'],
            'border'=>['none','subtle','strong'],'border_style'=>['none','solid','dashed','dotted'],'overflow'=>['visible','hidden'],'vertical_alignment'=>['start','center','end','stretch'],
            'font_family'=>['inherit','system','verdana','trebuchet','georgia','palatino'],'font_weight'=>['400','500','600','700','800'],
            'text_transform'=>['none','uppercase','lowercase','capitalize'],'button_width'=>['auto','full'],'visibility'=>['visible','hidden'],
            'alignment'=>['left','center','right','stretch'],'order'=>['1','2','3','4'],
        ];
        $dimensions=['border_width','radius','radius_top_left','radius_top_right','radius_bottom_right','radius_bottom_left','shadow_x','shadow_y','shadow_blur','shadow_spread','min_height','font_size','line_height','letter_spacing','button_border_width','button_padding_x','button_padding_y','button_radius'];
        $colors=['background_color','overlay_color','text_color','border_color','shadow_color','button_background','button_hover_background','button_text_color','button_border_color'];
        foreach($keys as $key) {
            $validation=['type'=>'string','max'=>in_array($key,['custom_css'],true)?50000:500];
            if(isset($enums[$key])) $validation['enum']=$enums[$key];
            if(in_array($key,$dimensions,true)) $validation['format']='css_dimension';
            if(in_array($key,$colors,true)) $validation['format']='theme_token_or_hex';
            if($key==='overlay_opacity') $validation += ['format'=>'integer','min'=>0,'max_value'=>100];
            if(in_array($key,['padding','margin'],true)) $validation=['type'=>'spacing_box','units'=>['px','%','em','rem','vw','vh'],'negative'=>$key==='margin'];
            $result[$key]=['label'=>str($key)->replace('_',' ')->title()->toString(),
                'control'=>in_array($key,$colors,true)?'color':($key==='custom_css'?'code':(isset($enums[$key])?'select':'text')),
                'options'=>array_map(fn(string $value): array => [$value,str($value)->replace('_',' ')->title()->toString()],$enums[$key]??[]),
                'validation'=>$validation];
        }
        return $result;
    }
    /** @return array<int,string> */
    private function elementValidation(string $type): array { return match($type){'table'=>['table_shape'],'video_embed'=>['supported_video_provider'],'custom_code','html'=>['native_gap_reason','sandbox_code','accessible_fallback','responsive_custom_code'],'image','image_card'=>['image_alt_text'],'progress'=>['progress_range'],default=>[]}; }
    /** @return array<int,string> */
    private function accessibility(string $type,string $category): array
    {
        $rules=['Use semantic content and preserve keyboard and screen-reader operation.'];
        if(in_array($type,['image','image_card','media_text','gallery'],true)) $rules[]='Provide meaningful alternative text for informative images; use empty alternative text only for decorative images.';
        if(in_array($type,['button','hero','call_to_action','image_card','icon_box'],true)) $rules[]='Action labels must describe the destination or result without relying on surrounding context.';
        if(in_array($type,['html','custom_code'],true)) $rules[]='Provide accessible fallback content and keyboard-operable semantics inside the isolated sandbox.';
        if($category==='text') $rules[]='Maintain a logical heading hierarchy and readable line length.';
        return $rules;
    }
    private function purpose(string $type): string
    {
        return match($type){'hero'=>'Introduce the page with its clearest message and primary action.','call_to_action'=>'Give visitors one focused next action.','media_text'=>'Explain a message with supporting visual media.','feature_list'=>'Summarize distinct benefits or expectations.','testimonial'=>'Present a staff-supplied personal account.','statistics'=>'Present verified numeric facts.','service_times'=>'Render installation service schedules.','events'=>'Render published upcoming events.','departments'=>'Render public ministry departments.','locations'=>'Render ministry locations.','custom_code','html'=>'Express an unusual design that no native element can represent.',default=>'Present '.str($type)->replace('_',' ')->toString().' content using the native page renderer.'};
    }
    /** @return array<int,string> */
    private function useCases(string $type,string $category): array
    {
        $specific=match($type){'hero'=>['Page opening','Campaign landing page','Visitor welcome'],'call_to_action'=>['Registration prompt','Visit invitation','Next-step prompt'],'custom_code','html'=>['Bespoke interactive visual','Special layout unavailable in native elements'],'statistics'=>['Verified impact figures','Published ministry totals'],default=>[]};
        return $specific?:["Use when a {$category} pattern best communicates the supplied content."];
    }
    /** @return array<int,string> */
    private function factualRestrictions(string $type): array
    {
        $rules=['Use only facts supplied by staff or available in approved public module data.','Do not invent people, dates, locations, promises, links, media, or ministry claims.'];
        if(in_array($type,['statistics','testimonial','service_times','events','departments','locations'],true)) $rules[]='Never fabricate records, quotations, numbers, schedules, or availability.';
        return $rules;
    }
    /** @param array<string,string> $defaults @return array<int,array<string,mixed>> */
    private function examples(string $type,array $defaults): array { return [['description'=>'A concise native '.$type.' example using only confirmed content.','content'=>$defaults]]; }
    private function guidance(string $type,string $category): string { return in_array($type,['html','custom_code'],true)
        ? 'Use only when no native element can express the design. Keep it accessible, self-contained, sandbox-safe, and free of external requests.'
        : "Use this {$category} element when it semantically fits. Configure content, style, spacing, typography, and responsive settings deliberately."; }
}
