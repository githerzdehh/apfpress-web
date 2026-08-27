<script setup lang="ts">
import { ref } from 'vue';
import { request } from '../lib/http';

const props = defineProps<{ offeringId: number; label?: string }>();
const loading = ref(false);
const error = ref('');

async function add(): Promise<void> {
    loading.value = true;
    error.value = '';
    try {
        const cart = await request('/api/cart/items', { method: 'POST', body: JSON.stringify({ offering_id: props.offeringId, quantity: 1 }) });
        window.dispatchEvent(new CustomEvent('apf:cart-updated', { detail: cart }));
        window.dispatchEvent(new CustomEvent('apf:cart-open'));
    } catch (caught) {
        error.value = caught instanceof Error ? caught.message : 'Unable to add this title.';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div>
        <button class="button button-small" type="button" :disabled="loading" @click="add">{{ loading ? 'Adding…' : (label || 'Add to cart') }}</button>
        <p v-if="error" class="error" role="alert">{{ error }}</p>
    </div>
</template>
