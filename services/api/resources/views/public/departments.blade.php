@php($terminology = app(\App\Support\MinistryTerminology::class))
@php($departmentSingular = $terminology->label('department'))
@php($departmentPlural = $terminology->label('department', 'plural'))
<x-layouts.public :title="$departmentPlural" :settings="$settings" :features="$features">
    <main class="department-public-archive"><section class="department-public-hero"><p>FIND YOUR PLACE</p><h1>Purpose grows in community.</h1><span>Explore ministry teams where you can connect, contribute, learn, and build meaningful relationships.</span></section>
    <section class="department-public-grid">
        @forelse($departments as $department)
            <a class="department-public-card" href="{{ route('public.departments.show',$department->slug) }}"><div class="department-public-cover" @if($department->image_url) style="background-image:linear-gradient(180deg,transparent,rgb(7 20 41 / 72%)),url('{{ $department->image_url }}')" @endif><span><x-ui.icon name="departments" :size="20"/></span>@if($department->featured)<b>Featured</b>@endif</div><div><small>{{ $department->category ?: 'Ministry '.$departmentSingular }}</small><h2>{{ $department->name }}</h2><p>{{ $department->description ?: 'Learn more about this '.str($departmentSingular)->lower().' and how to take part.' }}</p><footer>{{ $department->accepting_requests ? 'Accepting requests' : 'Explore '.str($departmentSingular)->lower() }} <x-ui.icon name="arrow-right" :size="15"/></footer></div></a>
        @empty
            <div class="public-empty">No public {{ str($departmentPlural)->lower() }} are available yet.</div>
        @endforelse
    </section>
    <div class="pagination-wrap">{{ $departments->links() }}</div></main>
</x-layouts.public>
