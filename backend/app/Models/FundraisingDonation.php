<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FundraisingDonation extends Model
{
    protected $fillable = ['fundraising_campaign_id', 'donor_name', 'donor_email', 'donor_phone', 'amount', 'currency', 'amount_ugx', 'method', 'provider', 'provider_reference', 'last5_reference', 'status', 'provider_payload', 'paid_at'];

    protected $casts = ['amount' => 'decimal:2', 'provider_payload' => 'array', 'paid_at' => 'datetime'];

    public function campaign()
    {
        return $this->belongsTo(FundraisingCampaign::class, 'fundraising_campaign_id');
    }
}
