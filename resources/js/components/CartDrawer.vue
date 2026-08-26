<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { money, request } from '../lib/http';

interface CartItem { id: number; quantity: number; line_amount: number; offering: { title: string; name: string; cover: string; price_amount: number } }
interface Cart { items: CartItem[]; item_count: number; subtotal_amount: number; currency: string }

const open = ref(false);
const loading = ref(false);
const error = ref('');
const drawer = ref<HTMLElement | null>(null);
let returnFocus: HTMLElement | null = null;
const cart = ref<Cart>({ items: [], item_count: 0, subtotal_amount: 0, currency: 'CAD' });
const empty = computed(() => cart.value.items.length === 0);

async function load(): Promise<void> {
    loading.value = true;
    try { cart.value = await request<Cart>('/api/cart'); }
    catch { error.value = 'Your cart could not be loaded.'; }
    finally { loading.value = false; }
}

async function update(item: CartItem, quantity: number): Promise<void> {
    try {
        cart.value = await request<Cart>(`/api/cart/items/${item.id}`, { method: 'PATCH', body: JSON.stringify({ quantity }) });
        window.dispatchEvent(new CustomEvent('apf:cart-updated', { detail: cart.value }));
    } catch (caught) { error.value = caught instanceof Error ? caught.message : 'Unable to update the cart.'; }
}

async function show(): Promise<void> {
    returnFocus = document.activeElement as HTMLElement | null;
    open.value = true;
    document.body.classList.add('drawer-open');
    await nextTick();
    drawer.value?.querySelector<HTMLElement>('[data-cart-close]')?.focus();
}
function hide(): void {
    open.value = false;
    document.body.classList.remove('drawer-open');
    returnFocus?.focus();
}
function onUpdated(event: Event): void { cart.value = (event as CustomEvent<Cart>).detail; }
function onKeydown(event: KeyboardEvent): void {
    if (!open.value) return;
    if (event.key === 'Escape') hide();
    if (event.key !== 'Tab' || !drawer.value) return;
    const focusable = [...drawer.value.querySelectorAll<HTMLElement>('a[href], button:not([disabled]), input:not([disabled])')];
    if (!focusable.length) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
}

onMounted(() => {
    load();
    window.addEventListener('apf:cart-open', show);
    window.addEventListener('apf:cart-updated', onUpdated);
    document.addEventListener('keydown', onKeydown);
});
onBeforeUnmount(() => {
    window.removeEventListener('apf:cart-open', show);
    window.removeEventListener('apf:cart-updated', onUpdated);
    document.removeEventListener('keydown', onKeydown);
    document.body.classList.remove('drawer-open');
});
</script>

<template>
    <button class="icon-button" type="button" aria-label="Open shopping cart" @click="show">
        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 3h2l2.4 11.3a2 2 0 0 0 2 1.6h7.8a2 2 0 0 0 2-1.6L21 7H6M10 21a1 1 0 1 1-2 0 1 1 0 0 1 2 0ZM19 21a1 1 0 1 1-2 0 1 1 0 0 1 2 0Z" /></svg>
        <span v-if="cart.item_count" class="cart-count">{{ cart.item_count }}</span>
    </button>
    <Transition name="drawer">
        <div v-if="open">
            <button class="cart-overlay" type="button" aria-label="Close cart" @click="hide"></button>
            <aside ref="drawer" class="cart-drawer" role="dialog" aria-modal="true" aria-labelledby="cart-title" aria-live="polite">
                <div class="drawer-head">
                    <h2 id="cart-title">Your cart</h2>
                    <button class="icon-button" data-cart-close type="button" aria-label="Close cart" @click="hide">×</button>
                </div>
                <p v-if="loading" class="muted">Loading your cart…</p>
                <p v-if="error" class="alert alert-error">{{ error }}</p>
                <div v-if="empty && !loading" class="section">
                    <p class="lead">Your cart is ready for a good book.</p>
                    <a class="button" href="/books" @click="hide">Explore the catalogue</a>
                </div>
                <div v-else>
                    <article v-for="item in cart.items" :key="item.id" class="cart-item">
                        <img v-if="item.offering.cover" class="cart-thumb" :src="item.offering.cover" :alt="`${item.offering.title} cover`">
                        <div v-else class="cart-thumb"></div>
                        <div>
                            <strong>{{ item.offering.title }}</strong>
                            <small class="muted">{{ item.offering.name }}</small>
                            <div class="quantity">
                                <button type="button" aria-label="Decrease quantity" @click="update(item, item.quantity - 1)">−</button>
                                <span>{{ item.quantity }}</span>
                                <button type="button" aria-label="Increase quantity" @click="update(item, item.quantity + 1)">+</button>
                            </div>
                        </div>
                        <strong>{{ money(item.line_amount, cart.currency) }}</strong>
                    </article>
                    <div class="cart-total"><span>Subtotal</span><span>{{ money(cart.subtotal_amount, cart.currency) }}</span></div>
                    <a class="button button-full" href="/checkout">Secure checkout</a>
                    <p class="help cart-help">Shipping and applicable tax are calculated at checkout.</p>
                </div>
            </aside>
        </div>
    </Transition>
</template>
