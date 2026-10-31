<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'username' => 'test_user',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('chats.index', absolute: false));

        $this->assertDatabaseHas('users', [
            'name' => 'Test User',
            'username' => 'test_user',
            'email' => 'test@example.com',
        ]);
    }

    public function test_usernames_are_normalized_to_lowercase()
    {
        $this->post(route('register.store'), [
            'name' => 'Test User',
            'username' => 'Test_User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['username' => 'test_user']);
    }

    public function test_username_is_required_and_validated()
    {
        $this->post(route('register.store'), [
            'name' => 'Test User',
            'username' => '',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('username');

        $this->post(route('register.store'), [
            'name' => 'Test User',
            'username' => 'no spaces allowed!',
            'email' => 'another@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('username');
    }

    public function test_username_must_be_unique()
    {
        User::factory()->create(['username' => 'taken']);

        $this->post(route('register.store'), [
            'name' => 'Test User',
            'username' => 'taken',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('username');
    }
}
