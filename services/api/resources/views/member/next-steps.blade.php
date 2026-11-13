<x-dynamic-component :component="($guest ?? false) ? 'layouts.public' : 'layouts.member'" :title="$title" :settings="$settings" :ministry-name="$settings->name" :features="$features">
    <section class="member-page-heading"><p>KEEP GROWING</p><h1>Next steps</h1><span>Choose what you would like to explore. The ministry team will follow up with you.</span></section>
    @if(session('status'))<div class="member-notice member-notice-success" role="status"><x-ui.icon name="followup"/><span>{{ session('status') }}</span></div>@endif
    @if($errors->any())<div class="member-notice member-notice-error" role="alert"><x-ui.icon name="care"/><span>{{ $errors->first() }}</span></div>@endif
    <section class="member-panel member-next-steps"><header><div><p>REQUEST A NEXT STEP</p><h2>How can we help?</h2></div></header><p>{{ ($guest ?? false) ? 'Whether or not you have an account, your request goes directly to the ministry follow-up team.' : 'Your request goes directly to the ministry follow-up team.' }}</p><x-connection.next-step-form :action="route('member.next-steps.request')" :journey-options="$journeyOptions" :guest="$guest ?? false"/></section>
</x-dynamic-component>
