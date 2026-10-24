import 'vuetify/styles'
import '@mdi/font/css/materialdesignicons.css'
import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'

export default createVuetify({
  components,
  directives,
  theme: {
    defaultTheme: 'uls',
    themes: {
      uls: {
        dark: false,
        colors: {
          primary: '#313945',
          secondary: '#2D52A8',
          background: '#F4F7FD',
          surface: '#FFFFFF',
          error: '#EF5350',
          warning: '#FFEAA7',
          success: '#57CC99',
          sidebar: '#161C5C',
        },
      },
    },
  },
  defaults: {
    VBtn: { variant: 'flat', rounded: 'lg', class: 'text-none' },
    VTextField: { variant: 'outlined', density: 'compact' },
    VSelect: { variant: 'outlined', density: 'compact' },
  },
})