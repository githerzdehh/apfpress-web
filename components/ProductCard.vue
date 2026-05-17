<script setup lang="ts">
import type { ProductCardData } from '~/types/woocommerce'

defineProps<{
  product: ProductCardData
}>()
</script>

<template>
  <article class="product-card">
    <a class="product-card__cover" :href="product.url" :aria-label="`View ${product.title}`">
      <img v-if="product.image" :src="product.image" :alt="product.imageAlt" loading="lazy">
      <VIcon v-else icon="mdi-book-open-page-variant-outline" size="64" />
    </a>

    <div class="product-card__body">
      <div class="product-card__meta">
        <span>{{ product.kind === 'ebook' ? 'E-book' : 'Print' }}</span>
        <span v-if="product.price">{{ product.price }}</span>
      </div>

      <h2>
        <a :href="product.url">{{ product.title }}</a>
      </h2>

      <p v-if="product.author" class="product-card__author">
        {{ product.author }}
      </p>
      <p class="product-card__excerpt">
        {{ product.excerpt }}
      </p>

      <div class="product-card__categories" aria-label="Product categories">
        <span
          v-for="category in product.categories.slice(0, 3)"
          :key="category.id"
        >
          {{ category.name }}
        </span>
      </div>

      <VBtn
        class="product-card__action"
        :href="product.url"
        color="primary"
        variant="outlined"
        append-icon="mdi-arrow-right"
      >
        {{ product.isPurchasable ? 'View or Buy' : 'View Details' }}
      </VBtn>
    </div>
  </article>
</template>
