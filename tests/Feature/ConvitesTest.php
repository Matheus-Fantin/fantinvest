<?php

namespace Tests\Feature;

use App\Models\Invite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConvitesTest extends TestCase
{
    use RefreshDatabase;

    public function test_primeira_conta_e_livre_e_vira_administradora(): void
    {
        $this->post('/register', ['name' => 'Matheus', 'email' => 'm@x.com', 'password' => 'senha-forte-123', 'password_confirmation' => 'senha-forte-123'])->assertRedirect('/dashboard');

        $this->assertTrue(User::first()->is_admin);
    }

    public function test_sem_convite_nao_cadastra(): void
    {
        User::factory()->create();

        $this->get('/register')->assertOk()->assertSee('Cadastro por convite');
        $this->post('/register', ['name' => 'Fulano', 'email' => 'f@x.com', 'password' => 'senha-forte-123', 'password_confirmation' => 'senha-forte-123'])
            ->assertSessionHasErrors('email');
        $this->assertSame(1, User::count());
    }

    public function test_cadastra_com_convite_valido_e_convite_vale_uma_vez(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        $this->actingAs($admin)->post('/admin/convites', ['email' => 'amigo@x.com', 'dias' => 7])->assertSessionHas('convite_novo');
        $convite = Invite::first();
        auth()->logout();

        $this->get('/register?convite='.$convite->codigo)->assertOk()->assertSee('Você foi convidado');
        $this->post('/register', ['name' => 'Outro', 'email' => 'outro@x.com', 'password' => 'senha-forte-123', 'password_confirmation' => 'senha-forte-123', 'convite' => $convite->codigo])
            ->assertSessionHasErrors('email');
        $this->post('/register', ['name' => 'Amigo', 'email' => 'amigo@x.com', 'password' => 'senha-forte-123', 'password_confirmation' => 'senha-forte-123', 'convite' => $convite->codigo])
            ->assertRedirect('/dashboard');

        $this->assertNotNull($convite->fresh()->usado_em);
        $this->assertFalse(User::where('email', 'amigo@x.com')->first()->is_admin);
        auth()->logout();
        $this->get('/register?convite='.$convite->codigo)->assertSee('inválido');
    }

    public function test_so_admin_ve_convites(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/convites')->assertForbidden();
    }

    public function test_comando_torna_admin(): void
    {
        $user = User::factory()->create(['email' => 'm@x.com']);
        $this->artisan('usuario:admin m@x.com')->assertSuccessful();
        $this->assertTrue($user->fresh()->is_admin);
    }
}
