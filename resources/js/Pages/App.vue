<script setup>
/**
 * SPA shell — handles client-side routing via hash or pushState.
 * All non-auth pages land here. Inertia renders this once, then Vue router handles navigation.
 */
import { computed, defineAsyncComponent, onMounted, ref } from 'vue';
import { session } from '../composables/useGame';
import EmojiRainLayer from '../components/EmojiRainLayer.vue';
import ProNagModal from '../components/ProNagModal.vue';
import { showProNag } from '../composables/useProTier';
import Toast from 'primevue/toast';

// Simple client-side router
const path = ref(window.location.pathname);

window.addEventListener('popstate', () => { path.value = window.location.pathname; });

function nav(to) {
    window.history.pushState({}, '', to);
    path.value = to;
}
window.nav = nav; // make accessible from components

const currentView = computed(() => {
    if (path.value.startsWith('/game/')) return 'game';
    if (path.value.startsWith('/join/')) return 'join';
    if (path.value === '/pro') return 'pro';
    return 'home';
});

const token = computed(() => {
    const m = path.value.match(/\/(?:game|join)\/([A-Z0-9]+)/i);
    return m ? m[1].toUpperCase() : null;
});
</script>

<template>
  <div :class="session?.color || 'default'">
    <component
      :is="() => import(`../Pages/${currentView === 'home' ? 'Home/Index' : currentView === 'game' || currentView === 'join' ? 'Game/Index' : 'Pro/Index'}.vue`).then(m => m.default)"
      :token="token"
    />
    <EmojiRainLayer />
    <ProNagModal v-if="showProNag" />
    <Toast position="bottom-right" />
  </div>
</template>
