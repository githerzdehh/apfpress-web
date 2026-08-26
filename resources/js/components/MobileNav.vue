<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const open = ref(false);
const toggleButton = ref<HTMLButtonElement | null>(null);
let navigation: HTMLElement | null = null;

function close(): void { open.value = false; }
function onKeydown(event: KeyboardEvent): void { if (event.key === 'Escape') close(); }

watch(open, async (value) => {
    navigation?.classList.toggle('is-open', value);
    document.body.classList.toggle('nav-open', value);

    await nextTick();
    requestAnimationFrame(() => {
        if (open.value !== value) return;
        if (value) navigation?.querySelector<HTMLAnchorElement>('a')?.focus();
        else toggleButton.value?.focus();
    });
});

onMounted(() => {
    navigation = document.querySelector('#main-navigation');
    navigation?.querySelectorAll('a').forEach((link) => link.addEventListener('click', close));
    document.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    navigation?.classList.remove('is-open');
    navigation?.querySelectorAll('a').forEach((link) => link.removeEventListener('click', close));
    document.body.classList.remove('nav-open');
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <button ref="toggleButton" class="icon-button mobile-toggle" type="button" :aria-expanded="open" aria-controls="main-navigation" :aria-label="open ? 'Close navigation' : 'Open navigation'" @click="open = !open">
        <svg v-if="!open" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M4 7h16M4 12h16M4 17h16" /></svg>
        <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="m6 6 12 12M18 6 6 18" /></svg>
    </button>
    <button v-if="open" class="mobile-nav-backdrop" type="button" aria-label="Close navigation" @click="close"></button>
</template>
