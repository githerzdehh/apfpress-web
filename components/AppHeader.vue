<script setup lang="ts">
const drawer = ref(false)

const navItems = [
  { label: 'Home', to: '/', icon: 'mdi-home-outline' },
  { label: 'Editorial Board', to: '/editorial-board/', icon: 'mdi-account-group-outline' },
  { label: 'Print Books', to: '/shop-print-books/', icon: 'mdi-book-open-page-variant-outline' },
  { label: 'E-books', to: '/shop-ebooks/', icon: 'mdi-tablet-cellphone' },
  { label: 'Contact', to: '/contact-us/', icon: 'mdi-email-outline' }
]
</script>

<template>
  <VAppBar class="site-header" height="82" flat>
    <VContainer class="header-inner">
      <NuxtLink class="brand-link" to="/" aria-label="APF Press home">
        <img
          class="brand-logo"
          src="https://apfpress.com/wp-content/uploads/2025/07/apf_logo_2-orig.png"
          alt="APF Press"
        >
      </NuxtLink>

      <nav class="desktop-nav" aria-label="Primary navigation">
        <NuxtLink
          v-for="item in navItems"
          :key="item.to"
          class="nav-link"
          :to="item.to"
        >
          {{ item.label }}
        </NuxtLink>
      </nav>

      <VBtn
        class="mobile-menu"
        icon="mdi-menu"
        variant="text"
        aria-label="Open navigation"
        @click="drawer = true"
      />
    </VContainer>
  </VAppBar>

  <VNavigationDrawer
    v-model="drawer"
    temporary
    location="right"
    width="300"
  >
    <div class="drawer-brand">
      <img
        src="https://apfpress.com/wp-content/uploads/2025/07/apf_logo_2-orig.png"
        alt="APF Press"
      >
      <VBtn
        icon="mdi-close"
        variant="text"
        aria-label="Close navigation"
        @click="drawer = false"
      />
    </div>
    <VList nav>
      <VListItem
        v-for="item in navItems"
        :key="item.to"
        :to="item.to"
        :prepend-icon="item.icon"
        :title="item.label"
        @click="drawer = false"
      />
    </VList>
  </VNavigationDrawer>
</template>
