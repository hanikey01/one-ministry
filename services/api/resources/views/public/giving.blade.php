<x-layouts.public :settings="$settings" :features="$features" title="Give">
    <section class="public-heading"><p>GIVING</p><h1>Give with purpose.</h1><span>Choose a purpose and an available secure payment provider. A receipt is issued only after the provider confirms payment.</span></section>
    <section class="public-card giving-shell" x-data="givingForm()" x-init="init()">
        @if($funds->isEmpty())
            <div class="giving-state giving-empty-state"><h2>{{ $offlineGivingMethods->isNotEmpty() ? 'Online giving is not available yet' : 'Giving is not available yet' }}</h2><p>{{ $offlineGivingMethods->isNotEmpty() ? 'Please use the published bank-transfer details below.' : 'This ministry has not published a donation purpose yet.' }}</p></div>
        @elseif($funds->isNotEmpty() && $givingMarkets->isNotEmpty())
        <form @submit.prevent="submit" novalidate>
            <div class="giving-grid">
                <label>Donation purpose<select x-model="fundId" @change="selectFund" required><option value="">Choose a purpose</option>@foreach($funds as $fund)<option value="{{ $fund->id }}" data-currency="{{ $fund->currency }}" data-suggested='@json(json_decode($fund->suggested_amounts ?: '[]', true))'>{{ $fund->name }}</option>@endforeach</select></label>
                <label>Country<select x-model="country" @change="loadProviders" required><option value="">Choose country</option>@foreach($givingMarkets->pluck('country')->unique() as $country)<option>{{ $country }}</option>@endforeach</select></label>
                <label>Currency<select x-model="currency" @change="loadProviders" required><option value="">Choose currency</option>@foreach($givingMarkets->pluck('currency')->unique() as $currency)<option>{{ $currency }}</option>@endforeach</select></label>
                <label>Amount <span x-text="currency"></span><input x-model="amount" type="number" min="0" step="0.01" inputmode="decimal" required></label>
            </div>
            <div class="suggested" x-show="suggested.length" x-cloak><span class="giving-field-hint">Suggested amounts</span><template x-for="value in suggested" :key="value"><button type="button" @click="amount=(value/(10**exponent())).toFixed(exponent())" x-text="currency+' '+(value/(10**exponent())).toFixed(exponent())"></button></template></div>
            <div class="giving-grid"><label>Name<input x-model="name" maxlength="160" autocomplete="name"></label><label>Email<input x-model="email" type="email" maxlength="254" autocomplete="email"></label></div>
            <label class="check"><input x-model="anonymous" type="checkbox"> Keep my name anonymous on public recognition</label>
            <fieldset><legend>Payment provider</legend><p x-show="loadingProviders">Checking available providers…</p><div class="providers"><template x-for="item in providers" :key="item.provider"><label><input type="radio" x-model="provider" :value="item.provider"><span x-text="item.display_name"></span><small x-text="item.mode==='live'?'Secure checkout':'Test checkout'"></small></label></template></div><p x-show="!loadingProviders && country && currency && !providers.length">No configured provider is available for this country and currency.</p></fieldset>
            <div class="giving-error" role="alert" x-show="error" x-text="error"></div>
            <button class="public-primary" type="submit" :disabled="busy || !provider"><span x-text="busy?'Preparing secure checkout…':'Continue to secure checkout'"></span></button>
        </form>
        <div class="giving-state" x-show="status" x-cloak role="status"><h2 x-text="statusTitle"></h2><p x-text="statusMessage"></p><button type="button" x-show="status==='pending'" @click="poll">Check again</button></div>
        @endif
        @if($funds->isNotEmpty() && $givingMarkets->isEmpty())<div class="giving-state giving-empty-state"><h2>Online checkout is not available yet</h2><p>Donation purposes are published, but no payment provider is currently available for the configured ministry markets.</p></div>@endif
    </section>
    @if($offlineGivingMethods->isNotEmpty())
    <section class="public-card bank-giving" aria-labelledby="bank-giving-title">
        <div><p class="eyebrow">BANK TRANSFER</p><h2 id="bank-giving-title">Give by bank transfer</h2><p>Use one of the ministry’s published accounts. A bank transfer is not automatically recorded or receipted as a confirmed donation; finance staff must reconcile it against the bank record.</p></div>
        <div class="bank-grid">
            @foreach($offlineGivingMethods as $method)
            <article>
                <h3>{{ $method->label }}</h3>
                @if($method->country || $method->currency)<p><strong>For:</strong> {{ collect([$method->country,$method->currency])->filter()->join(' · ') }}</p>@endif
                <dl><div><dt>Bank</dt><dd>{{ $method->bank_name }}</dd></div><div><dt>Account name</dt><dd>{{ $method->account_name }}</dd></div>@if($method->account_number)<div><dt>Account number</dt><dd>{{ $method->account_number }}</dd></div>@endif @if($method->iban)<div><dt>IBAN</dt><dd>{{ $method->iban }}</dd></div>@endif @if($method->swift_bic)<div><dt>SWIFT/BIC</dt><dd>{{ $method->swift_bic }}</dd></div>@endif @if($method->routing_code)<div><dt>Routing/sort code</dt><dd>{{ $method->routing_code }}</dd></div>@endif</dl>
                @if($method->instructions)<p>{{ $method->instructions }}</p>@endif
            </article>
            @endforeach
        </div>
    </section>
    @endif
    <script>
        function givingForm(){return{fundId:'',country:'',currency:'',amount:'',name:'',email:'',anonymous:false,provider:'',providers:[],suggested:[],busy:false,loadingProviders:false,error:'',status:'',statusTitle:'',statusMessage:'',
        init(){const q=new URLSearchParams(location.search);if(q.get('donation_id')&&q.get('status_token'))this.poll(q.get('donation_id'),q.get('status_token'));},
        exponent(){const zero=['BIF','CLP','DJF','GNF','ISK','JPY','KMF','KRW','PYG','RWF','UGX','VND','VUV','XAF','XOF','XPF'],three=['BHD','IQD','JOD','KWD','LYD','OMR','TND'];return zero.includes(this.currency)?0:(three.includes(this.currency)?3:2);},
        selectFund(e){const o=e.target.selectedOptions[0];if(o?.dataset.currency)this.currency=o.dataset.currency;this.suggested=JSON.parse(o?.dataset.suggested||'[]');this.loadProviders();},
        async loadProviders(){this.provider='';this.providers=[];if(!this.country||!this.currency)return;this.loadingProviders=true;try{const r=await fetch(`/api/v1/public/giving/options?country=${encodeURIComponent(this.country)}&currency=${encodeURIComponent(this.currency)}`);const j=await r.json();this.providers=j.data||[];if(this.providers.length===1)this.provider=this.providers[0].provider;}finally{this.loadingProviders=false;}},
        async submit(){this.error='';const minor=Math.round(Number(this.amount)*(10**this.exponent()));if(!this.fundId||!minor||!this.provider||!this.country||!this.currency){this.error='Complete the required donation details.';return;}this.busy=true;try{const r=await fetch('/api/v1/public/giving/intents',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify({fund_id:this.fundId,amount_minor:minor,currency:this.currency,country:this.country,provider:this.provider,receipt_email:this.email||null,donor_name:this.name||null,is_anonymous:this.anonymous})});const j=await r.json();if(!r.ok)throw new Error(Object.values(j.errors||{}).flat()[0]||j.message||'Checkout could not be started.');if(!j.data.checkout_url)throw new Error('The provider did not return a checkout link.');location.assign(j.data.checkout_url);}catch(e){this.error=e.message;}finally{this.busy=false;}},
        async poll(id=null,token=null){const q=new URLSearchParams(location.search);id=id||q.get('donation_id');token=token||q.get('status_token');if(!id||!token)return;this.status='pending';this.statusTitle='Verifying your donation';this.statusMessage='Payment is not confirmed until provider verification completes.';try{const r=await fetch(`/api/v1/public/giving/donations/${encodeURIComponent(id)}/status?token=${encodeURIComponent(token)}`);const j=await r.json();if(!r.ok)return;this.status=j.data.status;this.statusTitle=this.status==='confirmed'?'Thank you—your donation is confirmed':this.status==='failed'?'The payment was not completed':'Your payment is still pending';this.statusMessage=this.status==='confirmed'?(j.data.receipt_status==='issued'?'Your receipt is ready.':'A receipt will be issued to the supplied email address.'):this.status==='failed'?'You may return to the form and try again.':'We are waiting for secure confirmation from the provider.';}catch(e){}}}}
    </script>
</x-layouts.public>
