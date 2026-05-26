<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresProjectFeature;
use App\Models\Note;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NoteController extends Controller
{
    use EnsuresProjectFeature;

    public function store(Request $request, Project $project): RedirectResponse
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

        $project->notes()->create([
            'creator_id' => $request->user()->id,
            'title' => $validated['title'],
            'content' => $validated['content'] ?? null,
            'color' => $validated['color'] ?? '#fef3c7',
            'rank_id' => $validated['rank_id'] ?? null,
        ]);

        return back();
    }

    public function update(Request $request, Project $project, Note $note): RedirectResponse
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

        return back();
    }

    public function destroy(Request $request, Project $project, Note $note): RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'notes');
        abort_unless($note->project_id === $project->id, 404);

        $note->delete();

        return back();
    }

    public function togglePin(Request $request, Project $project, Note $note): RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'notes');
        abort_unless($note->project_id === $project->id, 404);

        $willPin = ! $note->pinned;
        $note->update([
            'pinned' => $willPin,
            'pinned_at' => $willPin ? now() : null,
        ]);

        return back();
    }

    private function ensureCanEdit(Request $request, Project $project): void
    {
        $user = $request->user();
        $isAdmin = $user->is_admin;
        $isMember = $project->members()->whereKey($user->id)->exists();
        abort_unless($isAdmin || $isMember, 403);
    }
}
