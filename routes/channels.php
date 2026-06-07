<?php

use App\Models\Bug;
use App\Models\Call;
use App\Models\DirectConversation;
use App\Models\Project;
use App\Support\BugChatAccess;
use App\Support\DirectMessageAccess;
use App\Support\PresenceUser;
use App\Support\SpaceChatAccess;
use App\Support\ProjectPermissions;
use Illuminate\Support\Facades\Broadcast;

Broadcast::routes(['middleware' => ['web', 'auth']]);
Broadcast::routes(['middleware' => ['auth:sanctum'], 'prefix' => 'api/v1', 'as' => 'api.']);

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('bug.{bugId}', function ($user, $bugId) {
    $bug = Bug::query()->with('project')->find($bugId);

    if (! $bug) {
        return false;
    }

    return BugChatAccess::canAccess($user, $bug)
        ? PresenceUser::payload($user)
        : false;
});

Broadcast::channel('project-chat.{projectId}.{spaceKey}', function ($user, $projectId, $spaceKey) {
    $project = Project::query()->find($projectId);

    if (! $project) {
        return false;
    }

    return SpaceChatAccess::canAccess($user, $project, $spaceKey)
        ? PresenceUser::payload($user)
        : false;
});

Broadcast::channel('direct.{conversationId}', function ($user, $conversationId) {
    $conversation = DirectConversation::query()->find($conversationId);

    if (! $conversation) {
        return false;
    }

    return DirectMessageAccess::canAccess($user, $conversation)
        ? PresenceUser::payload($user)
        : false;
});

Broadcast::channel('site-presence', function ($user) {
    return PresenceUser::payload($user);
});

Broadcast::channel('call.{callId}', function ($user, $callId) {
    $call = Call::query()->find($callId);

    if (! $call) {
        return false;
    }

    return $call->isParticipant((int) $user->id);
});

Broadcast::channel('voice-lobby.{projectId}', function ($user, $projectId) {
    $project = Project::query()->find($projectId);

    if (! $project) {
        return false;
    }

    return ProjectPermissions::canRead($user, $project, 'chat');
});

Broadcast::channel('project-kanban.{projectId}', function ($user, $projectId) {
    $project = Project::query()->find($projectId);

    if (! $project) {
        return false;
    }

    if (! ProjectPermissions::canRead($user, $project, 'kanban')) {
        return false;
    }

    return PresenceUser::payload($user);
});
