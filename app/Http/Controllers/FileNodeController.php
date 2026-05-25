<?php

namespace App\Http\Controllers;

use App\Models\FileNode;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FileNodeController extends Controller
{
    public function storeFolder(Request $request, Project $project): RedirectResponse
    {
        $this->ensureCanEdit($request, $project);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'parent_id' => ['nullable', 'integer'],
            'rank_id' => [
                'nullable',
                'integer',
                Rule::exists('ranks', 'id')->where('project_id', $project->id),
            ],
        ]);

        $parentId = $validated['parent_id'] ?? null;
        $rankId = $validated['rank_id'] ?? null;

        if ($parentId !== null) {
            $parent = $this->validateParent($project, $parentId);
            $rankId = $parent->rank_id;
        }

        $project->fileNodes()->create([
            'parent_id' => $parentId,
            'uploader_id' => $request->user()->id,
            'type' => FileNode::TYPE_FOLDER,
            'name' => $validated['name'],
            'rank_id' => $rankId,
        ]);

        return back();
    }

    public function upload(Request $request, Project $project): RedirectResponse
    {
        $this->ensureCanEdit($request, $project);

        $request->validate([
            'files' => ['required', 'array'],
            'files.*' => ['required', 'file', 'max:51200'],
            'parent_id' => ['nullable', 'integer'],
            'rank_id' => [
                'nullable',
                'integer',
                Rule::exists('ranks', 'id')->where('project_id', $project->id),
            ],
        ]);

        $parentId = $request->input('parent_id');
        if ($parentId === '' || $parentId === 'null') {
            $parentId = null;
        }

        $rankId = $request->input('rank_id');
        if ($rankId === '' || $rankId === 'null') {
            $rankId = null;
        }

        if ($parentId !== null) {
            $parent = $this->validateParent($project, (int) $parentId);
            $rankId = $parent->rank_id;
        }

        foreach ($request->file('files') as $file) {
            $path = $file->store("projects/{$project->id}/files", 'public');
            $project->fileNodes()->create([
                'parent_id' => $parentId !== null ? (int) $parentId : null,
                'uploader_id' => $request->user()->id,
                'type' => FileNode::TYPE_FILE,
                'name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
                'rank_id' => $rankId,
            ]);
        }

        return back();
    }

    public function update(Request $request, Project $project, FileNode $node): RedirectResponse
    {
        $this->ensureCanEdit($request, $project);
        abort_unless($node->project_id === $project->id, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
        ]);

        $node->update(['name' => $validated['name']]);

        return back();
    }

    public function move(Request $request, Project $project, FileNode $node): RedirectResponse
    {
        $this->ensureCanEdit($request, $project);
        abort_unless($node->project_id === $project->id, 404);

        $validated = $request->validate([
            'parent_id' => ['nullable', 'integer'],
        ]);

        $newParentId = $validated['parent_id'] ?? null;

        if ($newParentId !== null && $newParentId === $node->id) {
            abort(422, 'Déplacement invalide.');
        }

        if ($newParentId !== null) {
            $target = $this->validateParent($project, $newParentId);
            abort_unless($target->rank_id === $node->rank_id, 422, 'Impossible de déplacer entre espaces.');

            $walker = $target;
            while ($walker) {
                if ($walker->id === $node->id) {
                    abort(422, 'On ne peut pas déplacer un dossier dans lui-même.');
                }
                $walker = $walker->parent;
            }
        }

        $node->update(['parent_id' => $newParentId]);

        return back();
    }

    public function destroy(Request $request, Project $project, FileNode $node): RedirectResponse
    {
        $this->ensureCanEdit($request, $project);
        abort_unless($node->project_id === $project->id, 404);

        $paths = [];
        $this->collectPaths($node, $paths);

        $node->delete();

        foreach ($paths as $p) {
            Storage::disk('public')->delete($p);
        }

        return back();
    }

    public function download(Request $request, Project $project, FileNode $node): BinaryFileResponse
    {
        $this->ensureCanEdit($request, $project);
        abort_unless($node->project_id === $project->id, 404);
        abort_unless($node->type === FileNode::TYPE_FILE && $node->path, 404);
        abort_unless(Storage::disk('public')->exists($node->path), 404);

        return response()->download(
            Storage::disk('public')->path($node->path),
            $node->name,
        );
    }

    private function collectPaths(FileNode $node, array &$paths): void
    {
        if ($node->isFile() && $node->path) {
            $paths[] = $node->path;
        }
        foreach ($node->children as $child) {
            $this->collectPaths($child, $paths);
        }
    }

    private function validateParent(Project $project, int $parentId): FileNode
    {
        $parent = FileNode::find($parentId);
        abort_unless($parent && $parent->project_id === $project->id, 422, 'Dossier invalide.');
        abort_unless($parent->isFolder(), 422, 'Le parent doit être un dossier.');

        return $parent;
    }

    private function ensureCanEdit(Request $request, Project $project): void
    {
        $user = $request->user();
        $isAdmin = $user->is_admin;
        $isMember = $project->members()->whereKey($user->id)->exists();
        abort_unless($isAdmin || $isMember, 403);
    }
}
