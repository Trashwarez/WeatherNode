<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\FirstRunSetup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The first-run setup: where the station is, then where its readings come from.
 *
 * Values are written with Setting::setValue(), which is an updateOrCreate. The
 * generic settings save cannot be reused here because it only iterates rows
 * that already exist in the request, and the wizard writes a fixed set.
 */
class SetupController extends Controller
{
    /**
     * Formats that have a settings page of their own, so the owner lands where
     * the keys and addresses are actually entered. Everything else goes to the
     * live data page, which is where those formats are configured.
     */
    private const FORMAT_SETTINGS_GROUP = [
        'ecoLcl' => 'ecowitt',
        'ecowittAPI' => 'ecowitt',
        'wu' => 'wunderground',
        'DWL' => 'weatherlink',
        'DWL_v2api' => 'weatherlink',
        'DWL_v2api_demo' => 'weatherlink',
        'weatherlink' => 'weatherlink',
        'AWapi' => 'ambient',
        'wf' => 'weatherflow',
    ];

    public function station(): View
    {
        $timezones = \DateTimeZone::listIdentifiers();
        sort($timezones);

        return view('admin.setup.station', [
            'timezones' => $timezones,
            'name' => Setting::stationName(),
            'location' => Setting::stationLocation(),
            'latitude' => Setting::latitude(),
            'longitude' => Setting::longitude(),
            'elevation' => (float) Setting::getValue('station.elevation', 0),
            'timezone' => trim((string) (Setting::getValue('station.timezone', '') ?? '')),
            // Nothing has been chosen yet at step one: the seeded UTC and the
            // Greenwich coordinates are placeholders, not answers. The page
            // may suggest the browser's own zone and open the map at world
            // view rather than implying that London is right.
            'nothingChosenYet' => FirstRunSetup::state() === FirstRunSetup::STATION,
            'step' => 1,
        ]);
    }

    public function storeStation(Request $request): RedirectResponse
    {
        $validated = $request->validate(self::stationRules(), self::stationMessages());

        Setting::setValue('station.name', trim($validated['name']), 'string', 'station');
        Setting::setValue('station.location', trim((string) ($validated['location'] ?? '')), 'string', 'station');
        Setting::setValue('station.latitude', (string) $validated['latitude'], 'float', 'station');
        Setting::setValue('station.longitude', (string) $validated['longitude'], 'float', 'station');
        Setting::setValue('station.elevation', (string) ($validated['elevation'] ?? 0), 'float', 'station');
        Setting::setValue('station.timezone', $validated['timezone'], 'string', 'station');

        FirstRunSetup::moveTo(FirstRunSetup::SOURCE);

        return redirect()->route('admin.setup.source');
    }

    public function source(): View
    {
        return view('admin.setup.source', [
            'formats' => self::formatOptions(),
            'current' => (string) Setting::getValue('livedata.format', ''),
            'step' => 2,
        ]);
    }

    public function storeSource(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'format' => ['required', 'string', Rule::in(array_keys(self::formatOptions()))],
        ]);

        Setting::setValue('livedata.format', $validated['format'], 'select', 'livedata');

        FirstRunSetup::moveTo(FirstRunSetup::DONE);

        return redirect()
            ->route('admin.settings.group', self::FORMAT_SETTINGS_GROUP[$validated['format']] ?? 'livedata')
            ->with('success', __('Setup finished. Enter the details for your station below.'));
    }

    /** "I will do this later": stops the redirect, leaves the notice. */
    public function skip(): RedirectResponse
    {
        FirstRunSetup::moveTo(FirstRunSetup::SKIPPED);

        return redirect()->route('admin.dashboard');
    }

    /**
     * The formats the app supports, read from the setting itself so this list
     * cannot drift away from the one on the live data page.
     *
     * @return array<string, string>
     */
    public static function formatOptions(): array
    {
        return Setting::find('livedata.format')?->getOptionsArray() ?? [];
    }

    /**
     * Shared with the ordinary station settings page, so a coordinate that the
     * wizard refuses cannot be typed in later through the back door.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function stationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'elevation' => ['nullable', 'numeric', 'between:-500,9000'],
            'timezone' => ['required', 'string', Rule::in(\DateTimeZone::listIdentifiers())],
        ];
    }

    /** @return array<string, string> */
    public static function stationMessages(): array
    {
        return [
            'latitude.between' => __('Latitude runs from -90 at the south pole to 90 at the north pole.'),
            'longitude.between' => __('Longitude runs from -180 to 180.'),
            'timezone.in' => __('Pick a timezone from the list.'),
        ];
    }
}
