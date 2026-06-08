<?php

namespace App\Http\Controllers;

use App\Events\CallStateChanged;
use App\Events\IncomingCall;
use App\Models\Call;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\CallAccess;
use App\Support\LiveKitToken;
use App\Support\PanelNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CallController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'callee_id' => ['required', 'integer', 'exists:users,id'],
            'with_video' => ['nullable', 'boolean'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
        ]);

        $caller = $request->user();
        $callee = User::query()->findOrFail($validated['callee_id']);

        abort_unless(CallAccess::canCall($caller, $callee), 403, "Vous ne pouvez pas appeler cet utilisateur.");

        // Empêche les appels en double simultanés vers la même personne.
        $existing = Call::query()
            ->where('caller_id', $caller->id)
            ->where('callee_id', $callee->id)
            ->whereIn('status', [Call::STATUS_RINGING, Call::STATUS_ACCEPTED])
            ->first();

        if ($existing) {
            return response()->json(['call' => $existing->toPayload()]);
        }

        $call = Call::query()->create([
            'project_id' => $validated['project_id'] ?? null,
            'caller_id' => $caller->id,
            'callee_id' => $callee->id,
            'status' => Call::STATUS_RINGING,
            'with_video' => (bool) ($validated['with_video'] ?? false),
        ]);

        IncomingCall::dispatch($call);

        PanelNotifier::send(
            $callee->id,
            UserNotification::TYPE_CALL_INCOMING,
            'Appel entrant',
            sprintf('%s vous appelle', $caller->name),
            null,
            ['call_id' => $call->id, 'caller_id' => $caller->id],
        );

        return response()->json(['call' => $call->toPayload()]);
    }

    /**
     * Délivre un access token LiveKit pour la room dédiée à l'appel. Le média
     * 1:1 transite par le SFU LiveKit (gère le NAT/TURN), plus de P2P/SDP.
     */
    public function token(Request $request, Call $call): JsonResponse
    {
        $user = $request->user();
        abort_unless($call->isParticipant($user->id), 403);
        abort_unless($call->isActive(), 410, "Cet appel n'est plus actif.");

        $token = LiveKitToken::create(
            (string) $user->id,
            $user->name,
            $call->roomName(),
            ['avatar_url' => $user->avatar_url],
            canPublish: true,
        );

        return response()->json([
            'token' => $token,
            'url' => config('livekit.url'),
            'room' => $call->roomName(),
        ]);
    }

    public function accept(Request $request, Call $call): JsonResponse
    {
        $user = $request->user();
        abort_unless((int) $call->callee_id === (int) $user->id, 403);

        if ($call->status === Call::STATUS_RINGING) {
            $call->update(['status' => Call::STATUS_ACCEPTED, 'started_at' => now()]);
            CallStateChanged::dispatch($call->fresh());
        }

        return response()->json(['call' => $call->fresh()->toPayload()]);
    }

    public function decline(Request $request, Call $call): JsonResponse
    {
        $user = $request->user();
        abort_unless($call->isParticipant($user->id), 403);

        if ($call->isActive()) {
            $status = $call->status === Call::STATUS_RINGING ? Call::STATUS_DECLINED : Call::STATUS_ENDED;
            $call->update(['status' => $status, 'ended_at' => now()]);
            CallStateChanged::dispatch($call->fresh());
        }

        return response()->json(['call' => $call->fresh()->toPayload()]);
    }

    public function hangup(Request $request, Call $call): JsonResponse
    {
        $user = $request->user();
        abort_unless($call->isParticipant($user->id), 403);

        if ($call->isActive()) {
            $call->update(['status' => Call::STATUS_ENDED, 'ended_at' => now()]);
            CallStateChanged::dispatch($call->fresh());
        }

        return response()->json(['call' => $call->fresh()->toPayload()]);
    }
}
