<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\LaborParameter;
use App\Models\User;
use App\Notifications\ActivityNotification;
use App\Notifications\AlertNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

class NotificationCenter
{
    /** Tells everyone else on the team; the author already sees their own flash message. */
    public function announce(User $actor, string $title, string $body, string $url, string $icon = 'info', string $tone = 'info'): void
    {
        $team = User::whereKeyNot($actor->id)->where('active', true)->get();
        if ($team->isNotEmpty()) {
            Notification::send($team, new ActivityNotification($title, $body, $url, $icon, $tone));
        }
    }

    /** The data behind the alerts changed: every user re-syncs on their next request. */
    public function refresh(): void
    {
        Cache::forever('notifications-stamp', uniqid());
    }

    /**
     * Alerts mirror the dashboard work queue: retirements without a settlement, fixed-term contracts ending within 30
     * days and, for administrators, a missing labor parameter year. Each one keeps a stable id, so it is created once,
     * keeps its read state and disappears when the work is done. Runs at most once a minute per user, or right after
     * the data changes (refresh()).
     */
    public function sync(User $user): void
    {
        if (! Cache::add('notifications-sync:'.$user->id.':'.Cache::get('notifications-stamp', 0), true, 60)) {
            return;
        }
        $alerts = [];
        Contract::with('worker')->where('status', 'terminated')->doesntHave('settlements')->latest('end_date')->limit(20)->get()
            ->each(function (Contract $contract) use (&$alerts) {
                $alerts['unsettled:'.$contract->id] = new AlertNotification(
                    'Retiro sin liquidar',
                    $contract->worker->full_name.' · retiro '.$contract->end_date?->format('d/m/Y'),
                    route('settlements.show', $contract, false), 'wallet', 'warning');
            });
        Contract::with('worker')->where('status', 'active')->whereBetween('agreed_end_date', [today(), today()->addDays(30)])->orderBy('agreed_end_date')->get()
            ->each(function (Contract $contract) use (&$alerts) {
                $days = (int) today()->diffInDays($contract->agreed_end_date);
                $alerts['expiring:'.$contract->id.':'.$contract->agreed_end_date->toDateString()] = new AlertNotification(
                    'Contrato por vencer',
                    $contract->worker->full_name.' · '.match ($days) { 0 => 'vence hoy', 1 => 'vence mañana', default => "vence en {$days} días" },
                    route('contracts.show', $contract, false), 'clock', 'warning');
            });
        if ($user->isAdmin() && ! LaborParameter::where('year', now()->year)->exists()) {
            $alerts['parameters:'.now()->year] = new AlertNotification(
                'Faltan parámetros laborales '.now()->year,
                'Registra el salario mínimo y el auxilio de transporte para liquidar con fechas de este año.',
                route('labor-parameters.index', [], false), 'alert', 'danger');
        }

        $ids = [];
        foreach ($alerts as $key => $alert) {
            $alert->id = $ids[] = $this->alertId($user, $key);
        }
        $existing = $user->notifications()->where('type', AlertNotification::class)->pluck('id');
        $user->notifications()->where('type', AlertNotification::class)->whereNotIn('id', $ids)->delete();
        foreach ($alerts as $alert) {
            if (! $existing->contains($alert->id)) {
                $user->notify($alert);
            }
        }
    }

    public function feed(User $user, int $limit = 30): array
    {
        return [
            'unread' => $user->unreadNotifications()->count(),
            'items' => $user->notifications()->limit($limit)->get()->map(fn (DatabaseNotification $notification) => [
                'id' => $notification->id,
                'kind' => $notification->type === AlertNotification::class ? 'alert' : 'activity',
                'title' => $notification->data['title'] ?? '',
                'body' => $notification->data['body'] ?? '',
                'icon' => $notification->data['icon'] ?? 'info',
                'tone' => $notification->data['tone'] ?? 'info',
                'url' => route('notifications.open', $notification),
                'unread' => $notification->read_at === null,
                'time' => $notification->created_at->toIso8601String(),
            ])->all(),
        ];
    }

    /** A UUID derived from the alert key, so the same pending work never notifies twice. */
    private function alertId(User $user, string $key): string
    {
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(md5($user->id.'|'.$key), 4));
    }
}
