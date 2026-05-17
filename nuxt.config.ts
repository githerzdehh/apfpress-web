export default defineNuxtConfig({
  compatibilityDate: '2025-05-15',
  devtools: { enabled: true },
  modules: ['vuetify-nuxt-module'],
  css: ['@mdi/font/css/materialdesignicons.min.css', '~/assets/styles/main.scss'],
  typescript: {
    typeCheck: true,
    strict: true
  },
  runtimeConfig: {
    public: {
      siteUrl: 'https://apfpress.com',
      wpBaseUrl: 'https://www.apfpress.com'
    }
  },
  app: {
    head: {
      htmlAttrs: { lang: 'en' },
      meta: [
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
        { name: 'theme-color', content: '#01579f' }
      ],
      link: [
        { rel: 'icon', type: 'image/png', href: 'https://apfpress.com/wp-content/uploads/2025/07/cropped-apf_logo_2-orig-32x32.png' },
        { rel: 'preconnect', href: 'https://apfpress.com' }
      ]
    }
  },
  vuetify: {
    vuetifyOptions: './vuetify.config.ts'
  }
})
