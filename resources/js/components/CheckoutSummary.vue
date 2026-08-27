<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { money, request } from '../lib/http';

interface Quote { currency: string; subtotal_amount: number; shipping_amount: number; shipping_method?: string; tax_amount: number; total_amount: number }
const props = defineProps<{ initial: Quote }>();
const quote = ref(props.initial);
const error = ref('');
let timer = 0;

async function refresh(): Promise<void> {
    window.clearTimeout(timer);
    timer = window.setTimeout(async () => {
        const country = (document.querySelector<HTMLSelectElement>('#country')?.value ?? 'CA');
        const region = (document.querySelector<HTMLInputElement>('#region')?.value ?? '');
        try {
            quote.value = await request<Quote>('/checkout/quote', { method: 'POST', body: JSON.stringify({ country, region }) });
            error.value = '';
        } catch (caught) { error.value = caught instanceof Error ? caught.message : 'Unable to update this quote.'; }
    }, 250);
}

onMounted(() => {
    document.querySelector('#country')?.addEventListener('change', refresh);
    document.querySelector('#region')?.addEventListener('input', refresh);
});
onBeforeUnmount(() => {
    document.querySelector('#country')?.removeEventListener('change', refresh);
    document.querySelector('#region')?.removeEventListener('input', refresh);
});
</script>

<template>
    <div>
        <div class="summary-line"><span>Subtotal</span><span>{{ money(quote.subtotal_amount, quote.currency) }}</span></div>
        <div class="summary-line"><span>{{ quote.shipping_method || 'Shipping' }}</span><span>{{ quote.shipping_amount ? money(quote.shipping_amount, quote.currency) : 'Free' }}</span></div>
        <div class="summary-line"><span>Applicable tax</span><span>{{ money(quote.tax_amount, quote.currency) }}</span></div>
        <div class="summary-line summary-total"><span>Total</span><span>{{ money(quote.total_amount, quote.currency) }}</span></div>
        <p v-if="error" class="error">{{ error }}</p>
    </div>
</template>
