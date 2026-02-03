// main.js
import './assets/stylesheet.css'
import './assets/media.css'
import 'primeicons/primeicons.css'
import 'bootstrap-icons/font/bootstrap-icons.css'

import { createApp } from 'vue'
import App from './App.vue'
import router from './router'

const app = createApp(App)
app.use(router)
app.mount('#app')
