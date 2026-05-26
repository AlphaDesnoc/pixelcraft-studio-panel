<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(): Response
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
            ->map(function (Project $project) {
                $arr = $project->toArray();
                $arr['notes_count'] = 0;
                $arr['events_count'] = 0;

                return $arr;
            });

        return Inertia::render('Admin/Projects/Index', [
            'projects' => $projects,
            'statuses' => Project::STATUSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,gif,webp', 'max:5120'],
            'status' => ['required', Rule::in(array_keys(Project::STATUSES))],
            'start_date' => ['nullable', 'date'],
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

        return back()->with('success', 'Projet créé.');
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,gif,webp', 'max:5120'],
            'remove_image' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(array_keys(Project::STATUSES))],
            'start_date' => ['nullable', 'date'],
        ]);

        if ($validated['name'] !== $project->name) {
            $project->slug = $this->uniqueSlug($validated['name'], $project->id);
        }

        $project->name = $validated['name'];
        $project->description = $validated['description'] ?? null;
        $project->status = $validated['status'];
        $project->start_date = $validated['start_date'] ?? null;

        if ($request->hasFile('image')) {
            $this->deleteStoredImage($project);
            $project->image = $request->file('image')->store('projects', 'public');
        } elseif (! empty($validated['remove_image'])) {
            $this->deleteStoredImage($project);
            $project->image = null;
        }

        $project->save();

        return back()->with('success', 'Projet mis à jour.');
    }

    public function destroy(Request $request, Project $project): RedirectResponse
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

        return back()->with('success', 'Projet supprimé.');
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
