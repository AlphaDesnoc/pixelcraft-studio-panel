<?php

namespace App\Http\Controllers;

use App\Events\VoiceMembershipChanged;
use App\Models\Project;
use App\Models\User;
use App\Models\VoiceChannel;
use App\Models\VoiceParticipant;
use App\Support\LiveKitRoomService;
use App\Support\LiveKitToken;
use App\Support\ProjectAccess;
use App\Support\SpaceChatAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

        // Salon réunion (stage) : auditeur par défaut, intervenant si modérateur.
        // Salon vocal : tout le monde peut publier (micro/caméra/écran).
        $canModerate = ProjectAccess::canManageTeam($user, $project);
        $isStage = (bool) $voiceChannel->with_video;
        $role = (! $isStage || $canModerate) ? 'speaker' : 'audience';

        $token = LiveKitToken::create(
            (string) $user->id,
            $user->name,
            $voiceChannel->roomName(),
            [
                'avatar_url' => $user->avatar_url,
                'role' => $role,
                'can_moderate' => $canModerate,
            ],
            canPublish: $role === 'speaker',
        );

        return response()->json([
            'token' => $token,
            'url' => config('livekit.url'),
            'room' => $voiceChannel->roomName(),
            'is_stage' => $isStage,
            'role' => $role,
            'can_moderate' => $canModerate,
        ]);
    }

    /**
     * Promotion (intervenant) ou rétrogradation (auditeur) d'un participant
     * d'un salon réunion. Réservé aux modérateurs ; chacun peut se rétrograder.
     */
    public function setRole(Request $request, Project $project, VoiceChannel $voiceChannel): JsonResponse
    {
        abort_unless($voiceChannel->project_id === $project->id, 404);

        $validated = $request->validate([
            'identity' => ['required', 'string'],
            'role' => ['required', Rule::in(['speaker', 'audience'])],
        ]);

        $user = $request->user();
        $target = (string) $validated['identity'];
        $isSelf = $target === (string) $user->id;

        // Seuls les modérateurs promeuvent/rétrogradent autrui ; chacun peut
        // se retirer de la scène lui-même.
        abort_unless(
            $isSelf || ProjectAccess::canManageTeam($user, $project),
            403,
        );

        $targetUser = User::find($target);
        abort_unless($targetUser !== null, 404);

        $targetCanModerate = ProjectAccess::canManageTeam($targetUser, $project);
        $role = $validated['role'];

        $ok = LiveKitRoomService::updateParticipant(
            $voiceChannel->roomName(),
            $target,
            canPublish: $role === 'speaker',
            metadata: [
                'avatar_url' => $targetUser->avatar_url,
                'role' => $role,
                'can_moderate' => $targetCanModerate,
            ],
        );

        return response()->json(['ok' => $ok, 'role' => $role]);
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
