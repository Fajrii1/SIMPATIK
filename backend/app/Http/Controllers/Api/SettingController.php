<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingRequest;
use App\Http\Resources\SettingResource;
use App\Models\Setting;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Menampilkan seluruh pengaturan sistem.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Setting::class);

        $settings = Setting::all();

        $keyValue = [];
        foreach ($settings as $setting) {
            $value = $setting->nilai;
            if ($setting->kunci === 'pegawai_boleh_isi_kendala') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            } elseif (in_array($setting->kunci, ['ambang_beban_bulanan', 'maks_penugasan_bersamaan', 'maks_kegiatan_besar_bersamaan'])) {
                $value = is_numeric($value) ? (int) $value : $value;
            }
            $keyValue[$setting->kunci] = $value;
        }

        return response()->json([
            'settings' => $keyValue,
            'data' => SettingResource::collection($settings),
        ]);
    }

    /**
     * Memperbarui pengaturan sistem oleh Admin.
     */
    public function update(UpdateSettingRequest $request): JsonResponse
    {
        $this->authorize('update', Setting::class);

        $validated = $request->validated();

        $knownKeys = [
            'ambang_beban_bulanan',
            'maks_penugasan_bersamaan',
            'maks_kegiatan_besar_bersamaan',
            'pegawai_boleh_isi_kendala',
        ];

        $changes = [];

        foreach ($knownKeys as $key) {
            if (array_key_exists($key, $validated)) {
                $changes[$key] = $this->stringifySettingValue($validated[$key]);
            }
        }

        if (! empty($validated['settings'])) {
            foreach ($validated['settings'] as $item) {
                $changes[$item['kunci']] = $this->stringifySettingValue($item['nilai']);
            }
        }

        DB::transaction(function () use ($changes) {
            foreach ($changes as $key => $value) {
                $setting = Setting::where('kunci', $key)->first();

                if ($setting && $setting->nilai === $value) {
                    continue;
                }

                Setting::setVal($key, $value);
                $settingId = Setting::where('kunci', $key)->value('id');

                $this->auditLogger->log(
                    'update',
                    'Setting',
                    $settingId !== null ? (int) $settingId : null,
                    $setting ? ['kunci' => $key, 'nilai' => $setting->nilai] : null,
                    ['kunci' => $key, 'nilai' => $value]
                );
            }
        });

        // Ambil data terbaru
        $allSettings = Setting::all();
        $keyValue = [];
        foreach ($allSettings as $setting) {
            $value = $setting->nilai;
            if ($setting->kunci === 'pegawai_boleh_isi_kendala') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            } elseif (in_array($setting->kunci, ['ambang_beban_bulanan', 'maks_penugasan_bersamaan', 'maks_kegiatan_besar_bersamaan'])) {
                $value = is_numeric($value) ? (int) $value : $value;
            }
            $keyValue[$setting->kunci] = $value;
        }

        return response()->json([
            'message' => 'Pengaturan sistem berhasil diperbarui.',
            'settings' => $keyValue,
            'data' => SettingResource::collection($allSettings),
        ]);
    }

    private function stringifySettingValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }
}
