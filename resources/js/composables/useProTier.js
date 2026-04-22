import { ref, computed } from 'vue';
import { isRosebud, isPro } from './useGame';

const STORAGE_KEY  = 'pp_ai_uses';
const FREE_LIMIT   = 5;

const _uses = ref(parseInt(localStorage.getItem(STORAGE_KEY) ?? '0', 10));

export const aiUsesCount = computed(() => _uses.value);
export const aiUsesLeft  = computed(() => Math.max(0, FREE_LIMIT - _uses.value));
export const showProNag  = ref(false);

export function canUseAi() {
    if (isRosebud.value || isPro.value) return true;
    if (_uses.value >= FREE_LIMIT) {
        showProNag.value = true;
        return false;
    }
    return true;
}

export function recordAiUse() {
    if (isRosebud.value || isPro.value) return;
    _uses.value += 1;
    localStorage.setItem(STORAGE_KEY, String(_uses.value));
    if (_uses.value >= FREE_LIMIT) showProNag.value = true;
}

export function dismissProNag() {
    showProNag.value = false;
}
