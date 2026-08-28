<script setup lang="ts">
export interface ToastMessage { id: number; type: 'success' | 'error' | 'info'; title: string; message: string }
defineProps<{ messages: ToastMessage[] }>();
const emit = defineEmits<{ dismiss: [id: number] }>();
</script>

<template>
    <div class="admin-toast-stack" aria-live="polite" aria-atomic="false">
        <article v-for="message in messages" :key="message.id" class="admin-toast" :class="`admin-toast-${message.type}`" :role="message.type === 'error' ? 'alert' : 'status'">
            <span class="admin-toast-mark" aria-hidden="true">{{ message.type === 'success' ? '✓' : message.type === 'error' ? '!' : 'i' }}</span>
            <div><strong>{{ message.title }}</strong><p>{{ message.message }}</p></div>
            <button type="button" :aria-label="`Dismiss ${message.title}`" @click="emit('dismiss', message.id)">×</button>
        </article>
    </div>
</template>
