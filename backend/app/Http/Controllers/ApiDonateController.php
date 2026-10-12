<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
class ApiDonateController extends Controller
{
     public function createPayment(Request $request)
    {

        $amount = $request->input("amount");
        $description = $request->input("description");
        $email = $request->input("email");
        $callbackUrl = $request->input('callback_url');
        $consumerKey = $request->input('consumer_key');
        $consumerSecret = $request->input('consumer_secret_key');

        // processing requests
        $requiredKeys = ['amount', 'description', 'email'];

        foreach ($requiredKeys as $key) {
            if (! $request->has($key)) {
                return response()->json(['error' => 'Missing required key: '.$key], 400);
            }
        }

        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->post('https://pay.pesapal.com/v3/api/Auth/RequestToken', [
            'consumer_key' =>$consumerKey,
            'consumer_secret' => $consumerSecret,
        ]);

        $token = $response->json()['token'];
        // dd($token);


        // sending the payload to pesapal 
         $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->post('https://pay.pesapal.com/v3/api/Transactions/SubmitOrderRequest', [
            "id" => "AA1122-3344ZZ",
            "currency"=> "UGX",
            "amount"=> $amount,
            "description"=> $description  ,
            "callback_url"=>"https://www.myapplication.com/response-page",
            "redirect_mode"=>"",
            "notification_id"=>"5fa9af76-bab4-4bb3-9bb6-d9cbd15019a5",
            "branch" =>  "Store Name - HQ",
            "billing_address" => [
                "email_address"=> "john.doe@example.com",
                "phone_number"=> "0781260856",
                "country_code"=> "UG",
                "first_name"=> "John",
                "middle_name"=> "",
                "last_name"=> "Doe",
                "line_1"=> "Pesapal Limited",
                "line_2"=> "",
                "city"=> "",
                "state"=> "",
                "postal_code"=> "",
                "zip_code"=> ""
            ]
        ]);

        $url = '';
        $orderId = '';

        if($response->json()['status'] == 200){
          $url = $response->json()['redirect_url'];
          $orderId = $response->json()['order_tracking_id'];
        }

        return response()->json([
          'data'=> $response->json(),
          'url'=> $url,
          'order_tracking_id'=> $orderId,
        ]);
    }
}
