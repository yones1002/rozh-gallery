<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_guest_cannot_open_the_panel(): void
    {
        $this->get('/admin')
            ->assertRedirect('/admin/login');

        $this->get('/admin/login')
            ->assertOk();

        $this->assertGuest();
    }

    public function test_non_admin_cannot_open_the_panel(): void
    {
        $user = User::factory()->create()->fresh();

        $this->assertFalse($user->is_admin);
        $this->assertFalse($user->canAccessPanel(Filament::getPanel('admin')));

        $this->actingAs($user)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_non_admin_with_a_valid_password_cannot_sign_in(): void
    {
        $user = User::factory()->create();

        Livewire::test(Login::class)
            ->fillForm([
                'email' => $user->email,
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }

    public function test_admin_can_open_the_panel(): void
    {
        $admin = User::factory()->create();
        $admin->is_admin = true;
        $admin->save();

        $admin->refresh();

        $this->assertTrue($admin->canAccessPanel(Filament::getPanel('admin')));

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk();
    }

    public function test_admin_can_sign_in_through_the_login_form(): void
    {
        $admin = User::factory()->create();
        $admin->is_admin = true;
        $admin->save();

        Livewire::test(Login::class)
            ->fillForm([
                'email' => $admin->email,
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors()
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($admin);
    }

    public function test_is_admin_cannot_be_mass_assigned(): void
    {
        $user = User::query()->create([
            'name' => 'Customer',
            'email' => 'customer@example.com',
            'password' => 'password',
            'is_admin' => true,
        ]);

        $this->assertFalse($user->fresh()->is_admin);
        $this->assertTrue(Hash::check('password', $user->password));
    }

    public function test_make_admin_command_creates_only_an_admin_and_hides_the_password(): void
    {
        $password = Str::password(24);

        $this->artisan('make:admin')
            ->expectsQuestion('Name', ' Panel Admin ')
            ->expectsQuestion('Email address', ' Admin@Example.com ')
            ->expectsQuestion('Password', $password)
            ->expectsQuestion('Confirm password', $password)
            ->expectsOutputToContain('admin@example.com')
            ->doesntExpectOutputToContain($password)
            ->assertSuccessful();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->assertSame('Panel Admin', $admin->name);
        $this->assertTrue($admin->is_admin);
        $this->assertNotSame($password, $admin->password);
        $this->assertTrue(Hash::check($password, $admin->password));
        $this->assertTrue($admin->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_make_admin_command_rejects_a_duplicate_email_without_granting_access(): void
    {
        $existing = User::factory()->create([
            'email' => 'admin@example.com',
        ]);

        $this->artisan('make:admin')
            ->expectsQuestion('Name', 'Panel Admin')
            ->expectsQuestion('Email address', 'admin@example.com')
            ->assertFailed();

        $this->assertFalse($existing->fresh()->is_admin);
        $this->assertSame(1, User::query()->count());
    }

    public function test_make_admin_command_rejects_a_password_mismatch_without_creating_a_user(): void
    {
        $password = Str::password(24);
        $confirmation = Str::password(24);

        $this->artisan('make:admin')
            ->expectsQuestion('Name', 'Panel Admin')
            ->expectsQuestion('Email address', 'admin@example.com')
            ->expectsQuestion('Password', $password)
            ->expectsQuestion('Confirm password', $confirmation)
            ->doesntExpectOutputToContain($password)
            ->doesntExpectOutputToContain($confirmation)
            ->assertFailed();

        $this->assertSame(0, User::query()->count());
    }
}
