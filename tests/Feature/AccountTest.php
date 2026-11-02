<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['hashing.bcrypt.rounds' => 4]);
        $this->seed();
    }

    private function admin(): User
    {
        return User::where('role', 'admin')->firstOrFail();
    }

    public function test_guest_pages_render_and_login_links_to_recovery(): void
    {
        $this->get('/login')->assertOk()->assertSee(route('password.request'));
        $this->get('/olvide-contrasena')->assertOk()->assertSee('Recupera tu contraseña');
        $this->get('/restablecer-contrasena/abc?email=admin@blindem.test')->assertOk()->assertSee('admin@blindem.test');
    }

    public function test_only_the_login_is_indexable_and_shares_a_preview(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('<meta name="robots" content="index, follow, max-image-preview:large">', false)
            ->assertSee('<meta property="og:image" content="'.asset('og-image.png').'">', false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
            ->assertSee('"@type":"WebApplication"', false);
        $this->get('/olvide-contrasena')->assertOk()->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        $this->actingAs($this->admin())->get('/')->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertDontSee('application/ld+json', false);
    }

    public function test_reset_link_is_sent_without_revealing_unknown_addresses(): void
    {
        Notification::fake();
        $known = $this->from('/olvide-contrasena')->post('/olvide-contrasena', ['email' => 'admin@blindem.test']);
        $known->assertRedirect('/olvide-contrasena')->assertSessionHas('status');
        Notification::assertSentTo($this->admin(), ResetPassword::class);

        $unknown = $this->from('/olvide-contrasena')->post('/olvide-contrasena', ['email' => 'nadie@blindem.test']);
        $unknown->assertRedirect('/olvide-contrasena')->assertSessionHas('status', $known->getSession()->get('status'));
    }

    public function test_a_valid_token_sets_a_new_password(): void
    {
        $user = $this->admin();
        $token = Password::createToken($user);

        $this->post('/restablecer-contrasena', ['token' => $token, 'email' => $user->email, 'password' => 'Nueva12345', 'password_confirmation' => 'Nueva12345'])
            ->assertRedirect('/login')->assertSessionHas('status');
        $this->assertTrue(Hash::check('Nueva12345', $user->fresh()->password));

        // Tokens are single use.
        $this->from('/restablecer-contrasena/'.$token)->post('/restablecer-contrasena', ['token' => $token, 'email' => $user->email, 'password' => 'Otra123456', 'password_confirmation' => 'Otra123456'])
            ->assertSessionHasErrors('email');
    }

    public function test_profile_updates_name_and_email(): void
    {
        $user = $this->admin();
        $this->actingAs($user)->get('/perfil')->assertOk()->assertSee('Perfil y seguridad');

        $this->put('/perfil', ['name' => 'Ana Admin', 'email' => 'ana@blindem.test'])->assertRedirect('/perfil')->assertSessionHas('success');
        $this->assertSame('ana@blindem.test', $user->fresh()->email);

        $taken = User::where('role', 'collaborator')->value('email');
        $this->put('/perfil', ['name' => 'Ana Admin', 'email' => $taken])->assertSessionHasErrors('email');
    }

    public function test_password_change_requires_the_current_one(): void
    {
        $user = $this->admin();
        $this->actingAs($user)->put('/perfil/contrasena', ['current_password' => 'incorrecta', 'password' => 'Nueva12345', 'password_confirmation' => 'Nueva12345'])
            ->assertSessionHasErrors('current_password');
        $this->put('/perfil/contrasena', ['current_password' => 'Cambiar123!', 'password' => 'corta', 'password_confirmation' => 'corta'])
            ->assertSessionHasErrors('password');

        $this->put('/perfil/contrasena', ['current_password' => 'Cambiar123!', 'password' => 'Nueva12345', 'password_confirmation' => 'Nueva12345'])
            ->assertRedirect('/perfil')->assertSessionHas('success');
        $this->assertTrue(Hash::check('Nueva12345', $user->fresh()->password));
    }

    public function test_closing_other_sessions_asks_for_the_password(): void
    {
        $this->actingAs($this->admin())->delete('/perfil/sesiones', ['confirm_password' => 'incorrecta'])->assertSessionHasErrors('confirm_password');
        $this->delete('/perfil/sesiones', ['confirm_password' => 'Cambiar123!'])->assertRedirect('/perfil')->assertSessionHas('success');
    }
}
