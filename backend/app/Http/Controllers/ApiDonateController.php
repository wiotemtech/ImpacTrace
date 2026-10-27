<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ApiDonateController extends Controller
{
    public function createPayment(Request $request)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1000'],
            'description' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'callback_url' => ['required', 'url', 'max:2048'],
        ]);

        $baseUrl = 'https://pay.pesapal.com/v3';
        $consumerKey = '3fVVcSZWWA2O6feegWJjcDe2icPaciFu';
        $consumerSecret ='nXrULjEQntKrSvANjOvNPJru3ZQ=';
        $notificationId = '5fa9af76-bab4-4bb3-9bb6-d9cbd15019a5';

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

            $response = Http::acceptJson()
                ->withToken($token)
                ->connectTimeout(5)
                ->timeout(20)
                ->post($baseUrl.'/api/Transactions/SubmitOrderRequest', [
                    'id' => (string) Str::uuid(),
                    'currency' => 'UGX',
                    'amount' => $data['amount'],
                    'description' => $data['description'],
                    'callback_url' => $data['callback_url'],
                    'notification_id' => $notificationId,
                    'billing_address' => [
                        'email_address' => $data['email'] ?? 'admin@impactrace.site',
                        'country_code' => 'UG',
                        ''
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

        return response()->json([
            'data' => $result,
            'url' => $url,
            'order_tracking_id' => $orderId,
        ]);
    }
}
