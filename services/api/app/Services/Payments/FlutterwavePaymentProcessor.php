<?php

namespace App\Services\Payments;

use App\Contracts\PaymentProcessor;
use App\Services\CurrencyAmount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FlutterwavePaymentProcessor implements PaymentProcessor
{
    public function __construct(private readonly CurrencyAmount $money) {}
    public function key(): string { return 'flutterwave'; }
    public function label(): string { return 'Flutterwave'; }
    public function supports(string $country, string $currency): bool { return preg_match('/^[A-Z]{2}$/', strtoupper($country)) === 1 && preg_match('/^[A-Z]{3}$/', strtoupper($currency)) === 1; }
    public function createCheckout(object $donation, array $context = []): array
    {
        $email=$donation->receipt_email??null; if(!$email) throw new RuntimeException('Flutterwave requires a donor email address.');
        $data=$this->client($context)->post('/payments',['tx_ref'=>$donation->internal_reference,'amount'=>$this->money->major((int)$donation->amount_minor,$donation->currency),'currency'=>strtoupper($donation->currency),'redirect_url'=>$context['success_url'],'customer'=>['email'=>$email,'name'=>$donation->donor_name??'Donor'],'customizations'=>['title'=>$context['fund_name']??'Ministry donation'],'meta'=>['donation_id'=>$donation->id]])->throw()->json('data');
        return ['id'=>$donation->internal_reference,'url'=>$data['link']];
    }
    public function verifyTransaction(object $donation, array $context = []): array
    {
        $id=$context['transaction_id']??$donation->provider_reference; $data=$this->client($context)->get('/transactions/'.rawurlencode((string)$id).'/verify')->throw()->json('data');
        return ['provider_reference'=>(string)$data['id'],'status'=>$this->normalizeStatus($data['status']??'pending'),'amount_minor'=>$this->money->minor($data['amount']??0,$data['currency']??$donation->currency),'currency'=>strtoupper($data['currency']??''),'customer_id'=>(string)($data['customer']['id']??'')];
    }
    public function handleWebhook(Request $request): array
    {
        $hash=(string)$request->attributes->get('provider_webhook_secret'); if($hash===''||!hash_equals($hash,(string)$request->header('verif-hash'))) throw new RuntimeException('Flutterwave webhook signature is invalid.');
        $body=$request->json()->all(); $data=$body['data']??[]; $currency=strtoupper($data['currency']??'USD');
        return ['event_id'=>($body['event']??'unknown').':'.($data['id']??hash('sha256',$request->getContent())).':'.($data['status']??''),'type'=>$body['event']??'unknown','donation_id'=>$data['meta']['donation_id']??null,'provider_reference'=>(string)($data['id']??''),'amount_minor'=>$this->money->minor($data['amount']??0,$currency),'currency'=>$currency,'status'=>$this->normalizeStatus($data['status']??'pending'),'customer_id'=>(string)($data['customer']['id']??''),'payload'=>$body];
    }
    public function refund(object $donation, ?int $amountMinor = null): array { $payload=$amountMinor===null?[]:['amount'=>$this->money->major($amountMinor,$donation->currency)]; return $this->client(['credentials'=>$donation->credentials??[]])->post('/transactions/'.rawurlencode($donation->provider_reference).'/refund',$payload)->throw()->json(); }
    public function normalizeStatus(string $status): string { return match(strtolower($status)) {'successful','succeeded','completed'=>'confirmed','failed'=>'failed','refunded'=>'refunded','reversed'=>'reversed',default=>'pending'}; }
    private function client(array $context): PendingRequest { $key=$context['credentials']['secret_key']??null; if(!$key) throw new RuntimeException('Flutterwave secret key is not configured.'); return Http::baseUrl('https://api.flutterwave.com/v3')->withToken($key)->acceptJson()->timeout(15)->retry(2,200); }
}
