import { createApp } from 'vue';
import AddToCart from './components/AddToCart.vue';
import CartDrawer from './components/CartDrawer.vue';
import CheckoutSummary from './components/CheckoutSummary.vue';
import MobileNav from './components/MobileNav.vue';

document.querySelectorAll<HTMLElement>('[data-add-to-cart]').forEach((element) => {
    createApp(AddToCart, { offeringId: Number(element.dataset.offeringId), label: element.dataset.label }).mount(element);
});

const cart = document.querySelector('[data-cart-root]');
if (cart) createApp(CartDrawer).mount(cart);

const checkoutSummary = document.querySelector<HTMLElement>('[data-checkout-summary]');
if (checkoutSummary) createApp(CheckoutSummary, { initial: JSON.parse(checkoutSummary.dataset.quote ?? '{}') }).mount(checkoutSummary);

const mobileNav = document.querySelector('[data-mobile-nav]');
if (mobileNav) createApp(MobileNav).mount(mobileNav);
