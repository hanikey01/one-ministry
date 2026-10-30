<?php

namespace App\Http\Controllers;

use App\Services\PaymentProcessorRegistry;
use App\Services\DonationConfirmationService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class GivingController extends Controller
{
    public function funds(): JsonResponse
    {
        return response()->json(['data' => DB::table('giving_funds')->where('active', true)->where('public_visible',true)->orderBy('display_order')->get(['id','name','slug','description','currency','suggested_amounts','goal_amount_minor'])]);
    }

    public function options(Request $request, PaymentProcessorRegistry $processors): JsonResponse
    {
        $currency = strtoupper((string)$request->query('currency')); $country = strtoupper((string)$request->query('country'));
        abort_unless(preg_match('/^[A-Z]{3}$/',$currency)&&preg_match('/^[A-Z]{2}$/',$country),422);
        $rows=DB::table('payment_processor_configs')->where('enabled',true)->where('country',$country)->where('currency',$currency)->whereNotNull('credentials_encrypted')->orderBy('priority')->get();
        return response()->json(['data'=>$rows->filter(fn($row)=>array_key_exists($row->provider,$processors->all())&&$processors->get($row->provider)->supports($country,$currency))->map(fn($row)=>['provider'=>$row->provider,'display_name'=>$row->display_name?:$processors->get($row->provider)->label(),'mode'=>$row->mode])->values()]);
    }

    public function createFund(Request $request): JsonResponse|RedirectResponse
    {
        $data = $this->validateFund($request);
        $id = (string) Str::uuid();
        $data['slug']=$this->uniqueFundSlug($data['name']);
        DB::table('giving_funds')->insert([...$data,'id'=>$id,'created_by'=>$request->user()->id,'updated_by'=>$request->user()->id,'created_at'=>now(),'updated_at'=>now()]);
        $this->audit($request,'giving.fund_created','giving_funds',$id,['name'=>$data['name']]);
        $record = DB::table('giving_funds')->where('id', $id)->first();
        return $this->saved($request, $record, 'Donation purpose created.', 201);
    }

    public function updateFund(Request $request,string $fund): JsonResponse|RedirectResponse
    {
        abort_unless(DB::table('giving_funds')->where('id',$fund)->exists(),404);
        $data=$this->validateFund($request);
        $data['slug']=$this->uniqueFundSlug($data['name'],$fund);
        DB::table('giving_funds')->where('id',$fund)->update([...$data,'updated_by'=>$request->user()->id,'updated_at'=>now()]); $this->audit($request,'giving.fund_updated','giving_funds',$fund,array_keys($data));
        return $this->saved($request, DB::table('giving_funds')->where('id',$fund)->first(), 'Donation purpose updated.');
    }

    public function archiveFund(Request $request, string $fund): JsonResponse|RedirectResponse
    {
        abort_unless(DB::table('giving_funds')->where('id',$fund)->exists(),404);
        DB::table('giving_funds')->where('id',$fund)->update(['active'=>false,'public_visible'=>false,'updated_by'=>$request->user()->id,'updated_at'=>now()]);
        $this->audit($request,'giving.fund_archived','giving_funds',$fund,['active'=>false,'public_visible'=>false]);
        return $this->saved($request, ['id'=>$fund,'active'=>false], 'Donation purpose archived.');
    }

    public function saveProvider(Request $request,string $provider,PaymentProcessorRegistry $processors): JsonResponse|RedirectResponse
    {
        abort_unless(array_key_exists($provider,$processors->all()),404);
        $data=$request->validate(['configuration_id'=>['nullable','uuid'],'country'=>['required',Rule::in(array_keys(config('giving.countries',[])))],'currency'=>['required',Rule::in(array_keys(config('giving.currencies',[])))],'display_name'=>['nullable','string','max:120'],'mode'=>['required','in:test,live,sandbox'],'enabled'=>['sometimes','boolean'],'priority'=>['integer','min:0'],'credentials'=>['nullable','array']]);
        $data['country']=strtoupper($data['country']); $data['currency']=strtoupper($data['currency']);
        $existing=filled($data['configuration_id']??null)?DB::table('payment_processor_configs')->where('id',$data['configuration_id'])->where('provider',$provider)->first():DB::table('payment_processor_configs')->where('provider',$provider)->where('country',$data['country'])->where('currency',$data['currency'])->first();
        if(filled($data['configuration_id']??null)&&!$existing) abort(404);
        if(!$processors->get($provider)->supports($data['country'],$data['currency'])) throw ValidationException::withMessages(['currency'=>'This provider does not support the selected country and currency.']);
        $credentials=array_filter($data['credentials']??[],fn($value)=>is_string($value)&&trim($value)!=='');
        $encrypted=$existing->credentials_encrypted??null; if($credentials!==[]) $encrypted=Crypt::encryptString(json_encode($credentials,JSON_THROW_ON_ERROR)); if(($data['enabled']??false)&&!$encrypted) throw ValidationException::withMessages(['credentials'=>'Credentials are required before enabling this provider.']);
        $id=$existing->id??(string)Str::uuid(); DB::table('payment_processor_configs')->updateOrInsert(['id'=>$id],['provider'=>$provider,'country'=>$data['country'],'currency'=>$data['currency'],'display_name'=>$data['display_name']??null,'mode'=>$data['mode'],'enabled'=>$data['enabled']??false,'priority'=>$data['priority']??0,'credentials_encrypted'=>$encrypted,'connection_status'=>$encrypted?'unverified':'unconfigured','updated_by'=>$request->user()->id,'created_at'=>$existing->created_at??now(),'updated_at'=>now()]);
        $this->audit($request,'giving.provider_updated','payment_processor_configs',$id,['provider'=>$provider,'country'=>$data['country'],'currency'=>$data['currency'],'enabled'=>$data['enabled']??false,'credentials_changed'=>$credentials!==[]]);
        return $this->saved($request, ['id'=>$id,'provider'=>$provider,'country'=>$data['country'],'currency'=>$data['currency'],'enabled'=>$data['enabled']??false,'configured'=>(bool)$encrypted], 'Payment provider saved.');
    }

    public function disableProvider(Request $request, string $provider, string $config): JsonResponse|RedirectResponse
    {
        $record=DB::table('payment_processor_configs')->where('id',$config)->where('provider',$provider)->first(); abort_unless($record,404);
        DB::table('payment_processor_configs')->where('id',$config)->update(['enabled'=>false,'updated_by'=>$request->user()->id,'updated_at'=>now()]);
        $this->audit($request,'giving.provider_disabled','payment_processor_configs',$config,['provider'=>$provider,'enabled'=>false]);
        return $this->saved($request,['id'=>$config,'enabled'=>false],'Payment provider disabled.');
    }

    public function saveOfflineMethod(Request $request,?string $method=null): JsonResponse|RedirectResponse
    {
        $data=$request->validate(['label'=>['required','string','max:120'],'country'=>['nullable',Rule::in(array_keys(config('giving.countries',[])))],'currency'=>['nullable',Rule::in(array_keys(config('giving.currencies',[])))],'bank_name'=>['required','string','max:160'],'account_name'=>['required','string','max:160'],'account_number'=>['nullable','string','max:100'],'iban'=>['nullable','string','max:100'],'swift_bic'=>['nullable','string','max:32'],'routing_code'=>['nullable','string','max:100'],'instructions'=>['nullable','string','max:2000'],'active'=>['sometimes','boolean'],'display_order'=>['sometimes','integer','min:0']]);
        if(blank($data['account_number']??null)&&blank($data['iban']??null)) throw ValidationException::withMessages(['account_number'=>'Provide an account number or IBAN.']);
        $id=$method??(string)Str::uuid(); if($method) abort_unless(DB::table('offline_giving_methods')->where('id',$method)->exists(),404); $data['country']=filled($data['country']??null)?strtoupper($data['country']):null; $data['currency']=filled($data['currency']??null)?strtoupper($data['currency']):null; $data['active']=$request->boolean('active');
        DB::table('offline_giving_methods')->updateOrInsert(['id'=>$id],[...$data,'updated_by'=>$request->user()->id,'created_at'=>DB::table('offline_giving_methods')->where('id',$id)->value('created_at')??now(),'updated_at'=>now()]);
        $this->audit($request,'giving.offline_method_saved','offline_giving_methods',$id,['active'=>$data['active']??false,'country'=>$data['country'],'currency'=>$data['currency']]);
        return $this->saved($request,DB::table('offline_giving_methods')->where('id',$id)->first(),$method?'Bank-transfer details updated.':'Bank-transfer details created.',$method?200:201);
    }

    public function archiveOfflineMethod(Request $request, string $method): JsonResponse|RedirectResponse
    {
        abort_unless(DB::table('offline_giving_methods')->where('id',$method)->exists(),404);
        DB::table('offline_giving_methods')->where('id',$method)->update(['active'=>false,'updated_by'=>$request->user()->id,'updated_at'=>now()]);
        $this->audit($request,'giving.offline_method_archived','offline_giving_methods',$method,['active'=>false]);
        return $this->saved($request,['id'=>$method,'active'=>false],'Bank-transfer details archived.');
    }

    public function intent(Request $request, PaymentProcessorRegistry $processors): JsonResponse
    {
        $data = $request->validate([
            'fund_id' => ['required', 'uuid', 'exists:giving_funds,id'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
            'provider' => ['required', 'string', 'max:40'],
            'country' => ['required', 'string', 'size:2'],
            'receipt_email' => ['nullable', 'email', 'max:254'],
            'donor_name' => ['nullable', 'string', 'max:160'],
            'is_anonymous' => ['sometimes', 'boolean'],
        ]);
        $data['currency'] = strtoupper($data['currency']);
        $data['country'] = strtoupper($data['country']);
        if (! array_key_exists($data['provider'], $processors->all())) throw ValidationException::withMessages(['provider' => 'The selected payment provider is not available.']);
        $config = DB::table('payment_processor_configs')->where('provider', $data['provider'])->where('country', $data['country'])->where('currency', $data['currency'])->where('enabled', true)->whereNotNull('credentials_encrypted')->orderBy('priority')->first();
        if (! $config) throw ValidationException::withMessages(['provider' => 'This payment provider is not configured for the selected country and currency.']);
        $processor = $processors->get($data['provider']);
        if (! $processor->supports($data['country'], $data['currency'])) throw ValidationException::withMessages(['provider' => 'This provider does not support the selected country and currency.']);
        $fund = DB::table('giving_funds')->where('id', $data['fund_id'])->where('active', true)->where('public_visible', true)->first();
        if (! $fund) throw ValidationException::withMessages(['fund_id' => 'The selected donation purpose is not available.']);
        if (filled($fund->currency) && strtoupper($fund->currency) !== $data['currency']) throw ValidationException::withMessages(['currency' => 'The selected currency does not match this donation purpose.']);
        $id = (string) Str::uuid();
        $reference = 'DON-'.strtoupper(Str::random(16));
        $statusToken = Str::random(48);
        $returnUrl = route('public.giving');
        DB::table('donations')->insert([...collect($data)->except(['provider', 'country'])->all(), 'id' => $id, 'internal_reference' => $reference, 'anonymous_token' => hash('sha256', $statusToken), 'provider' => $data['provider'], 'status' => 'pending', 'return_url' => $returnUrl, 'expires_at' => now()->addMinutes(30), 'created_at' => now(), 'updated_at' => now()]);
        $donation = DB::table('donations')->where('id', $id)->first();
        $credentials = json_decode(Crypt::decryptString($config->credentials_encrypted), true, flags: JSON_THROW_ON_ERROR); $credentials['mode'] = $config->mode;
        $callbackUrl = route('giving.callback',['donation'=>$id,'status_token'=>$statusToken]);
        if($data['provider']==='stripe') $callbackUrl .= '&session_id={CHECKOUT_SESSION_ID}';
        $checkout = $processor->createCheckout($donation, ['credentials' => $credentials, 'success_url' => $callbackUrl, 'cancel_url' => $returnUrl.'?donation_id='.$id.'&status_token='.$statusToken.'&cancelled=1', 'fund_name' => $fund->name]);
        DB::table('donations')->where('id', $id)->update(['provider_reference' => $checkout['id'] ?? null, 'updated_at' => now()]);
        DB::table('payment_attempts')->insert(['id'=>(string)Str::uuid(),'donation_id'=>$id,'provider'=>$data['provider'],'provider_reference'=>$checkout['id']??null,'status'=>'checkout_created','response_metadata'=>json_encode(['checkout_url_created'=>filled($checkout['url']??null)],JSON_THROW_ON_ERROR),'expires_at'=>now()->addMinutes(30),'created_at'=>now(),'updated_at'=>now()]);

        return response()->json(['data' => ['donation_id' => $id, 'status_token' => $statusToken, 'internal_reference' => $reference, 'status' => 'pending', 'provider' => $data['provider'], 'next_action' => 'provider_checkout', 'checkout_url' => $checkout['url'] ?? null]], 201);
    }

    public function webhook(Request $request, string $provider, PaymentProcessorRegistry $processors, DonationConfirmationService $confirmation): JsonResponse
    {
        abort_unless(array_key_exists($provider, $processors->all()), 404);
        $config = DB::table('payment_processor_configs')->where('provider', $provider)->where('enabled', true)->whereNotNull('credentials_encrypted')->orderBy('priority')->first();
        abort_if(! $config, 503, 'Payment webhook verification is not configured.');
        $credentials = json_decode(Crypt::decryptString($config->credentials_encrypted), true, flags: JSON_THROW_ON_ERROR);
        $credentials['mode'] = $config->mode;
        $request->attributes->set('stripe_webhook_secret', $credentials['webhook_secret'] ?? '');
        $request->attributes->set('provider_webhook_secret', $credentials['webhook_secret'] ?? '');
        $request->attributes->set('provider_secret_key', $credentials['secret_key'] ?? '');
        $request->attributes->set('provider_credentials', $credentials);
        try { $result = $confirmation->apply($provider, $processors->get($provider)->handleWebhook($request)); }
        catch (\RuntimeException $exception) { abort(401, $exception->getMessage()); }
        return response()->json(['status' => $result], $result === 'already_processed' ? 200 : 202);
    }

    public function status(Request $request, string $donation): JsonResponse
    {
        $record = DB::table('donations')->where('id', $donation)->first(['id','internal_reference','status','provider','receipt_status','failure_reason','confirmed_at','anonymous_token']);
        abort_if(! $record, 404);
        $token = (string) $request->query('token');
        abort_unless($token !== '' && hash_equals((string)$record->anonymous_token, hash('sha256',$token)), 403);
        unset($record->anonymous_token);
        return response()->json(['data' => $record]);
    }

    public function callback(Request $request,string $donation,PaymentProcessorRegistry $processors,DonationConfirmationService $confirmation): RedirectResponse
    {
        $record=DB::table('donations')->where('id',$donation)->first(); abort_if(!$record,404); $token=(string)$request->query('status_token'); abort_unless($token!==''&&hash_equals((string)$record->anonymous_token,hash('sha256',$token)),403);
        $config=DB::table('payment_processor_configs')->where('provider',$record->provider)->where('enabled',true)->where('currency',$record->currency)->whereNotNull('credentials_encrypted')->first(); abort_if(!$config,503);
        $credentials=json_decode(Crypt::decryptString($config->credentials_encrypted),true,flags:JSON_THROW_ON_ERROR); $credentials['mode']=$config->mode; $processor=$processors->get($record->provider);
        try {
            if($record->provider==='paypal' && method_exists($processor,'capture')) $processor->capture($record,['credentials'=>$credentials,'order_id'=>$request->query('token')]);
            $context=['credentials'=>$credentials,'session_id'=>$request->query('session_id'),'transaction_id'=>$request->query('transaction_id'),'order_id'=>$request->query('token')];
            $verified=$processor->verifyTransaction($record,$context); $confirmation->apply($record->provider,['event_id'=>'callback:'.$record->provider.':'.($verified['provider_reference']??$record->provider_reference).':'.($verified['status']??'pending'),'type'=>'browser_callback_verified','donation_id'=>$record->id,'payload'=>['source'=>'verified_callback'],...$verified]);
        } catch (\Throwable $exception) { report($exception); }
        return redirect()->route('public.giving',['donation_id'=>$record->id,'status_token'=>$token]);
    }

    public function donations(): JsonResponse
    {
        return response()->json(['data' => DB::table('donations')->latest()->paginate(50)]);
    }

    public function reconcile(Request $request): JsonResponse
    {
        $data = $request->validate(['reference' => ['required', 'string', 'max:120', 'unique:reconciliation_batches,reference'], 'notes' => ['nullable', 'string', 'max:5000']]);
        $id = (string) Str::uuid();
        DB::table('reconciliation_batches')->insert([...$data, 'id' => $id, 'created_by' => $request->user()->id, 'reconciled_at' => now()]);

        return response()->json(['data' => DB::table('reconciliation_batches')->where('id', $id)->first()], 201);
    }

    private function uniqueFundSlug(string $name,?string $except=null): string { $base=Str::slug($name)?:'fund'; $slug=$base; $i=2; while(DB::table('giving_funds')->where('slug',$slug)->when($except,fn($q)=>$q->where('id','!=',$except))->exists()) $slug=$base.'-'.$i++; return $slug; }
    private function validateFund(Request $request): array
    {
        $data=$request->validate(['name'=>['required','string','max:160'],'description'=>['nullable','string','max:2000'],'currency'=>['nullable',Rule::in(array_keys(config('giving.currencies',[])))],'suggested_amounts_text'=>['nullable','string','max:160'],'suggested_amounts'=>['nullable','array','max:8'],'suggested_amounts.*'=>['integer','min:1'],'goal_amount_minor'=>['nullable','integer','min:1'],'public_visible'=>['sometimes','boolean'],'active'=>['sometimes','boolean'],'display_order'=>['sometimes','integer','min:0']]);
        $amountSource=$data['suggested_amounts']??preg_split('/[\s,]+/',(string)($data['suggested_amounts_text']??''),-1,PREG_SPLIT_NO_EMPTY);
        $amounts=collect($amountSource)->map(fn($amount)=>(int)$amount)->filter(fn($amount)=>$amount>0)->unique()->take(8)->values();
        unset($data['suggested_amounts_text'],$data['suggested_amounts']);
        $data['currency']=filled($data['currency']??null)?strtoupper($data['currency']):null;
        $data['suggested_amounts']=$amounts->isEmpty()?null:$amounts->toJson();
        $data['active']=$request->has('active')?$request->boolean('active'):$request->isMethod('post');
        $data['public_visible']=$request->has('public_visible')?$request->boolean('public_visible'):$request->isMethod('post');
        return $data;
    }
    private function saved(Request $request, mixed $data, string $message, int $status=200): JsonResponse|RedirectResponse
    {
        return $request->expectsJson()?response()->json(['data'=>$data],$status):redirect()->route('staff.giving')->with('success',$message);
    }
    private function audit(Request $request,string $action,string $type,string $id,array $changes): void { DB::table('audit_events')->insert(['id'=>(string)Str::uuid(),'actor_user_id'=>$request->user()->id,'action'=>$action,'subject_type'=>$type,'subject_id'=>$id,'changes_json'=>json_encode($changes,JSON_THROW_ON_ERROR),'ip_address'=>$request->ip(),'created_at'=>now()]); }
}
