<script setup lang="ts">
import type { ProductKind } from '~/utils/products'
import type { ProductCardData } from '~/types/woocommerce'

const props = defineProps<{
  kind: ProductKind
  title: string
  subtitle: string
  emptyMessage: string
}>()

const search = ref('')
const sort = ref('title')

const { data: products, pending, error } = await useFetch<ProductCardData[]>('/api/products', {
  query: { kind: props.kind },
  default: () => []
})

const filteredProducts = computed(() => {
  const term = search.value.trim().toLowerCase()
  const next = products.value.filter((product) => {
    const haystack = `${product.title} ${product.author} ${product.excerpt} ${product.categories.map((category) => category.name).join(' ')}`.toLowerCase()
    return !term || haystack.includes(term)
  })

  if (sort.value === 'price') {
    return next.sort((a, b) => Number(Boolean(b.price)) - Number(Boolean(a.price)) || a.title.localeCompare(b.title))
  }

  return next.sort((a, b) => a.title.localeCompare(b.title))
})
</script>

<template>
  <section class="product-listing">
    <VContainer>
      <div class="section-heading">
        <p class="eyebrow">
          APF Press catalog
        </p>
        <h1>{{ title }}</h1>
        <p>{{ subtitle }}</p>
      </div>

      <div class="catalog-tools">
        <VTextField
          v-model="search"
          label="Search catalog"
          prepend-inner-icon="mdi-magnify"
          hide-details
          clearable
        />
        <VSelect
          v-model="sort"
          label="Sort"
          :items="[
            { title: 'Title', value: 'title' },
            { title: 'Purchasable first', value: 'price' }
          ]"
          hide-details
        />
      </div>

      <VAlert v-if="error" class="catalog-alert" type="error" variant="tonal">
        The catalog could not be loaded right now. Please try again later.
      </VAlert>

      <div v-else-if="pending" class="catalog-state">
        <VProgressCircular indeterminate color="primary" />
        <span>Loading books...</span>
      </div>

      <div v-else-if="filteredProducts.length" class="product-grid">
        <ProductCard
          v-for="product in filteredProducts"
          :key="product.id"
          :product="product"
        />
      </div>

      <VAlert v-else class="catalog-alert" type="info" variant="tonal">
        {{ emptyMessage }}
      </VAlert>
    </VContainer>
  </section>
</template>
