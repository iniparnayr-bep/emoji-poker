<?php

namespace App\Http\Controllers\Api;

use App\Events\SessionUpdated;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Player;
use App\Models\PokerSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlayerController extends Controller
{
    /** PUT /api/players/{playerToken} — rename and/or change avatar */
    public function update(Request $request, string $playerToken): JsonResponse
    {
        $request->validate([
            'name'   => 'sometimes|string|max:50',
            'avatar' => 'sometimes|string|max:8',
        ]);

        $player  = Player::where('player_token', $playerToken)->firstOrFail();
        $session = PokerSession::with('players')->where('token', $player->session_token)->firstOrFail();

        if ($request->has('name')) {
            $name = trim($request->name);
            abort_if(empty($name), 400, 'Name required.');

            // Check uniqueness in session (excluding self)
            $taken = Player::where('session_token', $session->token)
                           ->where('name', $name)
                           ->where('id', '!=', $player->id)
                           ->exists();
            abort_if($taken, 409, 'Name already taken in this session.');

            $player->name = $name;
        }

        if ($request->has('avatar') && $request->avatar) {
            $player->avatar = $request->avatar;
        }

        $player->save();

        if ($request->has('name')) {
            Message::create([
                'session_token' => $session->token,
                'player_name'   => 'Server',
                'player_avatar' => '📣',
                'message'       => 'A player changed their name to ' . $player->name . '.',
                'type'          => 'server',
            ]);
        }

        $session->refresh()->load('players');
        broadcast(new SessionUpdated($session, 'playerJoined'));

        return response()->json($player->toPrivateArray());
    }

    /** PUT /api/players/{playerToken}/leave */
    public function leave(Request $request, string $playerToken): JsonResponse
    {
        $player  = Player::where('player_token', $playerToken)->firstOrFail();
        $session = PokerSession::with('players')->where('token', $player->session_token)->firstOrFail();
        $name    = $player->name;

        $player->delete();

        // If no players left, delete session
        $session->refresh();
        if ($session->players()->count() === 0) {
            $session->delete();
            return response()->json(['ok' => true]);
        }

        // If owner left, assign ownership to oldest remaining player
        if (!$session->players()->where('is_owner', true)->exists()) {
            $next = $session->players()->oldest()->first();
            if ($next) {
                $next->update(['is_owner' => true]);
                Message::create([
                    'session_token' => $session->token,
                    'player_name'   => 'Server',
                    'player_avatar' => '📣',
                    'message'       => $next->name . ' is now the session leader.',
                    'type'          => 'server',
                ]);
            }
        }

        Message::create([
            'session_token' => $session->token,
            'player_name'   => 'Server',
            'player_avatar' => '📣',
            'message'       => $name . ' left the session.',
            'type'          => 'server',
        ]);

        $session->refresh()->load('players');
        broadcast(new SessionUpdated($session, 'playerLeft'));

        return response()->json(['ok' => true]);
    }

    /** GET /api/players/{playerToken} — pull own player info */
    public function show(string $playerToken): JsonResponse
    {
        $player = Player::where('player_token', $playerToken)->firstOrFail();
        return response()->json($player->toPrivateArray());
    }
}
