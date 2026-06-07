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
        abort_unless(ProjectAccess::canAccess($request->user(), $project), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'rank_id' => [
                'nullable',
                'integer',
                Rule::exists('ranks', 'id')->where('project_id', $project->id),
            ],
            'with_video' => ['nullable', 'boolean'],
        ]);

        $rank = isset($validated['rank_id'])
            ? $project->ranks()->whereKey($validated['rank_id'])->first()
            : null;

        abort_unless(
            ProjectAccess::canManageVoiceChannel($request->user(), $project, $rank),
            403,
        );

        $maxPos = (int) $project->voiceChannels()->max('position');

        $channel = $project->voiceChannels()->create([
            'name' => $validated['name'],
            'rank_id' => $validated['rank_id'] ?? null,
            'with_video' => (bool) ($validated['with_video'] ?? true),
            'position' => $maxPos + 1,
        ]);

        return $this->respond($request, ['voice_channel' => $channel->toPayload()]);
    }

    public function update(Request $request, Project $project, VoiceChannel $voiceChannel): JsonResponse|RedirectResponse
    {
        abort_unless($voiceChannel->project_id === $project->id, 404);
        abort_unless(
            ProjectAccess::canManageVoiceChannel($request->user(), $project, $voiceChannel->rank),
            403,
        );

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
        ]);

        $voiceChannel->update(['name' => $validated['name']]);

        return $this->respond($request, ['voice_channel' => $voiceChannel->toPayload()]);
    }

    public function destroy(Request $request, Project $project, VoiceChannel $voiceChannel): JsonResponse|RedirectResponse
    {
        abort_unless($voiceChannel->project_id === $project->id, 404);
        abort_unless(
            ProjectAccess::canManageVoiceChannel($request->user(), $project, $voiceChannel->rank),
            403,
        );

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
