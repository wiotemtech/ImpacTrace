<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
      <div>
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-emerald-700">Accounts</p>
        <h1 class="mt-1 text-2xl font-semibold">Finance</h1>
        <p class="mt-1 text-sm text-slate-500">Review imports, organize categories and download a clean transaction document.</p>
      </div>
      <button @click="download" class="rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white">Download transactions</button>
    </div>

    <div class="grid gap-4 md:grid-cols-3">
      <div v-for="card in cards" :key="card.label" class="rounded-xl border bg-white p-5 shadow-sm">
        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ card.label }}</div>
        <div class="mt-2 text-2xl font-semibold text-slate-900">UGX {{ Number(card.value || 0).toLocaleString() }}</div>
      </div>
    </div>

    <section class="rounded-xl border bg-white p-5 shadow-sm">
      <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Payment accountability</p>
        <h2 class="mt-1 text-lg font-semibold">Reader payment revenue</h2>
        <p class="mt-1 text-sm text-slate-500">Amounts are grouped by their original payment currency and are not converted.</p>
      </div>
      <div class="mt-5 grid gap-3 sm:grid-cols-3">
        <div class="rounded-lg bg-slate-50 p-3"><div class="text-xs text-slate-500">Paid</div><div class="mt-1 font-semibold">{{ summary.payment_revenue?.paid_count || 0 }}</div></div>
        <div class="rounded-lg bg-amber-50 p-3"><div class="text-xs text-amber-700">Pending</div><div class="mt-1 font-semibold">{{ summary.payment_revenue?.pending_count || 0 }}</div></div>
        <div class="rounded-lg bg-rose-50 p-3"><div class="text-xs text-rose-700">Rejected</div><div class="mt-1 font-semibold">{{ summary.payment_revenue?.rejected_count || 0 }}</div></div>
      </div>
      <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <div v-for="item in summary.payment_revenue?.by_currency || []" :key="item.currency" class="rounded-lg bg-emerald-50 p-3">
          <div class="text-xs text-emerald-700">{{ item.currency }} verified revenue · {{ item.count }} payments</div>
          <div class="mt-1 font-semibold text-emerald-950">{{ item.currency }} {{ Number(item.total).toLocaleString() }}</div>
        </div>
      </div>
      <div class="mt-5 overflow-x-auto">
        <table class="w-full min-w-[620px] text-left text-sm">
          <thead class="border-b text-xs uppercase tracking-wide text-slate-500"><tr><th class="p-3">Method</th><th class="p-3">Currency</th><th class="p-3">Payment count</th><th class="p-3 text-right">Verified total</th></tr></thead>
          <tbody>
            <tr v-for="(item, key) in summary.payment_revenue?.by_method || {}" :key="key" class="border-b">
              <td class="p-3 capitalize">{{ item.method.replace('_', ' ') }}</td>
              <td class="p-3">{{ item.currency }}</td>
              <td class="p-3">{{ item.count }}</td>
              <td class="p-3 text-right font-semibold">{{ item.currency }} {{ Number(item.total).toLocaleString() }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section class="rounded-xl border border-amber-100 bg-[#fffdf6] p-5 shadow-sm">
      <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Fundraising</p>
        <h2 class="mt-1 text-lg font-semibold">Verified donations</h2>
        <p class="mt-1 text-sm text-slate-500">Donations remain in the currency selected by each donor; different currencies are never added together.</p>
      </div>
      <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <div v-for="item in fundraising.by_currency || []" :key="item.currency" class="rounded-lg bg-amber-100 p-3">
          <div class="text-xs text-amber-800">{{ item.currency }} raised · {{ item.count }} donations</div>
          <div class="mt-1 font-semibold text-amber-950">{{ item.currency }} {{ Number(item.total).toLocaleString() }}</div>
        </div>
      </div>
      <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <div v-for="item in fundraising.by_campaign || []" :key="`${item.fundraising_campaign_id}-${item.currency}`" class="rounded-lg bg-white p-3">
          <div class="text-sm font-semibold">{{ item.campaign?.title }}</div>
          <div class="mt-1 text-xs text-slate-500">{{ item.count }} verified donation{{ item.count === 1 ? '' : 's' }}</div>
          <div class="mt-2 font-semibold">{{ item.currency }} {{ Number(item.total).toLocaleString() }}</div>
        </div>
      </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-[1fr_22rem]">
      <section class="rounded-xl border bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b p-5">
          <div><h2 class="font-semibold">Transactions</h2><p class="mt-1 text-sm text-slate-500">Search by description, category or reference.</p></div>
          <input v-model="search" @keyup.enter="loadTransactions" placeholder="Search transactions..." class="rounded-lg border-slate-300" />
        </div>
        <div class="overflow-x-auto">
          <table class="w-full min-w-[760px] text-left text-sm">
            <thead class="border-b bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="p-3">Date</th><th class="p-3">Type</th><th class="p-3">Category</th><th class="p-3">Description</th><th class="p-3">Reference</th><th class="p-3 text-right">Amount</th></tr></thead>
            <tbody><tr v-for="row in transactions" :key="row.id" class="border-b last:border-0 hover:bg-slate-50"><td class="whitespace-nowrap p-3">{{ row.transaction_date }}</td><td class="p-3 capitalize">{{ row.type }}</td><td class="p-3">{{ row.category || 'Uncategorised' }}</td><td class="p-3">{{ row.description || '—' }}</td><td class="p-3">{{ row.reference || '—' }}</td><td class="p-3 text-right font-medium">{{ row.currency }} {{ Number(row.amount).toLocaleString() }}</td></tr></tbody>
          </table>
        </div>
        <p v-if="!transactions.length" class="p-8 text-center text-sm text-slate-500">No transactions found.</p>
      </section>
      <aside class="space-y-6">
        <section class="rounded-xl border bg-white p-5 shadow-sm">
          <h2 class="font-semibold">Manage categories</h2><p class="mt-1 text-sm text-slate-500">Create the categories your team uses for imports.</p>
          <form class="mt-4 space-y-3" @submit.prevent="createCategory"><input v-model="categoryForm.name" required placeholder="Category name" class="w-full" /><select v-model="categoryForm.type" class="w-full rounded-lg border-slate-300"><option value="expense">Expense</option><option value="income">Income</option></select><button class="w-full rounded-lg bg-[#0d1d2d] px-4 py-2 font-semibold text-white">Add category</button></form>
          <div class="mt-5 space-y-2"><div v-for="category in categories" :key="category.id" class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm"><span>{{ category.name }} <span class="text-xs text-slate-400">({{ category.type }})</span></span><button @click="removeCategory(category)" class="text-xs text-red-600">Remove</button></div><p v-if="!categories.length" class="text-sm text-slate-500">No custom categories yet.</p></div>
        </section>
        <section class="rounded-xl border bg-white p-5 shadow-sm">
          <h2 class="font-semibold">Import accounting export</h2><p class="mt-1 text-sm text-slate-500">CSV columns: Date, Type, Account, Category, Project, Reference, Description, Amount, Currency.</p>
          <input type="file" accept=".csv,.txt" @change="file = $event.target.files[0]" class="mt-4 block w-full text-sm" /><button @click="upload" :disabled="!file" class="mt-4 rounded-lg border px-4 py-2 text-sm font-semibold disabled:opacity-40">Import CSV</button><p v-if="message" class="mt-3 text-sm text-emerald-700">{{ message }}</p>
        </section>
      </aside>
    </div>
  </div>
</template>
<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import api from '../api/client'
import { useAuthStore } from '../store/auth'

const auth = useAuthStore()
const summary = ref({})
const fundraising = ref({})
const transactions = ref([])
const categories = ref([])
const file = ref(null)
const message = ref('')
const search = ref('')
const categoryForm = ref({ name: '', type: 'expense' })
const cards = computed(() => [
  { label: 'Income', value: summary.value.income },
  { label: 'Expenses', value: summary.value.expense },
  { label: 'Balance', value: summary.value.balance },
])

async function load () {
  const { data } = await api.get('/finance/summary')
  summary.value = data
  const fundraisingResponse = await api.get('/finance/fundraising')
  fundraising.value = fundraisingResponse.data
  await Promise.all([loadTransactions(), loadCategories()])
}

async function loadTransactions () {
  const { data } = await api.get('/finance/transactions', { params: { q: search.value } })
  transactions.value = data.data || data
}

async function loadCategories () {
  const { data } = await api.get('/finance/categories')
  categories.value = data.data || data
}

async function createCategory () {
  await api.post('/finance/categories', categoryForm.value)
  categoryForm.value = { name: '', type: 'expense' }
  loadCategories()
}

async function removeCategory (category) {
  if (window.confirm(`Remove ${category.name}?`)) {
    await api.delete(`/finance/categories/${category.id}`)
    loadCategories()
  }
}

async function upload () {
  const formData = new FormData()
  formData.append('file', file.value)
  try {
    const { data } = await api.post('/finance/import', formData, { headers: { 'Content-Type': 'multipart/form-data' } })
    message.value = `Imported ${data.import.rows_imported} rows.`
    await load()
  } catch (error) {
    message.value = error.response?.data?.message || 'Import failed.'
  }
}

async function download () {
  const response = await api.get('/finance/export', { responseType: 'blob' })
  const url = URL.createObjectURL(response.data)
  const link = document.createElement('a')
  link.href = url
  link.download = 'finance-transactions.csv'
  link.click()
  URL.revokeObjectURL(url)
}

watch(() => auth.selectedOrganizationId, load)
onMounted(load)
</script>
