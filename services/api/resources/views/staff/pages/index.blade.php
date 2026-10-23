<x-layouts.workspace title="Pages" :ministry-name="$settings->name" :user-name="$user->name" user-role="Staff workspace">
    <x-slot:navigation></x-slot:navigation>
    <header class="workspace-page-heading staff-page-command"><div><p>PUBLIC SITE</p><h1>Pages</h1><span>Create clear, useful ministry information pages. Drafts stay private until a publisher reviews them.</span></div><div class="page-command-actions"><a class="button" href="{{ route('staff.pages.create') }}"><x-ui.icon name="add" :size="16"/> New page</a></div></header>
    @if(session('status'))<div class="workspace-notice workspace-notice-success" role="status">{{ session('status') }}</div>@endif
    <section class="page-starters" aria-labelledby="page-starters-title">
        <div class="page-starters-heading"><div><p>START WITH A STRUCTURE</p><h2 id="page-starters-title">Common ministry pages</h2><span>Choose an editable starting point or begin with a blank page.</span></div><span class="page-starters-count">{{ count($starters) }} starter layouts</span></div>
        <div class="page-starter-grid">
            @foreach($starters as $key => $starter)
                <a href="{{ route('staff.pages.create', ['template' => $key]) }}">
                    <span class="page-starter-number">{{ str($loop->iteration)->padLeft(2, '0') }}</span>
                    <strong>{{ $starter['label'] }}</strong>
                    <span>{{ $starter['summary'] }}</span>
                    <small>Use this structure <span aria-hidden="true">→</span></small>
                </a>
            @endforeach
        </div>
    </section>
    <section class="pages-records-panel">
        <header><div><p>YOUR PAGES</p><h2>Published and draft pages</h2><span>Open a page to continue editing, review its status, or publish a completed draft.</span></div><span class="pages-record-count">{{ $pages->count() }} {{ str('page')->plural($pages->count()) }}</span></header>
        <div class="data-panel responsive-records"><table><thead><tr><th>Page</th><th>Address</th><th>Type</th><th>Status</th><th>Updated</th><th><span class="sr-only">Actions</span></th></tr></thead><tbody>@forelse($pages as $page)<tr><td data-label="Page"><strong>{{ $page->title }}</strong></td><td data-label="Address"><code>/pages/{{ $page->slug }}</code></td><td data-label="Type"><span class="table-status">{{ $page->page_type === 'homepage' ? 'Homepage' : 'Standard page' }}</span></td><td data-label="Status"><span class="table-status">{{ str($page->status)->headline() }}</span></td><td data-label="Updated">{{ \Illuminate\Support\Carbon::parse($page->updated_at)->timezone($settings->timezone)->format('M j, Y') }}</td><td data-label="Actions"><a class="table-action" href="{{ route('staff.pages.edit', $page->id) }}">Edit page <span aria-hidden="true">→</span></a>@if($page->status === 'published')<form method="POST" action="{{ route('staff.settings.homepage.update') }}" style="display:inline;margin-left:8px">@csrf @method('PUT')<input type="hidden" name="homepage_page_id" value="{{ $page->id }}"><button class="table-action" type="submit">Set homepage</button></form>@endif</td></tr>@empty<tr><td colspan="6" class="empty-cell">No pages yet. Choose a structure above or create a blank page.</td></tr>@endforelse</tbody></table></div>
    </section>
</x-layouts.workspace>
