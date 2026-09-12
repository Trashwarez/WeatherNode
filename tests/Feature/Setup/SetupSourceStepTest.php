<?php

declare(strict_types=1);

namespace Tests\Feature\Setup;

use App\Models\Setting;
use App\Models\User;
use App\Support\FirstRunSetup;
use Database\Seeders\FirstRunSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Step two asks where the readings come from, then drops the owner on that
 * source's own settings page, which is where the keys and addresses live.
 */
class SetupSourceStepTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function atSourceStep(): void
    {
        $this->seed(SettingsSeeder::class);
        FirstRunSetup::moveTo(FirstRunSetup::SOURCE);
    }

    public function test_the_source_step_opens(): void
    {
        $this->atSourceStep();

        $this->actingAs($this->admin())
            ->get(route('admin.setup.source'))
            ->assertOk();
    }

    /** The list is the one the settings page already offers, not a new one. */
    public function test_it_offers_the_formats_the_app_supports(): void
    {
        $this->atSourceStep();

        $response = $this->actingAs($this->admin())->get(route('admin.setup.source'));

        $response->assertSee('Ecowitt Local (push)');
        $response->assertSee('Weather Underground');
        $response->assertSee('WeatherFlow');
    }

    public function test_it_saves_the_choice_and_finishes_setup(): void
    {
        $this->atSourceStep();

        $this->actingAs($this->admin())
            ->post(route('admin.setup.source.store'), ['format' => 'wu']);

        $this->assertSame('wu', Setting::getValue('livedata.format'));
        $this->assertSame(FirstRunSetup::DONE, FirstRunSetup::state());
    }

    /** Where the keys are entered, so that is where the owner is left. */
    public function test_it_lands_on_the_settings_page_for_that_source(): void
    {
        $this->atSourceStep();

        $this->actingAs($this->admin())
            ->post(route('admin.setup.source.store'), ['format' => 'wf'])
            ->assertRedirect(route('admin.settings.group', 'weatherflow'));
    }

    /** Not every format has a settings group of its own. */
    public function test_a_source_without_its_own_page_lands_on_live_data(): void
    {
        $this->atSourceStep();

        $this->actingAs($this->admin())
            ->post(route('admin.setup.source.store'), ['format' => 'weewx'])
            ->assertRedirect(route('admin.settings.group', 'livedata'));
    }

    public function test_it_rejects_a_format_the_app_does_not_know(): void
    {
        $this->atSourceStep();

        $this->actingAs($this->admin())
            ->post(route('admin.setup.source.store'), ['format' => 'not-a-station'])
            ->assertSessionHasErrors('format');

        $this->assertSame(FirstRunSetup::SOURCE, FirstRunSetup::state());
    }
}
