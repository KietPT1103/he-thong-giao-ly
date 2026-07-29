import './bootstrap';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';
import { useAuthStore } from './stores/authStore';
import { toast } from 'vue-sonner';

const pinia = createPinia();
createApp(App).use(pinia).use(router).mount('#app');

window.addEventListener('auth:expired', async () => {
    const auth = useAuthStore(pinia);
    auth.expireSession();
    toast.error('Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.');

    const currentPath = router.currentRoute.value.fullPath;
    if (router.currentRoute.value.path !== '/login') {
        await router.replace({
            path: '/login',
            query: { redirect: currentPath, reason: 'expired' },
        });
    }
});
