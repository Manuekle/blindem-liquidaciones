<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UsersTest extends TestCase
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

    public function test_only_admins_manage_users(): void
    {
        $this->actingAs($this->user('collaborator'))->get('/usuarios')->assertForbidden();
        $this->post('/usuarios', ['name' => 'X', 'email' => 'x@blindem.test', 'role' => 'admin'])->assertForbidden();

        $this->actingAs($this->user('admin'))->get('/usuarios')->assertOk()->assertSee('colaborador@blindem.test');
        $this->get('/usuarios/nuevo/crear')->assertOk();
        $this->get('/usuarios/'.$this->user('collaborator')->id.'/editar')->assertOk();
    }

    public function test_new_user_gets_an_invitation_link_or_the_given_password(): void
    {
        Notification::fake();
        $this->actingAs($this->user('admin'));

        $this->post('/usuarios', ['name' => 'Laura Gómez', 'email' => 'laura@blindem.test', 'role' => 'collaborator'])->assertRedirect('/usuarios');
        $invited = User::where('email', 'laura@blindem.test')->firstOrFail();
        $this->assertTrue($invited->active);
        Notification::assertSentTo($invited, ResetPassword::class);

        $this->post('/usuarios', ['name' => 'Pedro Ruiz', 'email' => 'pedro@blindem.test', 'role' => 'admin', 'password' => 'Inicial123', 'password_confirmation' => 'Inicial123'])->assertRedirect('/usuarios');
        $direct = User::where('email', 'pedro@blindem.test')->firstOrFail();
        $this->assertTrue($direct->isAdmin());
        $this->assertTrue(Hash::check('Inicial123', $direct->password));
        Notification::assertNotSentTo($direct, ResetPassword::class);

        $this->post('/usuarios', ['name' => 'Repetido', 'email' => 'laura@blindem.test', 'role' => 'collaborator'])->assertSessionHasErrors('email');
    }

    public function test_deactivated_users_cannot_sign_in_or_recover_their_password(): void
    {
        Notification::fake();
        $collaborator = $this->user('collaborator');
        $this->actingAs($this->user('admin'))->patch("/usuarios/{$collaborator->id}/estado")->assertRedirect();
        $this->assertFalse($collaborator->fresh()->active);
        auth()->logout();

        $this->post('/login', ['email' => $collaborator->email, 'password' => 'Cambiar123!'])->assertSessionHasErrors(['email' => 'Tu cuenta está desactivada. Pide acceso a un administrador.']);
        $this->assertGuest();
        $this->post('/login', ['email' => $collaborator->email, 'password' => 'incorrecta'])->assertSessionHasErrors(['email' => 'Las credenciales no son correctas.']);

        $this->post('/olvide-contrasena', ['email' => $collaborator->email])->assertSessionHas('status');
        Notification::assertNothingSent();

        // A session that was already open is closed on its next request.
        $this->actingAs($collaborator->fresh())->get('/')->assertRedirect('/login');
        $this->assertGuest();

        $this->actingAs($this->user('admin'))->patch("/usuarios/{$collaborator->id}/estado");
        auth()->logout();
        $this->post('/login', ['email' => $collaborator->email, 'password' => 'Cambiar123!'])->assertRedirect('/');
    }

    public function test_admins_cannot_lock_themselves_or_the_team_out(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin)->patch("/usuarios/{$admin->id}/estado")->assertSessionHas('error');
        $this->put("/usuarios/{$admin->id}", ['name' => $admin->name, 'email' => $admin->email, 'role' => 'collaborator'])->assertSessionHasErrors('role');
        $this->assertTrue($admin->fresh()->active);
        $this->assertTrue($admin->fresh()->isAdmin());

        // A second admin can be demoted while another one remains.
        $other = User::create(['name' => 'Otra Admin', 'email' => 'otra@blindem.test', 'password' => 'Cambiar123!', 'role' => 'admin']);
        $this->put("/usuarios/{$other->id}", ['name' => 'Otra Admin', 'email' => 'otra@blindem.test', 'role' => 'collaborator'])->assertRedirect('/usuarios');
        $this->assertFalse($other->fresh()->isAdmin());
    }

    public function test_admin_can_send_a_password_link(): void
    {
        Notification::fake();
        $collaborator = $this->user('collaborator');
        $this->actingAs($this->user('admin'))->post("/usuarios/{$collaborator->id}/enlace")->assertSessionHas('success');
        Notification::assertSentTo($collaborator, ResetPassword::class);
    }
}
