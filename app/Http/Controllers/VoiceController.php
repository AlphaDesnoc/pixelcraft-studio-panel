<?php

namespace App\Http\Controllers;

use App\Events\VoiceMembershipChanged;
use App\Models\Project;
use App\Models\VoiceChannel;
use App\Models\VoiceParticipant;
use App\Support\LiveKitToken;
use App\Support\SpaceChatAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoiceController extends Controller
{
    /**
     * Délivre un access token LiveKit pour un salon vocal, et enregistre la
     * présence de l'utilisateur (un seul salon actif à la fois).
     */
    public function token(Request $request, Project $project, VoiceChannel $voiceChannel): JsonResponse
    {
        abort_unless($voiceChannel->project_id === $project->id, 404);

        $user = $request->user();

        abort_unless(
            SpaceChatAccess::canAccess($user, $project, $voiceChannel->spaceKey()),
            403,
            "Accès au salon vocal refusé.",
        );

        // L'utilisateur quitte tout autre salon avant de rejoindre celui-ci.
        $this->clearMembership($user->id);

        VoiceParticipant::query()->create([
            'voice_channel_id' => $voiceChannel->id,
            'project_id' => $project->id,
            'user_id' => $user->id,
            'joined_at' => now(),
        ]);

        VoiceMembershipChanged::dispatch($project->id, $voiceChannel->id, 'join', [
            'id' => $user->id,
            'name' => $user->name,
            'avatar_url' => $user->avatar_url,
        ]);

        $token = LiveKitToken::create(
            (string) $user->id,
            $user->name,
            $voiceChannel->roomName(),
            ['avatar_url' => $user->avatar_url],
        );

        return response()->json([
            'token' => $token,
            'url' => config('livekit.url'),
            'room' => $voiceChannel->roomName(),
        ]);
    }

    public function leave(Request $request, Project $project, VoiceChannel $voiceChannel): JsonResponse
    {
        $user = $request->user();

        VoiceParticipant::query()
            ->where('user_id', $user->id)
            ->where('voice_channel_id', $voiceChannel->id)
            ->delete();

        VoiceMembershipChanged::dispatch($project->id, $voiceChannel->id, 'leave', [
            'id' => $user->id,
            'name' => $user->name,
            'avatar_url' => $user->avatar_url,
        ]);

        return response()->json(['ok' => true]);
    }

    private function clearMembership(int $userId): void
    {
        VoiceParticipant::query()
            ->where('user_id', $userId)
            ->get()
            ->each(function (VoiceParticipant $p) {
                $p->delete();
                VoiceMembershipChanged::dispatch($p->project_id, $p->voice_channel_id, 'leave', [
                    'id' => $p->user_id,
                ]);
            });
    }
}
