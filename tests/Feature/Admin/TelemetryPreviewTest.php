<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Setting;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The telemetry page shows "this is what will be shared" so an owner can
 * decide before switching it on. It only built that list once telemetry was
 * already enabled and saved, so the one moment it was wanted was the one
 * moment it was missing.
 */
class TelemetryPreviewTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_it_shows_what_would_be_shared_before_it_is_switched_on(): void
    {
        $this->seed(SettingsSeeder::class);
        Setting::setValue('telemetry.enabled', false, 'boolean', 'telemetry');
        Setting::setValue('station.name', 'Testfield Weather', 'string', 'station');

        $this->actingAs($this->admin())
            ->get(route('admin.settings.telemetry'))
            ->assertOk()
            ->assertSee('Your Station Data')
            ->assertSee('Testfield Weather');
    }

    public function test_it_still_shows_it_once_switched_on(): void
    {
        $this->seed(SettingsSeeder::class);
        Setting::setValue('telemetry.enabled', true, 'boolean', 'telemetry');
        Setting::setValue('station.name', 'Testfield Weather', 'string', 'station');

        $this->actingAs($this->admin())
            ->get(route('admin.settings.telemetry'))
            ->assertOk()
            ->assertSee('Testfield Weather');
    }

    /**
     * Showing it is not sharing it. Nothing may be sent while the setting is
     * off, whatever the page displays.
     */
    public function test_nothing_is_collected_for_sending_while_it_is_off(): void
    {
        $this->seed(SettingsSeeder::class);
        Setting::setValue('telemetry.enabled', false, 'boolean', 'telemetry');

        $service = app(\App\Services\Telemetry\TelemetryService::class);

        $this->assertNull($service->collectStationData());
        $this->assertNotNull($service->previewStationData());
    }
}
