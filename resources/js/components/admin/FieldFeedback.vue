<script setup lang="ts">
defineProps<{ id: string; error?: string; warning?: string }>();
</script>

<template>
    <p v-if="error" :id="id" class="admin-field-message admin-field-error">{{ error }}</p>
    <p v-else-if="warning" :id="id" class="admin-field-message admin-field-warning">{{ warning }}</p>
</template>
