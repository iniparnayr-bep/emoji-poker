<script setup>
import { ref, onMounted } from 'vue';
import { createSession, joinSession } from '../../composables/useGame';
import axios from 'axios';

const mode         = ref('create');
const sessionName  = ref('');
const playerName   = ref('');
const joinToken    = ref('');
const loading      = ref(false);
const error        = ref('');
const activeCount  = ref(null);

onMounted(async () => {
    // Restore session if stored
    const storedToken  = localStorage.getItem('sessionToken');
    const storedPlayer = localStorage.getItem('playerToken');
    if (storedToken && storedPlayer) {
        window.nav(`/game/${storedToken}`);
        return;
    }
    try {
        const { data } = await axios.get('/api/sessions/stats');
        activeCount.value = data.active;
    } catch {}
});

async function handleCreate() {
    if (!sessionName.value.trim() || !playerName.value.trim()) return;
    loading.value = true; error.value = '';
    try {
        const data = await createSession(sessionName.value.trim(), playerName.value.trim());
        window.nav(`/game/${data.token}`);
    } catch (e) {
        error.value = 'Could not reach the server — try again.';
    } finally { loading.value = false; }
}

async function handleJoin() {
    if (!joinToken.value.trim() || !playerName.value.trim()) return;
    loading.value = true; error.value = '';
    try {
        const data = await joinSession(joinToken.value.trim().toUpperCase(), playerName.value.trim());
        window.nav(`/game/${data.session.token}`);
    } catch (e) {
        error.value = e?.response?.status === 404
            ? "Session not found — check the room code."
            : 'Could not join — try again.';
    } finally { loading.value = false; }
}
</script>

<template>
  <div class="home">
    <!-- Live count -->
    <aside v-if="activeCount !== null" class="live-chip">
      <span class="live-dot"></span>
      <span class="label-xs">{{ activeCount }} active</span>
    </aside>

    <main class="stage">
      <!-- Hero -->
      <div class="hero">
        <div class="deck-deco" aria-hidden="true">
          <div class="deco-card dc1 mono">8</div>
          <div class="deco-card dc2 mono">5</div>
          <div class="deco-card dc3 mono">3</div>
        </div>
        <h1 class="hero-title">
          Estimate<br>
          <span class="hero-accent">together.</span>
        </h1>
        <p class="hero-sub">
          Spin up a room and start planning your sprints!<br>
          No ads, no signup required. Chat, plan, and vote for free!
        </p>
      </div>

      <!-- Action panel -->
      <section class="panel">
        <div class="tabs">
          <button :class="['tab', mode === 'create' && 'tab-active']" @click="mode = 'create'">
            <span class="label-xs">01 ·</span> Create
          </button>
          <button :class="['tab', mode === 'join' && 'tab-active']" @click="mode = 'join'">
            <span class="label-xs">02 ·</span> Join
          </button>
        </div>

        <div class="panel-body">
          <template v-if="mode === 'create'">
            <label class="field">
              <span class="label-xs">Session name</span>
              <input v-model="sessionName" type="text" placeholder="Sprint 42 · Pricing squad" @keydown.enter="handleCreate" />
            </label>
            <label class="field">
              <span class="label-xs">Your name</span>
              <input v-model="playerName" type="text" placeholder="Ryan" @keydown.enter="handleCreate" />
            </label>
            <button class="btn-primary" @click="handleCreate" :disabled="loading">
              {{ loading ? 'Creating…' : 'Deal the cards →' }}
            </button>
          </template>

          <template v-else>
            <label class="field">
              <span class="label-xs">Room code</span>
              <input v-model="joinToken" type="text" placeholder="AB3K9" class="mono"
                style="text-transform:uppercase" @input="joinToken = joinToken.toUpperCase()" @keydown.enter="handleJoin" />
            </label>
            <label class="field">
              <span class="label-xs">Your name</span>
              <input v-model="playerName" type="text" placeholder="Ryan" @keydown.enter="handleJoin" />
            </label>
            <div class="btn-row">
              <button class="btn-primary" @click="handleJoin" :disabled="loading">
                {{ loading ? 'Joining…' : 'Join →' }}
              </button>
              <button class="btn-ghost" @click="() => { joinSession(joinToken.toUpperCase(), ''); window.nav(`/game/${joinToken.toUpperCase()}`); }">
                Spectate
              </button>
            </div>
          </template>

          <p v-if="error" class="error-msg">{{ error }}</p>
        </div>
      </section>

      <!-- Feature blurb -->
      <section class="features">
        <div class="feature-card">
          <span class="feat-icon">🃏</span>
          <div><div class="feat-title">4 preset decks</div><div class="feat-sub">Fibonacci, T-Shirt, Powers of 2, Person-days</div></div>
        </div>
        <div class="feature-card">
          <span class="feat-icon">🗳️</span>
          <div><div class="feat-title">Collaborative voting</div><div class="feat-sub">Everyone votes simultaneously — no anchoring bias</div></div>
        </div>
        <div class="feature-card">
          <span class="feat-icon">🤖</span>
          <div><div class="feat-title">AI chat</div><div class="feat-sub">Ask the AI to estimate or answer questions in context</div></div>
        </div>
        <div class="feature-card">
          <span class="feat-icon">🎉</span>
          <div><div class="feat-title">Emoji reactions</div><div class="feat-sub">Throw emojis at teammates, or make it rain</div></div>
        </div>
      </section>

      <!-- CTA -->
      <div class="cta-banner">
        <span>Support further development of</span>
        <strong>emoji-poker.com</strong>
        <button class="btn-cta" @click="window.nav('/pro')">Upgrade to Pro</button>
      </div>
    </main>
  </div>
</template>

<style scoped>
.home { min-height: 100dvh; display: flex; flex-direction: column; padding: 1rem 1.25rem 3rem; overflow-y: auto; }

.live-chip {
  position: fixed; top: 1rem; right: 1rem;
  display: inline-flex; align-items: center; gap: 0.5rem;
  padding: 0.4rem 0.8rem;
  background: rgba(22,28,37,0.85); backdrop-filter: blur(12px);
  border: 1px solid var(--line); border-radius: 999px; font-size: 0.75rem; z-index: 50;
}
.live-dot { width: 7px; height: 7px; background: var(--lime); border-radius: 50%;
  box-shadow: 0 0 10px var(--lime); animation: pulse 2s ease-in-out infinite; }
@keyframes pulse { 50% { opacity: 0.4; transform: scale(0.75); } }

.stage { flex: 1; max-width: 860px; width: 100%; margin: 0 auto; display: flex; flex-direction: column; gap: 2rem; padding-top: 1.5rem; }

.hero { position: relative; }
.deck-deco {
  position: absolute; right: 0; top: 0; width: 160px; height: 160px;
  pointer-events: none; opacity: 0.85;
}
.deco-card {
  position: absolute; width: 64px; height: 90px; border-radius: 8px;
  background: linear-gradient(155deg, #1f2732, #141a22); border: 1px solid var(--line);
  display: flex; align-items: center; justify-content: center;
  font-size: 1.6rem; font-weight: 700; box-shadow: 0 4px 16px rgba(0,0,0,0.5);
}
.dc1 { top: 10px; right: 80px; transform: rotate(-12deg); color: var(--lime); }
.dc2 { top: 0;    right: 30px; transform: rotate(2deg);   color: var(--cream);
       border-color: var(--lime); box-shadow: 0 4px 20px var(--lime-dim); }
.dc3 { top: 15px; right: -5px; transform: rotate(14deg);  color: var(--violet); }

.hero-title {
  font-size: clamp(2.8rem, 9vw, 5.5rem); font-weight: 800;
  letter-spacing: -0.04em; font-variation-settings: 'opsz' 96;
  line-height: 0.92; margin-bottom: 1rem;
  animation: fade-up 0.6s cubic-bezier(0.34,1.56,0.64,1) both;
}
.hero-accent {
  background: linear-gradient(105deg, var(--lime) 0%, var(--ice) 50%, var(--violet) 100%);
  -webkit-background-clip: text; background-clip: text; color: transparent;
  font-style: italic; padding-right: 0.15em; display: inline-block;
}
.hero-sub { color: var(--cream-dim); font-size: 1rem; line-height: 1.55; max-width: 26rem;
  animation: fade-up 0.7s cubic-bezier(0.34,1.56,0.64,1) 0.1s both; }

/* Panel */
.panel {
  max-width: 36rem;
  background: rgba(22,28,37,0.75); backdrop-filter: blur(20px);
  border: 1px solid var(--line); border-radius: var(--radius-xl); overflow: hidden;
  animation: fade-up 0.8s cubic-bezier(0.34,1.56,0.64,1) 0.2s both;
}
.tabs { display: flex; gap: 0; padding: 0.5rem; border-bottom: 1px solid var(--line); background: var(--ink-deep); }
.tab {
  flex: 1; padding: 0.8rem 1rem; font-family: var(--font-display); font-weight: 600;
  font-size: 0.9rem; background: transparent; color: var(--muted); border: none;
  border-radius: var(--radius-md); cursor: pointer; transition: all 0.2s;
  display: flex; align-items: center; justify-content: center; gap: 0.5rem;
}
.tab:hover { color: var(--cream); }
.tab-active { background: var(--panel-raised); color: var(--lime); }
.panel-body { padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem; }
.field { display: flex; flex-direction: column; gap: 0.4rem; }
.field input {
  background: var(--ink-soft); border: 1px solid var(--line); color: var(--cream);
  padding: 0.8rem 1rem; font-family: var(--font-display); font-size: 0.95rem;
  border-radius: var(--radius-md); outline: none; transition: all 0.15s;
}
.field input::placeholder { color: var(--muted); }
.field input:focus { border-color: var(--lime); box-shadow: 0 0 0 3px var(--lime-dim); }
.btn-primary {
  padding: 0.9rem 1.25rem; font-family: var(--font-display); font-weight: 700;
  font-size: 1rem; background: var(--lime); color: var(--ink); border: none;
  border-radius: var(--radius-md); cursor: pointer;
  transition: all 0.2s cubic-bezier(0.34,1.56,0.64,1);
  box-shadow: 0 2px 0 rgba(0,0,0,0.35);
}
.btn-primary:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 6px 20px var(--lime-dim); }
.btn-primary:disabled { opacity: 0.7; cursor: wait; }
.btn-ghost {
  padding: 0.9rem 1.25rem; font-family: var(--font-display); font-weight: 700;
  font-size: 1rem; background: transparent; color: var(--cream-dim);
  border: 1px solid var(--line); border-radius: var(--radius-md); cursor: pointer;
  transition: all 0.15s;
}
.btn-ghost:hover { color: var(--lime); border-color: var(--lime); }
.btn-row { display: flex; gap: 0.5rem; }
.btn-row .btn-primary { flex: 1; }
.error-msg { color: var(--coral); font-size: 0.85rem; }

/* Features */
.features { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.6rem; max-width: 36rem; }
.feature-card {
  display: flex; align-items: flex-start; gap: 0.75rem; padding: 0.8rem 0.9rem;
  background: var(--panel); border: 1px solid var(--line); border-radius: var(--radius-md);
}
.feat-icon { font-size: 1.2rem; flex-shrink: 0; }
.feat-title { font-weight: 700; color: var(--cream); font-size: 0.88rem; }
.feat-sub { color: var(--muted); font-size: 0.75rem; margin-top: 0.1rem; line-height: 1.4; }

/* CTA */
.cta-banner {
  display: flex; align-items: center; flex-wrap: wrap; gap: 0.75rem;
  padding: 1rem 1.25rem; max-width: 36rem;
  background: var(--lime-dim); border: 1px solid rgba(212,255,58,0.2);
  border-radius: var(--radius-md); color: var(--cream-dim); font-size: 0.88rem;
}
.cta-banner strong { color: var(--lime); }
.btn-cta {
  margin-left: auto; padding: 0.45rem 0.9rem;
  background: var(--lime); color: var(--ink); border: none;
  border-radius: var(--radius-md); font-weight: 700; cursor: pointer;
  font-family: var(--font-display); font-size: 0.85rem;
  transition: all 0.15s;
}
.btn-cta:hover { transform: translateY(-1px); }

@keyframes fade-up { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }

@media (max-width: 600px) {
  .deck-deco { display: none; }
  .features { grid-template-columns: 1fr; }
  .home { padding: 1rem 0.9rem 2rem; }
}
</style>
