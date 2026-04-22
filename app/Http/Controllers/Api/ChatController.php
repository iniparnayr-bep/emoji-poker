<?php

namespace App\Http\Controllers\Api;

use App\Events\ChatMessageSent;
use App\Events\EmojiThrown;
use App\Http\Controllers\Controller;
use App\Models\ChatImage;
use App\Models\Message;
use App\Models\Player;
use App\Models\PokerSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenAI\Laravel\Facades\OpenAI;

class ChatController extends Controller
{
    const FREE_AI_LIMIT   = 5;
    const GLOBAL_AI_LIMIT = 100;

    /** GET /api/sessions/{token}/messages */
    public function index(string $token): JsonResponse
    {
        $token    = strtoupper($token);
        $session  = PokerSession::where('token', $token)->firstOrFail();
        $messages = $session->messages()->orderBy('created_at')->get();
        return response()->json($messages->map->toClientArray());
    }

    /** POST /api/sessions/{token}/chat */
    public function send(Request $request, string $token): JsonResponse
    {
        $token = strtoupper($token);
        $request->validate([
            'playerToken' => 'required|string',
            'message'     => 'required|string|max:2000',
        ]);

        $session = PokerSession::with('players')->where('token', $token)->firstOrFail();
        $player  = Player::where('player_token', $request->playerToken)
                         ->where('session_token', $token)->firstOrFail();

        $text = trim($request->message);

        // Check if AI command
        $isAiCmd = str_starts_with($text, '/ask') || str_starts_with($text, '/estimate');

        if ($isAiCmd) {
            [$allowed, $reason] = $this->checkAiLimit($request, $session);
            if (!$allowed) {
                $msg = $this->storeAndBroadcast($token, 'AI', '🤖', "⚠️ $reason", 'ai');
                return response()->json($msg);
            }
        }

        // Store sender's message
        $this->storeAndBroadcast($token, $player->name, $player->avatar, $text, 'std');

        // Handle AI command asynchronously (dispatch job or inline for simplicity)
        if ($isAiCmd) {
            $this->recordAiUsage($request, $session);
            $this->handleAiCommand($text, $session, $token);
        }

        return response()->json(['ok' => true]);
    }

    /** POST /api/sessions/{token}/images */
    public function uploadImage(Request $request, string $token): JsonResponse
    {
        $token = strtoupper($token);
        $request->validate([
            'playerToken' => 'required|string',
            'image'       => 'required|image|max:5120', // 5MB
        ]);

        $session = PokerSession::where('token', $token)->firstOrFail();
        $player  = Player::where('player_token', $request->playerToken)
                         ->where('session_token', $token)->firstOrFail();

        $file      = $request->file('image');
        $ext       = $file->getClientOriginalExtension();
        $timestamp = now()->timestamp;
        $filename  = "{$timestamp}_{$token}_" . Str::random(8) . ".{$ext}";
        $path      = "chat-images/{$filename}";

        Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));

        $image = ChatImage::create([
            'session_token' => $token,
            'filename'      => $filename,
            'path'          => $path,
            'expires_at'    => now()->addHour(),
        ]);

        $url = Storage::disk('public')->url($path);
        $imgTag = "<img src=\"{$url}\" style=\"max-height:240px;max-width:100%;border-radius:8px\" />";

        $msg = $this->storeAndBroadcast($token, $player->name, $player->avatar, $imgTag, 'std');

        return response()->json(['url' => $url, 'message' => $msg]);
    }

    /** POST /api/sessions/{token}/throw */
    public function throwEmoji(Request $request, string $token): JsonResponse
    {
        $token = strtoupper($token);
        $request->validate([
            'playerToken'    => 'required|string',
            'targetPlayerId' => 'required|integer',
            'emoji'          => 'required|string|max:8',
        ]);

        $session = PokerSession::where('token', $token)->firstOrFail();
        Player::where('player_token', $request->playerToken)->where('session_token', $token)->firstOrFail();

        broadcast(new EmojiThrown($token, (string) $request->targetPlayerId, $request->emoji));

        return response()->json(['ok' => true]);
    }

    /** POST /api/sessions/{token}/shake */
    public function shake(Request $request, string $token): JsonResponse
    {
        $token = strtoupper($token);
        $request->validate(['playerToken' => 'required|string', 'targetPlayerId' => 'required|integer']);

        $session = PokerSession::where('token', $token)->firstOrFail();
        Player::where('player_token', $request->playerToken)->where('session_token', $token)->firstOrFail();

        // Reuse EmojiThrown with special shake marker
        broadcast(new EmojiThrown($token, (string) $request->targetPlayerId, '__shake__'));

        return response()->json(['ok' => true]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function storeAndBroadcast(string $token, string $name, string $avatar, string $text, string $type): array
    {
        $msg = Message::create([
            'session_token' => $token,
            'player_name'   => $name,
            'player_avatar' => $avatar,
            'message'       => $text,
            'type'          => $type,
        ]);
        $arr = $msg->toClientArray();
        broadcast(new ChatMessageSent($token, $arr));
        return $arr;
    }

    private function checkAiLimit(Request $request, PokerSession $session): array
    {
        // Rosebud rooms bypass all limits
        if ($session->rosebud) return [true, null];

        // Pro room (owner has active subscription)
        if ($session->isPro()) return [true, null];

        // Global hourly limit
        $globalKey = 'ai_global_hour';
        $globalCount = Cache::get($globalKey, 0);
        if ($globalCount >= self::GLOBAL_AI_LIMIT) {
            return [false, 'The AI assistant is temporarily busy — please try again in a few minutes.'];
        }

        // Per-IP limit
        $ip    = $request->ip();
        $ipKey = "ai_ip_{$ip}";
        $used  = Cache::get($ipKey, 0);
        if ($used >= self::FREE_AI_LIMIT) {
            return [false, "You've used your " . self::FREE_AI_LIMIT . " free AI interactions. Upgrade to Pro for unlimited access."];
        }

        return [true, null];
    }

    private function recordAiUsage(Request $request, PokerSession $session): void
    {
        if ($session->rosebud || $session->isPro()) return;

        $ip = $request->ip();
        Cache::increment("ai_ip_{$ip}");

        $key = 'ai_global_hour';
        Cache::put($key, Cache::get($key, 0) + 1, now()->addHour());
    }

    private function handleAiCommand(string $text, PokerSession $session, string $token): void
    {
        $estimationValues = $session->estimation_values ?? PokerSession::DECKS['Fibonacci'];

        if (str_starts_with($text, '/ask')) {
            $prompt = trim(substr($text, 4));
            $system = "You are a helpful agile planning assistant. The team uses estimation values: " . implode(', ', $estimationValues) . ". Answer concisely — this is a chat interface.";
        } else {
            $prompt = trim(substr($text, 9)); // /estimate
            $system = "You are a planning poker assistant. The team's estimation scale is: " . implode(', ', $estimationValues) . ". Respond with ONLY a single value from that scale — no explanation.";
        }

        if (empty($prompt)) {
            $this->storeAndBroadcast($token, 'AI', '🤖', 'Please provide a question or task description.', 'ai');
            return;
        }

        try {
            // Use openai-php/laravel with Anthropic-compatible endpoint via config
            $response = OpenAI::chat()->create([
                'model'    => 'claude-haiku-4-5',
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user',   'content' => $prompt],
                ],
                'max_tokens' => 300,
            ]);
            $reply = $response->choices[0]->message->content ?? 'No response.';
        } catch (\Throwable $e) {
            \Log::error('AI command failed: ' . $e->getMessage());
            $reply = 'AI unavailable at the moment.';
        }

        $this->storeAndBroadcast($token, 'AI', '🤖', $reply, 'ai');
    }
}
