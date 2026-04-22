<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const drops = ref([]);
let nextId = 0;

function launch(emoji, count = 40, durationMs = 3500) {
    const newDrops = [];
    for (let i = 0; i < count; i++) {
        newDrops.push({
            id: ++nextId,
            emoji,
            left: Math.random() * 100,
            delay: Math.random() * 1.2,
            duration: 2.2 + Math.random() * 1.6,
            size: 1.4 + Math.random() * 1.8,
        });
    }
    drops.value.push(...newDrops);
    setTimeout(() => {
        const ids = new Set(newDrops.map(d => d.id));
        drops.value = drops.value.filter(d => !ids.has(d.id));
    }, durationMs + 1500);
}

// Listen for rain events
const handler = (e) => launch(e.detail.emoji, e.detail.count, e.detail.durationMs);
onMounted(() => window.addEventListener('emoji-rain', handler));
onUnmounted(() => window.removeEventListener('emoji-rain', handler));

// Export launch so components can call it directly
window.launchEmojiRain = (emoji, opts) => {
    launch(emoji, opts?.count ?? 40, opts?.durationMs ?? 3500);
    window.dispatchEvent(new CustomEvent('emoji-rain', { detail: { emoji, ...opts } }));
};
</script>

<template>
  <div class="emoji-rain-layer" aria-hidden="true">
    <span
      v-for="d in drops"
      :key="d.id"
      class="emoji-rain-drop"
      :style="{
        left: d.left + 'vw',
        fontSize: d.size + 'rem',
        animationDuration: d.duration + 's',
        animationDelay: d.delay + 's',
      }"
    >{{ d.emoji }}</span>
  </div>
</template>
