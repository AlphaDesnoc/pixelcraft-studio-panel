<?php

use App\Http\Controllers\Api\Admin\AuditLogController as AdminAuditLogController;
use App\Http\Controllers\Api\Admin\ProjectController as AdminProjectController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\MyTasksController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PanelSessionController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProfileThemeController;
use App\Http\Controllers\Api\ProfileTwoFactorController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectTeamController;
use App\Http\Controllers\Api\ProjectWorkspaceController;
use App\Http\Controllers\Api\PushTokenController;
use App\Http\Controllers\Api\RealtimeConfigController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\BugController;
use App\Http\Controllers\BugMessageController;
use App\Http\Controllers\CalendarEventController;
use App\Http\Controllers\ChatMessageController;
use App\Http\Controllers\ChatReactionController;
use App\Http\Controllers\DirectMessageReactionController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\FileNodeController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\ProjectMemberController;
use App\Http\Controllers\RankController;
use App\Http\Controllers\RankDashboardController;
use App\Http\Controllers\RealtimeController;
use App\Http\Controllers\SheetController;
use App\Http\Controllers\TaskChecklistController;
use App\Http\Controllers\TaskCommentController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskListController;
use App\Http\Controllers\TaskTagController;
use App\Http\Controllers\TaskTemplateController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/two-factor-challenge', [AuthController::class, 'twoFactorChallenge'])->middleware('throttle:10,1');

    Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
        Route::get('/user', [AuthController::class, 'user']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/panel/enter-url', [PanelSessionController::class, 'enterUrl']);

        Route::get('/realtime/config', RealtimeConfigController::class);
        Route::post('/realtime/heartbeat', [RealtimeController::class, 'heartbeat']);
        Route::get('/realtime/sync', [RealtimeController::class, 'sync']);

        Route::get('/profile/notifications', [ProfileController::class, 'notifications']);
        Route::put('/profile/notifications', [ProfileController::class, 'updateNotifications']);
        Route::put('/profile/password', [ProfileController::class, 'updatePassword']);
        Route::put('/profile/theme', [ProfileThemeController::class, 'update']);
        Route::post('/profile/two-factor/setup', [ProfileTwoFactorController::class, 'setup']);
        Route::post('/profile/two-factor/confirm', [ProfileTwoFactorController::class, 'confirm']);
        Route::delete('/profile/two-factor', [ProfileTwoFactorController::class, 'destroy']);

        Route::get('/export/my-tasks', [ExportController::class, 'myTasks']);
        Route::get('/export/audit', [ExportController::class, 'audit']);
        Route::get('/export/bugs/{project:slug}', [ExportController::class, 'bugs']);
        Route::get('/export/activity/{project:slug}', [ExportController::class, 'projectActivity']);

        Route::post('/push-tokens', [PushTokenController::class, 'store']);
        Route::delete('/push-tokens', [PushTokenController::class, 'destroy']);

        Route::get('/projects', [ProjectController::class, 'index']);
        Route::get('/my-tasks', [MyTasksController::class, 'index']);
        Route::get('/search', GlobalSearchController::class);

        Route::get('/conversations', [ConversationController::class, 'index']);
        Route::get('/conversations/{conversation}/messages', [ConversationController::class, 'messages']);
        Route::post('/conversations/{conversation}/read', [ConversationController::class, 'markRead']);
        Route::post('/conversations/{conversation}/attachments', [ConversationController::class, 'storeAttachment'])
            ->middleware('throttle:panel-uploads');
        Route::post('/messages', [ConversationController::class, 'store']);
        Route::post('/messages/{message}/reactions', [DirectMessageReactionController::class, 'toggle']);

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);

        Route::middleware('admin')->prefix('admin')->group(function (): void {
            Route::get('/users', [AdminUserController::class, 'index']);
            Route::post('/users', [AdminUserController::class, 'store']);
            Route::put('/users/{user}', [AdminUserController::class, 'update']);
            Route::delete('/users/{user}', [AdminUserController::class, 'destroy']);
            Route::post('/users/{user}/toggle-active', [AdminUserController::class, 'toggleActive']);
            Route::get('/projects', [AdminProjectController::class, 'index']);
            Route::post('/projects', [AdminProjectController::class, 'store']);
            Route::put('/projects/{project}', [AdminProjectController::class, 'update']);
            Route::delete('/projects/{project}', [AdminProjectController::class, 'destroy']);
            Route::get('/audit', [AdminAuditLogController::class, 'index']);
        });

        Route::prefix('projects/{project:slug}')->middleware('project.member')->group(function (): void {
            Route::get('/', [ProjectWorkspaceController::class, 'show']);
            Route::get('/team', [ProjectTeamController::class, 'show']);

            Route::post('/members', [ProjectMemberController::class, 'store']);
            Route::put('/members/{user}', [ProjectMemberController::class, 'update']);
            Route::delete('/members/{user}', [ProjectMemberController::class, 'destroy']);
            Route::put('/members/{user}/permissions', [ProjectMemberController::class, 'permissions']);

            Route::post('/lists', [TaskListController::class, 'store']);
            Route::put('/lists/{list}', [TaskListController::class, 'update']);
            Route::delete('/lists/{list}', [TaskListController::class, 'destroy']);
            Route::post('/lists/reorder', [TaskListController::class, 'reorder']);

            Route::post('/tasks', [TaskController::class, 'store']);
            Route::put('/tasks/{task}', [TaskController::class, 'update']);
            Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);
            Route::post('/tasks/{task}/move', [TaskController::class, 'move']);
            Route::post('/tasks/{task}/duplicate', [TaskController::class, 'duplicate']);
            Route::post('/tasks/{task}/archive', [TaskController::class, 'archive']);
            Route::post('/tasks/{task}/unarchive', [TaskController::class, 'unarchive']);
            Route::post('/tasks/templates', [TaskTemplateController::class, 'store']);
            Route::post('/tasks/{task}/templates/apply', [TaskTemplateController::class, 'apply']);
            Route::put('/tasks/{task}/tags', [TaskTagController::class, 'sync']);

            Route::post('/tags', [TaskTagController::class, 'store']);
            Route::delete('/tags/{tag}', [TaskTagController::class, 'destroy']);

            Route::post('/tasks/{task}/comments', [TaskCommentController::class, 'store']);
            Route::delete('/tasks/{task}/comments/{comment}', [TaskCommentController::class, 'destroy']);
            Route::post('/tasks/{task}/attachments', [AttachmentController::class, 'storeTask']);

            Route::post('/tasks/{task}/checklists', [TaskChecklistController::class, 'store']);
            Route::put('/tasks/{task}/checklists/{checklist}', [TaskChecklistController::class, 'update']);
            Route::delete('/tasks/{task}/checklists/{checklist}', [TaskChecklistController::class, 'destroy']);
            Route::post('/tasks/{task}/checklists/{checklist}/items', [TaskChecklistController::class, 'storeItem']);
            Route::put('/tasks/{task}/checklists/{checklist}/items/{item}', [TaskChecklistController::class, 'updateItem']);
            Route::delete('/tasks/{task}/checklists/{checklist}/items/{item}', [TaskChecklistController::class, 'destroyItem']);

            Route::get('/chat/messages', [ChatMessageController::class, 'index']);
            Route::get('/chat/presence', [ChatMessageController::class, 'presence']);
            Route::post('/chat/messages', [ChatMessageController::class, 'store'])->middleware('throttle:panel-chat');
            Route::put('/chat/messages/{message}', [ChatMessageController::class, 'update']);
            Route::delete('/chat/messages/{message}', [ChatMessageController::class, 'destroy']);
            Route::post('/chat/messages/{message}/pin', [ChatMessageController::class, 'pin']);
            Route::post('/chat/messages/{message}/reactions', [ChatReactionController::class, 'toggle']);
            Route::post('/chat/attachments', [AttachmentController::class, 'storeChat'])->middleware('throttle:panel-uploads');

            Route::get('/activity-logs', [ActivityLogController::class, 'index']);

            Route::get('/attachments/{attachment}', [AttachmentController::class, 'show']);
            Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy']);

            Route::post('/events', [CalendarEventController::class, 'store']);
            Route::put('/events/{event}', [CalendarEventController::class, 'update']);
            Route::delete('/events/{event}', [CalendarEventController::class, 'destroy']);

            Route::post('/notes', [NoteController::class, 'store']);
            Route::put('/notes/{note}', [NoteController::class, 'update']);
            Route::delete('/notes/{note}', [NoteController::class, 'destroy']);
            Route::post('/notes/{note}/pin', [NoteController::class, 'togglePin']);

            Route::post('/sheets', [SheetController::class, 'store']);
            Route::put('/sheets/{sheet}', [SheetController::class, 'update']);
            Route::delete('/sheets/{sheet}', [SheetController::class, 'destroy']);
            Route::post('/sheets/reorder', [SheetController::class, 'reorder']);

            Route::post('/files/folder', [FileNodeController::class, 'storeFolder']);
            Route::post('/files/upload', [FileNodeController::class, 'upload']);
            Route::put('/files/{node}', [FileNodeController::class, 'update']);
            Route::post('/files/{node}/move', [FileNodeController::class, 'move']);
            Route::post('/files-bulk-move', [FileNodeController::class, 'bulkMove']);
            Route::delete('/files-bulk', [FileNodeController::class, 'bulkDestroy']);
            Route::delete('/files/{node}', [FileNodeController::class, 'destroy']);
            Route::get('/files/{node}/download', [FileNodeController::class, 'download']);
            Route::get('/files/{node}/preview', [FileNodeController::class, 'preview']);
            Route::get('/files-download-zip', [FileNodeController::class, 'downloadZip']);
            Route::post('/files/{node}/duplicate', [FileNodeController::class, 'duplicate']);
            Route::post('/files/{node}/share', [FileNodeController::class, 'share']);
            Route::post('/files/{node}/restore', [FileNodeController::class, 'restore']);
            Route::delete('/files/{node}/force', [FileNodeController::class, 'forceDestroy']);
            Route::delete('/files-trash', [FileNodeController::class, 'emptyTrash']);

            Route::post('/bugs', [BugController::class, 'store']);
            Route::put('/bugs/{bug}', [BugController::class, 'update']);
            Route::delete('/bugs/{bug}', [BugController::class, 'destroy']);
            Route::get('/bugs/{bug}/messages', [BugMessageController::class, 'index']);
            Route::post('/bugs/{bug}/messages', [BugMessageController::class, 'store']);
            Route::post('/bugs/{bug}/link-task', [BugController::class, 'linkTask']);
            Route::post('/bugs/{bug}/create-task', [BugController::class, 'createTaskFromBug']);

            Route::get('/ranks/dashboard', [RankDashboardController::class, 'index']);
            Route::get('/ranks', [RankController::class, 'index']);
            Route::post('/ranks', [RankController::class, 'store']);
            Route::put('/ranks/{rank}', [RankController::class, 'update']);
            Route::delete('/ranks/{rank}', [RankController::class, 'destroy']);
            Route::post('/ranks/{rank}/members', [RankController::class, 'addMember']);
            Route::delete('/ranks/{rank}/members/{user}', [RankController::class, 'removeMember']);
            Route::post('/ranks/{rank}/responsible', [RankController::class, 'setResponsible']);
            Route::post('/ranks/{rank}/bugs', [RankController::class, 'toggleBugs']);
        });
    });
});
