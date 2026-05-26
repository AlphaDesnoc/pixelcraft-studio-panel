<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresProjectFeature;
use App\Models\Project;
use App\Models\Sheet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SheetController extends Controller
{
    use EnsuresProjectFeature;

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->ensureFeature($request, $project, 'spreadsheet');
        $this->ensureCanEdit($request, $project);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'rank_id' => [
                'nullable',
                'integer',
                Rule::exists('ranks', 'id')->where('project_id', $project->id),
            ],
        ]);

        $rankId = $validated['rank_id'] ?? null;
        $scoped = $project->sheets()->where('rank_id', $rankId);
        $maxPos = (int) $scoped->max('position');
        $count = $scoped->count();

        $project->sheets()->create([
            'name' => $validated['name'] ?? ('Feuille '.($count + 1)),
            'position' => $maxPos + 1,
            'rows' => 50,
            'cols' => 26,
            'data' => new \stdClass,
            'rank_id' => $rankId,
        ]);

        return back();
    }

    public function update(Request $request, Project $project, Sheet $sheet): RedirectResponse
    {
        $this->ensureFeature($request, $project, 'spreadsheet');
        $this->ensureCanEdit($request, $project);
        abort_unless($sheet->project_id === $project->id, 404);

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'rows' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'cols' => ['nullable', 'integer', 'min:1', 'max:200'],
            'data' => ['nullable', 'array'],
        ]);

        $update = [];
        if (array_key_exists('name', $validated) && $validated['name'] !== null) {
            $update['name'] = $validated['name'];
        }
        if (array_key_exists('rows', $validated) && $validated['rows'] !== null) {
            $update['rows'] = $validated['rows'];
        }
        if (array_key_exists('cols', $validated) && $validated['cols'] !== null) {
            $update['cols'] = $validated['cols'];
        }
        if (array_key_exists('data', $validated)) {
            $update['data'] = $validated['data'] ?? [];
        }

        if (! empty($update)) {
            $sheet->update($update);
        }

        return back();
    }

    public function destroy(Request $request, Project $project, Sheet $sheet): RedirectResponse
    {
        $this->ensureFeature($request, $project, 'spreadsheet');
        $this->ensureCanEdit($request, $project);
        abort_unless($sheet->project_id === $project->id, 404);

        if ($project->sheets()->where('rank_id', $sheet->rank_id)->count() <= 1) {
            abort(422, 'Impossible de supprimer la dernière feuille.');
        }

        $sheet->delete();

        $project->sheets()
            ->where('rank_id', $sheet->rank_id)
            ->orderBy('position')
            ->get()
            ->each(fn ($s, $idx) => $s->update(['position' => $idx]));

        return back();
    }

    public function reorder(Request $request, Project $project): RedirectResponse
    {
        $this->ensureFeature($request, $project, 'spreadsheet');
        $this->ensureCanEdit($request, $project);

        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
            'rank_id' => [
                'nullable',
                'integer',
                Rule::exists('ranks', 'id')->where('project_id', $project->id),
            ],
        ]);

        $rankId = $validated['rank_id'] ?? null;
        $allowedIds = $project->sheets()->where('rank_id', $rankId)->pluck('id')->all();

        foreach ($validated['ids'] as $id) {
            abort_unless(in_array($id, $allowedIds, true), 404);
        }

        foreach ($validated['ids'] as $idx => $id) {
            $project->sheets()->whereKey($id)->update(['position' => $idx]);
        }

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
