<script setup>
import { dismissProNag } from '../composables/useProTier';
function handleUpgrade() { dismissProNag(); window.nav('/pro'); }
</script>

<template>
  <div class="nag-backdrop">
    <div class="nag-card">
      <div class="nag-hero">
        <div class="nag-icon">✦</div>
        <span class="nag-badge label-xs">Free limit reached</span>
      </div>
      <h2 class="nag-title">You've used your 5 free AI moves</h2>
      <p class="nag-sub">Unlock unlimited <code>/ask</code> and <code>/estimate</code> with a <strong>Pro account</strong>.</p>
      <ul class="nag-perks">
        <li><span class="check">✓</span> Unlimited AI interactions</li>
        <li><span class="check">✓</span> Faster responses</li>
        <li><span class="check">✓</span> Session history <span class="soon">soon</span></li>
        <li><span class="check">✓</span> Custom deck types <span class="soon">soon</span></li>
      </ul>
      <button class="btn-upgrade" @click="handleUpgrade">Create a Pro account →</button>
      <button class="btn-dismiss" @click="dismissProNag()">Not now — I'll play without AI</button>
      <p class="disclaimer">Unlimited AI is subject to fair-use rate limiting to protect our service from abuse.</p>
    </div>
  </div>
</template>

<style scoped>
.nag-backdrop {
  position: fixed; inset: 0; background: rgba(6,9,13,0.85);
  backdrop-filter: blur(10px); z-index: 200;
  display: flex; align-items: center; justify-content: center; padding: 1.25rem;
}
.nag-card {
  background: var(--panel); border: 1px solid var(--line); border-radius: var(--radius-xl);
  box-shadow: var(--shadow-md); width: 100%; max-width: 420px; padding: 2rem;
  display: flex; flex-direction: column; align-items: center; text-align: center; gap: 1rem;
  animation: slideUp 0.25s cubic-bezier(0.34,1.56,0.64,1);
}
@keyframes slideUp { from { opacity:0; transform: translateY(16px) scale(0.97); } to { opacity:1; transform: none; } }
.nag-hero { display: flex; flex-direction: column; align-items: center; gap: 0.5rem; }
.nag-icon {
  width: 60px; height: 60px; border-radius: 50%;
  background: var(--lime-dim); border: 1px solid var(--lime);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.8rem; color: var(--lime); font-weight: 900;
}
.nag-badge { color: var(--lime); padding: 3px 10px; background: var(--lime-dim); border: 1px solid var(--lime); border-radius: 999px; }
.nag-title { font-size: 1.5rem; font-weight: 800; letter-spacing: -0.025em; color: var(--cream); line-height: 1.1; }
.nag-sub { color: var(--cream-dim); font-size: 0.95rem; }
.nag-sub code { background: var(--ink-soft); padding: 1px 6px; border-radius: 4px; font-family: var(--font-mono); color: var(--lime); }
.nag-sub strong { color: var(--cream); }
.nag-perks { list-style: none; width: 100%; background: var(--ink-soft); border: 1px solid var(--line); border-radius: var(--radius-md); padding: 0.9rem 1.1rem; display: flex; flex-direction: column; gap: 0.5rem; text-align: left; }
.nag-perks li { display: flex; align-items: center; gap: 0.6rem; font-size: 0.9rem; color: var(--cream); }
.check { color: var(--lime); font-weight: 800; }
.soon { font-size: 0.65rem; background: var(--marigold); color: var(--ink); padding: 1px 6px; border-radius: 999px; font-weight: 700; margin-left: auto; text-transform: uppercase; }
.btn-upgrade { width: 100%; padding: 1rem; font-family: var(--font-display); font-weight: 800; font-size: 1rem; background: var(--lime); color: var(--ink); border: none; border-radius: var(--radius-md); cursor: pointer; transition: all 0.2s cubic-bezier(0.34,1.56,0.64,1); }
.btn-upgrade:hover { transform: translateY(-2px); }
.btn-dismiss { background: transparent; border: none; color: var(--muted); font-family: var(--font-display); font-size: 0.85rem; cursor: pointer; text-decoration: underline; text-underline-offset: 3px; }
.disclaimer { color: var(--muted); font-size: 0.68rem; opacity: 0.65; line-height: 1.4; max-width: 300px; }
</style>
