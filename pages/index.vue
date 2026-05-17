<script setup lang="ts">
import type { ProductCardData } from '~/types/woocommerce'

useApfSeo({
  title: 'APF Press | Voices That Challenge. Ideas That Matter.',
  description: 'APF Press publishes bold academic books for critical inquiry, social justice, human rights, race, gender, law, health, and political economy.',
  path: '/',
  image: 'https://apfpress.com/wp-content/uploads/2026/04/apftypewriter-1024x1024.png'
})

const { data: products } = await useFetch<ProductCardData[]>('/api/products', {
  query: { kind: 'all' },
  default: () => []
})

const featuredProducts = computed(() => products.value.slice(0, 6))

const features = [
  {
    icon: 'mdi-school-outline',
    title: 'For faculty and students',
    text: 'Books shaped for university and college readers, with critical frameworks that support teaching, research, and public debate.'
  },
  {
    icon: 'mdi-scale-balance',
    title: 'Critical inquiry',
    text: 'A catalog grounded in race, gender, human rights, law, health, youth studies, globalization, and social justice.'
  },
  {
    icon: 'mdi-book-open-variant',
    title: 'Print and digital access',
    text: 'Browse print editions and e-books from the APF Press WooCommerce catalog with clear book-first presentation.'
  }
]

const subjects = [
  { icon: 'mdi-account-heart-outline', title: 'Human Rights', text: 'Scholarship on rights, justice, dignity, and social transformation.' },
  { icon: 'mdi-gavel', title: 'Law & Justice', text: 'Critical perspectives on law, criminology, youth, and public institutions.' },
  { icon: 'mdi-earth', title: 'Global Economy', text: 'Political economy, trade, globalization, and development studies.' },
  { icon: 'mdi-heart-pulse', title: 'Health & Society', text: 'Health care, social determinants, equity, trauma, and lived experience.' }
]
</script>

<template>
  <div>
    <PageHero
      eyebrow="Since 1990"
      title="Voices That Challenge. Ideas That Matter."
      text="APF Press serves university and college faculty and students by publishing bold academic work across social justice, human rights, law, health, race, gender, and political economy."
      image="https://apfpress.com/wp-content/uploads/2026/04/apftypewriter-1024x1024.png"
      image-alt="Typewriter graphic for APF Press publishing"
    >
      <div class="hero-actions">
        <VBtn color="secondary" size="large" to="/shop-print-books/" prepend-icon="mdi-book-open-page-variant-outline">
          Browse Print Books
        </VBtn>
        <VBtn color="primary" size="large" variant="outlined" to="/shop-ebooks/" prepend-icon="mdi-tablet-cellphone">
          Browse E-books
        </VBtn>
      </div>
    </PageHero>

    <section class="content-band">
      <VContainer>
        <div class="feature-grid">
          <article v-for="feature in features" :key="feature.title" class="feature-item">
            <VIcon :icon="feature.icon" size="34" />
            <h2>{{ feature.title }}</h2>
            <p>{{ feature.text }}</p>
          </article>
        </div>
      </VContainer>
    </section>

    <section class="content-band content-band--soft">
      <VContainer>
        <div class="section-heading">
          <p class="eyebrow">
            Catalog
          </p>
          <h2>Recent and available titles</h2>
          <p>
            A book-forward view of the APF Press WooCommerce catalog, with direct paths to product details and purchasing.
          </p>
        </div>

        <div class="product-grid featured-books">
          <ProductCard
            v-for="product in featuredProducts"
            :key="product.id"
            :product="product"
          />
        </div>

        <div class="section-actions">
          <VBtn color="secondary" to="/shop-print-books/" append-icon="mdi-arrow-right">
            Shop Print Books
          </VBtn>
          <VBtn color="primary" variant="outlined" to="/shop-ebooks/" append-icon="mdi-arrow-right">
            Shop E-books
          </VBtn>
        </div>
      </VContainer>
    </section>

    <section class="content-band">
      <VContainer>
        <div class="section-heading">
          <p class="eyebrow">
            Areas of focus
          </p>
          <h2>Critical scholarship across disciplines</h2>
          <p>
            The refreshed design keeps APF Press academic and direct, with stronger hierarchy, cleaner catalog browsing, and the existing brand colors.
          </p>
        </div>

        <div class="subject-grid">
          <article v-for="subject in subjects" :key="subject.title" class="subject-item">
            <VIcon :icon="subject.icon" size="32" />
            <h2>{{ subject.title }}</h2>
            <p>{{ subject.text }}</p>
          </article>
        </div>
      </VContainer>
    </section>
  </div>
</template>
