import './assets/stylesheet.css'
import './assets/media.css'

import { createApp } from 'vue'
import App from './App.vue'
import router from './router';

createApp(App).use(router).mount('#app')