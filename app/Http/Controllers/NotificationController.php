<?php

namespace App\Http\Controllers;

use App\Services\NotificationTargetResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request, NotificationTargetResolver $resolver): View
    {
        $notifications = $request->user()->notifications()->latest()->orderByDesc('id')->paginate(15);

        return view('notifications.index', [
            'notifications' => $notifications,
            'targets' => $resolver->resolve($request->user(), $notifications->getCollection()),
        ]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        $record = $request->user()->notifications()->findOrFail($notification);
        $request->user()->notifications()->whereKey($record->id)->whereNull('read_at')->update(['read_at' => now()]);

        return to_route('notifications.index')->with('success', 'Notifikasi ditandai sudah dibaca.');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->notifications()->whereNull('read_at')->update(['read_at' => now()]);

        return to_route('notifications.index')->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }
}
