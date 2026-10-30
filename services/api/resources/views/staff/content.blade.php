@php
    $labels = ['books_library' => 'Books', 'audio_sermons' => 'Audio sermons', 'video_sermons' => 'Video sermons', 'sermon_series' => 'Sermon series'];
    $isSeries = $library === 'sermon_series';
    $isBook = $library === 'books_library';
    $isVideo = $library === 'video_sermons';
@endphp
<x-layouts.workspace title="Content Library" :ministry-name="$settings->name" :user-name="$user->name" :user-initials="str($user->name)->substr(0, 2)" user-role="Staff workspace">
    <x-slot:navigation></x-slot:navigation>

    <header class="workspace-page-heading staff-page-command">
        <div>
            <p>CONTENT LIBRARY</p>
            <h1>Books, sermons, and series</h1>
            <span>Create resources as drafts, then submit them to an authorized publisher for review.</span>
        </div>
        <button class="button" type="button" data-workspace-dialog-open="content-create">Add {{ $labels[$library] }}</button>
        <dialog class="workspace-dialog content-edit-dialog" id="content-create" data-workspace-dialog aria-labelledby="content-create-title">
            <form method="POST" action="{{ route('staff.content.store', $library) }}" enctype="multipart/form-data" class="workspace-dialog-card content-edit-form">
                @csrf
                <header><div><p>CONTENT LIBRARY</p><h2 id="content-create-title">Add {{ str($labels[$library])->singular() }}</h2><span>Create the resource, then publish when it is ready for members.</span></div><button type="button" class="workspace-dialog-close" data-workspace-dialog-close aria-label="Close editor"><x-ui.icon name="close" :size="18"/></button></header>
                <div class="content-edit-fields">
                    <label>Title<input name="title" value="{{ old('title') }}" required maxlength="200"></label>
                    @if($isSeries)
                        <label class="field-wide">Description<textarea name="description" rows="4" maxlength="5000">{{ old('description') }}</textarea></label>
                    @else
                        @if($isVideo)
                            <label>Video file<input type="file" name="file" accept="video/mp4,video/webm,video/quicktime,video/x-m4v"></label>
                            <label>YouTube URL <input type="url" name="youtube_url" value="{{ old('youtube_url') }}" placeholder="https://youtu.be/..."></label>
                        @else
                            <label class="field-wide">{{ $isBook ? 'Book file' : 'Audio file' }}<input type="file" name="file" required accept="{{ $isBook ? '.pdf,.epub,.doc,.docx,.mobi,.azw,.azw3,.txt' : 'audio/mpeg,audio/mp4,audio/wav,audio/ogg,audio/aac' }}"></label>
                        @endif
                        <label>Speaker <input name="speaker" value="{{ old('speaker') }}" maxlength="160"></label>
                        <label>Scripture reference <input name="scripture_reference" value="{{ old('scripture_reference') }}" maxlength="255"></label>
                        <label>Duration in seconds <input type="number" name="duration_seconds" value="{{ old('duration_seconds') }}" min="0"></label>
                        @if(!$isBook)<label>Series<select name="sermon_series_id"><option value="">No series</option>@foreach($series as $seriesItem)<option value="{{ $seriesItem->id }}">{{ $seriesItem->title }}</option>@endforeach</select></label>@endif
                        <label>Category<select name="library_category_id"><option value="">No category</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></label>
                        <label class="field-wide">Tags<select name="tag_ids[]" multiple size="4">@foreach($tags as $tag)<option value="{{ $tag->id }}">{{ $tag->name }}</option>@endforeach</select></label>
                        <label class="field-wide">Description<textarea name="description" rows="4" maxlength="5000">{{ old('description') }}</textarea></label>
                    @endif
                </div>
                <footer><button class="button button-secondary" type="button" data-workspace-dialog-close>Cancel</button><button class="button" type="submit">Save draft</button></footer>
            </form>
        </dialog>
    </header>

    @if(session('status'))<div class="workspace-notice workspace-notice-success" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="workspace-notice workspace-notice-error" role="alert">{{ $errors->first() }}</div>@endif

    <nav class="table-toolbar content-library-tabs" aria-label="Content libraries">
        @foreach($libraries as $libraryKey)
            <a class="button {{ $libraryKey === $library ? '' : 'button-secondary' }}" href="{{ route('staff.content', ['library' => $libraryKey]) }}">{{ $labels[$libraryKey] }}</a>
        @endforeach
    </nav>

    <div class="staff-page-command content-library-tools">
        <div class="table-toolbar" aria-label="Organize content">
            <strong>Organize</strong>
            <button class="button button-secondary" type="button" data-workspace-dialog-open="content-category-create">New category</button>
            <button class="button button-secondary" type="button" data-workspace-dialog-open="content-tag-create">New tag</button>
        </div>

        <form class="table-toolbar" method="GET" role="search">
            <input type="hidden" name="library" value="{{ $library }}">
            <label class="people-search-field"><span class="sr-only">Search {{ $labels[$library] }}</span><input type="search" name="q" value="{{ $query }}" placeholder="Search title, speaker, or scripture"></label>
            <label><span class="sr-only">Publication status</span><select name="status"><option value="">All statuses</option>@foreach(['draft' => 'Draft', 'published' => 'Published', 'rejected' => 'Rejected'] as $value => $label)<option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>@endforeach</select></label>
            <button class="button button-secondary" type="submit">Filter</button>
        </form>
    </div>

    <section class="data-panel responsive-records">
        <table>
            <thead><tr><th>Title</th><th>Details</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                @forelse($items as $item)
                    <tr>
                        <td data-label="Title"><strong>{{ $item->title }}</strong>@if(!$isSeries && $item->file_name)<small>{{ $item->file_name }}</small>@endif</td>
                        <td data-label="Details">@if($isSeries){{ str($item->description)->limit(90) ?: '—' }}@else{{ $item->speaker ?: $item->category_name ?: '—' }}@if($item->scripture_reference)<small>{{ $item->scripture_reference }}</small>@endif@endif</td>
                        <td data-label="Status"><span class="table-status">{{ str($item->status)->headline() }}</span></td>
                        <td data-label="Actions">
                            <div class="content-library-row-actions">
                                <button class="button button-secondary content-library-icon-action" type="button" data-workspace-dialog-open="content-edit-{{ $item->id }}" aria-label="Edit {{ $item->title }}" title="Edit"><x-ui.icon name="edit" :size="16"/><span class="sr-only">Edit</span></button>
                                @if($item->status === 'draft' && $canPublish)
                                    <form method="POST" action="{{ route('staff.content.review', [$library, $item->id]) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="published"><button class="button content-library-icon-action" type="submit" aria-label="Publish {{ $item->title }}" title="Publish"><x-ui.icon name="check" :size="16"/><span class="sr-only">Publish</span></button></form>
                                    <form method="POST" action="{{ route('staff.content.review', [$library, $item->id]) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="rejected"><button class="button button-secondary content-library-icon-action" type="submit" aria-label="Reject {{ $item->title }}" title="Reject"><x-ui.icon name="close" :size="16"/><span class="sr-only">Reject</span></button></form>
                                @endif
                                <form method="POST" action="{{ route('staff.content.destroy', [$library, $item->id]) }}" onsubmit="return confirm('Remove this content item?');">@csrf @method('DELETE')<button class="button button-secondary content-library-icon-action" type="submit" aria-label="Delete {{ $item->title }}" title="Delete"><x-ui.icon name="trash" :size="16"/><span class="sr-only">Delete</span></button></form>
                            </div>
                            <dialog class="workspace-dialog content-edit-dialog" id="content-edit-{{ $item->id }}" data-workspace-dialog aria-labelledby="content-edit-title-{{ $item->id }}">
                                <form method="POST" action="{{ route('staff.content.update', [$library, $item->id]) }}" class="workspace-dialog-card content-edit-form">
                                    @csrf @method('PUT')
                                    <header>
                                        <div><p>{{ strtoupper(str($labels[$library])->singular()) }}</p><h2 id="content-edit-title-{{ $item->id }}">Edit {{ $item->title }}</h2><span>Update details without leaving this library.</span></div>
                                        <button type="button" class="workspace-dialog-close" data-workspace-dialog-close aria-label="Close editor"><x-ui.icon name="close" :size="18"/></button>
                                    </header>
                                    <div class="content-edit-fields">
                                        <label>Title<input name="title" value="{{ $item->title }}" required maxlength="200"></label>
                                        @if(!$isSeries)
                                            <label>Speaker<input name="speaker" value="{{ $item->speaker }}" maxlength="160"></label><label>Scripture reference<input name="scripture_reference" value="{{ $item->scripture_reference }}" maxlength="255"></label><label>Duration in seconds<input type="number" name="duration_seconds" value="{{ $item->duration_seconds }}" min="0"></label>
                                            @if(!$isBook)<label>Series<select name="sermon_series_id"><option value="">No series</option>@foreach($series as $seriesItem)<option value="{{ $seriesItem->id }}" @selected($item->sermon_series_id === $seriesItem->id)>{{ $seriesItem->title }}</option>@endforeach</select></label>@endif
                                            <label>Category<select name="library_category_id"><option value="">No category</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected($item->library_category_id === $category->id)>{{ $category->name }}</option>@endforeach</select></label>
                                            <label class="field-wide">Tags<select name="tag_ids[]" multiple size="4">@foreach($tags as $tag)<option value="{{ $tag->id }}" @selected(in_array($tag->id, $assetTagIds->get($item->id, []), true))>{{ $tag->name }}</option>@endforeach</select></label>
                                        @endif
                                        <label class="field-wide">Description<textarea name="description" rows="4" maxlength="5000">{{ $item->description }}</textarea></label>
                                    </div>
                                    <footer><button class="button button-secondary" type="button" data-workspace-dialog-close>Cancel</button><button class="button" type="submit">Save changes</button></footer>
                                </form>
                            </dialog>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty-cell">No {{ strtolower($labels[$library]) }} match this view. Add the first item as a draft.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
    <div class="pagination-wrap">{{ $items->links() }}</div>

    <dialog class="workspace-dialog content-edit-dialog" id="content-category-create" data-workspace-dialog aria-labelledby="content-category-create-title">
        <form method="POST" action="{{ route('staff.content.categories.store') }}" class="workspace-dialog-card content-edit-form">@csrf<input type="hidden" name="library" value="{{ $library }}"><header><div><p>CONTENT LIBRARY</p><h2 id="content-category-create-title">New category</h2><span>Use categories to organize {{ strtolower($labels[$library]) }}.</span></div><button type="button" class="workspace-dialog-close" data-workspace-dialog-close aria-label="Close editor"><x-ui.icon name="close" :size="18"/></button></header><div class="content-edit-fields"><label class="field-wide">Category name<input name="name" required maxlength="100"></label></div><footer><button class="button button-secondary" type="button" data-workspace-dialog-close>Cancel</button><button class="button" type="submit">Create category</button></footer></form>
    </dialog>
    <dialog class="workspace-dialog content-edit-dialog" id="content-tag-create" data-workspace-dialog aria-labelledby="content-tag-create-title">
        <form method="POST" action="{{ route('staff.content.tags.store') }}" class="workspace-dialog-card content-edit-form">@csrf<input type="hidden" name="library" value="{{ $library }}"><header><div><p>CONTENT LIBRARY</p><h2 id="content-tag-create-title">New tag</h2><span>Tags can be reused across books and sermons.</span></div><button type="button" class="workspace-dialog-close" data-workspace-dialog-close aria-label="Close editor"><x-ui.icon name="close" :size="18"/></button></header><div class="content-edit-fields"><label class="field-wide">Tag name<input name="name" required maxlength="80"></label></div><footer><button class="button button-secondary" type="button" data-workspace-dialog-close>Cancel</button><button class="button" type="submit">Create tag</button></footer></form>
    </dialog>
</x-layouts.workspace>
