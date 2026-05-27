<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresProjectFeature;
use App\Http\Controllers\Concerns\RespondsForApi;
use App\Models\Note;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NoteController extends Controller
{
    use EnsuresProjectFeature;
    use RespondsForApi;

    public function store(Request $request, Project $project): JsonResponse|RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'notes');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string', 'max:10000'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'rank_id' => [
                'nullable',
                'integer',
                Rule::exists('ranks', 'id')->where('project_id', $project->id),
            ],
        ]);

        $note = $project->notes()->create([
            'creator_id' => $request->user()->id,
            'title' => $validated['title'],
            'content' => $validated['content'] ?? null,
            'color' => $validated['color'] ?? '#fef3c7',
            'rank_id' => $validated['rank_id'] ?? null,
        ]);

        $note->load('creator:id,name,email');

        return $this->apiOrBack($request, ['note' => $this->notePayload($note)]);
    }

    public function update(Request $request, Project $project, Note $note): JsonResponse|RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'notes');
        abort_unless($note->project_id === $project->id, 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string', 'max:10000'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $note->update([
            'title' => $validated['title'],
            'content' => $validated['content'] ?? null,
            'color' => $validated['color'] ?? $note->color,
        ]);

        $note->load('creator:id,name,email');

        return $this->apiOrBack($request, ['note' => $this->notePayload($note->fresh())]);
    }

    public function destroy(Request $request, Project $project, Note $note): JsonResponse|RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'notes');
        abort_unless($note->project_id === $project->id, 404);

        $noteId = $note->id;
        $note->delete();

        return $this->apiOrBack($request, ['note_id' => $noteId]);
    }

    public function togglePin(Request $request, Project $project, Note $note): JsonResponse|RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'notes');
        abort_unless($note->project_id === $project->id, 404);

        $willPin = ! $note->pinned;
        $note->update([
            'pinned' => $willPin,
            'pinned_at' => $willPin ? now() : null,
        ]);

        return $this->apiOrBack($request, ['note' => $this->notePayload($note->fresh())]);
    }

    /** @return array<string, mixed> */
    private function notePayload(Note $note): array
    {
        return [
            'id' => $note->id,
            'title' => $note->title,
            'content' => $note->content,
            'color' => $note->color,
            'pinned' => (bool) $note->pinned,
            'pinned_at' => optional($note->pinned_at)?->toIso8601String(),
            'created_at' => optional($note->created_at)?->toIso8601String(),
            'updated_at' => optional($note->updated_at)?->toIso8601String(),
            'rank_id' => $note->rank_id,
            'creator' => $note->creator ? [
                'id' => $note->creator->id,
                'name' => $note->creator->name,
            ] : null,
        ];
    }

    private function ensureCanEdit(Request $request, Project $project): void
    {
        $user = $request->user();
        $isAdmin = $user->is_admin;
        $isMember = $project->members()->whereKey($user->id)->exists();
        abort_unless($isAdmin || $isMember, 403);
    }
}
