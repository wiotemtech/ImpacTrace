<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FundraisingCampaign;
use App\Models\FundraisingDonation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FundraisingController extends Controller
{
    private function organizationId(Request $request): int
    {
        $user = $request->user();
        return (int) ($user->role === 'super_admin'
            ? ($request->header('X-Organization-Id') ?: $request->input('organization_id'))
            : $user->organization_id);
    }

    public function publicIndex()
    {
        return FundraisingCampaign::with('organization:id,name')
            ->where('status', 'published')
            ->latest('published_at')
            ->get();
    }

    public function publicShow(string $slug)
    {
        $campaign = FundraisingCampaign::with('organization:id,name')
            ->where('slug', $slug)->where('status', 'published')->firstOrFail();
        $campaign->setAttribute('paid_amount_ugx', (int) $campaign->donations()->where('status', 'paid')->where('currency', 'UGX')->sum('amount'));
        return $campaign;
    }

    public function initiateDonation(Request $request, string $slug)
    {
        $campaign = FundraisingCampaign::where('slug', $slug)->where('status', 'published')->firstOrFail();
        $data = $request->validate([
            'donor_name' => 'nullable|string|max:120',
            'donor_email' => 'nullable|email|max:160',
            'donor_phone' => 'nullable|string|max:40',
            'amount' => 'required|numeric|min:0.01|max:9999999999999.99',
            'currency' => 'required|in:USD,EUR,GBP,UGX,KES,ZAR',
            'method' => 'required|in:pesapal,momo_manual',
        ]);
        abort_if($data['method'] === 'momo_manual' && $data['currency'] !== 'UGX', 422, 'Manual mobile-money payments are only available in UGX.');
        $donation = $campaign->donations()->create([
            'donor_name' => $data['donor_name'] ?? null,
            'donor_email' => $data['donor_email'] ?? null,
            'donor_phone' => $data['donor_phone'] ?? null,
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'amount_ugx' => $data['currency'] === 'UGX' ? $data['amount'] : 0,
            'method' => $data['method'],
            'provider' => $data['method'] === 'pesapal' ? 'pesapal' : config('services.momo.provider'),
            'status' => 'pending',
        ]);

        if ($data['method'] === 'momo_manual') {
            return ['donation' => $donation, 'merchant_code' => config('services.momo.merchant_code'), 'ussd_code' => config('services.momo.ussd_code'), 'instructions' => 'Pay the displayed amount to the merchant code, then submit the last 5 characters of your transaction reference.'];
        }

        $checkout = config('services.pesapal.checkout_url');
        abort_unless($checkout, 503, 'PesaPal is not configured yet. Add PESAPAL_CHECKOUT_URL or connect the PesaPal API credentials.');
        $query = http_build_query([
            'donation_id' => $donation->id,
            'amount' => $donation->amount,
            'currency' => $donation->currency,
        ]);
        return ['donation' => $donation, 'checkout_url' => $checkout . (str_contains($checkout, '?') ? '&' : '?') . $query];
    }

    public function submitReference(Request $request, FundraisingDonation $donation)
    {
        $data = $request->validate(['last5_reference' => 'required|string|min:5|max:5']);
        $duplicate = FundraisingDonation::where('method', 'momo_manual')
            ->where('last5_reference', strtoupper($data['last5_reference']))
            ->whereIn('status', ['pending', 'paid'])->where('id', '<>', $donation->id)->exists();
        abort_if($duplicate, 422, 'This transaction reference has already been submitted.');
        $donation->update(['last5_reference' => strtoupper($data['last5_reference'])]);
        return ['donation' => $donation->fresh(), 'status' => 'pending_verification'];
    }

    public function adminIndex(Request $request)
    {
        $organizationId = $this->organizationId($request);
        abort_if(!$organizationId, 422, 'Select an organisation first.');
        return FundraisingCampaign::with('organization:id,name')->withCount('donations')->where('organization_id', $organizationId)->latest()->paginate(30);
    }

    public function adminDonations(Request $request)
    {
        $organizationId = $this->organizationId($request);
        abort_if(!$organizationId, 422, 'Select an organisation first.');
        return FundraisingDonation::with('campaign:id,title,organization_id')->where('status', 'pending')->whereHas('campaign', fn ($q) => $q->where('organization_id', $organizationId))->latest()->paginate(50);
    }

    public function uploadPhoto(Request $request)
    {
        $data = $request->validate(['photo' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120']);
        $path = $data['photo']->store('fundraising-campaigns', 'public');
        return response()->json(['url' => Storage::disk('public')->url($path)], 201);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->role === 'super_admin', 403, 'Only the Super Admin can create fundraising posts.');
        $organizationId = $this->organizationId($request);
        abort_if(!$organizationId, 422, 'Select an organisation first.');
        $data = $request->validate(['title' => 'required|string|max:255', 'summary' => 'required|string|max:1000', 'message' => 'required|string', 'photo_url' => 'required|url|max:2048', 'target_amount_ugx' => 'nullable|integer|min:100', 'show_progress' => 'boolean', 'status' => 'in:draft,published']);
        $data['organization_id'] = $organizationId;
        $data['created_by'] = $request->user()->id;
        $data['slug'] = Str::slug($data['title']) . '-' . Str::lower(Str::random(5));
        if (($data['status'] ?? 'draft') === 'published') $data['published_at'] = now();
        return response()->json(FundraisingCampaign::create($data), 201);
    }

    public function publish(Request $request, FundraisingCampaign $campaign)
    {
        abort_unless($request->user()->role === 'super_admin', 403);
        abort_unless($campaign->organization_id === $this->organizationId($request), 404);
        $campaign->update(['status' => 'published', 'published_at' => now()]);
        return $campaign->fresh();
    }

    public function financeSummary(Request $request)
    {
        $organizationId = $this->organizationId($request);
        abort_if(!$organizationId, 422, 'Select an organisation first.');
        $query = FundraisingDonation::where('status', 'paid')->whereHas('campaign', fn ($q) => $q->where('organization_id', $organizationId));
        return [
            'paid_count' => (clone $query)->count(),
            'by_currency' => (clone $query)->selectRaw('currency, COUNT(*) as count, SUM(amount) as total')->groupBy('currency')->get(),
            'by_campaign' => (clone $query)->with('campaign:id,title')->selectRaw('fundraising_campaign_id, currency, COUNT(*) as count, SUM(amount) as total')->groupBy('fundraising_campaign_id', 'currency')->get(),
        ];
    }

    public function verifyDonation(Request $request, FundraisingDonation $donation)
    {
        abort_unless($request->user()->role === 'super_admin', 403);
        abort_unless($donation->campaign->organization_id === $this->organizationId($request), 404);
        $data = $request->validate(['status' => 'required|in:paid,rejected', 'provider_reference' => 'nullable|string|max:120']);
        $donation->update(['status' => $data['status'], 'provider_reference' => $data['provider_reference'] ?? $donation->provider_reference, 'paid_at' => $data['status'] === 'paid' ? now() : null]);
        return ['donation' => $donation->fresh('campaign'), 'message' => $data['status'] === 'paid' ? 'Donation verified.' : 'Donation rejected.'];
    }

    public function webhook(Request $request)
    {
        $reference = $request->input('provider_reference');
        $donation = FundraisingDonation::where('provider_reference', $reference)->first();
        if (!$donation) return ['received' => true];
        if ($request->input('status') === 'paid' && $donation->status !== 'paid') $donation->update(['status' => 'paid', 'paid_at' => now()]);
        return ['received' => true];
    }
}
