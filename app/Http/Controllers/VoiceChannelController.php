<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Rank;
use App\Models\VoiceChannel;
use App\Support\ProjectAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VoiceChannelController extends Controller
{
    public function store(Request $request, Project $project): JsonResponse|RedirectResponse
    {
        abort_unless(ProjectAccess::canManageTeam($request->user(), $project), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'rank_id' => [
                'nullable',
                'integer',
                Rule::exists('ranks', 'id')->where('project_id', $project->id),
            ],
            'with_video' => ['nullable', 'boolean'],
        ]);

        $maxPos = (int) $project->voiceChannels()->max('position');

        $channel = $project->voiceChannels()->create([
            'name' => $validated['name'],
            'rank_id' => $validated['rank_id'] ?? null,
            'with_video' => (bool) ($validated['with_video'] ?? true),
            'position' => $maxPos + 1,
        ]);

        return $this->respond($request, ['voice_channel' => $channel->toPayload()]);
    }

    public function destroy(Request $request, Project $project, VoiceChannel $voiceChannel): JsonResponse|RedirectResponse
    {
        abort_unless($voiceChannel->project_id === $project->id, 404);
        abort_unless(ProjectAccess::canManageTeam($request->user(), $project), 403);

        $voiceChannel->delete();

        return $this->respond($request, ['voice_channel_id' => $voiceChannel->id]);
    }

    private function respond(Request $request, array $payload): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return back();
    }
}
