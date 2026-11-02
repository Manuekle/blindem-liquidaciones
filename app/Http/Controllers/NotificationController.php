<?php

namespace App\Http\Controllers;

use App\Services\NotificationCenter;
use Illuminate\Http\Request;

class NotificationController
{
    public function index(Request $request, NotificationCenter $center)
    {
        $center->sync($request->user());

        return response()->json($center->feed($request->user()));
    }

    /** Marks the notification read and follows it; only paths stored by the app are ever opened. */
    public function open(Request $request, string $notification)
    {
        $record = $request->user()->notifications()->findOrFail($notification);
        $record->markAsRead();
        $url = $record->data['url'] ?? '';

        return redirect(str_starts_with($url, '/') && ! str_starts_with($url, '//') ? $url : route('dashboard'));
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return $request->expectsJson() ? response()->json(['unread' => 0]) : back();
    }
}
