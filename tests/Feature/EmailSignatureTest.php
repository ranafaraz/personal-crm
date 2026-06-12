<?php

namespace Tests\Feature;

use App\Models\EmailAccount;
use App\Models\EmailMessage;
use App\Models\EmailSignature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmailSignatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_signature_with_image_and_first_signature_is_default(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->withSession(['_token' => 'test-token'])->post(route('email-signatures.store'), [
            '_token' => 'test-token',
            'name' => 'Main Signature',
            'body' => '<p>Regards,<br>Rana</p>',
            'image' => UploadedFile::fake()->image('signature.png', 300, 80),
        ]);

        $response->assertRedirect(route('email-signatures.index'));

        $signature = EmailSignature::first();
        $this->assertNotNull($signature);
        $this->assertTrue($signature->is_default);
        $this->assertSame($user->id, $signature->user_id);
        Storage::disk('public')->assertExists($signature->image_path);
    }

    public function test_setting_default_signature_unsets_previous_default(): void
    {
        $user = User::factory()->create();
        $first = EmailSignature::create(['user_id' => $user->id, 'tenant_id' => $user->tenant_id, 'name' => 'One', 'is_default' => true]);
        $second = EmailSignature::create(['user_id' => $user->id, 'tenant_id' => $user->tenant_id, 'name' => 'Two', 'is_default' => false]);

        $this->actingAs($user)
            ->withSession(['_token' => 'test-token'])
            ->post(route('email-signatures.set-default', $second), ['_token' => 'test-token'])
            ->assertRedirect(route('email-signatures.index'));

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
    }

    public function test_compose_loads_default_signature_selector(): void
    {
        $user = User::factory()->create();
        EmailAccount::factory()->create(['user_id' => $user->id, 'is_active' => true]);
        EmailSignature::create([
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'name' => 'Default Signature',
            'body' => '<p>Default sign-off</p>',
            'is_default' => true,
        ]);

        $this->actingAs($user)
            ->get(route('compose'))
            ->assertOk()
            ->assertSee('Default Signature')
            ->assertSee('Default sign-off', false);
    }

    public function test_email_store_persists_selected_signature(): void
    {
        $user = User::factory()->create();
        $account = EmailAccount::factory()->create(['user_id' => $user->id, 'is_active' => true]);
        $signature = EmailSignature::create([
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'name' => 'Main Signature',
            'body' => '<p>Regards</p>',
            'is_default' => true,
        ]);

        $response = $this->actingAs($user)->withSession(['_token' => 'test-token'])->post(route('emails.store'), [
            '_token' => 'test-token',
            'email_account_id' => $account->id,
            'email_signature_id' => $signature->id,
            'to_email' => 'recipient@example.com',
            'subject' => 'Hello',
            'body' => '<p>Hello there</p>' . $signature->renderHtml(),
            'send_option' => 'draft',
        ]);

        $message = EmailMessage::first();
        $response->assertRedirect(route('emails.show', $message));
        $this->assertSame($signature->id, $message->email_signature_id);
    }
}
