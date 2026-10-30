<?php

namespace App\Services\Payments;

use App\Contracts\PaymentProcessor;
use App\Services\CurrencyAmount;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PayPalPaymentProcessor implements PaymentProcessor
{
    public function __construct(private readonly CurrencyAmount $money) {}
    public function key(): string { return 'paypal'; }
    public function label(): string { return 'PayPal'; }
    public function supports(string $country, string $currency): bool { return preg_match('/^[A-Z]{2}$/',strtoupper($country))===1&&preg_match('/^[A-Z]{3}$/',strtoupper($currency))===1; }
    public function createCheckout(object $donation,array $context=[]): array
    {
        $client=$this->client($context); $response=$client->withHeaders(['PayPal-Request-Id'=>$donation->internal_reference])->post('/v2/checkout/orders',['intent'=>'CAPTURE','purchase_units'=>[['reference_id'=>$donation->id,'custom_id'=>$donation->internal_reference,'amount'=>['currency_code'=>strtoupper($donation->currency),'value'=>$this->money->major((int)$donation->amount_minor,$donation->currency)]]],'payment_source'=>['paypal'=>['experience_context'=>['return_url'=>$context['success_url'],'cancel_url'=>$context['cancel_url'],'user_action'=>'PAY_NOW']]]])->throw()->json();
        $approve=collect($response['links']??[])->firstWhere('rel','payer-action')??collect($response['links']??[])->firstWhere('rel','approve');
        return ['id'=>$response['id'],'url'=>$approve['href']??null];
    }
    public function verifyTransaction(object $donation,array $context=[]): array
    {
        $orderId=$context['order_id']??$donation->provider_reference; $data=$this->client($context)->get('/v2/checkout/orders/'.rawurlencode((string)$orderId))->throw()->json();
        $unit=$data['purchase_units'][0]??[]; $capture=$unit['payments']['captures'][0]??[]; $amount=$capture['amount']??$unit['amount']??[];
        return ['provider_reference'=>$data['id'],'status'=>$this->normalizeStatus($capture['status']??$data['status']??'pending'),'amount_minor'=>$this->money->minor($amount['value']??0,$amount['currency_code']??$donation->currency),'currency'=>strtoupper($amount['currency_code']??''),'customer_id'=>$data['payer']['payer_id']??null];
    }
    public function capture(object $donation,array $context=[]): array { return $this->client($context)->withHeaders(['PayPal-Request-Id'=>$donation->internal_reference.'-capture'])->post('/v2/checkout/orders/'.rawurlencode($donation->provider_reference).'/capture')->throw()->json(); }
    public function handleWebhook(Request $request): array
    {
        $context=['credentials'=>$request->attributes->get('provider_credentials',[])]; $body=$request->json()->all();
        $verify=$this->client($context)->post('/v1/notifications/verify-webhook-signature',['transmission_id'=>$request->header('paypal-transmission-id'),'transmission_time'=>$request->header('paypal-transmission-time'),'cert_url'=>$request->header('paypal-cert-url'),'auth_algo'=>$request->header('paypal-auth-algo'),'transmission_sig'=>$request->header('paypal-transmission-sig'),'webhook_id'=>$context['credentials']['webhook_id']??'','webhook_event'=>$body])->throw()->json();
        if(($verify['verification_status']??'')!=='SUCCESS') throw new RuntimeException('PayPal webhook signature is invalid.');
        $resource=$body['resource']??[]; $amount=$resource['amount']??$resource['seller_receivable_breakdown']['gross_amount']??[]; $currency=strtoupper($amount['currency_code']??'');
        return ['event_id'=>$body['id']??throw new RuntimeException('PayPal event ID is missing.'),'type'=>$body['event_type']??'unknown','donation_id'=>$resource['custom_id']??$resource['supplementary_data']['related_ids']['order_id']??null,'provider_reference'=>$resource['id']??null,'amount_minor'=>$currency?$this->money->minor($amount['value']??0,$currency):null,'currency'=>$currency,'status'=>$this->normalizeStatus($resource['status']??$body['event_type']??'pending'),'customer_id'=>$resource['payer']['payer_id']??null,'payload'=>$body,'occurred_at'=>$body['create_time']??now()];
    }
    public function refund(object $donation,?int $amountMinor=null): array { $payload=$amountMinor===null?[]:['amount'=>['value'=>$this->money->major($amountMinor,$donation->currency),'currency_code'=>$donation->currency]]; return $this->client(['credentials'=>$donation->credentials??[]])->post('/v2/payments/captures/'.rawurlencode($donation->provider_reference).'/refund',$payload)->throw()->json(); }
    public function normalizeStatus(string $status): string { $s=strtolower($status); return match(true) { in_array($s,['completed','payment.capture.completed'],true)=>'confirmed',str_contains($s,'refund')&&str_contains($s,'completed')=>'refunded',in_array($s,['denied','declined','failed','voided'],true)=>'failed',str_contains($s,'reversed')=>'reversed',default=>'pending'}; }
    private function client(array $context): PendingRequest
    {
        $c=$context['credentials']??[]; if(empty($c['client_id'])||empty($c['client_secret'])) throw new RuntimeException('PayPal credentials are not configured.'); $base=($c['mode']??'sandbox')==='live'?'https://api-m.paypal.com':'https://api-m.sandbox.paypal.com';
        $token=Http::baseUrl($base)->withBasicAuth($c['client_id'],$c['client_secret'])->asForm()->post('/v1/oauth2/token',['grant_type'=>'client_credentials'])->throw()->json('access_token');
        return Http::baseUrl($base)->withToken($token)->acceptJson()->timeout(15)->retry(2,200);
    }
}
