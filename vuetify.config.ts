import { defineVuetifyConfiguration } from 'vuetify-nuxt-module/custom-configuration'

export default defineVuetifyConfiguration({
  icons: {
    defaultSet: 'mdi'
  },
  theme: {
    defaultTheme: 'apfLight',
    themes: {
      apfLight: {
        dark: false,
        colors: {
          primary: '#01579f',
          secondary: '#dd5f56',
          accent: '#dd5f56',
          surface: '#ffffff',
          background: '#ffffff',
          info: '#01579f',
          error: '#b3261e'
        }
      }
    }
  },
  defaults: {
    VBtn: {
      rounded: '0',
      elevation: 0
    },
    VCard: {
      rounded: '0',
      elevation: 0
    },
    VTextField: {
      variant: 'outlined',
      color: 'primary'
    },
    VTextarea: {
      variant: 'outlined',
      color: 'primary'
    },
    VSelect: {
      variant: 'outlined',
      color: 'primary'
    }
  }
})
