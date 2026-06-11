<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\ProjectController as AdminProjectController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\GlobalSearchController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\MyTasksController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\CallController;
use App\Http\Controllers\VoiceController;
use App\Http\Controllers\VoiceChannelController;
use App\Http\Controllers\NotificationPreferenceController;
use App\Http\Controllers\ProfileAvatarController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfileTwoFactorController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\RealtimeController;
use App\Http\Controllers\ProjectMemberController;
use App\Http\Controllers\BugController;
use App\Http\Controllers\BugMessageController;
use App\Http\Controllers\CalendarEventController;
use App\Http\Controllers\CalendarIcalController;
use App\Http\Controllers\ChatMessageController;
use App\Http\Controllers\ChatReactionController;
use App\Http\Controllers\FileNodeController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\RankController;
use App\Http\Controllers\RankDashboardController;
use App\Http\Controllers\SheetController;
use App\Http\Controllers\TaskChecklistController;
use App\Http\Controllers\TaskCommentController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskListController;
use App\Http\Controllers\TaskTagController;
use App\Http\Controllers\DirectMessageReactionController;
use App\Http\Controllers\ProfileThemeController;
use App\Http\Controllers\ProfileDashboardWidgetsController;
use App\Http\Controllers\TaskTemplateController;
use App\Http\Controllers\Api\PanelSessionController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/mobile/enter/{token}', [PanelSessionController::class, 'enter'])
    ->name('mobile.enter');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/my-tasks', [MyTasksController::class, 'index'])->name('my-tasks.index');
    Route::get('/search', GlobalSearchController::class)->name('search.global');

    Route::get('/export/my-tasks', [ExportController::class, 'myTasks'])->name('export.my-tasks');
    Route::get('/export/audit', [ExportController::class, 'audit'])->name('export.audit');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    Route::post('/realtime/heartbeat', [RealtimeController::class, 'heartbeat'])->name('realtime.heartbeat');
    Route::get('/realtime/sync', [RealtimeController::class, 'sync'])->name('realtime.sync');

    Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::get('/messages/conversations', [MessageController::class, 'conversations'])->name('messages.conversations.index');
    Route::get('/messages/conversations/{conversation}/messages', [MessageController::class, 'messages'])->name('messages.conversations.messages');
    Route::post('/messages/conversations/{conversation}/read', [MessageController::class, 'markRead'])->name('messages.conversations.read');
    Route::post('/messages/conversations/{conversation}/attachments', [MessageController::class, 'storeAttachment'])
        ->middleware('throttle:panel-uploads')
        ->name('messages.attachments.store');
    Route::post('/messages', [MessageController::class, 'store'])
        ->middleware('throttle:panel-chat')
        ->name('messages.store');
    Route::post('/messages/{message}/reactions', [DirectMessageReactionController::class, 'toggle'])
        ->name('messages.reactions.toggle');

    Route::prefix('projects/{project:slug}')->name('projects.')->middleware('project.member')->group(function () {
        Route::get('/', [ProjectController::class, 'show'])->name('show');

        Route::post('/members', [ProjectMemberController::class, 'store'])->name('members.store');
        Route::put('/members/{user}', [ProjectMemberController::class, 'update'])->name('members.update');
        Route::delete('/members/{user}', [ProjectMemberController::class, 'destroy'])->name('members.destroy');

        Route::put('/members/{user}/permissions', [ProjectMemberController::class, 'permissions'])->name('members.permissions');

        Route::post('/lists', [TaskListController::class, 'store'])->name('lists.store');
        Route::put('/lists/{list}', [TaskListController::class, 'update'])->name('lists.update');
        Route::delete('/lists/{list}', [TaskListController::class, 'destroy'])->name('lists.destroy');
        Route::post('/lists/reorder', [TaskListController::class, 'reorder'])->name('lists.reorder');

        Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
        Route::put('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
        Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
        Route::post('/tasks/{task}/move', [TaskController::class, 'move'])->name('tasks.move');
        Route::post('/tasks/{task}/duplicate', [TaskController::class, 'duplicate'])->name('tasks.duplicate');
        Route::post('/tasks/{task}/archive', [TaskController::class, 'archive'])->name('tasks.archive');
        Route::post('/tasks/{task}/unarchive', [TaskController::class, 'unarchive'])->name('tasks.unarchive');
        Route::post('/tasks/templates', [TaskTemplateController::class, 'store'])->name('tasks.templates.store');
        Route::post('/tasks/{task}/templates/apply', [TaskTemplateController::class, 'apply'])->name('tasks.templates.apply');
        Route::put('/tasks/{task}/tags', [TaskTagController::class, 'sync'])->name('tasks.tags.sync');

        Route::post('/tags', [TaskTagController::class, 'store'])->name('tags.store');
        Route::delete('/tags/{tag}', [TaskTagController::class, 'destroy'])->name('tags.destroy');

        Route::post('/tasks/{task}/comments', [TaskCommentController::class, 'store'])->name('tasks.comments.store');
        Route::delete('/tasks/{task}/comments/{comment}', [TaskCommentController::class, 'destroy'])->name('tasks.comments.destroy');
        Route::post('/tasks/{task}/attachments', [AttachmentController::class, 'storeTask'])->name('tasks.attachments.store');

        Route::post('/tasks/{task}/checklists', [TaskChecklistController::class, 'store'])->name('tasks.checklists.store');
        Route::put('/tasks/{task}/checklists/{checklist}', [TaskChecklistController::class, 'update'])->name('tasks.checklists.update');
        Route::delete('/tasks/{task}/checklists/{checklist}', [TaskChecklistController::class, 'destroy'])->name('tasks.checklists.destroy');

        Route::post('/tasks/{task}/checklists/{checklist}/items', [TaskChecklistController::class, 'storeItem'])->name('tasks.checklists.items.store');
        Route::post('/tasks/{task}/checklists/{checklist}/items/reorder', [TaskChecklistController::class, 'reorderItems'])->name('tasks.checklists.items.reorder');
        Route::put('/tasks/{task}/checklists/{checklist}/items/{item}', [TaskChecklistController::class, 'updateItem'])->name('tasks.checklists.items.update');
        Route::delete('/tasks/{task}/checklists/{checklist}/items/{item}', [TaskChecklistController::class, 'destroyItem'])->name('tasks.checklists.items.destroy');

        Route::get('/chat/messages', [ChatMessageController::class, 'index'])->name('chat.messages.index');
        Route::get('/chat/presence', [ChatMessageController::class, 'presence'])->name('chat.presence');
        Route::post('/chat/messages', [ChatMessageController::class, 'store'])
            ->middleware('throttle:panel-chat')
            ->name('chat.messages.store');
        Route::put('/chat/messages/{message}', [ChatMessageController::class, 'update'])->name('chat.messages.update');
        Route::delete('/chat/messages/{message}', [ChatMessageController::class, 'destroy'])->name('chat.messages.destroy');
        Route::post('/chat/messages/{message}/pin', [ChatMessageController::class, 'pin'])->name('chat.messages.pin');
        Route::post('/chat/messages/{message}/reactions', [ChatReactionController::class, 'toggle'])->name('chat.reactions.toggle');
        Route::post('/chat/attachments', [AttachmentController::class, 'storeChat'])
            ->middleware('throttle:panel-uploads')
            ->name('chat.attachments.store');
        Route::get('/attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');
        Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

        Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
        Route::post('/announcements', [AnnouncementController::class, 'store'])
            ->middleware('throttle:panel-uploads')
            ->name('announcements.store');
        Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');

        Route::post('/events', [CalendarEventController::class, 'store'])->name('events.store');
        Route::put('/events/{event}', [CalendarEventController::class, 'update'])->name('events.update');
        Route::delete('/events/{event}', [CalendarEventController::class, 'destroy'])->name('events.destroy');

        Route::post('/notes', [NoteController::class, 'store'])->name('notes.store');
        Route::put('/notes/{note}', [NoteController::class, 'update'])->name('notes.update');
        Route::delete('/notes/{note}', [NoteController::class, 'destroy'])->name('notes.destroy');
        Route::post('/notes/{note}/pin', [NoteController::class, 'togglePin'])->name('notes.pin');

        Route::post('/sheets', [SheetController::class, 'store'])->name('sheets.store');
        Route::put('/sheets/{sheet}', [SheetController::class, 'update'])->name('sheets.update');
        Route::delete('/sheets/{sheet}', [SheetController::class, 'destroy'])->name('sheets.destroy');
        Route::post('/sheets/reorder', [SheetController::class, 'reorder'])->name('sheets.reorder');

        Route::post('/files/folder', [FileNodeController::class, 'storeFolder'])->name('files.folder.store');
        Route::post('/files/upload', [FileNodeController::class, 'upload'])->name('files.upload');
        Route::put('/files/{node}', [FileNodeController::class, 'update'])->name('files.update');
        Route::post('/files/{node}/move', [FileNodeController::class, 'move'])->name('files.move');
        Route::post('/files-bulk-move', [FileNodeController::class, 'bulkMove'])->name('files.bulk-move');
        Route::delete('/files-bulk', [FileNodeController::class, 'bulkDestroy'])->name('files.bulk-destroy');
        Route::delete('/files/{node}', [FileNodeController::class, 'destroy'])->name('files.destroy');
        Route::get('/files/{node}/download', [FileNodeController::class, 'download'])->name('files.download');
        Route::get('/files/{node}/preview', [FileNodeController::class, 'preview'])->name('files.preview');
        Route::get('/files-download-zip', [FileNodeController::class, 'downloadZip'])->name('files.download-zip');
        Route::post('/files/{node}/duplicate', [FileNodeController::class, 'duplicate'])->name('files.duplicate');
        Route::post('/files/{node}/share', [FileNodeController::class, 'share'])->name('files.share');
        Route::get('/files/{node}/shared', [FileNodeController::class, 'shared'])->middleware('signed')->name('files.shared');
        Route::post('/files/{node}/restore', [FileNodeController::class, 'restore'])->name('files.restore');
        Route::delete('/files/{node}/force', [FileNodeController::class, 'forceDestroy'])->name('files.force-destroy');
        Route::delete('/files-trash', [FileNodeController::class, 'emptyTrash'])->name('files.empty-trash');

        Route::post('/bugs', [BugController::class, 'store'])->name('bugs.store');
        Route::put('/bugs/{bug}', [BugController::class, 'update'])->name('bugs.update');
        Route::delete('/bugs/{bug}', [BugController::class, 'destroy'])->name('bugs.destroy');
        Route::get('/bugs/{bug}/messages', [BugMessageController::class, 'index'])->name('bugs.messages.index');
        Route::post('/bugs/{bug}/messages', [BugMessageController::class, 'store'])->name('bugs.messages.store');
        Route::post('/bugs/{bug}/link-task', [BugController::class, 'linkTask'])->name('bugs.link-task');
        Route::post('/bugs/{bug}/create-task', [BugController::class, 'createTaskFromBug'])->name('bugs.create-task');

        Route::get('/calendar/export.ics', CalendarIcalController::class)->name('calendar.ical');

        Route::get('/export/bugs', [ExportController::class, 'bugs'])->name('export.bugs');
        Route::get('/export/activity', [ExportController::class, 'projectActivity'])->name('export.activity');
        Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');

        Route::get('/ranks/dashboard', [RankDashboardController::class, 'index'])->name('ranks.dashboard');
        Route::get('/ranks/dashboard/export', [RankDashboardController::class, 'export'])->name('ranks.dashboard.export');
        Route::get('/ranks', [RankController::class, 'index'])->name('ranks.index');
        Route::post('/ranks', [RankController::class, 'store'])->name('ranks.store');
        Route::put('/ranks/{rank}', [RankController::class, 'update'])->name('ranks.update');
        Route::delete('/ranks/{rank}', [RankController::class, 'destroy'])->name('ranks.destroy');
        Route::post('/ranks/{rank}/members', [RankController::class, 'addMember'])->name('ranks.members.add');
        Route::delete('/ranks/{rank}/members/{user}', [RankController::class, 'removeMember'])->name('ranks.members.remove');
        Route::post('/ranks/{rank}/responsible', [RankController::class, 'toggleResponsible'])->name('ranks.responsible');
        Route::post('/ranks/{rank}/bugs', [RankController::class, 'toggleBugs'])->name('ranks.bugs');
    });

    Route::post('/calls', [CallController::class, 'store'])->name('calls.store');
    Route::post('/calls/{call}/token', [CallController::class, 'token'])->name('calls.token');
    Route::post('/calls/{call}/accept', [CallController::class, 'accept'])->name('calls.accept');
    Route::post('/calls/{call}/decline', [CallController::class, 'decline'])->name('calls.decline');
    Route::post('/calls/{call}/hangup', [CallController::class, 'hangup'])->name('calls.hangup');

    Route::post('/projects/{project}/voice-channels', [VoiceChannelController::class, 'store'])
        ->name('projects.voice-channels.store');
    Route::patch('/projects/{project}/voice-channels/{voiceChannel}', [VoiceChannelController::class, 'update'])
        ->name('projects.voice-channels.update');
    Route::delete('/projects/{project}/voice-channels/{voiceChannel}', [VoiceChannelController::class, 'destroy'])
        ->name('projects.voice-channels.destroy');
    Route::post('/projects/{project}/voice-channels/{voiceChannel}/token', [VoiceController::class, 'token'])
        ->name('projects.voice.token');
    Route::post('/projects/{project}/voice-channels/{voiceChannel}/leave', [VoiceController::class, 'leave'])
        ->name('projects.voice.leave');
    Route::post('/projects/{project}/voice-channels/{voiceChannel}/set-role', [VoiceController::class, 'setRole'])
        ->name('projects.voice.set-role');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile/avatar', [ProfileAvatarController::class, 'update'])
        ->name('profile.avatar.update');
    Route::delete('/profile/avatar', [ProfileAvatarController::class, 'destroy'])
        ->name('profile.avatar.destroy');
    Route::put('/profile/notifications', [NotificationPreferenceController::class, 'update'])
        ->name('profile.notifications.update');
    Route::put('/profile/theme', [ProfileThemeController::class, 'update'])
        ->name('profile.theme');
    Route::put('/profile/dashboard-widgets', [ProfileDashboardWidgetsController::class, 'update'])
        ->name('profile.dashboard-widgets');
    Route::post('/profile/two-factor/setup', [ProfileTwoFactorController::class, 'setup'])
        ->name('profile.two-factor.setup');
    Route::post('/profile/two-factor/confirm', [ProfileTwoFactorController::class, 'confirm'])
        ->name('profile.two-factor.confirm');
    Route::delete('/profile/two-factor', [ProfileTwoFactorController::class, 'destroy'])
        ->name('profile.two-factor.disable');

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', fn () => redirect()->route('admin.users.index'))->name('index');

        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::patch('/users/{user}/active', [AdminUserController::class, 'toggleActive'])->name('users.toggle-active');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');

        Route::get('/projects', [AdminProjectController::class, 'index'])->name('projects.index');
        Route::post('/projects', [AdminProjectController::class, 'store'])->name('projects.store');
        Route::post('/projects/{project}', [AdminProjectController::class, 'update'])->name('projects.update');
        Route::delete('/projects/{project}', [AdminProjectController::class, 'destroy'])->name('projects.destroy');

        Route::get('/audit', [AuditLogController::class, 'index'])->name('audit.index');
    });
});

require __DIR__.'/auth.php';
