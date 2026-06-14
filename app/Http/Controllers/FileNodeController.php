<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresProjectFeature;
use App\Models\FileNode;
use App\Models\Project;
use App\Support\AccessLevels;
use App\Support\ProjectAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileNodeController extends Controller
{
    use EnsuresProjectFeature;

    // Disque privé dédié : les fichiers ne sont jamais servis directement,
    // uniquement via les routes authentifiées de ce contrôleur.
    private const DISK = 'files';

    public function storeFolder(Request $request, Project $project): RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'files');

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
        $accessLevel = 0;

        if ($parentId !== null) {
            $parent = $this->validateParent($project, $parentId);
            $rankId = $parent->rank_id;
            $accessLevel = (int) $parent->access_level;
        }

        $project->fileNodes()->create([
            'parent_id' => $parentId,
            'uploader_id' => $request->user()->id,
            'type' => FileNode::TYPE_FOLDER,
            'name' => $validated['name'],
            'rank_id' => $rankId,
            'access_level' => $accessLevel,
        ]);

        return back();
    }

    public function upload(Request $request, Project $project): RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'files');

        $maxKb = (int) config('files.max_upload_kb', 51200);

        $request->validate([
            'files' => ['required', 'array'],
            'files.*' => ['required', 'file', 'max:'.$maxKb],
            'relative_paths' => ['nullable', 'array'],
            'relative_paths.*' => ['nullable', 'string', 'max:1024'],
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

        $accessLevel = 0;

        if ($parentId !== null) {
            $parent = $this->validateParent($project, (int) $parentId);
            $parentId = (int) $parentId;
            $rankId = $parent->rank_id;
            $accessLevel = (int) $parent->access_level;
        }

        $files = $request->file('files');
        $relativePaths = $request->input('relative_paths', []);
        $blocked = (array) config('files.blocked_extensions', []);

        // Validation des extensions interdites.
        foreach ($files as $file) {
            $ext = strtolower($file->getClientOriginalExtension());
            abort_if(in_array($ext, $blocked, true), 422, "Type de fichier interdit : .{$ext}");
        }

        // Vérification du quota.
        $incoming = collect($files)->sum(fn ($f) => $f->getSize());
        $quota = $this->projectQuota($project);
        $used = $this->projectUsedBytes($project);
        abort_if(
            $used + $incoming > $quota,
            422,
            'Quota de stockage du projet dépassé.',
        );

        $folderCache = [];

        foreach ($files as $i => $file) {
            $targetParentId = $parentId;

            // Recrée l'arborescence si un chemin relatif (upload de dossier) est fourni.
            $relative = $relativePaths[$i] ?? null;
            if (is_string($relative) && str_contains($relative, '/')) {
                $segments = array_values(array_filter(explode('/', str_replace('\\', '/', $relative))));
                array_pop($segments); // retire le nom du fichier
                if (! empty($segments)) {
                    $targetParentId = $this->findOrCreateFolderPath(
                        $project,
                        $parentId,
                        $rankId,
                        $accessLevel,
                        $segments,
                        $request->user()->id,
                        $folderCache,
                    );
                }
            }

            $path = $file->store("projects/{$project->id}/files", self::DISK);
            $project->fileNodes()->create([
                'parent_id' => $targetParentId,
                'uploader_id' => $request->user()->id,
                'type' => FileNode::TYPE_FILE,
                'name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
                'rank_id' => $rankId,
                'access_level' => $accessLevel,
            ]);
        }

        return back();
    }

    public function update(Request $request, Project $project, FileNode $node): RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'files');
        abort_unless($node->project_id === $project->id, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
        ]);

        $node->update(['name' => $validated['name']]);

        return back();
    }

    /**
     * Définit le niveau d'accréditation d'un dossier (ou fichier) et propage
     * la restriction à toute sa descendance. Réservé aux gestionnaires d'équipe.
     */
    public function setAccessLevel(Request $request, Project $project, FileNode $node): RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'files');
        abort_unless($node->project_id === $project->id, 404);
        abort_unless(
            ProjectAccess::canManageTeam($request->user(), $project),
            403,
            'Seuls les gestionnaires peuvent verrouiller un dossier.',
        );

        $validated = $request->validate([
            'level' => ['required', 'integer', Rule::in(AccessLevels::values($project))],
        ]);

        $level = (int) $validated['level'];

        // Héritage : on ne peut pas ouvrir un dossier en dessous de son parent.
        $parentLevel = $node->parent_id
            ? (int) (FileNode::where('id', $node->parent_id)->value('access_level') ?? 0)
            : 0;
        abort_if(
            $level < $parentLevel,
            422,
            'Le niveau ne peut pas être inférieur à celui du dossier parent.',
        );

        $node->update(['access_level' => $level]);
        $this->raiseSubtree($node, $level);

        return back();
    }

    public function move(Request $request, Project $project, FileNode $node): RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'files');
        abort_unless($node->project_id === $project->id, 404);

        $validated = $request->validate([
            'parent_id' => ['nullable', 'integer'],
        ]);

        $newParentId = $validated['parent_id'] ?? null;

        if ($newParentId !== null && $newParentId === $node->id) {
            abort(422, 'Déplacement invalide.');
        }

        $target = null;
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
        $this->enforceParentLevel($node, $target);

        return back();
    }

    public function destroy(Request $request, Project $project, FileNode $node): RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'files');
        $this->ensureCanDelete($request, $project);
        abort_unless($node->project_id === $project->id, 404);

        // Suppression douce : déplace vers la corbeille.
        $this->softDeleteTree($node, $request->user()->id);

        return back();
    }

    public function bulkDestroy(Request $request, Project $project): RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'files');
        $this->ensureCanDelete($request, $project);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $nodes = FileNode::query()
            ->where('project_id', $project->id)
            ->whereIn('id', $validated['ids'])
            ->get();

        foreach ($nodes as $node) {
            $this->softDeleteTree($node, $request->user()->id);
        }

        return back();
    }

    public function restore(Request $request, Project $project, int $node): RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'files');
        $this->ensureCanDelete($request, $project);

        $target = FileNode::withTrashed()
            ->where('project_id', $project->id)
            ->findOrFail($node);

        $this->restoreTree($target);

        // Si le parent est lui-même en corbeille, on remonte l'élément à la racine.
        if ($target->parent_id !== null) {
            $parent = FileNode::withTrashed()->find($target->parent_id);
            if (! $parent || $parent->trashed()) {
                $target->update(['parent_id' => null]);
            }
        }

        return back();
    }

    public function forceDestroy(Request $request, Project $project, int $node): RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'files');
        $this->ensureCanDelete($request, $project);

        $target = FileNode::withTrashed()
            ->where('project_id', $project->id)
            ->findOrFail($node);

        $this->forceDeleteTree($target);

        return back();
    }

    public function emptyTrash(Request $request, Project $project): RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'files');
        $this->ensureCanDelete($request, $project);

        $trashed = FileNode::onlyTrashed()
            ->where('project_id', $project->id)
            ->get();

        foreach ($trashed as $node) {
            if ($node->path) {
                Storage::disk(self::DISK)->delete($node->path);
            }
            $node->forceDelete();
        }

        return back();
    }

    public function bulkMove(Request $request, Project $project): RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'files');

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'parent_id' => ['nullable', 'integer'],
        ]);

        $newParentId = $validated['parent_id'] ?? null;
        $target = $newParentId !== null ? $this->validateParent($project, $newParentId) : null;

        $nodes = FileNode::query()
            ->where('project_id', $project->id)
            ->whereIn('id', $validated['ids'])
            ->get();

        foreach ($nodes as $node) {
            if ($newParentId !== null) {
                if ($newParentId === $node->id) {
                    continue;
                }
                if ($target->rank_id !== $node->rank_id) {
                    abort(422, 'Impossible de déplacer entre espaces.');
                }

                $walker = $target;
                $isDescendant = false;
                while ($walker) {
                    if ($walker->id === $node->id) {
                        $isDescendant = true;
                        break;
                    }
                    $walker = $walker->parent;
                }
                if ($isDescendant) {
                    continue;
                }
            }

            $node->update(['parent_id' => $newParentId]);
            $this->enforceParentLevel($node, $target);
        }

        return back();
    }

    public function download(Request $request, Project $project, FileNode $node): BinaryFileResponse
    {
        $this->ensureFeature($request, $project, 'files');
        abort_unless($node->project_id === $project->id, 404);
        $this->ensureClearance($request, $project, $node);
        abort_unless($node->type === FileNode::TYPE_FILE && $node->path, 404);
        abort_unless(Storage::disk(self::DISK)->exists($node->path), 404);

        return response()->download(
            Storage::disk(self::DISK)->path($node->path),
            $node->name,
        );
    }

    public function downloadZip(Request $request, Project $project): StreamedResponse
    {
        $this->ensureFeature($request, $project, 'files');

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $clearance = ProjectAccess::clearanceLevel($request->user(), $project);

        $nodes = FileNode::query()
            ->where('project_id', $project->id)
            ->whereIn('id', $validated['ids'])
            ->where('access_level', '<=', $clearance)
            ->get();

        abort_if($nodes->isEmpty(), 404);

        $files = [];
        foreach ($nodes as $node) {
            $this->collectZipEntries($node, '', $files, $clearance);
        }
        abort_if(empty($files), 404);

        $disk = Storage::disk(self::DISK);
        $fileName = 'fichiers-'.$project->slug.'-'.now()->format('Ymd-His').'.zip';

        return response()->streamDownload(function () use ($files, $disk) {
            $zip = new \ZipArchive;
            $tmp = tempnam(sys_get_temp_dir(), 'zip');
            $zip->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
            foreach ($files as $entry) {
                if ($disk->exists($entry['path'])) {
                    $zip->addFile($disk->path($entry['path']), $entry['name']);
                }
            }
            $zip->close();
            readfile($tmp);
            @unlink($tmp);
        }, $fileName, ['Content-Type' => 'application/zip']);
    }

    public function preview(Request $request, Project $project, FileNode $node): BinaryFileResponse
    {
        $this->ensureFeature($request, $project, 'files');
        abort_unless($node->project_id === $project->id, 404);
        $this->ensureClearance($request, $project, $node);
        abort_unless($node->type === FileNode::TYPE_FILE && $node->path, 404);
        abort_unless(Storage::disk(self::DISK)->exists($node->path), 404);

        return response()->file(
            Storage::disk(self::DISK)->path($node->path),
            [
                'Content-Type' => $node->mime ?: 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.addslashes($node->name).'"',
            ],
        );
    }

    public function duplicate(Request $request, Project $project, FileNode $node): RedirectResponse
    {
        $this->ensureFeatureWrite($request, $project, 'files');
        abort_unless($node->project_id === $project->id, 404);

        $size = $this->subtreeSize($node);
        abort_if(
            $this->projectUsedBytes($project) + $size > $this->projectQuota($project),
            422,
            'Quota de stockage du projet dépassé.',
        );

        $this->duplicateTree($project, $node, $node->parent_id, $request->user()->id, true);

        return back();
    }

    public function share(Request $request, Project $project, FileNode $node): JsonResponse
    {
        $this->ensureFeature($request, $project, 'files');
        abort_unless($node->project_id === $project->id, 404);
        $this->ensureClearance($request, $project, $node);
        abort_unless($node->type === FileNode::TYPE_FILE && $node->path, 404);

        $validated = $request->validate([
            // Durée de validité en minutes (max 30 jours). Vide = lien permanent.
            'expires_in' => ['nullable', 'integer', 'min:1', 'max:43200'],
        ]);

        $params = ['project' => $project->slug, 'node' => $node->id];
        $minutes = $validated['expires_in'] ?? null;

        $url = $minutes
            ? URL::temporarySignedRoute('projects.files.shared', now()->addMinutes($minutes), $params)
            : URL::signedRoute('projects.files.shared', $params);

        return response()->json([
            'url' => $url,
            'expires_in' => $minutes,
        ]);
    }

    public function shared(Request $request, Project $project, FileNode $node): BinaryFileResponse
    {
        // Accès réservé aux membres connectés ayant le droit de lecture (en plus
        // de la signature validée par le middleware 'signed').
        $this->ensureFeature($request, $project, 'files');
        abort_unless($node->project_id === $project->id, 404);
        $this->ensureClearance($request, $project, $node);
        abort_unless($node->type === FileNode::TYPE_FILE && $node->path, 404);
        abort_unless(Storage::disk(self::DISK)->exists($node->path), 404);

        return response()->file(
            Storage::disk(self::DISK)->path($node->path),
            [
                'Content-Type' => $node->mime ?: 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.addslashes($node->name).'"',
            ],
        );
    }

    private function collectZipEntries(FileNode $node, string $prefix, array &$files, int $clearance): void
    {
        // Respecte l'accréditation même en téléchargement groupé / récursif.
        if ((int) $node->access_level > $clearance) {
            return;
        }

        if ($node->isFile() && $node->path) {
            $files[] = [
                'path' => $node->path,
                'name' => $prefix.$node->name,
            ];

            return;
        }

        if ($node->isFolder()) {
            $folderPrefix = $prefix.$node->name.'/';
            foreach ($node->children as $child) {
                $this->collectZipEntries($child, $folderPrefix, $files, $clearance);
            }
        }
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

    private function softDeleteTree(FileNode $node, int $userId): void
    {
        foreach ($node->children as $child) {
            $this->softDeleteTree($child, $userId);
        }
        $node->update(['deleted_by' => $userId]);
        $node->delete();
    }

    private function restoreTree(FileNode $node): void
    {
        $node->restore();
        $node->update(['deleted_by' => null]);

        $children = FileNode::onlyTrashed()
            ->where('parent_id', $node->id)
            ->get();
        foreach ($children as $child) {
            $this->restoreTree($child);
        }
    }

    private function forceDeleteTree(FileNode $node): void
    {
        $children = FileNode::withTrashed()
            ->where('parent_id', $node->id)
            ->get();
        foreach ($children as $child) {
            $this->forceDeleteTree($child);
        }
        if ($node->path) {
            Storage::disk(self::DISK)->delete($node->path);
        }
        $node->forceDelete();
    }

    private function duplicateTree(Project $project, FileNode $node, ?int $parentId, int $userId, bool $isRoot): FileNode
    {
        $name = $isRoot ? $this->copyName($node->name) : $node->name;

        if ($node->isFolder()) {
            $copy = $project->fileNodes()->create([
                'parent_id' => $parentId,
                'uploader_id' => $userId,
                'type' => FileNode::TYPE_FOLDER,
                'name' => $name,
                'rank_id' => $node->rank_id,
                'access_level' => (int) $node->access_level,
            ]);
            foreach ($node->children as $child) {
                $this->duplicateTree($project, $child, $copy->id, $userId, false);
            }

            return $copy;
        }

        $newPath = null;
        if ($node->path && Storage::disk(self::DISK)->exists($node->path)) {
            $ext = pathinfo($node->path, PATHINFO_EXTENSION);
            $newPath = "projects/{$node->project_id}/files/".Str::random(40).($ext ? '.'.$ext : '');
            Storage::disk(self::DISK)->copy($node->path, $newPath);
        }

        return $project->fileNodes()->create([
            'parent_id' => $parentId,
            'uploader_id' => $userId,
            'type' => FileNode::TYPE_FILE,
            'name' => $name,
            'path' => $newPath,
            'mime' => $node->mime,
            'size' => $node->size,
            'rank_id' => $node->rank_id,
            'access_level' => (int) $node->access_level,
        ]);
    }

    private function copyName(string $name): string
    {
        $dot = strrpos($name, '.');
        if ($dot === false || $dot === 0) {
            return $name.' (copie)';
        }

        return substr($name, 0, $dot).' (copie)'.substr($name, $dot);
    }

    private function findOrCreateFolderPath(Project $project, ?int $parentId, ?int $rankId, int $accessLevel, array $segments, int $userId, array &$cache): int
    {
        $currentParent = $parentId;
        $key = (string) ($parentId ?? 'root');

        foreach ($segments as $name) {
            $key .= '/'.$name;
            if (isset($cache[$key])) {
                $currentParent = $cache[$key];

                continue;
            }

            $existing = FileNode::where('project_id', $project->id)
                ->where('parent_id', $currentParent)
                ->where('type', FileNode::TYPE_FOLDER)
                ->where('name', $name)
                ->first();

            if (! $existing) {
                $existing = $project->fileNodes()->create([
                    'parent_id' => $currentParent,
                    'uploader_id' => $userId,
                    'type' => FileNode::TYPE_FOLDER,
                    'name' => $name,
                    'rank_id' => $rankId,
                    'access_level' => $accessLevel,
                ]);
            }

            $cache[$key] = $existing->id;
            $currentParent = $existing->id;
        }

        return (int) $currentParent;
    }

    private function subtreeSize(FileNode $node): int
    {
        $sum = $node->isFile() ? (int) $node->size : 0;
        foreach ($node->children as $child) {
            $sum += $this->subtreeSize($child);
        }

        return $sum;
    }

    private function projectQuota(Project $project): int
    {
        return (int) ($project->storage_quota ?? config('files.default_quota'));
    }

    private function projectUsedBytes(Project $project): int
    {
        return (int) FileNode::withTrashed()
            ->where('project_id', $project->id)
            ->whereNotNull('path')
            ->sum('size');
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

    /**
     * Toute suppression (corbeille, définitive, vidage, restauration) est
     * réservée aux gestionnaires du projet (admin/proprio/gestionnaire). Les
     * membres peuvent créer, uploader, renommer et déplacer normalement.
     */
    private function ensureCanDelete(Request $request, Project $project): void
    {
        abort_unless(
            ProjectAccess::canManageTeam($request->user(), $project),
            403,
            'Seuls les gestionnaires du projet peuvent supprimer des fichiers.',
        );
    }

    /**
     * Garantit l'invariant « enfant >= parent » après un déplacement : si le
     * nœud est moins restrictif que sa nouvelle destination, on l'aligne sur
     * elle puis on propage à sa descendance.
     */
    private function enforceParentLevel(FileNode $node, ?FileNode $target): void
    {
        $parentLevel = $target ? (int) $target->access_level : 0;

        if ((int) $node->access_level < $parentLevel) {
            $node->update(['access_level' => $parentLevel]);
            $this->raiseSubtree($node, $parentLevel);
        }
    }

    /** Relève au niveau donné tous les descendants situés en dessous. */
    private function raiseSubtree(FileNode $node, int $level): void
    {
        foreach ($node->children as $child) {
            if ((int) $child->access_level < $level) {
                $child->update(['access_level' => $level]);
            }
            $this->raiseSubtree($child, $level);
        }
    }

    /**
     * Bloque l'accès direct (download/preview/partage) à un nœud dont le niveau
     * d'accréditation dépasse la clairance de l'utilisateur courant.
     */
    private function ensureClearance(Request $request, Project $project, FileNode $node): void
    {
        $clearance = ProjectAccess::clearanceLevel($request->user(), $project);
        abort_if((int) $node->access_level > $clearance, 403, 'Accréditation insuffisante.');
    }
}
