<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\ProjectController as AdminProjectController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\MyTasksController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\RealtimeController;
use App\Http\Controllers\ProjectMemberController;
use App\Http\Controllers\BugController;
use App\Http\Controllers\BugMessageController;
use App\Http\Controllers\CalendarEventController;
use App\Http\Controllers\ChatMessageController;
use App\Http\Controllers\FileNodeController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\RankController;
use App\Http\Controllers\SheetController;
use App\Http\Controllers\TaskChecklistController;
use App\Http\Controllers\TaskCommentController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskListController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/my-tasks', [MyTasksController::class, 'index'])->name('my-tasks.index');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    Route::post('/realtime/heartbeat', [RealtimeController::class, 'heartbeat'])->name('realtime.heartbeat');
    Route::get('/realtime/sync', [RealtimeController::class, 'sync'])->name('realtime.sync');

    Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::get('/messages/conversations/{conversation}/messages', [MessageController::class, 'messages'])->name('messages.conversations.messages');
    Route::post('/messages/conversations/{conversation}/read', [MessageController::class, 'markRead'])->name('messages.conversations.read');
    Route::post('/messages', [MessageController::class, 'store'])->name('messages.store');

    Route::prefix('projects/{project:slug}')->name('projects.')->middleware('project.member')->group(function () {
        Route::get('/', [ProjectController::class, 'show'])->name('show');

        Route::post('/members', [ProjectMemberController::class, 'store'])->name('members.store');
        Route::put('/members/{user}', [ProjectMemberController::class, 'update'])->name('members.update');
        Route::delete('/members/{user}', [ProjectMemberController::class, 'destroy'])->name('members.destroy');

        Route::post('/lists', [TaskListController::class, 'store'])->name('lists.store');
        Route::put('/lists/{list}', [TaskListController::class, 'update'])->name('lists.update');
        Route::delete('/lists/{list}', [TaskListController::class, 'destroy'])->name('lists.destroy');
        Route::post('/lists/reorder', [TaskListController::class, 'reorder'])->name('lists.reorder');

        Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
        Route::put('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
        Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
        Route::post('/tasks/{task}/move', [TaskController::class, 'move'])->name('tasks.move');

        Route::post('/tasks/{task}/comments', [TaskCommentController::class, 'store'])->name('tasks.comments.store');
        Route::delete('/tasks/{task}/comments/{comment}', [TaskCommentController::class, 'destroy'])->name('tasks.comments.destroy');
        Route::post('/tasks/{task}/attachments', [AttachmentController::class, 'storeTask'])->name('tasks.attachments.store');

        Route::post('/tasks/{task}/checklists', [TaskChecklistController::class, 'store'])->name('tasks.checklists.store');
        Route::put('/tasks/{task}/checklists/{checklist}', [TaskChecklistController::class, 'update'])->name('tasks.checklists.update');
        Route::delete('/tasks/{task}/checklists/{checklist}', [TaskChecklistController::class, 'destroy'])->name('tasks.checklists.destroy');

        Route::post('/tasks/{task}/checklists/{checklist}/items', [TaskChecklistController::class, 'storeItem'])->name('tasks.checklists.items.store');
        Route::put('/tasks/{task}/checklists/{checklist}/items/{item}', [TaskChecklistController::class, 'updateItem'])->name('tasks.checklists.items.update');
        Route::delete('/tasks/{task}/checklists/{checklist}/items/{item}', [TaskChecklistController::class, 'destroyItem'])->name('tasks.checklists.items.destroy');

        Route::get('/chat/messages', [ChatMessageController::class, 'index'])->name('chat.messages.index');
        Route::get('/chat/presence', [ChatMessageController::class, 'presence'])->name('chat.presence');
        Route::post('/chat/messages', [ChatMessageController::class, 'store'])->name('chat.messages.store');
        Route::put('/chat/messages/{message}', [ChatMessageController::class, 'update'])->name('chat.messages.update');
        Route::delete('/chat/messages/{message}', [ChatMessageController::class, 'destroy'])->name('chat.messages.destroy');
        Route::post('/chat/attachments', [AttachmentController::class, 'storeChat'])->name('chat.attachments.store');
        Route::get('/attachments/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');
        Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');

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
        Route::delete('/files/{node}', [FileNodeController::class, 'destroy'])->name('files.destroy');
        Route::get('/files/{node}/download', [FileNodeController::class, 'download'])->name('files.download');

        Route::post('/bugs', [BugController::class, 'store'])->name('bugs.store');
        Route::put('/bugs/{bug}', [BugController::class, 'update'])->name('bugs.update');
        Route::delete('/bugs/{bug}', [BugController::class, 'destroy'])->name('bugs.destroy');
        Route::get('/bugs/{bug}/messages', [BugMessageController::class, 'index'])->name('bugs.messages.index');
        Route::post('/bugs/{bug}/messages', [BugMessageController::class, 'store'])->name('bugs.messages.store');

        Route::get('/ranks', [RankController::class, 'index'])->name('ranks.index');
        Route::post('/ranks', [RankController::class, 'store'])->name('ranks.store');
        Route::put('/ranks/{rank}', [RankController::class, 'update'])->name('ranks.update');
        Route::delete('/ranks/{rank}', [RankController::class, 'destroy'])->name('ranks.destroy');
        Route::post('/ranks/{rank}/members', [RankController::class, 'addMember'])->name('ranks.members.add');
        Route::delete('/ranks/{rank}/members/{user}', [RankController::class, 'removeMember'])->name('ranks.members.remove');
        Route::post('/ranks/{rank}/responsible', [RankController::class, 'setResponsible'])->name('ranks.responsible');
        Route::post('/ranks/{rank}/bugs', [RankController::class, 'toggleBugs'])->name('ranks.bugs');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');

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
