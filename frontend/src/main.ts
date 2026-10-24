import { createApp } from 'vue'
import { createPinia } from 'pinia'
import '@fontsource/nunito-sans/400.css'
import '@fontsource/nunito-sans/700.css'
import './assets/main.css'
import vuetify from './plugins/vuetify'
import router from './router'
import App from './App.vue'

createApp(App).use(createPinia()).use(router).use(vuetify).mount('#app')