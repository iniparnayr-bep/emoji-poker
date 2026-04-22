<?php

namespace App\Http\Controllers\Api;

use App\Events\SessionUpdated;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Player;
use App\Models\PokerSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GameSessionController extends Controller
{
    /** POST /api/sessions */
    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'name'       => 'required|string|max:60',
            'leaderName' => 'required|string|max:50',
        ]);

        $session = DB::transaction(function () use ($request) {
            $session = PokerSession::create([
                'token'              => PokerSession::generateToken(),
                'name'               => $request->name,
                'estimation_options' => 'Fibonacci',
                'estimation_values'  => PokerSession::DECKS['Fibonacci'],
                'rosebud'            => strtolower(trim($request->name)) === 'rosebud',
                'owner_user_id'      => auth()->id(),
            ]);

            $player = Player::create([
                'session_token' => $session->token,
                'user_id'       => auth()->id(),
                'name'          => $request->leaderName,
                'avatar'        => Player::pickAvatar([]),
                'is_owner'      => true,
                'player_token'  => Player::generateToken(),
            ]);

            return ['session' => $session, 'player' => $player];
        });

        return response()->json([
            'token'       => $session['session']->token,
            'playerToken' => $session['player']->player_token,
            'player'      => $session['player']->toPrivateArray(),
            'session'     => $session['session']->load('players')->toClientArray(),
        ], 201);
    }

    /** POST /api/sessions/{token}/join */
    public function join(Request $request, string $token): JsonResponse
    {
        $token = strtoupper($token);
        $request->validate(['name' => 'required|string|max:50']);

        $session = PokerSession::with('players')->where('token', $token)->firstOrFail();

        $usedAvatars = $session->players->pluck('avatar')->toArray();
        $player = Player::create([
            'session_token' => $session->token,
            'user_id'       => auth()->id(),
            'name'          => $request->name,
            'avatar'        => Player::pickAvatar($usedAvatars),
            'is_owner'      => false,
            'player_token'  => Player::generateToken(),
        ]);

        $this->serverMessage($session->token, $player->name . ' joined the session.');
        $session->refresh()->load('players');
        broadcast(new SessionUpdated($session, 'playerJoined'))->toOthers();

        return response()->json([
            'playerToken' => $player->player_token,
            'player'      => $player->toPrivateArray(),
            'session'     => $session->toClientArray(),
        ]);
    }

    /** GET /api/sessions/{token} */
    public function show(string $token): JsonResponse
    {
        $token = strtoupper($token);
        $session = PokerSession::with('players')->where('token', $token)->firstOrFail();
        return response()->json($session->toClientArray());
    }

    /** PUT /api/sessions/{token}/open */
    public function setOpen(Request $request, string $token): JsonResponse
    {
        $token = strtoupper($token);
        $request->validate(['open' => 'required|boolean', 'playerToken' => 'required|string']);

        $session = PokerSession::with('players')->where('token', $token)->firstOrFail();
        $player  = Player::where('player_token', $request->playerToken)
                         ->where('session_token', $token)->firstOrFail();

        abort_unless($player->is_owner, 403, 'Only the session owner can reveal estimates.');

        DB::transaction(function () use ($session, $request) {
            $session->update(['is_open' => $request->open]);
            if (!$request->open) {
                // New round: reset all estimates
                $session->players()->update(['estimate' => null]);
            }
        });

        $session->refresh()->load('players');
        broadcast(new SessionUpdated($session, 'sessionOpened'));

        if ($request->open) {
            $stats = $this->calculateStats($session);
            if ($stats) {
                $this->serverMessage($token, "Average: {$stats['avg']} · Median: {$stats['median']} · Suggested (2nd highest): {$stats['suggestion']}");
            }
        }

        return response()->json($session->toClientArray());
    }

    /** PUT /api/sessions/{token}/estimate */
    public function estimate(Request $request, string $token): JsonResponse
    {
        $token = strtoupper($token);
        $request->validate(['playerToken' => 'required|string', 'estimate' => 'required|string']);

        $session = PokerSession::with('players')->where('token', $token)->firstOrFail();
        $player  = Player::where('player_token', $request->playerToken)
                         ->where('session_token', $token)->firstOrFail();

        abort_unless(
            in_array($request->estimate, $session->estimation_values ?? PokerSession::DECKS['Fibonacci']),
            422, 'Invalid estimate value.'
        );

        $player->update(['estimate' => $request->estimate]);
        $session->refresh()->load('players');
        broadcast(new SessionUpdated($session, 'playerEstimated'));

        return response()->json($session->toClientArray());
    }

    /** PUT /api/sessions/{token}/settings */
    public function updateSettings(Request $request, string $token): JsonResponse
    {
        $token = strtoupper($token);
        $request->validate([
            'playerToken'       => 'required|string',
            'color'             => 'sometimes|string|in:default,purple,blue,green,gray,red,dark',
            'emojisEnabled'     => 'sometimes|boolean',
            'name'              => 'sometimes|string|max:60',
            'estimationOptions' => 'sometimes|string|in:Fibonacci,PowersOfTwo,TShirtSizes,PersonDays',
        ]);

        $session = PokerSession::with('players')->where('token', $token)->firstOrFail();
        $player  = Player::where('player_token', $request->playerToken)
                         ->where('session_token', $token)->firstOrFail();

        abort_unless($player->is_owner, 403, 'Only the session owner can change settings.');

        $updates = array_filter([
            'color'             => $request->color,
            'emojis_enabled'    => $request->has('emojisEnabled') ? $request->emojisEnabled : null,
            'name'              => $request->name,
            'estimation_options'=> $request->estimationOptions,
            'estimation_values' => $request->estimationOptions
                ? PokerSession::DECKS[$request->estimationOptions] ?? null
                : null,
        ], fn($v) => $v !== null);

        if (isset($updates['name'])) {
            $updates['rosebud'] = strtolower(trim($updates['name'])) === 'rosebud';
        }

        $session->update($updates);

        if ($request->has('estimationOptions')) {
            $session->players()->update(['estimate' => null]);
        }

        $session->refresh()->load('players');
        broadcast(new SessionUpdated($session, 'sessionSettings'));

        return response()->json($session->toClientArray());
    }

    /** POST /api/sessions/{token}/kick */
    public function kick(Request $request, string $token): JsonResponse
    {
        $token = strtoupper($token);
        $request->validate(['playerToken' => 'required|string', 'kickId' => 'required|integer']);

        $session = PokerSession::with('players')->where('token', $token)->firstOrFail();
        $owner   = Player::where('player_token', $request->playerToken)
                         ->where('session_token', $token)->firstOrFail();

        abort_unless($owner->is_owner, 403, 'Not owner.');

        $target = Player::where('id', $request->kickId)
                        ->where('session_token', $token)->firstOrFail();

        abort_if($target->is_owner, 400, 'Cannot kick the owner.');

        $targetName = $target->name;
        $target->delete();

        $this->serverMessage($token, $targetName . ' was removed from the session.');
        $session->refresh()->load('players');
        broadcast(new SessionUpdated($session, 'playerKicked'));

        return response()->json(['ok' => true]);
    }

    /** PUT /api/sessions/{token}/make-admin — atomic owner transfer to fix the stuck-admin bug */
    public function makeAdmin(Request $request, string $token): JsonResponse
    {
        $token = strtoupper($token);
        $request->validate(['playerToken' => 'required|string', 'targetId' => 'required|integer']);

        $session = PokerSession::with('players')->where('token', $token)->firstOrFail();

        DB::transaction(function () use ($request, $token, $session) {
            $currentOwner = Player::where('player_token', $request->playerToken)
                                  ->where('session_token', $token)
                                  ->lockForUpdate()->firstOrFail();

            abort_unless($currentOwner->is_owner, 403, 'Not owner.');

            $newOwner = Player::where('id', $request->targetId)
                              ->where('session_token', $token)
                              ->lockForUpdate()->firstOrFail();

            // Atomically swap — clear all owners first, then set new one
            Player::where('session_token', $token)->update(['is_owner' => false]);
            $newOwner->update(['is_owner' => true]);

            $this->serverMessage($token, $newOwner->name . ' is now the session leader.');
        });

        $session->refresh()->load('players');
        broadcast(new SessionUpdated($session, 'sessionSettings'));

        return response()->json($session->toClientArray());
    }

    /** GET /api/sessions/stats */
    public function stats(): JsonResponse
    {
        $active = PokerSession::whereHas('players')->count();
        $total  = PokerSession::count();
        return response()->json(['active' => $active, 'total' => $total]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function serverMessage(string $token, string $text): void
    {
        $msg = Message::create([
            'session_token' => $token,
            'player_name'   => 'Server',
            'player_avatar' => '📣',
            'message'       => $text,
            'type'          => 'server',
        ]);
        broadcast(new \App\Events\ChatMessageSent($token, $msg->toClientArray()));
    }

    private function calculateStats(PokerSession $session): ?array
    {
        $numeric = $session->players
            ->pluck('estimate')
            ->filter(fn($e) => is_numeric($e))
            ->map(fn($e) => (float) $e)
            ->sort()
            ->values();

        if ($numeric->isEmpty()) return null;

        $count  = $numeric->count();
        $avg    = round($numeric->avg(), 1);
        $median = $count % 2
            ? $numeric[$count >> 1]
            : ($numeric[($count >> 1) - 1] + $numeric[$count >> 1]) / 2;

        $sorted    = $numeric->sortDesc()->values();
        $suggestion = $sorted->get(1) ?? $sorted->first();

        return ['avg' => $avg, 'median' => $median, 'suggestion' => $suggestion];
    }
}
