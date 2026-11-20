<?php
return [
 'momo' => [
  'provider' => env('MOMO_PROVIDER',''),
  'merchant_code' => env('MOMO_MERCHANT_CODE','99485612'),
  'ussd_code' => env('MOMO_USSD_CODE','*165*3#'),
   'base_url' => env('MOMO_BASE_URL',''),
   'api_key' => env('MOMO_API_KEY',''),
   'api_secret' => env('MOMO_API_SECRET',''),
   'webhook_secret' => env('MOMO_WEBHOOK_SECRET',''),
 ],
 'pesapal' => [
  'base_url' => env('PESAPAL_BASE_URL','https://pay.pesapal.com/v3'),
  'consumer_key' => env('PESAPAL_CONSUMER_KEY',''),
  'consumer_secret' => env('PESAPAL_CONSUMER_SECRET',''),
  'checkout_url' => env('PESAPAL_CHECKOUT_URL',''),
  'ipn_id' => env('PESAPAL_IPN_ID',''),
 ],
];
