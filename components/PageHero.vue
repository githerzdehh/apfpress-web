<script setup lang="ts">
defineProps<{
  eyebrow?: string
  title: string
  text: string
  image?: string
  imageAlt?: string
}>()
</script>

<template>
  <section class="page-hero">
    <VContainer>
      <div class="page-hero__grid" :class="{ 'page-hero__grid--with-image': image }">
        <div class="page-hero__content">
          <p v-if="eyebrow" class="eyebrow">
            {{ eyebrow }}
          </p>
          <h1>{{ title }}</h1>
          <p class="lede">
            {{ text }}
          </p>
          <slot />
        </div>

        <div v-if="image" class="page-hero__image">
          <img :src="image" :alt="imageAlt || ''">
        </div>
      </div>
    </VContainer>
  </section>
</template>
