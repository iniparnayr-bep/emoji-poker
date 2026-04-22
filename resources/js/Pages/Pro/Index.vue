<script setup>
import { ref } from 'vue';

const email   = ref('');
const loading = ref(false);
const done    = ref(false);

function handleSubmit() {
    if (!email.value.includes('@')) return;
    loading.value = true;
    // TODO: wire real backend
    setTimeout(() => { loading.value = false; done.value = true; }, 1200);
}
</script>

<template>
  <div class="pro-page">
    <div class="pro-topbar">
      <button class="back-btn" @click="window.nav('/')">← Back</button>
    </div>

    <div class="pro-scroll">
      <div class="pro-card">

        <div v-if="done" class="success-state">
          <div class="success-icon">🎉</div>
          <h2 class="success-title">You're on the list!</h2>
          <p class="success-sub">We'll email <strong>{{ email }}</strong> when Pro launches.</p>
          <button class="btn-primary" @click="window.nav('/')">Back to Planning Poker</button>
        </div>

        <template v-else>
          <div class="deck-logo" aria-hidden="true">
            <div class="deck-stack">
              <div class="deck-card dc3 mono">3</div>
              <div class="deck-card dc1 mono">8</div>
              <div class="deck-card dc2 mono">5</div>
            </div>
            <div class="deck-glow"></div>
          </div>

          <div class="pro-heading">
            <h1 class="pro-title">Planning Poker <span class="pro-accent">Pro</span></h1>
            <p class="pro-sub">Unlimited AI · faster responses · more features coming.</p>
          </div>

          <div class="features">
            <div class="feature"><span class="fi">🤖</span><div><div class="ft">Unlimited AI</div><div class="fd">No caps on <code>/ask</code> and <code>/estimate</code></div></div></div>
            <div class="feature"><span class="fi">⚡</span><div><div class="ft">Priority responses</div><div class="fd">Your AI calls skip the queue</div></div></div>
            <div class="feature"><span class="fi">📊</span><div><div class="ft">Session history <span class="badge-soon">soon</span></div><div class="fd">Export estimates after the meeting</div></div></div>
            <div class="feature"><span class="fi">🃏</span><div><div class="ft">Custom decks <span class="badge-soon">soon</span></div><div class="fd">Build your own estimation scale</div></div></div>
          </div>

          <div class="signup-form">
            <input v-model="email" type="email" placeholder="you@company.com" @keydown.enter="handleSubmit" :disabled="loading" />
            <button class="btn-primary" @click="handleSubmit" :disabled="loading || !email.includes('@')">
              {{ loading ? 'Joining…' : 'Get early access →' }}
            </button>
            <p class="signup-note">No credit card required to join the waitlist.</p>
            <p class="disclaimer">Unlimited AI is subject to fair-use rate limiting to protect our service from abuse.</p>
          </div>
        </template>

      </div>
    </div>
  </div>
</template>

<style scoped>
.pro-page { display: flex; flex-direction: column; height: 100dvh; overflow: hidden; background: var(--ink); }
.pro-topbar { flex-shrink: 0; padding: 0.75rem 1rem; border-bottom: 1px solid var(--line); }
.back-btn { background: var(--panel-raised); border: 1px solid var(--line); color: var(--cream-dim); padding: 0.45rem 0.9rem; border-radius: 999px; cursor: pointer; font-family: var(--font-display); font-size: 0.88rem; font-weight: 600; transition: all 0.15s; }
.back-btn:hover { color: var(--lime); border-color: var(--lime); }
.pro-scroll { flex: 1; overflow-y: auto; display: flex; align-items: flex-start; justify-content: center; padding: 1.5rem 1rem 2rem; }
.pro-card { width: 100%; max-width: 460px; background: var(--panel); border: 1px solid var(--line); border-radius: var(--radius-xl); box-shadow: var(--shadow-md); padding: 1.75rem 1.5rem; display: flex; flex-direction: column; gap: 1.25rem; }
.deck-logo { display: flex; flex-direction: column; align-items: center; padding: 1rem 0 0.5rem; }
.deck-stack { position: relative; width: 64px; height: 92px; }
.deck-card { position: absolute; width: 60px; height: 84px; border-radius: 8px; background: linear-gradient(155deg, #1f2732, #141a22); border: 1px solid var(--line); display: flex; align-items: center; justify-content: center; font-size: 1.55rem; font-weight: 700; box-shadow: 0 4px 16px rgba(0,0,0,0.5); }
.dc3 { top: 8px; left: -14px; color: var(--violet); z-index: 1; transform: rotate(-8deg); }
.dc1 { top: 8px; left: 18px;  color: var(--lime);   z-index: 1; transform: rotate(8deg); }
.dc2 { top: 0;   left: 2px;   color: var(--cream);  z-index: 2; border-color: var(--lime); box-shadow: 0 4px 24px var(--lime-dim), 0 0 0 1px var(--lime); }
.deck-glow { width: 80px; height: 20px; margin-top: 4px; background: radial-gradient(ellipse, var(--lime-dim) 0%, transparent 70%); }
.pro-heading { text-align: center; }
.pro-title { font-size: clamp(1.6rem, 6vw, 2.4rem); font-weight: 800; letter-spacing: -0.03em; color: var(--cream); line-height: 1.1; }
.pro-accent { background: linear-gradient(105deg, var(--lime) 0%, var(--ice) 60%, var(--violet) 100%); -webkit-background-clip: text; background-clip: text; color: transparent; font-style: italic; padding-right: 0.15em; display: inline-block; }
.pro-sub { margin-top: 0.4rem; color: var(--cream-dim); font-size: 0.88rem; }
.features { display: flex; flex-direction: column; gap: 0.45rem; }
.feature { display: flex; align-items: flex-start; gap: 0.75rem; padding: 0.7rem 0.85rem; background: var(--ink-soft); border: 1px solid var(--line); border-radius: var(--radius-md); }
.fi { font-size: 1.1rem; flex-shrink: 0; margin-top: 1px; }
.ft { font-weight: 700; color: var(--cream); font-size: 0.88rem; display: flex; align-items: center; gap: 0.4rem; }
.fd { color: var(--muted); font-size: 0.77rem; margin-top: 0.1rem; }
.fd code { font-family: var(--font-mono); color: var(--lime); background: var(--ink); padding: 1px 5px; border-radius: 3px; }
.badge-soon { font-size: 0.6rem; background: var(--marigold); color: var(--ink); padding: 1px 6px; border-radius: 999px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; }
.signup-form { display: flex; flex-direction: column; gap: 0.65rem; }
.signup-form input { background: var(--ink-soft); border: 1px solid var(--line); color: var(--cream); padding: 0.8rem 0.9rem; border-radius: var(--radius-md); font-family: var(--font-display); font-size: 1rem; outline: none; transition: all 0.15s; }
.signup-form input:focus { border-color: var(--lime); box-shadow: 0 0 0 3px var(--lime-dim); }
.signup-form input::placeholder { color: var(--muted); }
.btn-primary { padding: 0.9rem; font-family: var(--font-display); font-weight: 800; font-size: 1rem; background: var(--lime); color: var(--ink); border: none; border-radius: var(--radius-md); cursor: pointer; transition: all 0.2s; box-shadow: 0 2px 0 rgba(0,0,0,0.3); }
.btn-primary:hover:not(:disabled) { transform: translateY(-2px); }
.btn-primary:disabled { opacity: 0.4; cursor: not-allowed; }
.signup-note { color: var(--muted); font-size: 0.75rem; text-align: center; }
.disclaimer { color: var(--muted); font-size: 0.7rem; text-align: center; opacity: 0.7; line-height: 1.4; }
.success-state { display: flex; flex-direction: column; align-items: center; gap: 1rem; text-align: center; padding: 1rem 0; }
.success-icon { font-size: 2.5rem; }
.success-title { font-size: 1.5rem; font-weight: 800; letter-spacing: -0.025em; color: var(--cream); }
.success-sub { color: var(--cream-dim); font-size: 0.9rem; line-height: 1.5; }
.success-sub strong { color: var(--lime); }
</style>
