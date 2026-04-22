/**
 * Core game state composable — manages session, player, messages and Reverb socket.
 */
import { ref, computed } from 'vue';
import axios from 'axios';
import echo from '../echo';

// ── State ─────────────────────────────────────────────────────────────────────
export const session   = ref(null);   // full session object from server
export const selfPlayer = ref(null);   // current player's private info
export const messages  = ref([]);
export const connected = ref(false);

let channel = null;

// ── Derived ───────────────────────────────────────────────────────────────────
export const isOwner     = computed(() => selfPlayer.value?.isOwner ?? false);
export const isSpectator = computed(() => !selfPlayer.value || selfPlayer.value?.isSpectator);
export const isRevealed  = computed(() => session.value?.open ?? false);
export const isPro       = computed(() => session.value?.isPro ?? false);
export const isRosebud   = computed(() => session.value?.rosebud ?? false);

// ── API base ──────────────────────────────────────────────────────────────────
const api = axios.create({ baseURL: '/api', headers: { Accept: 'application/json' } });

// ── Bootstrap ─────────────────────────────────────────────────────────────────
export async function createSession(name, leaderName) {
    const { data } = await api.post('/sessions', { name, leaderName });
    session.value  = data.session;
    selfPlayer.value = data.player;
    localStorage.setItem('playerToken', data.playerToken);
    localStorage.setItem('sessionToken', data.token);
    subscribeToSession(data.token);
    return data;
}

export async function joinSession(token, name) {
    const { data } = await api.post(`/sessions/${token}/join`, { name });
    session.value  = data.session;
    selfPlayer.value = data.player;
    localStorage.setItem('playerToken', data.playerToken);
    localStorage.setItem('sessionToken', data.token);
    subscribeToSession(data.token);
    // Load message history
    const { data: msgs } = await api.get(`/sessions/${token}/messages`);
    messages.value = msgs;
    return data;
}

export async function spectateSession(token) {
    const { data } = await api.get(`/sessions/${token}`);
    session.value = data;
    subscribeToSession(token);
    const { data: msgs } = await api.get(`/sessions/${token}/messages`);
    messages.value = msgs;
}

export async function reconnect(token, playerToken) {
    const [sessionRes, playerRes, msgRes] = await Promise.all([
        api.get(`/sessions/${token}`),
        api.get(`/players/${playerToken}`),
        api.get(`/sessions/${token}/messages`),
    ]);
    session.value    = sessionRes.data;
    selfPlayer.value = playerRes.data;
    messages.value   = msgRes.data;
    subscribeToSession(token);
}

// ── Game actions ──────────────────────────────────────────────────────────────
export async function submitEstimate(estimate) {
    const { data } = await api.put(`/sessions/${session.value.token}/estimate`, {
        playerToken: selfPlayer.value.token,
        estimate,
    });
    session.value = data;
}

export async function toggleReveal() {
    const { data } = await api.put(`/sessions/${session.value.token}/open`, {
        playerToken: selfPlayer.value.token,
        open: !session.value.open,
    });
    session.value = data;
}

export async function updateSettings(settings) {
    const { data } = await api.put(`/sessions/${session.value.token}/settings`, {
        playerToken: selfPlayer.value.token,
        ...settings,
    });
    session.value = data;
}

export async function kickPlayer(playerId) {
    await api.post(`/sessions/${session.value.token}/kick`, {
        playerToken: selfPlayer.value.token,
        kickId: playerId,
    });
}

export async function makeAdmin(targetId) {
    const { data } = await api.put(`/sessions/${session.value.token}/make-admin`, {
        playerToken: selfPlayer.value.token,
        targetId,
    });
    session.value = data;
    // Update own owner status
    const me = data.players.find(p => p.id === selfPlayer.value?.id);
    if (me) selfPlayer.value = { ...selfPlayer.value, isOwner: me.isOwner };
}

export async function leaveSession() {
    const playerToken = selfPlayer.value?.token || localStorage.getItem('playerToken');
    if (playerToken) {
        await api.put(`/players/${playerToken}/leave`).catch(() => {});
    }
    clearState();
}

export async function updateSelf(opts) {
    const { data } = await api.put(`/players/${selfPlayer.value.token}`, opts);
    selfPlayer.value = data;
    // Update name in session players list
    if (session.value) {
        session.value = {
            ...session.value,
            players: session.value.players.map(p =>
                p.id === data.id ? { ...p, name: data.name, avatar: data.avatar } : p
            ),
        };
    }
}

// ── Chat ──────────────────────────────────────────────────────────────────────
export async function sendMessage(text) {
    await api.post(`/sessions/${session.value.token}/chat`, {
        playerToken: selfPlayer.value.token,
        message: text,
    });
}

export async function uploadImage(file) {
    const form = new FormData();
    form.append('image', file);
    form.append('playerToken', selfPlayer.value.token);
    const { data } = await api.post(`/sessions/${session.value.token}/images`, form, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });
    return data;
}

export async function throwEmoji(targetPlayerId, emoji) {
    await api.post(`/sessions/${session.value.token}/throw`, {
        playerToken: selfPlayer.value.token,
        targetPlayerId,
        emoji,
    });
}

export async function shakePlayer(targetPlayerId) {
    await api.post(`/sessions/${session.value.token}/shake`, {
        playerToken: selfPlayer.value.token,
        targetPlayerId,
    });
}

// ── Reverb subscription ───────────────────────────────────────────────────────
function subscribeToSession(token) {
    if (channel) channel.stopListening('.sessionUpdated').stopListening('.chatMessage').stopListening('.emojiThrown');

    channel = echo.channel(`session.${token}`);
    connected.value = true;

    const sessionEvents = ['sessionUpdated','playerJoined','playerLeft','playerEstimated','sessionOpened','playerKicked','sessionSettings'];
    sessionEvents.forEach(evt => {
        channel.listen(`.${evt}`, (data) => {
            session.value = data;
            // Sync own player's owner status from incoming session
            if (selfPlayer.value) {
                const me = data.players?.find(p => p.id === selfPlayer.value.id);
                if (me) selfPlayer.value = { ...selfPlayer.value, isOwner: me.isOwner, avatar: me.avatar, name: me.name };
            }
            // Apply color theme to body
            if (data.color) document.body.className = data.color;
        });
    });

    channel.listen('.chatMessage', (msg) => {
        messages.value = [...messages.value, msg];
    });

    channel.listen('.emojiThrown', (data) => {
        window.dispatchEvent(new CustomEvent('emoji-thrown', { detail: data }));
    });

    // Apply initial color
    if (session.value?.color) document.body.className = session.value.color;
}

function clearState() {
    session.value = null;
    selfPlayer.value = null;
    messages.value = [];
    localStorage.removeItem('playerToken');
    localStorage.removeItem('sessionToken');
    if (channel) {
        channel.stopListening('.sessionUpdated');
        channel = null;
    }
    connected.value = false;
}
