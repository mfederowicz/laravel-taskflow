<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\ForgotPasswordController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OAuthClientController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\OrganizationMemberController;
use App\Http\Controllers\Api\PersonalTokenController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectMemberController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::post('/oauth/client', [AuthController::class, 'getPassportClient'])
        ->middleware('throttle:6,1');

    Route::group([
        'as' => 'passport.',
        'prefix' => 'oauth',
        'namespace' => 'Laravel\Passport\Http\Controllers',
        'middleware' => config('passport.middleware', []),
    ], fn () => require __DIR__.'/passport.php');

    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'application' => 'TaskFlow API',
        ]);
    });

    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:10,1');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1');
    Route::post('/login/jwt', [AuthController::class, 'loginJwt'])
        ->middleware('throttle:10,1');
    Route::post('/jwt/refresh', [AuthController::class, 'refreshJwt'])
        ->middleware('throttle:10,1');
    Route::post('/sanctum/refresh', [AuthController::class, 'refreshSanctum'])
        ->middleware('throttle:10,1');
    Route::post('/forgot-password/challenge', [ForgotPasswordController::class, 'challenge'])
        ->middleware('throttle:10,1');
    Route::post('/forgot-password/reset', [ForgotPasswordController::class, 'reset'])
        ->middleware('throttle:5,1');

    Route::middleware(['auth.multi', 'user.active'])->group(function () {

        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', function (Request $request) {
            return response()->json([
                'data' => $request->user(),
            ]);
        });
        Route::put('/user/password', [AuthController::class, 'updateOwnPassword']);
        Route::put('/user/profile', [AuthController::class, 'updateOwnProfile']);
        Route::get('/user/tokens', [PersonalTokenController::class, 'index']);
        Route::post('/user/tokens', [PersonalTokenController::class, 'store']);
        Route::delete('/user/tokens/{token}', [PersonalTokenController::class, 'destroy']);

        // Users (manager only; search for project invitations)
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/search', [UserController::class, 'search']);
        Route::post('/users/{user}/lock', [UserController::class, 'lock']);
        Route::post('/users/{user}/unlock', [UserController::class, 'unlock']);
        Route::patch('/users/{user}/role', [UserController::class, 'updateRole']);
        Route::put('/users/{user}/password', [UserController::class, 'resetPassword']);

        // OAuth clients (manager only)
        Route::get('/oauth/clients', [OAuthClientController::class, 'index']);
        Route::post('/oauth/clients', [OAuthClientController::class, 'store']);
        Route::delete('/oauth/clients/{client}', [OAuthClientController::class, 'destroy']);

        // Tags (shared vocabulary)
        Route::get('/tags', [TagController::class, 'index']);
        Route::post('/tags', [TagController::class, 'store']);
        Route::delete('/tags/{tag}', [TagController::class, 'destroy']);

        // Tasks
        Route::get('/tasks', [TaskController::class, 'index']);
        Route::get('/tasks/export', [TaskController::class, 'export']);
        Route::post('/tasks', [TaskController::class, 'store']);
        Route::get('/tasks/{task}', [TaskController::class, 'show']);
        Route::put('/tasks/{task}', [TaskController::class, 'update']);
        Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);
        Route::post('/tasks/{task}/transfer', [TaskController::class, 'transfer']);

        // Projects
        Route::get('/projects', [ProjectController::class, 'index']);
        Route::get('/projects/export', [ProjectController::class, 'export']);
        Route::post('/projects', [ProjectController::class, 'store']);
        Route::get('/projects/{project}', [ProjectController::class, 'show']);
        Route::put('/projects/{project}', [ProjectController::class, 'update']);
        Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);
        Route::patch('/projects/{project}/organization', [ProjectController::class, 'updateOrganization']);
        Route::post('/projects/{project}/archive', [ProjectController::class, 'archive']);
        Route::post('/projects/{project}/restore', [ProjectController::class, 'restore']);
        Route::post('/projects/{project}/pin', [ProjectController::class, 'pin']);
        Route::delete('/projects/{project}/pin', [ProjectController::class, 'unpin']);

        // Project members
        Route::get('/projects/{project}/members', [ProjectMemberController::class, 'index']);
        Route::post('/projects/{project}/members', [ProjectMemberController::class, 'store']);
        Route::patch('/projects/{project}/members/{member}', [ProjectMemberController::class, 'update'])->scopeBindings();
        Route::delete('/projects/{project}/members/{member}', [ProjectMemberController::class, 'destroy'])->scopeBindings();

        // Project activity feed
        Route::get('/projects/{project}/activities', [ActivityController::class, 'index']);

        // Organizations
        Route::get('/organizations', [OrganizationController::class, 'index']);
        Route::post('/organizations', [OrganizationController::class, 'store']);
        Route::get('/organizations/{organization}', [OrganizationController::class, 'show']);
        Route::put('/organizations/{organization}', [OrganizationController::class, 'update']);
        Route::delete('/organizations/{organization}', [OrganizationController::class, 'destroy']);

        // Organization members
        Route::get('/organizations/{organization}/members', [OrganizationMemberController::class, 'index']);
        Route::post('/organizations/{organization}/members', [OrganizationMemberController::class, 'store']);
        Route::patch('/organizations/{organization}/members/{member}', [OrganizationMemberController::class, 'update'])->scopeBindings();
        Route::delete('/organizations/{organization}/members/{member}', [OrganizationMemberController::class, 'destroy'])->scopeBindings();

        // Comments
        Route::get('/tasks/{task}/comments', [CommentController::class, 'index']);
        Route::post('/tasks/{task}/comments', [CommentController::class, 'store']);
        Route::get('/tasks/{task}/comments/{comment}', [CommentController::class, 'show'])->scopeBindings();
        Route::put('/tasks/{task}/comments/{comment}', [CommentController::class, 'update'])->scopeBindings();
        Route::delete('/tasks/{task}/comments/{comment}', [CommentController::class, 'destroy'])->scopeBindings();

        // Notifications
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);
        Route::post('/notifications/clear-read', [NotificationController::class, 'clearRead']);
    });
});
