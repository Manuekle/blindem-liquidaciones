<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\User;
use App\Notifications\ActivityNotification;
use App\Notifications\AlertNotification;
use App\Services\NotificationCenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['hashing.bcrypt.rounds' => 4]);
        $this->seed();
    }

    private function user(string $role): User
    {
        return User::where('role', $role)->firstOrFail();
    }

    public function test_pending_retirements_become_alerts_once_and_clear_when_settled(): void
    {
        $collaborator = $this->user('collaborator');
        $pending = Contract::where('status', 'terminated')->doesntHave('settlements')->count();
        $this->assertGreaterThan(0, $pending);

        $feed = $this->actingAs($collaborator)->getJson('/notificaciones')->assertOk()->json();
        $alerts = collect($feed['items'])->where('kind', 'alert');
        $this->assertCount($pending, $alerts->where('title', 'Retiro sin liquidar'));
        $this->assertSame($feed['unread'], count($feed['items']));

        // Syncing again never duplicates.
        app(NotificationCenter::class)->refresh();
        $this->getJson('/notificaciones')->assertOk()->assertJsonCount(count($feed['items']), 'items');

        $contract = Contract::where('status', 'terminated')->doesntHave('settlements')->firstOrFail();
        $this->post("/contratos/{$contract->public_id}/liquidacion")->assertRedirect();
        $after = collect($this->getJson('/notificaciones')->json('items'))->where('title', 'Retiro sin liquidar');
        $this->assertCount($pending - 1, $after);
    }

    public function test_activity_reaches_the_rest_of_the_team_but_not_its_author(): void
    {
        $collaborator = $this->user('collaborator');
        $admin = $this->user('admin');
        $contract = Contract::where('status', 'terminated')->doesntHave('settlements')->firstOrFail();

        $this->actingAs($collaborator)->post("/contratos/{$contract->public_id}/liquidacion")->assertRedirect();

        $this->assertSame(0, $collaborator->notifications()->where('type', ActivityNotification::class)->count());
        $notification = $admin->notifications()->where('type', ActivityNotification::class)->firstOrFail();
        $this->assertSame('Liquidación guardada', $notification->data['title']);
        $this->assertStringStartsWith("/contratos/{$contract->public_id}/liquidaciones/", $notification->data['url']);
    }

    public function test_opening_marks_read_and_read_all_clears_the_count(): void
    {
        $collaborator = $this->user('collaborator');
        $this->actingAs($collaborator)->getJson('/notificaciones')->assertOk();
        $notification = $collaborator->notifications()->where('type', AlertNotification::class)->firstOrFail();

        $this->get("/notificaciones/{$notification->id}/abrir")->assertRedirect($notification->data['url']);
        $this->assertNotNull($notification->fresh()->read_at);

        $this->postJson('/notificaciones/leer')->assertOk()->assertJson(['unread' => 0]);
        $this->assertSame(0, $collaborator->unreadNotifications()->count());
    }

    public function test_notifications_of_another_user_cannot_be_opened(): void
    {
        $collaborator = $this->user('collaborator');
        $this->actingAs($collaborator)->getJson('/notificaciones')->assertOk();
        $notification = $collaborator->notifications()->firstOrFail();

        $this->actingAs($this->user('admin'))->get("/notificaciones/{$notification->id}/abrir")->assertNotFound();
    }
}
