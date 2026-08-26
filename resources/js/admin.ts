import { createApp } from 'vue';
import AdminApp from './components/AdminApp.vue';

const root = document.querySelector<HTMLElement>('#admin-app');
if (root) createApp(AdminApp, { user: JSON.parse(root.dataset.user ?? '{}') }).mount(root);
