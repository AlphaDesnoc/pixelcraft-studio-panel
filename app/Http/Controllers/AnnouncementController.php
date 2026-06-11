<?php

namespace App\Http\Controllers;

use App\Events\AnnouncementDeleted;
use App\Events\AnnouncementPosted;
use App\Models\Announcement;
use App\Models\Project;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\PanelNotifier;
use App\Support\ProjectAccess;
use App\Support\ProjectSpace;
use App\Support\SpaceChatAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AnnouncementController extends Controller
{
    public function index(Request $request, Project $project): JsonResponse
    {
        $user = $request->user();
        ProjectAccess::ensureAccess($user, $project);

        $announcements = $project->announcements()
            ->where('space_key', ProjectSpace::GLOBAL)
            ->with(['user:id,name,avatar_path', 'attachments'])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (Announcement $a) => $a->toPayload())
            ->values();

        return response()->json(['announcements' => $announcements]);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $user = $request->user();
        ProjectAccess::ensureAccess($user, $project);
        abort_unless($user->is_admin, 403);

        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:160'],
            'body' => ['nullable', 'string', 'max:5000'],
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => ['file', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:8192'],
        ]);

        $body = trim((string) ($validated['body'] ?? ''));
        $images = $request->file('images', []);

        if ($body === '' && count($images) === 0) {
            throw ValidationException::withMessages([
                'body' => 'Ajoutez un message ou au moins une image.',
            ]);
        }

        $announcement = $project->announcements()->create([
            'user_id' => $user->id,
            'space_key' => ProjectSpace::GLOBAL,
            'title' => $validated['title'] ?? null,
            'body' => $body !== '' ? $body : null,
        ]);

        foreach ($images as $image) {
            $path = $image->store("projects/{$project->id}/announcements/{$announcement->id}", 'public');
            $mimeType = $image->getMimeType() ?: $image->getClientMimeType();

            $announcement->attachments()->create([
                'user_id' => $user->id,
                'path' => $path,
                'original_name' => $image->getClientOriginalName(),
                'mime_type' => $mimeType,
                'size' => $image->getSize(),
            ]);
        }

        $announcement->load(['user:id,name,avatar_path', 'attachments']);

        $this->notifyMembers($project, $announcement, $user);

        AnnouncementPosted::dispatch($announcement);

        return response()->json(['announcement' => $announcement->toPayload()]);
    }

    public function destroy(Request $request, Project $project, Announcement $announcement): JsonResponse
    {
        $user = $request->user();
        ProjectAccess::ensureAccess($user, $project);
        abort_unless($announcement->project_id === $project->id, 404);
        abort_unless(
            $user->is_admin || $announcement->user_id === $user->id,
            403,
        );

        foreach ($announcement->attachments as $attachment) {
            Storage::disk('public')->delete($attachment->path);
            $attachment->delete();
        }

        $announcementId = $announcement->id;
        $announcement->delete();

        AnnouncementDeleted::dispatch($announcementId, $project->id);

        return response()->json(['ok' => true]);
    }

    private function notifyMembers(Project $project, Announcement $announcement, User $author): void
    {
        $url = route('projects.show', $project->slug).'?space='.ProjectSpace::GLOBAL.'&tab=announcements';

        $title = $announcement->title
            ? sprintf('Annonce : %s', str($announcement->title)->limit(80))
            : 'Nouvelle annonce';

        $preview = $announcement->body
            ? str($announcement->body)->limit(120)
            : ($announcement->attachments->isNotEmpty() ? 'A partagé une image.' : '');

        foreach (SpaceChatAccess::eligibleUsers($project, ProjectSpace::GLOBAL) as $member) {
            if ($member->id === $author->id) {
                continue;
            }

            PanelNotifier::send(
                $member,
                UserNotification::TYPE_ANNOUNCEMENT,
                $title,
                sprintf('%s · %s', $author->name, $preview),
                $url,
                ['project_id' => $project->id, 'announcement_id' => $announcement->id],
            );
        }
    }
}
