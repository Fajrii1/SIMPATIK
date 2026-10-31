<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingRequest;
use App\Http\Resources\SettingResource;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingController extends Controller
{
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

        DB::transaction(function () use ($validated) {
            // Update dari format flat kunci-nilai
            $knownKeys = [
                'ambang_beban_bulanan',
                'maks_penugasan_bersamaan',
                'maks_kegiatan_besar_bersamaan',
                'pegawai_boleh_isi_kendala',
            ];

            foreach ($knownKeys as $key) {
                if (array_key_exists($key, $validated)) {
                    $val = $validated[$key];
                    if (is_bool($val)) {
                        $val = $val ? 'true' : 'false';
                    }
                    Setting::setVal($key, (string) $val);
                }
            }

            // Update dari array settings jika disediakan
            if (! empty($validated['settings'])) {
                foreach ($validated['settings'] as $item) {
                    $val = $item['nilai'];
                    if (is_bool($val)) {
                        $val = $val ? 'true' : 'false';
                    }
                    Setting::setVal($item['kunci'], (string) $val);
                }
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
}
