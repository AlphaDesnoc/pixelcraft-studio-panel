<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresProjectFeature;
use App\Http\Controllers\Concerns\RespondsForApi;
use App\Models\Project;
use App\Models\Sheet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SheetController extends Controller
{
    use EnsuresProjectFeature;
    use RespondsForApi;

    public function store(Request $request, Project $project): JsonResponse|RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'spreadsheet');

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

        $sheet = $project->sheets()->create([
            'name' => $validated['name'] ?? ('Feuille '.($count + 1)),
            'position' => $maxPos + 1,
            'rows' => 50,
            'cols' => 26,
            'data' => new \stdClass,
            'rank_id' => $rankId,
        ]);

        return $this->apiOrBack($request, ['sheet' => $this->sheetPayload($sheet)]);
    }

    public function update(Request $request, Project $project, Sheet $sheet): JsonResponse|RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'spreadsheet');
        abort_unless($sheet->project_id === $project->id, 404);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'rows' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1000'],
            'cols' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:200'],
            'data' => ['sometimes', 'nullable', 'array'],
        ]);

        $update = [];
        if (array_key_exists('name', $validated)) {
            $update['name'] = trim($validated['name']);
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

        return $this->apiOrBack($request, ['sheet' => $this->sheetPayload($sheet->fresh())]);
    }

    public function destroy(Request $request, Project $project, Sheet $sheet): JsonResponse|RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'spreadsheet');
        abort_unless($sheet->project_id === $project->id, 404);

        if ($project->sheets()->where('rank_id', $sheet->rank_id)->count() <= 1) {
            abort(422, 'Impossible de supprimer la dernière feuille.');
        }

        $sheetId = $sheet->id;
        $sheet->delete();

        $project->sheets()
            ->where('rank_id', $sheet->rank_id)
            ->orderBy('position')
            ->get()
            ->each(fn ($s, $idx) => $s->update(['position' => $idx]));

        return $this->apiOrBack($request, ['sheet_id' => $sheetId]);
    }

    public function reorder(Request $request, Project $project): JsonResponse|RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'spreadsheet');

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

        $sheets = $project->sheets()
            ->where('rank_id', $rankId)
            ->orderBy('position')
            ->get()
            ->map(fn (Sheet $s) => $this->sheetPayload($s))
            ->values();

        return $this->apiOrBack($request, ['sheets' => $sheets]);
    }

    /** @return array<string, mixed> */
    public function sheetPayload(Sheet $sheet): array
    {
        return [
            'id' => $sheet->id,
            'name' => $sheet->name,
            'position' => (int) $sheet->position,
            'rows' => (int) $sheet->rows,
            'cols' => (int) $sheet->cols,
            'data' => $sheet->data ?: new \stdClass,
            'rank_id' => $sheet->rank_id,
        ];
    }
}
