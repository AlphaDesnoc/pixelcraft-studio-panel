<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\ChatMessage;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    public function storeTask(Request $request, Project $project, Task $task): RedirectResponse|JsonResponse
    {
        $this->ensureMember($request, $project);
        abort_unless($task->project_id === $project->id, 404);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $file = $validated['file'];
        $path = $file->store("projects/{$project->id}/tasks/{$task->id}", 'public');

        $task->attachments()->create([
            'user_id' => $request->user()->id,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        return back();
    }

    public function storeChat(Request $request, Project $project): JsonResponse
    {
        $this->ensureMember($request, $project);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'space' => ['required', 'string', 'max:64'],
        ]);

        $file = $validated['file'];
        $path = $file->store("projects/{$project->id}/chat", 'public');

        $message = ChatMessage::query()->create([
            'project_id' => $project->id,
            'user_id' => $request->user()->id,
            'space_key' => $validated['space'],
            'body' => '📎 '.$file->getClientOriginalName(),
        ]);

        $attachment = $message->attachments()->create([
            'user_id' => $request->user()->id,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        $message->load('user:id,name', 'attachments');

        return response()->json([
            'message' => $message->toPayload(),
            'attachment' => $attachment->toPayload(),
        ]);
    }

    public function destroy(Request $request, Project $project, Attachment $attachment): RedirectResponse
    {
        $this->ensureMember($request, $project);
        abort_unless($attachment->user_id === $request->user()->id || $request->user()->is_admin, 403);

        Storage::disk('public')->delete($attachment->path);
        $attachment->delete();

        return back();
    }

    private function ensureMember(Request $request, Project $project): void
    {
        $user = $request->user();
        abort_unless($user->is_admin || $project->members()->whereKey($user->id)->exists(), 403);
    }
}
