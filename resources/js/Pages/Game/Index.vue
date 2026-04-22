<script setup>
import { onMounted, onUnmounted, ref, computed, watch } from 'vue';
import {
    session, selfPlayer, messages, isOwner, isRevealed, isPro, isRosebud,
    joinSession, spectateSession, reconnect, leaveSession,
    submitEstimate, toggleReveal, updateSettings, kickPlayer, makeAdmin,
    sendMessage, uploadImage, throwEmoji, shakePlayer, updateSelf,
} from '../../composables/useGame';
import { canUseAi, recordAiUse, aiUsesLeft, showProNag } from '../../composables/useProTier';
import { useToast } from 'primevue/usetoast';

const props  = defineProps(['token']);
const toast  = useToast();

// UI state
const chatOpen      = ref(false);
const emojiOpen     = ref(false);
const settingsOpen  = ref(false);
const profileOpen   = ref(false);
const selectedCard  = ref(null);
const toggling      = ref(false);
const chatInput     = ref('');
const fileInput     = ref(null);
const shakingIds    = ref(new Set());
const throwItemsMap = ref({});
const joinName      = ref('');
const joinMode      = ref(false); // spectator joining as player

// Profile edit
const profileName   = ref('');
const profileAvatar = ref('');
const profileSaving = ref(false);

// Settings
const settingsValues = ref({});

// Emoji presets
const rainPresets = ['🎉','🔥','💰','🌧️','🍀','💜','⭐','💸','🎊','✨','💥','❤️'];
const throwPresets = ['🚀','🎉','🔥','❤️','👍','👎','🤣','💀','🎱','🍕','🫶','💩'];

const estimationOptions = computed(() => session.value?.estimationValues ?? []);
const playerCount = computed(() => session.value?.players?.length ?? 0);
const readyCount  = computed(() => session.value?.players?.filter(p => p.estimate !== null).length ?? 0);

// ── Bootstrap ─────────────────────────────────────────────────────────────────
onMounted(async () => {
    if (!props.token) { window.nav('/'); return; }

    const storedToken  = localStorage.getItem('sessionToken');
    const storedPlayer = localStorage.getItem('playerToken');

    try {
        if (storedToken === props.token && storedPlayer) {
            await reconnect(props.token, storedPlayer);
            toast.add({ severity: 'success', summary: 'Reconnected', life: 2000 });
        } else {
            // New visit — spectate until they provide name
            await spectateSession(props.token);
            joinMode.value = true;
        }
    } catch {
        toast.add({ severity: 'error', summary: 'Session not found', detail: 'Returning home…', life: 3000 });
        setTimeout(() => window.nav('/'), 3000);
    }

    // Listen for emoji throws targeting our players
    window.addEventListener('emoji-thrown', handleEmojiThrown);
});

onUnmounted(() => {
    window.removeEventListener('emoji-thrown', handleEmojiThrown);
});

watch(session, (s) => {
    if (s?.color) document.body.className = s.color;
    // Reset card selection on new round
    if (!s?.open && selectedCard.value) {
        const myEstimate = s?.players?.find(p => p.id === selfPlayer.value?.id)?.estimate;
        if (!myEstimate) selectedCard.value = null;
    }
}, { deep: true });

// ── Actions ───────────────────────────────────────────────────────────────────
async function handleReveal() {
    if (toggling.value) return;
    toggling.value = true;
    try { await toggleReveal(); }
    catch (e) {
        const status = e?.response?.status;
        toast.add({ severity: 'error', summary: status === 403 ? 'Only the host can reveal.' : 'Could not update session.', life: 3000 });
    }
    finally { setTimeout(() => toggling.value = false, 400); }
}

async function handleEstimate(val) {
    if (selectedCard.value === val) return;
    selectedCard.value = val;
    try { await submitEstimate(val); }
    catch { selectedCard.value = null; }
}

async function handleLeave() {
    await leaveSession();
    localStorage.clear();
    window.nav('/');
}

async function handleJoin() {
    if (!joinName.value.trim()) return;
    await joinSession(props.token, joinName.value.trim());
    joinMode.value = false;
}

function copyInvite() {
    const base = window.location.origin;
    navigator.clipboard.writeText(`${base}/join/${props.token}`).then(() => {
        toast.add({ severity: 'success', summary: `Link copied! Room code: ${props.token}`, life: 2500 });
    });
}

async function handleSend() {
    const text = chatInput.value.trim();
    if (!text) return;

    // AI command gate
    const isAi = text.startsWith('/ask') || text.startsWith('/estimate');
    if (isAi) {
        if (!canUseAi()) return;
        recordAiUse();
    }

    chatInput.value = '';
    try { await sendMessage(text); }
    catch { toast.add({ severity: 'error', summary: 'Message failed', life: 2000 }); }
}

async function handleImageSelect(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    try { await uploadImage(file); }
    catch { toast.add({ severity: 'error', summary: 'Image upload failed', life: 2000 }); }
}

async function handleThrow(playerId, emoji) {
    await throwEmoji(playerId, emoji);
}

async function handleRain(emoji) {
    window.launchEmojiRain?.(emoji);
    // also broadcast via throw to all clients
    const targetId = session.value?.players?.[0]?.id;
    if (targetId) await throwEmoji(targetId, '🌧' + emoji);
    emojiOpen.value = false;
}

function handleEmojiThrown(e) {
    const { id, emoji } = e.detail;
    if (emoji === '__shake__') {
        shakingIds.value.add(id);
        setTimeout(() => shakingIds.value.delete(id), 700);
        return;
    }
    if (emoji?.startsWith('🌧')) {
        window.launchEmojiRain?.(emoji.slice(1));
        return;
    }
    if (!throwItemsMap.value[id]) throwItemsMap.value[id] = [];
    const tid = Date.now();
    throwItemsMap.value[id].push({ id: tid, emoji });
    setTimeout(() => {
        throwItemsMap.value[id] = throwItemsMap.value[id]?.filter(t => t.id !== tid);
    }, 1200);
}

function openProfile() {
    profileName.value   = selfPlayer.value?.name ?? '';
    profileAvatar.value = selfPlayer.value?.avatar ?? '';
    profileOpen.value   = true;
}

async function saveProfile() {
    profileSaving.value = true;
    try {
        await updateSelf({ name: profileName.value, avatar: profileAvatar.value });
        profileOpen.value = false;
        toast.add({ severity: 'success', summary: 'Profile updated', life: 1500 });
    } catch (e) {
        const status = e?.response?.status;
        toast.add({ severity: 'error', summary: status === 409 ? 'Name already taken' : 'Update failed', life: 2000 });
    } finally { profileSaving.value = false; }
}

function openSettings() {
    settingsValues.value = {
        color:              session.value?.color ?? 'default',
        emojisEnabled:      session.value?.emojisEnabled ?? true,
        name:               session.value?.name ?? '',
        estimationOptions:  session.value?.estimationOptions ?? 'Fibonacci',
    };
    settingsOpen.value = true;
}

async function saveSettings() {
    await updateSettings(settingsValues.value);
    settingsOpen.value = false;
}

const avatarOptions = ['🦊','🐯','🦁','🐼','🐻','🐨','🐾','🦖','🐙','🦀','🐬','🐠','🐧','🦆','🦉','🐔','🦄','🐉','🐲','🐳','🐍','🐢','🦎','🦝','🐝','🐞','🐛','🦋','🚀','⭐','🌈','🔥'];
const colorOptions  = [
    { key: 'default', label: 'Neon',    swatch: '#d4ff3a' },
    { key: 'blue',    label: 'Arctic',  swatch: '#7be0ff' },
    { key: 'purple',  label: 'Violet',  swatch: '#b794ff' },
    { key: 'green',   label: 'Moss',    swatch: '#7dd87d' },
    { key: 'red',     label: 'Coral',   swatch: '#ff5a6b' },
    { key: 'gray',    label: 'Graphite',swatch: '#a8b0bd' },
    { key: 'dark',    label: 'Midnight',swatch: '#4a5668' },
];
const deckOptions = [
    { key: 'Fibonacci',   label: 'Fibonacci',     sample: '1 · 2 · 3 · 5 · 8 · 13' },
    { key: 'PowersOfTwo', label: 'Powers of 2',   sample: '1 · 2 · 4 · 8 · 16 · 32' },
    { key: 'TShirtSizes', label: 'T-Shirt',        sample: 'XS · S · M · L · XL' },
    { key: 'PersonDays',  label: 'Person-days',    sample: '0.5 · 1 · 2 · 3 · 4' },
];
</script>

<template>
  <div class="game-shell">
    <!-- ── Join overlay (spectator) ── -->
    <div v-if="joinMode" class="join-overlay">
      <div class="join-card">
        <div class="join-room-name">{{ session?.name ?? token }}</div>
        <h2 class="join-title">Enter your name to play</h2>
        <input v-model="joinName" type="text" placeholder="Your name" @keydown.enter="handleJoin" class="join-input" />
        <button class="btn-primary" @click="handleJoin" style="width:100%">Join →</button>
        <button class="btn-ghost" style="margin-top:0.5rem;width:100%" @click="joinMode = false">Spectate instead</button>
      </div>
    </div>

    <!-- ── Header ── -->
    <header class="topbar">
      <div class="tb-left">
        <div class="tb-session">
          <span class="label-xs">Session</span>
          <span class="tb-name">{{ session?.name }}</span>
        </div>
        <span v-if="isPro || isRosebud" class="pro-badge">{{ isRosebud ? '🌹 unlimited' : '✦ Pro' }}</span>
      </div>
      <div class="tb-right">
        <button v-if="selfPlayer" class="who-btn" @click="openProfile">
          <span class="who-avatar">{{ selfPlayer.avatar || '👤' }}</span>
          <span class="who-name">{{ selfPlayer.name }}</span>
          <span v-if="selfPlayer.isOwner" class="owner-pill label-xs">host</span>
        </button>
        <button class="icon-btn" @click="openSettings" title="Settings">⚙</button>
      </div>
    </header>

    <!-- ── Reveal bar ── -->
    <div v-if="isOwner && session" class="reveal-bar">
      <button
        class="reveal-btn"
        :class="{ pending: !isRevealed, done: isRevealed }"
        :disabled="toggling"
        @click="handleReveal"
      >
        <span>{{ isRevealed ? 'New round' : 'Reveal estimates' }}</span>
        <span v-if="!isRevealed">👁</span>
        <span v-else>🙈</span>
      </button>
    </div>

    <!-- ── Table ── -->
    <main class="board">
      <div class="board-head">
        <span class="label-xs">Table</span>
        <span class="board-count mono"><span class="ready">{{ readyCount }}</span> / {{ playerCount }}</span>
      </div>

      <div class="players" v-if="session">
        <div
          v-for="player in session.players"
          :key="player.id"
          :class="['player-tile', shakingIds.has(player.id) && 'shake', player.id === selfPlayer?.id && 'is-self']"
          @click="player.id !== selfPlayer?.id && (selectedThrowTarget = player.id); "
        >
          <!-- Avatar badge -->
          <div class="p-avatar">{{ player.avatar || '👤' }}</div>
          <!-- Card -->
          <div :class="['p-card', player.estimate !== null && 'p-card-ready', selectedCard === player.estimate && player.id === selfPlayer?.id && 'p-card-selected']">
            <span class="mono" v-if="isRevealed || player.id === selfPlayer?.id">
              {{ player.estimate === -1 ? '?' : player.estimate ?? '?' }}
            </span>
            <span class="mono" v-else>{{ player.estimate !== null ? '✓' : '?' }}</span>
          </div>
          <!-- Name + status -->
          <div class="p-name">
            {{ player.name }}
            <span v-if="player.id === selfPlayer?.id" class="self-pip">you</span>
          </div>
          <div class="p-status" :class="player.estimate !== null ? 'ready' : 'waiting'">
            <span class="status-dot"></span>
            {{ player.estimate !== null ? 'ready' : 'thinking' }}
          </div>

          <!-- Throw items animation -->
          <div v-for="t in throwItemsMap[player.id]" :key="t.id" class="throw-item fly-in">{{ t.emoji }}</div>
        </div>
      </div>
    </main>

    <!-- ── Estimate dock ── -->
    <div v-if="selfPlayer && !selfPlayer.isSpectator && !isRevealed" class="dock">
      <div class="dock-inner">
        <span class="label-xs dock-label">Pick your card</span>
        <div class="dock-cards">
          <div
            v-for="val in estimationOptions"
            :key="val"
            :class="['card', selectedCard === val && 'card-selected']"
            @click="handleEstimate(val)"
          >
            <span class="mono">{{ val }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ── FAB stack ── -->
    <div class="fab-stack" :class="selfPlayer && !isRevealed && 'fab-lifted'">
      <button class="fab" @click="copyInvite" title="Copy invite">🔗</button>
      <button v-if="session?.emojisEnabled" class="fab" :class="emojiOpen && 'active'" @click="emojiOpen = !emojiOpen" title="Emoji rain">{{ emojiOpen ? '✕' : '😊' }}</button>
      <button class="fab" :class="chatOpen && 'active'" @click="chatOpen = !chatOpen" title="Chat">{{ chatOpen ? '✕' : '💬' }}</button>
    </div>

    <!-- ── Emoji popover ── -->
    <transition name="pop">
      <div v-if="emojiOpen" class="emoji-popover" @click.self="emojiOpen = false">
        <div class="emoji-pop-card">
          <div class="pop-head"><span class="label-xs">Make it rain 🌧</span><button class="pop-close" @click="emojiOpen = false">✕</button></div>
          <div class="emoji-grid">
            <button v-for="e in rainPresets" :key="e" class="emoji-btn" @click="handleRain(e)">{{ e }}</button>
          </div>
        </div>
      </div>
    </transition>

    <!-- ── Chat drawer ── -->
    <transition name="chat-slide">
      <aside v-if="chatOpen" class="chat-drawer">
        <header class="chat-head">
          <span class="label-xs">Chat</span>
          <div class="chat-ai-left" :class="isRosebud ? 'rosebud' : aiUsesLeft <= 1 ? 'low' : ''">
            {{ isRosebud ? '🌹 unlimited' : `${aiUsesLeft} AI left` }}
          </div>
          <button class="pop-close" @click="chatOpen = false">✕</button>
        </header>
        <div class="chat-messages">
          <div v-if="!messages.length" class="chat-empty">Say something — or try <code>/ask</code></div>
          <div
            v-for="msg in messages"
            :key="msg.id ?? msg.timestamp"
            :class="['msg', msg.type === 'server' && 'msg-server', msg.type === 'ai' && 'msg-ai']"
          >
            <div class="msg-avatar">{{ msg.avatar || (msg.type === 'ai' ? '🤖' : msg.name === 'Server' ? '📣' : '👤') }}</div>
            <div class="msg-body">
              <div class="msg-head">
                <span class="msg-name">{{ msg.name }}</span>
                <span class="msg-time mono">{{ new Date(msg.timestamp).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'}) }}</span>
              </div>
              <div class="msg-text" v-html="msg.message"></div>
            </div>
          </div>
        </div>
        <div class="chat-cmds">
          <button class="cmd-chip" @click="chatInput = '/ask '">🤖 /ask</button>
          <button class="cmd-chip" @click="chatInput = '/estimate '">📊 /estimate</button>
          <button class="cmd-chip" @click="() => fileInput.click()">📎 Image</button>
          <input ref="fileInput" type="file" accept="image/*" style="display:none" @change="handleImageSelect" />
        </div>
        <div class="chat-input-row">
          <textarea v-model="chatInput" rows="1" placeholder="Message or / for commands" @keydown.enter.exact.prevent="handleSend"></textarea>
          <button class="send-btn" :class="chatInput.trim() && 'ready'" @click="handleSend">➤</button>
        </div>
      </aside>
    </transition>
    <transition name="chat-fade">
      <div v-if="chatOpen" class="chat-backdrop" @click="chatOpen = false"></div>
    </transition>

    <!-- ── Profile dialog ── -->
    <transition name="pop">
      <div v-if="profileOpen" class="dialog-backdrop" @click.self="profileOpen = false">
        <div class="dialog">
          <div class="dialog-head"><span class="label-xs">Your profile</span><h3 class="dialog-title">Who are you?</h3></div>
          <div class="dialog-body">
            <div class="avatar-preview"><span class="avatar-big">{{ profileAvatar || '👤' }}</span></div>
            <div class="avatar-grid">
              <button v-for="a in avatarOptions" :key="a" :class="['av-tile', profileAvatar === a && 'active']" @click="profileAvatar = a">{{ a }}</button>
            </div>
            <div class="field">
              <span class="label-xs">Display name</span>
              <input v-model="profileName" type="text" maxlength="50" placeholder="Your name" @keydown.enter="saveProfile" />
            </div>
          </div>
          <div class="dialog-actions">
            <button class="btn-danger" @click="handleLeave">🚪 Leave session</button>
            <div class="dialog-right">
              <button class="btn-ghost" @click="profileOpen = false">Cancel</button>
              <button class="btn-primary" @click="saveProfile" :disabled="profileSaving">{{ profileSaving ? '…' : 'Save' }}</button>
            </div>
          </div>
        </div>
      </div>
    </transition>

    <!-- ── Settings drawer ── -->
    <transition name="settings-slide">
      <aside v-if="settingsOpen" class="settings-drawer">
        <header class="settings-head">
          <div><span class="label-xs">Settings</span><h2 class="settings-title">Room controls</h2></div>
          <button class="pop-close" @click="settingsOpen = false">✕</button>
        </header>
        <div class="settings-body">
          <div v-if="!isOwner" class="settings-warn">Only the host can change these.</div>

          <!-- Rename -->
          <section class="setting-section">
            <span class="label-xs">Room name</span>
            <div class="rename-row">
              <input v-model="settingsValues.name" type="text" maxlength="60" :disabled="!isOwner" placeholder="Room name" @keydown.enter="saveSettings" />
              <button class="btn-save" :disabled="!isOwner" @click="saveSettings">Save</button>
            </div>
          </section>

          <!-- Deck -->
          <section class="setting-section">
            <span class="label-xs">Deck</span>
            <div class="deck-options">
              <button v-for="d in deckOptions" :key="d.key"
                :class="['deck-opt', settingsValues.estimationOptions === d.key && 'active']"
                :disabled="!isOwner" @click="settingsValues.estimationOptions = d.key">
                <div class="deck-opt-label">{{ d.label }}</div>
                <div class="deck-opt-sample mono">{{ d.sample }}</div>
                <span v-if="settingsValues.estimationOptions === d.key" class="deck-check">●</span>
              </button>
            </div>
          </section>

          <!-- Color -->
          <section class="setting-section">
            <span class="label-xs">Color theme</span>
            <div class="color-grid">
              <button v-for="c in colorOptions" :key="c.key"
                :class="['color-tile', settingsValues.color === c.key && 'active']"
                :disabled="!isOwner" @click="settingsValues.color = c.key">
                <span class="color-swatch" :style="{ background: c.swatch }"></span>
                <span>{{ c.label }}</span>
              </button>
            </div>
          </section>

          <!-- Emojis toggle -->
          <section class="setting-section">
            <div class="toggle-row">
              <div><div class="toggle-label">Enable emojis</div><div class="toggle-sub">Allow throwing and raining emojis</div></div>
              <button class="switch" :class="settingsValues.emojisEnabled && 'on'" :disabled="!isOwner" @click="settingsValues.emojisEnabled = !settingsValues.emojisEnabled">
                <span class="switch-knob"></span>
              </button>
            </div>
          </section>
        </div>
        <footer class="settings-footer">
          <button class="btn-primary" @click="saveSettings" :disabled="!isOwner">Apply changes</button>
        </footer>
      </aside>
    </transition>
    <div v-if="settingsOpen" class="settings-backdrop" @click="settingsOpen = false"></div>
  </div>
</template>

<style scoped>
/* ── Layout ── */
.game-shell { display: flex; flex-direction: column; height: 100dvh; overflow: hidden; position: relative; }

/* ── Join overlay ── */
.join-overlay { position: fixed; inset: 0; background: rgba(6,9,13,0.85); backdrop-filter: blur(8px); z-index: 150; display: flex; align-items: center; justify-content: center; padding: 1.25rem; }
.join-card { background: var(--panel); border: 1px solid var(--line); border-radius: var(--radius-xl); padding: 2rem; width: 100%; max-width: 360px; display: flex; flex-direction: column; gap: 1rem; }
.join-room-name { font-size: 0.75rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.1em; font-family: var(--font-mono); }
.join-title { font-size: 1.5rem; font-weight: 800; letter-spacing: -0.02em; }
.join-input { background: var(--ink-soft); border: 1px solid var(--line); color: var(--cream); padding: 0.8rem 1rem; border-radius: var(--radius-md); font-family: var(--font-display); font-size: 1rem; outline: none; }
.join-input:focus { border-color: var(--lime); box-shadow: 0 0 0 3px var(--lime-dim); }

/* ── Topbar ── */
.topbar { display: flex; align-items: center; gap: 1rem; padding: 0.85rem 1.25rem; background: linear-gradient(180deg, var(--panel), var(--ink)); border-bottom: 1px solid var(--line); flex-shrink: 0; min-height: 60px; z-index: 40; }
.tb-left { display: flex; align-items: center; gap: 0.75rem; flex: 1; min-width: 0; }
.tb-session { display: flex; flex-direction: column; min-width: 0; }
.tb-name { font-weight: 700; font-size: 1.1rem; letter-spacing: -0.02em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 14rem; }
.tb-right { display: flex; align-items: center; gap: 0.5rem; flex-shrink: 0; }
.who-btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.45rem 0.85rem; background: var(--ink-soft); border: 1px solid var(--line); color: var(--cream); border-radius: 999px; cursor: pointer; font-family: var(--font-display); font-size: 0.88rem; font-weight: 600; transition: all 0.15s; }
.who-btn:hover { border-color: var(--lime); color: var(--lime); }
.who-avatar { font-size: 1.1rem; }
.who-name { max-width: 7rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.owner-pill { background: var(--lime); color: var(--ink); padding: 2px 7px; border-radius: 999px; font-size: 0.6rem; }
.icon-btn { width: 38px; height: 38px; background: var(--ink-soft); border: 1px solid var(--line); color: var(--cream-dim); border-radius: 999px; cursor: pointer; font-size: 1rem; transition: all 0.2s; }
.icon-btn:hover { color: var(--lime); border-color: var(--lime); }

/* ── Reveal bar ── */
.reveal-bar { display: flex; justify-content: flex-end; padding: 0.75rem 1.25rem 0; flex-shrink: 0; max-width: 1200px; width: 100%; margin: 0 auto; }
.reveal-btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.7rem 1.4rem; font-family: var(--font-display); font-weight: 700; font-size: 0.95rem; border-radius: var(--radius-md); cursor: pointer; transition: all 0.2s cubic-bezier(0.34,1.56,0.64,1); border: 1px solid transparent; }
.reveal-btn.pending { background: var(--lime); color: var(--ink); border-color: var(--lime); animation: reveal-pulse 2.5s ease-in-out infinite; }
.reveal-btn.done { background: var(--panel-raised); color: var(--cream); border-color: var(--line); }
.reveal-btn.done:hover { border-color: var(--ice); color: var(--ice); }
.reveal-btn:disabled { opacity: 0.6; cursor: wait; }
@keyframes reveal-pulse { 0%,100% { box-shadow: 0 2px 0 rgba(0,0,0,0.3), 0 0 16px var(--lime-dim); } 50% { box-shadow: 0 2px 0 rgba(0,0,0,0.3), 0 0 28px var(--lime-glow); } }

/* ── Board ── */
.board { flex: 1; overflow-y: auto; padding: 1.25rem; max-width: 1200px; width: 100%; margin: 0 auto; padding-bottom: 14rem; }
.board-head { display: flex; align-items: baseline; gap: 0.75rem; margin-bottom: 1.5rem; }
.board-count { font-size: 1.1rem; color: var(--muted); }
.board-count .ready { color: var(--lime); font-weight: 700; }
.players { display: flex; flex-wrap: wrap; justify-content: center; gap: 0.85rem; }

/* ── Player tiles ── */
.player-tile { width: 9rem; min-height: 10rem; display: flex; flex-direction: column; align-items: center; gap: 0.5rem; padding: 1.5rem 0.5rem 0.5rem; border-radius: var(--radius-lg); position: relative; transition: all 0.25s cubic-bezier(0.34,1.56,0.64,1); cursor: pointer; }
.player-tile:hover:not(.is-self) { background: rgba(255,255,255,0.03); transform: translateY(-3px); }
.is-self { background: linear-gradient(180deg, rgba(212,255,58,0.08), transparent); border: 1px solid rgba(212,255,58,0.15); }
.p-avatar { position: absolute; top: 0; left: 50%; transform: translate(-50%, -10%); width: 36px; height: 36px; border-radius: 50%; background: var(--panel-raised); border: 2px solid var(--ink); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.4); z-index: 2; }
.p-card { width: 3.25rem; height: 4.8rem; border-radius: 8px; background: linear-gradient(155deg, #1f2732, #141a22); border: 1px solid var(--line); display: flex; align-items: center; justify-content: center; font-size: 1.4rem; font-weight: 700; box-shadow: 0 4px 12px rgba(0,0,0,0.5); transition: all 0.3s cubic-bezier(0.34,1.56,0.64,1); }
.p-card-ready { box-shadow: 0 4px 18px var(--lime-dim), 0 0 0 1px var(--lime); }
.p-card-selected { background: linear-gradient(155deg, var(--lime), #aee02a); border-color: var(--lime); transform: translateY(-8px); }
.p-card-selected .mono { color: var(--ink); }
.p-name { font-weight: 600; font-size: 0.92rem; color: var(--cream); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 8rem; display: flex; align-items: center; gap: 0.35rem; }
.self-pip { background: var(--lime); color: var(--ink); padding: 1px 5px; border-radius: 999px; font-size: 0.6rem; font-weight: 700; }
.p-status { display: inline-flex; align-items: center; gap: 0.4rem; }
.p-status.ready { color: var(--lime); }
.p-status.waiting { color: var(--muted); }
.status-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.p-status.ready .status-dot { box-shadow: 0 0 6px var(--lime); }
.p-status.waiting .status-dot { animation: pulse 1.5s ease-in-out infinite; }
@keyframes pulse { 50% { opacity: 0.3; } }
.throw-item { position: absolute; font-size: 1.4rem; top: 0; left: 50%; pointer-events: none; z-index: 10; }
.fly-in { animation: fly-in 1s ease-out forwards; }
@keyframes fly-in { 0% { transform: translate(-50%, -40px); opacity: 1; } 100% { transform: translate(-50%, 0); opacity: 0; } }

/* ── Dock ── */
.dock { position: fixed; left: 0; right: 0; bottom: 0; padding: 0.75rem 1rem 1rem; background: linear-gradient(180deg, transparent, var(--ink-deep) 25%); backdrop-filter: blur(12px); z-index: 30; }
.dock-inner { max-width: 1100px; margin: 0 auto; background: rgba(17,22,29,0.9); border: 1px solid var(--line); border-radius: var(--radius-xl); padding: 0.85rem 1rem 1rem; box-shadow: 0 -10px 40px rgba(0,0,0,0.5); }
.dock-label { display: block; text-align: center; margin-bottom: 0.7rem; }
.dock-cards { display: flex; gap: 0.6rem; overflow-x: auto; scrollbar-width: none; padding: 0.5rem 0.25rem 0.75rem; justify-content: center; }
.dock-cards::-webkit-scrollbar { display: none; }
.card { width: 3.25rem; height: 4.8rem; flex-shrink: 0; border-radius: 8px; background: linear-gradient(155deg, #1f2732, #141a22); border: 1px solid var(--line); display: flex; align-items: center; justify-content: center; font-size: 1.45rem; font-weight: 700; color: var(--cream); cursor: pointer; transition: all 0.3s cubic-bezier(0.34,1.56,0.64,1); }
.card:hover { transform: translateY(-10px) rotate(-2deg); border-color: var(--lime); }
.card-selected { background: linear-gradient(155deg, var(--lime), #aee02a); border-color: var(--lime); transform: translateY(-8px); }
.card-selected .mono { color: var(--ink); }

/* ── FABs ── */
.fab-stack { position: fixed; right: 1.25rem; bottom: 9.5rem; display: flex; flex-direction: column; align-items: flex-end; gap: 0.6rem; z-index: 25; transition: bottom 0.35s cubic-bezier(0.34,1.56,0.64,1); }
.fab-lifted { bottom: 10.5rem; }
.fab { width: 48px; height: 48px; border-radius: 999px; background: var(--panel-raised); border: 1px solid var(--line); color: var(--cream); font-size: 1.15rem; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s cubic-bezier(0.34,1.56,0.64,1); box-shadow: var(--shadow-md); }
.fab:hover { transform: translateY(-2px) scale(1.06); border-color: var(--lime); }
.fab.active { background: var(--lime); color: var(--ink); border-color: var(--lime); }

/* ── Emoji popover ── */
.emoji-popover { position: fixed; inset: 0; z-index: 60; display: flex; align-items: flex-end; justify-content: flex-end; padding: 1.25rem; padding-bottom: 15rem; background: rgba(6,9,13,0.55); backdrop-filter: blur(4px); }
.emoji-pop-card { background: var(--panel); border: 1px solid var(--line); border-radius: var(--radius-xl); width: 320px; max-width: calc(100vw - 2.5rem); }
.pop-head { display: flex; align-items: center; justify-content: space-between; padding: 0.85rem 1.1rem; border-bottom: 1px solid var(--line); }
.pop-close { background: var(--ink-soft); border: 1px solid var(--line); color: var(--cream-dim); width: 28px; height: 28px; border-radius: 999px; cursor: pointer; font-size: 0.8rem; }
.pop-close:hover { color: var(--coral); border-color: var(--coral); }
.emoji-grid { padding: 1rem; display: grid; grid-template-columns: repeat(4,1fr); gap: 0.5rem; }
.emoji-btn { aspect-ratio:1; background: var(--ink-soft); border: 1px solid var(--line); font-size: 1.6rem; border-radius: var(--radius-md); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.15s; }
.emoji-btn:hover { transform: translateY(-2px) scale(1.1); border-color: var(--ice); }

/* ── Chat drawer ── */
.chat-backdrop { position: fixed; inset: 0; background: rgba(6,9,13,0.55); backdrop-filter: blur(4px); z-index: 70; }
.chat-drawer { position: fixed; top: 0; right: 0; bottom: 0; width: min(420px, 100vw); background: var(--panel); border-left: 1px solid var(--line); z-index: 75; display: flex; flex-direction: column; box-shadow: -12px 0 40px rgba(0,0,0,0.5); }
.chat-head { padding: 0.85rem 1.1rem; display: flex; align-items: center; gap: 0.75rem; border-bottom: 1px solid var(--line); flex-shrink: 0; }
.chat-ai-left { margin-left: auto; font-family: var(--font-mono); font-size: 0.72rem; color: var(--muted); }
.chat-ai-left.rosebud { color: var(--lime); }
.chat-ai-left.low { color: var(--coral); }
.chat-messages { flex: 1; overflow-y: auto; padding: 1rem; display: flex; flex-direction: column; gap: 0.85rem; }
.chat-empty { color: var(--muted); font-size: 0.9rem; text-align: center; margin: auto; }
.chat-empty code { background: var(--ink-soft); padding: 1px 6px; border-radius: 4px; font-family: var(--font-mono); color: var(--lime); }
.msg { display: flex; gap: 0.6rem; align-items: flex-start; }
.msg-avatar { width: 32px; height: 32px; flex-shrink: 0; border-radius: 50%; background: var(--ink-soft); border: 1px solid var(--line); display: flex; align-items: center; justify-content: center; font-size: 1rem; }
.msg-body { flex: 1; min-width: 0; }
.msg-head { display: flex; align-items: baseline; gap: 0.5rem; margin-bottom: 0.15rem; }
.msg-name { font-weight: 700; color: var(--cream); font-size: 0.85rem; }
.msg-time { font-size: 0.68rem; color: var(--muted); font-family: var(--font-mono); }
.msg-text { color: var(--cream); font-size: 0.9rem; line-height: 1.45; word-break: break-word; }
.msg-text :deep(img) { max-height: 240px; max-width: 100%; border-radius: var(--radius-md); margin-top: 0.35rem; }
.msg-server .msg-avatar { background: var(--coral-dim); }
.msg-server .msg-name { color: var(--coral); }
.msg-ai .msg-avatar { background: var(--lime-dim); border-color: var(--lime); }
.msg-ai .msg-name { color: var(--lime); }
.chat-cmds { display: flex; gap: 0.35rem; padding: 0.5rem 0.9rem 0.25rem; overflow-x: auto; scrollbar-width: none; border-top: 1px solid var(--line); }
.cmd-chip { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.4rem 0.75rem; background: var(--ink-soft); border: 1px solid var(--line); color: var(--cream-dim); border-radius: 999px; cursor: pointer; font-size: 0.75rem; white-space: nowrap; flex-shrink: 0; transition: all 0.15s; font-family: var(--font-mono); }
.cmd-chip:hover { border-color: var(--lime); color: var(--lime); }
.chat-input-row { display: flex; gap: 0.5rem; padding: 0.5rem 0.9rem 0.9rem; align-items: flex-end; }
.chat-input-row textarea { flex: 1; background: var(--ink-soft); border: 1px solid var(--line); color: var(--cream); padding: 0.65rem 0.85rem; border-radius: var(--radius-md); font-family: var(--font-display); font-size: 0.92rem; resize: none; outline: none; max-height: 120px; min-height: 42px; line-height: 1.4; transition: border-color 0.15s; }
.chat-input-row textarea:focus { border-color: var(--lime); }
.chat-input-row textarea::placeholder { color: var(--muted); }
.send-btn { width: 42px; height: 42px; border-radius: var(--radius-md); background: var(--panel-raised); border: 1px solid var(--line); color: var(--muted); cursor: pointer; font-size: 1rem; display: flex; align-items: center; justify-content: center; transition: all 0.15s; flex-shrink: 0; }
.send-btn.ready { background: var(--lime); color: var(--ink); border-color: var(--lime); }

/* ── Profile dialog ── */
.dialog-backdrop { position: fixed; inset: 0; background: rgba(6,9,13,0.7); backdrop-filter: blur(6px); z-index: 90; display: flex; align-items: center; justify-content: center; padding: 1rem; }
.dialog { background: var(--panel); border: 1px solid var(--line); border-radius: var(--radius-xl); width: 100%; max-width: 460px; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden; }
.dialog-head { padding: 1.25rem 1.4rem 0.75rem; flex-shrink: 0; }
.dialog-title { font-size: 1.6rem; font-weight: 800; letter-spacing: -0.025em; margin-top: 0.3rem; }
.dialog-body { padding: 0.5rem 1.4rem 0.5rem; display: flex; flex-direction: column; gap: 1rem; overflow-y: auto; }
.avatar-preview { display: flex; justify-content: center; }
.avatar-big { font-size: 2.4rem; width: 68px; height: 68px; border-radius: 50%; background: var(--ink-soft); border: 1px solid var(--lime); display: flex; align-items: center; justify-content: center; box-shadow: 0 0 20px var(--lime-dim); }
.avatar-grid { display: grid; grid-template-columns: repeat(8,1fr); gap: 0.35rem; }
.av-tile { aspect-ratio:1; font-size: 1.35rem; background: var(--ink-soft); border: 1px solid var(--line); border-radius: var(--radius-md); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.15s; }
.av-tile:hover { transform: translateY(-2px) scale(1.1); }
.av-tile.active { border-color: var(--lime); background: var(--lime-dim); box-shadow: 0 0 0 1px var(--lime); }
.field { display: flex; flex-direction: column; gap: 0.4rem; }
.field input { background: var(--ink-soft); border: 1px solid var(--line); color: var(--cream); padding: 0.75rem 0.9rem; border-radius: var(--radius-md); font-family: var(--font-display); font-size: 1rem; outline: none; }
.field input:focus { border-color: var(--lime); box-shadow: 0 0 0 3px var(--lime-dim); }
.dialog-actions { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; padding: 1rem 1.4rem; border-top: 1px solid var(--line); background: var(--ink-soft); flex-shrink: 0; }
.dialog-right { display: flex; gap: 0.5rem; }
.btn-primary { padding: 0.65rem 1.1rem; font-family: var(--font-display); font-weight: 700; font-size: 0.9rem; background: var(--lime); color: var(--ink); border: none; border-radius: var(--radius-md); cursor: pointer; transition: all 0.15s; }
.btn-primary:hover:not(:disabled) { transform: translateY(-1px); }
.btn-primary:disabled { opacity: 0.4; cursor: not-allowed; }
.btn-ghost { padding: 0.65rem 1.1rem; font-family: var(--font-display); font-weight: 700; font-size: 0.9rem; background: transparent; color: var(--cream-dim); border: 1px solid var(--line); border-radius: var(--radius-md); cursor: pointer; }
.btn-ghost:hover { color: var(--cream); }
.btn-danger { padding: 0.65rem 1.1rem; font-family: var(--font-display); font-weight: 700; font-size: 0.9rem; background: transparent; color: var(--coral); border: 1px solid rgba(255,90,107,0.35); border-radius: var(--radius-md); cursor: pointer; display: flex; align-items: center; gap: 0.4rem; }
.btn-danger:hover { background: var(--coral-dim); }

/* ── Settings drawer ── */
.settings-backdrop { position: fixed; inset: 0; z-index: 78; background: rgba(6,9,13,0.6); backdrop-filter: blur(6px); }
.settings-drawer { position: fixed; top: 0; right: 0; bottom: 0; width: min(420px, 100vw); background: var(--panel); border-left: 1px solid var(--line); z-index: 80; display: flex; flex-direction: column; box-shadow: -12px 0 40px rgba(0,0,0,0.5); overflow: hidden; }
.settings-head { display: flex; align-items: flex-start; justify-content: space-between; padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--line); flex-shrink: 0; }
.settings-title { font-size: 1.5rem; font-weight: 800; letter-spacing: -0.02em; margin-top: 0.2rem; }
.settings-body { flex: 1; overflow-y: auto; padding: 1.25rem 1.5rem; display: flex; flex-direction: column; gap: 1.25rem; }
.settings-footer { padding: 1rem 1.5rem; border-top: 1px solid var(--line); flex-shrink: 0; background: var(--ink-soft); }
.settings-warn { padding: 0.75rem 1rem; background: var(--coral-dim); border: 1px solid rgba(255,90,107,0.25); border-radius: var(--radius-md); color: var(--cream); font-size: 0.85rem; }
.setting-section { display: flex; flex-direction: column; gap: 0.6rem; }
.rename-row { display: flex; gap: 0.5rem; }
.rename-row input { flex: 1; background: var(--ink-soft); border: 1px solid var(--line); color: var(--cream); padding: 0.65rem 0.85rem; border-radius: var(--radius-md); font-family: var(--font-display); font-size: 0.95rem; outline: none; }
.rename-row input:focus { border-color: var(--lime); }
.rename-row input:disabled { opacity: 0.5; }
.btn-save { padding: 0 1rem; background: var(--lime); color: var(--ink); border: none; border-radius: var(--radius-md); cursor: pointer; font-weight: 700; font-family: var(--font-display); font-size: 0.9rem; }
.btn-save:disabled { opacity: 0.4; cursor: not-allowed; }
.deck-options { display: flex; flex-direction: column; gap: 0.4rem; }
.deck-opt { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 0.9rem; background: var(--ink-soft); border: 1px solid var(--line); color: var(--cream); border-radius: var(--radius-md); cursor: pointer; font-family: var(--font-display); text-align: left; transition: all 0.15s; }
.deck-opt:hover:not(:disabled) { border-color: var(--lime); }
.deck-opt.active { border-color: var(--lime); background: var(--lime-dim); }
.deck-opt:disabled { opacity: 0.6; cursor: not-allowed; }
.deck-opt-label { font-weight: 600; font-size: 0.92rem; }
.deck-opt-sample { font-size: 0.72rem; color: var(--muted); flex: 1; }
.deck-check { color: var(--lime); }
.color-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 0.5rem; }
.color-tile { display: flex; flex-direction: column; align-items: center; gap: 0.4rem; padding: 0.7rem 0.5rem; background: var(--ink-soft); border: 1px solid var(--line); color: var(--cream-dim); border-radius: var(--radius-md); cursor: pointer; font-size: 0.72rem; font-weight: 600; transition: all 0.15s; }
.color-tile:hover:not(:disabled) { transform: translateY(-2px); color: var(--cream); }
.color-tile.active { border-color: var(--lime); background: var(--lime-dim); color: var(--cream); }
.color-tile:disabled { opacity: 0.6; cursor: not-allowed; }
.color-swatch { width: 26px; height: 26px; border-radius: 50%; box-shadow: 0 0 0 1px rgba(255,255,255,0.1); }
.toggle-row { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: 0.9rem 1rem; background: var(--ink-soft); border: 1px solid var(--line); border-radius: var(--radius-md); }
.toggle-label { font-weight: 600; color: var(--cream); }
.toggle-sub { font-size: 0.78rem; color: var(--muted); margin-top: 0.15rem; }
.switch { flex-shrink: 0; width: 48px; height: 28px; background: var(--line); border: none; border-radius: 999px; position: relative; cursor: pointer; transition: background 0.2s; padding: 0; }
.switch:disabled { cursor: not-allowed; opacity: 0.6; }
.switch-knob { position: absolute; top: 3px; left: 3px; width: 22px; height: 22px; border-radius: 999px; background: var(--cream); transition: transform 0.25s cubic-bezier(0.34,1.56,0.64,1); box-shadow: 0 2px 4px rgba(0,0,0,0.3); }
.switch.on { background: var(--lime); }
.switch.on .switch-knob { transform: translateX(20px); background: var(--ink); }

/* ── Transitions ── */
.pop-enter-active, .pop-leave-active { transition: all 0.25s cubic-bezier(0.34,1.56,0.64,1); }
.pop-enter-from, .pop-leave-to { opacity: 0; transform: translateY(20px) scale(0.95); }
.chat-fade-enter-active, .chat-fade-leave-active { transition: opacity 0.25s; }
.chat-fade-enter-from, .chat-fade-leave-to { opacity: 0; }
.chat-slide-enter-active, .chat-slide-leave-active { transition: transform 0.3s cubic-bezier(0.34,1.56,0.64,1); }
.chat-slide-enter-from, .chat-slide-leave-to { transform: translateX(100%); }
.settings-slide-enter-active, .settings-slide-leave-active { transition: transform 0.3s cubic-bezier(0.34,1.56,0.64,1); }
.settings-slide-enter-from, .settings-slide-leave-to { transform: translateX(100%); }

@media (max-width: 640px) {
  .board { padding: 1rem 0.75rem 14rem; }
  .players { gap: 0.5rem; }
  .player-tile { width: 7.5rem; }
  .fab-stack { right: 1rem; bottom: 11rem; }
  .fab-lifted { bottom: 12rem; }
  .fab { width: 44px; height: 44px; }
  .tb-name { max-width: 8rem; font-size: 1rem; }
}
</style>
