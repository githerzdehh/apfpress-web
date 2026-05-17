<script setup lang="ts">
useApfSeo({
  title: 'Shop E-books | APF Press',
  description: 'Browse APF Press e-books from the WooCommerce catalog, with direct links to product details and purchase options.',
  path: '/shop-ebooks/'
})
</script>

<template>
  <ProductListing
    kind="ebook"
    title="Shop E-books"
    subtitle="Browse APF Press digital editions from the WooCommerce catalog. Use search to find authors, titles, and subject areas."
    empty-message="No e-books were found in the WooCommerce catalog."
  />
</template>
