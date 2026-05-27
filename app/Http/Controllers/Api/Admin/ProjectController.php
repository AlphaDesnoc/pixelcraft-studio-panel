<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\Task;
use App\Support\AuditLogger;
use App\Support\ProjectTemplateApplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index(): JsonResponse
    {
        $projects = Project::query()
            ->with('owner:id,name,email')
            ->withCount([
                'members as members_count',
                'tasks as tasks_count',
                'tasks as tasks_done_count' => fn ($q) => $q->where('status', Task::STATUS_DONE),
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Project $project) => $this->projectPayload($project));

        return response()->json([
            'projects' => $projects,
            'statuses' => Project::STATUSES,
            'projectTemplates' => ProjectTemplate::query()->orderBy('name')->get()->map->toPayload(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,gif,webp', 'max:5120'],
            'status' => ['required', Rule::in(array_keys(Project::STATUSES))],
            'start_date' => ['nullable', 'date'],
            'template_id' => ['nullable', 'integer', 'exists:project_templates,id'],
        ]);

        $slug = $this->uniqueSlug($validated['name']);

        $project = new Project([
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'],
            'start_date' => $validated['start_date'] ?? null,
            'color' => '#7c5cff',
        ]);
        $project->owner_id = $request->user()->id;

        if ($request->hasFile('image')) {
            $project->image = $request->file('image')->store('projects', 'public');
        }

        $project->save();

        $project->members()->syncWithoutDetaching([
            $request->user()->id => ['role' => 'owner', 'joined_at' => now()],
        ]);

        if (! empty($validated['template_id'])) {
            $template = ProjectTemplate::query()->find($validated['template_id']);
            if ($template) {
                ProjectTemplateApplier::apply($project, $template);
            }
        }

        AuditLogger::log(
            $request->user(),
            'project_created',
            sprintf(
                '%s a créé le projet « %s »',
                $request->user()->name,
                $project->name,
            ),
            $project,
            ['slug' => $project->slug, 'status' => $project->status],
            $request,
        );

        $project->load('owner:id,name,email');
        $project->loadCount([
            'members as members_count',
            'tasks as tasks_count',
            'tasks as tasks_done_count' => fn ($q) => $q->where('status', Task::STATUS_DONE),
        ]);

        return response()->json([
            'project' => $this->projectPayload($project),
        ], 201);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,gif,webp', 'max:5120'],
            'remove_image' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(array_keys(Project::STATUSES))],
            'start_date' => ['nullable', 'date'],
        ]);

        $changes = [];

        if ($validated['name'] !== $project->name) {
            $changes['name'] = ['from' => $project->name, 'to' => $validated['name']];
            $project->slug = $this->uniqueSlug($validated['name'], $project->id);
        }

        if (($validated['description'] ?? null) !== $project->description) {
            $changes['description'] = ['changed' => true];
        }

        if ($validated['status'] !== $project->status) {
            $changes['status'] = [
                'from' => $project->status,
                'to' => $validated['status'],
            ];
        }

        $newStartDate = $validated['start_date'] ?? null;
        $oldStartDate = $project->start_date?->format('Y-m-d');
        if ($newStartDate !== $oldStartDate) {
            $changes['start_date'] = ['from' => $oldStartDate, 'to' => $newStartDate];
        }

        $project->name = $validated['name'];
        $project->description = $validated['description'] ?? null;
        $project->status = $validated['status'];
        $project->start_date = $validated['start_date'] ?? null;

        if ($request->hasFile('image')) {
            $this->deleteStoredImage($project);
            $project->image = $request->file('image')->store('projects', 'public');
            $changes['image'] = ['changed' => true];
        } elseif (! empty($validated['remove_image'])) {
            $this->deleteStoredImage($project);
            $project->image = null;
            $changes['image'] = ['removed' => true];
        }

        $project->save();

        if ($changes !== []) {
            AuditLogger::log(
                $request->user(),
                'project_updated',
                sprintf(
                    '%s a modifié le projet « %s »',
                    $request->user()->name,
                    $project->name,
                ),
                $project,
                ['changes' => $changes],
                $request,
            );
        }

        $project->load('owner:id,name,email');
        $project->loadCount([
            'members as members_count',
            'tasks as tasks_count',
            'tasks as tasks_done_count' => fn ($q) => $q->where('status', Task::STATUS_DONE),
        ]);

        return response()->json([
            'project' => $this->projectPayload($project),
        ]);
    }

    public function destroy(Request $request, Project $project): JsonResponse
    {
        $name = $project->name;
        $this->deleteStoredImage($project);
        $project->delete();

        AuditLogger::log(
            $request->user(),
            'project_deleted',
            sprintf('%s a supprimé le projet « %s »', $request->user()->name, $name),
            null,
            ['project_name' => $name],
            $request,
        );

        return response()->json(['ok' => true]);
    }

    /** @return array<string, mixed> */
    private function projectPayload(Project $project): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'slug' => $project->slug,
            'description' => $project->description,
            'status' => $project->status,
            'image_url' => $project->image_url,
            'owner' => $project->owner ? [
                'id' => $project->owner->id,
                'name' => $project->owner->name,
            ] : null,
            'members_count' => $project->members_count ?? 0,
            'tasks_count' => $project->tasks_count ?? 0,
            'tasks_done_count' => $project->tasks_done_count ?? 0,
            'created_at' => $project->created_at?->toIso8601String(),
        ];
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: Str::random(8);
        $slug = $base;
        $i = 1;

        while (
            Project::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }

    private function deleteStoredImage(Project $project): void
    {
        if ($project->image && ! str_starts_with($project->image, 'http')) {
            Storage::disk('public')->delete($project->image);
        }
    }
}
