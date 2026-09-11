<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

#[Group('Notifications')]
class NotificationController extends Controller
{
    /**
     * List the current user's notifications (newest first).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Notification::class);

        $notifications = Notification::query()
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->paginate(10);

        return NotificationResource::collection($notifications);
    }

    /**
     * Count the current user's unread notifications.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = Notification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();

        return response()->json(['data' => ['unread' => $count]]);
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(Notification $notification): NotificationResource
    {
        $this->authorize('markAsRead', $notification);

        $notification->update(['read_at' => now()]);

        return new NotificationResource($notification);
    }

    /**
     * Mark all of the current user's notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $updated = Notification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['data' => ['updated' => $updated]]);
    }
}
