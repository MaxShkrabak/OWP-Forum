import './assets/stylesheet.css'
import './assets/media.css'

import { createApp } from 'vue'
import App from './App.vue'
import router from './router'

import PrimeVue from 'primevue/config'
import Aura from '@primevue/themes/aura'
import 'primeicons/primeicons.css'
import 'quill/dist/quill.snow.css'

createApp(App).use(router).use(PrimeVue, { theme: { preset: Aura } }).mount('#app')