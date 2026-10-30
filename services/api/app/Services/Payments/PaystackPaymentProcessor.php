<?php

namespace App\Services\Payments;

use App\Contracts\PaymentProcessor;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PaystackPaymentProcessor implements PaymentProcessor
{
    public function key(): string { return 'paystack'; }
    public function label(): string { return 'Paystack'; }
    public function supports(string $country, string $currency): bool { return in_array(strtoupper($country), ['NG','GH','ZA','KE'], true); }
    public function createCheckout(object $donation, array $context = []): array
    {
        $email = $donation->receipt_email ?? null;
        if (! $email) throw new RuntimeException('Paystack requires a donor email address.');
        $data = $this->client($context)->post('/transaction/initialize', ['email'=>$email,'amount'=>$donation->amount_minor,'currency'=>strtoupper($donation->currency),'reference'=>$donation->internal_reference,'callback_url'=>$context['success_url'],'metadata'=>['donation_id'=>$donation->id]])->throw()->json('data');
        return ['id'=>$data['reference'],'url'=>$data['authorization_url'],'access_code'=>$data['access_code'] ?? null];
    }
    public function verifyTransaction(object $donation, array $context = []): array
    {
        $data = $this->client($context)->get('/transaction/verify/'.rawurlencode($donation->internal_reference))->throw()->json('data');
        return ['provider_reference'=>$data['reference'],'status'=>$this->normalizeStatus($data['status'] ?? 'pending'),'amount_minor'=>(int)($data['amount'] ?? 0),'currency'=>strtoupper($data['currency'] ?? ''),'customer_id'=>(string)($data['customer']['id'] ?? '')];
    }
    public function handleWebhook(Request $request): array
    {
        $secret = (string) $request->attributes->get('provider_secret_key');
        $signature = (string) $request->header('x-paystack-signature');
        if ($secret === '' || ! hash_equals(hash_hmac('sha512', $request->getContent(), $secret), $signature)) throw new RuntimeException('Paystack webhook signature is invalid.');
        $body = $request->json()->all(); $data = $body['data'] ?? []; $reference = $data['reference'] ?? $data['transaction_reference'] ?? null;
        return ['event_id'=>($body['event'] ?? 'unknown').':'.($reference ?? hash('sha256',$request->getContent())).':'.($data['status'] ?? ''),'type'=>$body['event'] ?? 'unknown','donation_id'=>$data['metadata']['donation_id'] ?? null,'provider_reference'=>$reference,'amount_minor'=>(int)($data['amount'] ?? 0),'currency'=>strtoupper($data['currency'] ?? ''),'status'=>$this->normalizeStatus($data['status'] ?? ($body['event'] ?? 'pending')),'customer_id'=>(string)($data['customer']['id'] ?? ''),'payload'=>$body];
    }
    public function refund(object $donation, ?int $amountMinor = null): array { return $this->client(['credentials'=>$donation->credentials ?? []])->post('/refund',['transaction'=>$donation->provider_reference,'amount'=>$amountMinor])->throw()->json(); }
    public function normalizeStatus(string $status): string { return match(strtolower($status)) {'success','charge.success'=>'confirmed','failed','abandoned','refund.failed'=>'failed','reversed'=>'reversed','refund.processed','processed'=>'refunded',default=>'pending'}; }
    private function client(array $context): PendingRequest { $key=$context['credentials']['secret_key']??null; if(!$key) throw new RuntimeException('Paystack secret key is not configured.'); return Http::baseUrl('https://api.paystack.co')->withToken($key)->acceptJson()->timeout(15)->retry(2,200); }
}
