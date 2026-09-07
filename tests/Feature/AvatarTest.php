<?php

namespace IsProject\Framework\Tests\Feature;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use IsProject\Framework\Concerns\HasRoles;
use IsProject\Framework\Models\Profile;
use IsProject\Framework\Models\Role;
use IsProject\Framework\Support\Access;
use IsProject\Framework\Support\Avatars;
use IsProject\Framework\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class AvatarTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('auth.providers.users.model', AvatarTestUser::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('avatar_users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });

        Storage::fake('public');
    }

    // -------------------------------------------------------------- initials

    #[Test]
    public function a_two_word_name_gives_first_and_last_initials(): void
    {
        $this->assertSame('AR', $this->avatars()->initials($this->user(['name' => 'Aisha Rahman'])));
    }

    #[Test]
    public function a_three_word_name_still_gives_two_letters(): void
    {
        // First and last, not first and second: "Nur Aisha Rahman" ends in an R.
        $this->assertSame('NR', $this->avatars()->initials($this->user(['name' => 'Nur Aisha Rahman'])));
    }

    #[Test]
    public function a_single_word_name_gives_its_first_two_letters(): void
    {
        $this->assertSame('LE', $this->avatars()->initials($this->user(['name' => 'lecturer'])));
    }

    #[Test]
    public function a_blank_name_falls_back_to_the_email(): void
    {
        $user = $this->user(['name' => '  ', 'email' => 'zoe@example.test']);

        $this->assertSame('ZO', $this->avatars()->initials($user));
    }

    #[Test]
    public function nobody_signed_in_gives_a_question_mark(): void
    {
        $this->assertSame('?', $this->avatars()->initials(null));
    }

    // ------------------------------------------------------------- uploading

    #[Test]
    public function uploading_a_photo_stores_a_file_and_a_row(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->put('/profile', $this->details(['avatar' => UploadedFile::fake()->image('me.png')]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $path = Profile::query()->where('user_id', $user->id)->value('avatar');

        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    #[Test]
    public function the_stored_name_is_random_rather_than_the_user_id(): void
    {
        $user = $this->user();

        $this->actingAs($user)->put('/profile', $this->details([
            'avatar' => UploadedFile::fake()->image('me.png'),
        ]));

        $path = Profile::query()->where('user_id', $user->id)->value('avatar');

        // A guessable name on a public disk would let anyone walk the ids and
        // collect every photograph on the site.
        $this->assertSame('isproject/avatars', dirname($path));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{40}\.png$/', basename($path));
    }

    #[Test]
    public function an_uppercase_extension_is_stored_lowercased(): void
    {
        $user = $this->user();

        $this->actingAs($user)->put('/profile', $this->details([
            'avatar' => UploadedFile::fake()->image('ME.PNG'),
        ]));

        $path = Profile::query()->where('user_id', $user->id)->value('avatar');

        $this->assertStringEndsWith('.png', $path);
    }

    #[Test]
    public function replacing_a_photo_deletes_the_previous_file(): void
    {
        $user = $this->user();

        $this->actingAs($user)->put('/profile', $this->details([
            'avatar' => UploadedFile::fake()->image('first.png'),
        ]));

        $first = Profile::query()->where('user_id', $user->id)->value('avatar');

        $this->actingAs($user)->put('/profile', $this->details([
            'avatar' => UploadedFile::fake()->image('second.png'),
        ]));

        $second = Profile::query()->where('user_id', $user->id)->value('avatar');

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    #[Test]
    public function a_new_upload_beats_the_remove_tickbox(): void
    {
        $user = $this->withAvatar();

        // Someone who chose a file and left the tick behind meant to replace.
        $this->actingAs($user)->put('/profile', $this->details([
            'avatar' => UploadedFile::fake()->image('new.png'),
            'remove_avatar' => '1',
        ]));

        $path = Profile::query()->where('user_id', $user->id)->value('avatar');

        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    // --------------------------------------------------------------- removing

    #[Test]
    public function ticking_remove_clears_the_row_and_the_file(): void
    {
        $user = $this->withAvatar();
        $path = Profile::query()->where('user_id', $user->id)->value('avatar');

        $this->actingAs($user)->put('/profile', $this->details(['remove_avatar' => '1']));

        $this->assertNull(Profile::query()->where('user_id', $user->id)->value('avatar'));
        Storage::disk('public')->assertMissing($path);
    }

    #[Test]
    public function saving_the_details_alone_leaves_the_photo_alone(): void
    {
        $user = $this->withAvatar();
        $path = Profile::query()->where('user_id', $user->id)->value('avatar');

        $this->actingAs($user)->put('/profile', $this->details(['name' => 'Renamed Person']));

        $this->assertSame($path, Profile::query()->where('user_id', $user->id)->value('avatar'));
        Storage::disk('public')->assertExists($path);
    }

    #[Test]
    public function deleting_an_account_takes_its_photo_with_it(): void
    {
        $admin = $this->superAdmin();
        $user = $this->withAvatar();
        $path = Profile::query()->where('user_id', $user->id)->value('avatar');

        $this->actingAs($admin)
            ->delete("/access/users/{$user->id}")
            ->assertSessionHas('success');

        // Otherwise the file outlives the person it belonged to, with nothing
        // left pointing at it to find it by.
        Storage::disk('public')->assertMissing($path);
        $this->assertSame(0, Profile::query()->where('user_id', $user->id)->count());
    }

    // -------------------------------------------------------- what is refused

    #[Test]
    public function a_document_is_refused(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->put('/profile', $this->details([
                'avatar' => UploadedFile::fake()->create('notes.pdf', 40, 'application/pdf'),
            ]))
            ->assertSessionHasErrors('avatar');

        $this->assertSame(0, Profile::query()->count());
    }

    #[Test]
    public function an_svg_is_refused(): void
    {
        $user = $this->user();

        // An SVG can carry script, and these files are served from the
        // application own origin: accepting one would be stored XSS that any
        // signed-in user could upload.
        $this->actingAs($user)
            ->put('/profile', $this->details([
                'avatar' => UploadedFile::fake()->create('logo.svg', 4, 'image/svg+xml'),
            ]))
            ->assertSessionHasErrors('avatar');
    }

    #[Test]
    public function a_refused_upload_leaves_the_existing_photo_in_place(): void
    {
        $user = $this->withAvatar();
        $path = Profile::query()->where('user_id', $user->id)->value('avatar');

        $this->actingAs($user)->put('/profile', $this->details([
            'avatar' => UploadedFile::fake()->create('notes.pdf', 40, 'application/pdf'),
        ]))->assertSessionHasErrors('avatar');

        $this->assertSame($path, Profile::query()->where('user_id', $user->id)->value('avatar'));
        Storage::disk('public')->assertExists($path);
    }

    #[Test]
    public function an_oversized_image_is_refused(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->put('/profile', $this->details([
                'avatar' => UploadedFile::fake()->image('huge.png')->size(3000),
            ]))
            ->assertSessionHasErrors('avatar');
    }

    // ------------------------------------------------------------- rendering

    #[Test]
    public function the_component_renders_initials_when_there_is_no_photo(): void
    {
        $user = $this->user(['name' => 'Aisha Rahman']);

        $html = (string) $this->blade('<x-isproject::avatar :user="$user" />', ['user' => $user]);

        $this->assertStringContainsString('AR', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    #[Test]
    public function the_component_renders_an_image_once_there_is_one(): void
    {
        $user = $this->withAvatar();

        $html = (string) $this->blade('<x-isproject::avatar :user="$user" />', ['user' => $user]);

        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('is-avatar-img', $html);
    }

    #[Test]
    public function the_profile_screen_offers_removal_only_once_there_is_a_photo(): void
    {
        $user = $this->user();

        $this->actingAs($user)->get('/profile')->assertOk()->assertDontSee('remove_avatar');

        $this->avatars()->store($user, UploadedFile::fake()->image('me.png'));

        $this->actingAs($user)->get('/profile')->assertOk()->assertSee('remove_avatar');
    }

    // ---------------------------------------------------------------- helpers

    private function avatars(): Avatars
    {
        return app(Avatars::class);
    }

    /** @param  array<string, mixed>  $overrides */
    private function user(array $overrides = []): AvatarTestUser
    {
        return AvatarTestUser::query()->create(array_merge([
            'name' => 'Administrator',
            'email' => 'lecturer'.AvatarTestUser::query()->count().'@example.test',
            'password' => 'hashed',
        ], $overrides));
    }

    /** A user who already has a photo stored. */
    private function withAvatar(): AvatarTestUser
    {
        $user = $this->user();

        $this->avatars()->store($user, UploadedFile::fake()->image('existing.png'));

        return $user;
    }

    private function superAdmin(): AvatarTestUser
    {
        $admin = $this->user(['name' => 'Administrator', 'email' => 'admin@example.test']);
        $admin->roles()->attach(Role::query()->create(['name' => 'Administrator', 'is_super_admin' => true]));

        Access::flush();

        return $admin->fresh();
    }

    /**
     * The photo posts with the details, so every request needs both.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function details(array $extra = []): array
    {
        return array_merge([
            'name' => 'Administrator',
            'email' => 'admin@example.test',
        ], $extra);
    }
}

/** A User model that exists only for these tests. */
class AvatarTestUser extends Authenticatable
{
    use HasRoles;

    protected $table = 'avatar_users';

    protected $guarded = [];
}
