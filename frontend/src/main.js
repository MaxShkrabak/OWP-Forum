import './assets/stylesheet.css'
import './assets/media.css'
import 'primeicons/primeicons.css'
import 'quill/dist/quill.snow.css'

import { createApp } from 'vue'
import PrimeVue from 'primevue/config'
import Aura from '@primevue/themes/aura';
import App from './App.vue'
import router from './router';

const app = createApp(App)
app.use(router)
app.use(PrimeVue, { theme: { preset: Aura } })
app.mount('#app')