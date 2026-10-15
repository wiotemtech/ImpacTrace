<template>
  <div class="min-h-screen bg-[#eef5f1] text-slate-900">
    <header class="bg-[#123f31] px-5 py-5 text-white">
      <div class="mx-auto flex max-w-5xl items-center justify-between gap-3">
        <RouterLink to="/" class="font-semibold"
          >ImpacTrace Knowledge Hub</RouterLink
        ><RouterLink to="/" class="text-sm text-emerald-100"
          >Back to hub</RouterLink
        >
      </div>
    </header>
    <main v-if="campaign" class="mx-auto max-w-5xl px-5 py-8">
      <div class="grid gap-8 lg:grid-cols-[1.15fr_.85fr]">
        <article
          class="overflow-hidden rounded-2xl border border-emerald-100 bg-white shadow-sm"
        >
          <img
            v-if="campaign.photo_url"
            :src="campaign.photo_url"
            :alt="campaign.title"
            class="h-64 w-full object-cover sm:h-80"
          />
          <div class="p-6 sm:p-8">
            <p
              class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-700"
            >
              {{ campaign.organization?.name }} fundraiser
            </p>
            <h1 class="mt-2 text-3xl font-bold">{{ campaign.title }}</h1>
            <p class="mt-3 text-lg leading-7 text-slate-600">
              {{ campaign.summary }}
            </p>
            <div
              class="mt-6 whitespace-pre-line text-[15px] leading-7 text-slate-700"
            >
              {{ campaign.message }}
            </div>
            <div
              v-if="campaign.show_progress && campaign.target_amount_ugx"
              class="mt-7 border-t pt-5"
            >
              <div class="flex justify-between text-sm">
                <span>Raised so far</span
                ><strong
                  >UGX
                  {{
                    Number(campaign.paid_amount_ugx || 0).toLocaleString()
                  }}
                  of
                  {{
                    Number(campaign.target_amount_ugx).toLocaleString()
                  }}</strong
                >
              </div>
              <div class="mt-2 h-2 rounded-full bg-slate-100">
                <div
                  class="h-2 rounded-full bg-emerald-600"
                  :style="{
                    width: `${Math.min(100, (campaign.paid_amount_ugx / campaign.target_amount_ugx) * 100)}%`,
                  }"
                ></div>
              </div>
            </div>
          </div>
        </article>
        <section
          class="h-fit rounded-2xl border border-amber-100 bg-[#fffdf6] p-6 shadow-sm sm:p-8"
        >
          <p
            class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700"
          >
            Support this cause
          </p>
          <h2 class="mt-2 text-2xl font-bold">Choose your contribution</h2>
          <div class="mt-5 grid grid-cols-3 gap-2">
            <button
              v-for="amount in [2000, 5000, 10000]"
              :key="amount"
              type="button"
              @click="form.amount_ugx = amount"
              :class="
                Number(form.amount_ugx) === amount
                  ? 'border-emerald-700 bg-emerald-50 text-emerald-800'
                  : 'border-slate-200 bg-white'
              "
              class="rounded-lg border px-2 py-3 text-sm font-semibold"
            >
              UGX {{ amount.toLocaleString() }}
            </button>
          </div>
          <label class="mt-4 block text-sm font-medium"
            >Amount (UGX)<input
              v-model.number="form.amount_ugx"
              required
              min="100"
              type="number"
              class="mt-1 w-full rounded-lg border p-3"
          /></label>
          <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <input
              v-model="form.donor_name"
              required
              placeholder="Sponsor's full name"
              class="rounded-lg border p-3"
            /><input
              v-model="form.donor_phone"
              placeholder="Phone number (optional)"
              class="rounded-lg border p-3"
            /><input
              v-model="form.donor_email"
              type="email"
              placeholder="Email (optional)"
              class="rounded-lg border p-3 sm:col-span-2"
            />
          </div>
         
          <button
            @click="donating"
            :disabled="loading"
            class="mt-5 w-full rounded-xl bg-[#176b4d] px-4 py-3 font-semibold text-white disabled:opacity-50"
          >
            Donate Now
          </button>
          <div
            v-if="manual"
            class="mt-5 overflow-hidden rounded-xl border border-slate-300 bg-white text-base"
          >
            <div class="border-b px-4 py-3 font-semibold underline">
              - &nbsp;MTN MOBILE MONEY
            </div>
            <div class="space-y-1 px-4 py-5">
              <p class="font-semibold tracking-wide">*165*4*4#</p>
              <p>Merchant Code: {{ manual.merchant_code || "99485612" }}</p>
              <p>Ref: {{ form.donor_name || "Sponsor's Full Name" }}</p>
              <p>Amount: UGX {{ Number(form.amount_ugx).toLocaleString() }}</p>
              <p>PIN</p>
            </div>
          </div>
          <p v-if="message" class="mt-4 text-sm font-medium text-emerald-800">
            {{ message }}
          </p>
          <p v-if="error" class="mt-4 text-sm text-red-700">{{ error }}</p>
        </section>
      </div>
    </main>
    <div v-else class="mx-auto max-w-5xl px-5 py-12 text-slate-500">
      Loading fundraiser...
    </div>
  </div>
</template>
<script setup>
import { onMounted, ref } from "vue";
import { useRoute } from "vue-router";
import publicApi from "../api/publicClient";
import { demoCampaign } from "../api/demoData";
import axios from 'axios';
const route = useRoute(),
  campaign = ref(null),
  loading = ref(false),
  manual = ref(null),
  message = ref(""),
  error = ref("");
const form = ref({
  donor_name: "",
  donor_email: "",
  donor_phone: "",
  amount_ugx: 5000,
  method: "momo_manual",
});
async function load() {
  try {
    const { data } = await publicApi.get(
      `/public/fundraisers/${route.params.slug}`,
    );
    campaign.value = data;
  } catch {
    campaign.value =
      route.params.slug === demoCampaign.slug ? demoCampaign : null;
    error.value = campaign.value ? "" : "This fundraiser is not available.";
  }
}
async function donate() {
  loading.value = true;
  manual.value = null;
  message.value = "";
  error.value = "";
  try {
    const { data } = await publicApi.post(
      `/public/fundraisers/${route.params.slug}/donate`,
      form.value,
    );
    if (data.checkout_url) window.location.href = data.checkout_url;
    else manual.value = data;
  } catch (e) {
    error.value = e.response?.data?.message || "Could not start the donation.";
  } finally {
    loading.value = false;
  }
}


async function donating() {
  if(form.value.amount_ugx < 1000) {
    error.value = "Amount must be at least UGX 1000.";
    return;
  }
  const data = {
    amount: form.value.amount_ugx,
    description: "Donation",
    email: form.value.donor_email,
    callback_url: "https://impactrace.site/donation-success",
  };

  const resp = await axios.post(
    "https://127.0.0.1:8000/api/initiate-donation",
    data
  );

  if(resp.data.url) {
    window.location.href = resp.data.url;
  }
  console.log(resp.data.url) ;
}
onMounted(load);
</script>
