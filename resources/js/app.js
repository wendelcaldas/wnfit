import './bootstrap';
import { createApp } from 'vue';
import { createPinia } from 'pinia';

import App from './App.vue';
import ActionFeedback from './components/ActionFeedback.vue';
import { router } from './router';
import { inputMask } from './forms/inputMasks';

const app = createApp(App);

app.component('ActionFeedback', ActionFeedback);
app.directive('input-mask', inputMask);
app.use(createPinia());
app.use(router);
app.mount('#app');
