<?php

namespace App\Http\Controllers;

use App\Models\FundraisingCampaign;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ApiDonateController extends Controller
{
    public function createPayment(Request $request)
    {
        $data = $request->validate([
            'campaign_slug' => ['required', 'string', 'exists:fundraising_campaigns,slug'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999999.99'],
            'currency' => ['required', 'in:USD,EUR,GBP,UGX,KES,ZAR'],
            'description' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'donor_name' => ['nullable', 'string', 'max:120'],
            'donor_phone' => ['nullable', 'string', 'max:40'],
            'callback_url' => ['required', 'url', 'max:2048'],
        ]);

        $campaign = FundraisingCampaign::where('slug', $data['campaign_slug'])
            ->where('status', 'published')
            ->firstOrFail();
        $baseUrl = rtrim(config('services.pesapal.base_url'), '/');
        $consumerKey = config('services.pesapal.consumer_key');
        $consumerSecret = config('services.pesapal.consumer_secret');
        $notificationId = config('services.pesapal.ipn_id');

        if (! $consumerKey || ! $consumerSecret || ! $notificationId) {
            return response()->json([
                'error' => 'PesaPal is not configured. Set PESAPAL_CONSUMER_KEY, PESAPAL_CONSUMER_SECRET, and PESAPAL_IPN_ID.',
            ], 503);
        }

        try {
            $tokenResponse = Http::acceptJson()
                ->connectTimeout(5)
                ->timeout(20)
                ->post($baseUrl.'/api/Auth/RequestToken', [
                    'consumer_key' => $consumerKey,
                    'consumer_secret' => $consumerSecret,
                ]);

            $token = $tokenResponse->json('token');

            if (! $tokenResponse->successful() || ! $token) {
                return response()->json([
                    'error' => 'Could not authenticate with PesaPal.',
                ], 502);
            }

            $merchantReference = (string) Str::uuid();
            $response = Http::acceptJson()
                ->withToken($token)
                ->connectTimeout(5)
                ->timeout(20)
                ->post($baseUrl.'/api/Transactions/SubmitOrderRequest', [
                    'id' => $merchantReference,
                    'currency' => $data['currency'],
                    'amount' => $data['amount'],
                    'description' => $data['description'],
                    'callback_url' => $data['callback_url'],
                    'notification_id' => $notificationId,
                    'billing_address' => [
                        'email_address' => $data['email'] ?? 'admin@impactrace.site',
                        'country_code' => 'UG',
                    ],
                ]);
        } catch (ConnectionException $exception) {
            report($exception);

            return response()->json([
                'error' => 'Could not connect to PesaPal. Please try again later.',
            ], 502);
        }

        if (! $response->successful()) {
            return response()->json([
                'error' => 'PesaPal could not create the payment order.',
            ], 502);
        }

        $result = $response->json();
        $url = data_get($result, 'redirect_url');
        $orderId = data_get($result, 'order_tracking_id');

        if (! $url || ! $orderId) {
            return response()->json([
                'error' => 'PesaPal returned an incomplete payment order.',
            ], 502);
        }

        $campaign->donations()->create([
            'donor_name' => $data['donor_name'] ?? null,
            'donor_email' => $data['email'] ?? null,
            'donor_phone' => $data['donor_phone'] ?? null,
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'amount_ugx' => $data['currency'] === 'UGX' ? $data['amount'] : 0,
            'method' => 'pesapal',
            'provider' => 'pesapal',
            'provider_reference' => $orderId,
            'provider_payload' => $result,
            'status' => 'pending',
        ]);

        return response()->json([
            'data' => $result,
            'url' => $url,
            'order_tracking_id' => $orderId,
        ]);
    }
}
