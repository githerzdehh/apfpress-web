<script setup lang="ts">
useApfSeo({
  title: 'Shop Print Books | APF Press',
  description: 'Browse APF Press print books from the WooCommerce catalog, including social justice, human rights, law, health, race, gender, and political economy titles.',
  path: '/shop-print-books/'
})
</script>

<template>
  <ProductListing
    kind="print"
    title="Shop Print Books"
    subtitle="Browse APF Press printed books from the WooCommerce catalog. Use search to find authors, titles, and subject areas."
    empty-message="No print books were found in the WooCommerce catalog."
  />
</template>
