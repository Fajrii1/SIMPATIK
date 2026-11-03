<?php

namespace App\Http\Requests;

use App\Enums\JenisKegiatan;
use App\Models\Activity;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        /** @var Activity|null $activity */
        $activity = $this->route('activity');
        if (! $activity) {
            return false;
        }

        if (! $user->isKetuaOf($activity->team_id)) {
            return false;
        }

        // Jika mengubah team_id, pastikan user juga ketua tim tujuan
        if ($this->has('team_id') && $this->integer('team_id') !== $activity->team_id) {
            return $user->isKetuaOf($this->integer('team_id'));
        }

        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nama' => ['sometimes', 'required', 'string', 'max:200'],
            'jenis' => ['sometimes', 'required', 'string', Rule::in(JenisKegiatan::values())],
            'team_id' => ['sometimes', 'required', 'integer', 'exists:teams,id'],
            'mulai' => ['sometimes', 'required', 'date'],
            'selesai' => ['sometimes', 'required', 'date'],
            'bobot' => ['sometimes', 'required', 'numeric', 'min:0.5', 'max:3.0'],
            'kegiatan_besar' => ['sometimes', 'boolean'],
            'keterangan' => ['nullable', 'string'],
        ];
    }

    /**
     * Validasi lanjutan keterkaitan tanggal mulai dan selesai.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            /** @var Activity|null $activity */
            $activity = $this->route('activity');

            $mulai = $this->filled('mulai')
                ? Carbon::parse($this->input('mulai'))->startOfDay()
                : ($activity ? Carbon::parse($activity->mulai)->startOfDay() : null);

            $selesai = $this->filled('selesai')
                ? Carbon::parse($this->input('selesai'))->startOfDay()
                : ($activity ? Carbon::parse($activity->selesai)->startOfDay() : null);

            if ($mulai && $selesai && $selesai->lt($mulai)) {
                $validator->errors()->add('selesai', 'Tanggal selesai tidak boleh sebelum tanggal mulai kegiatan.');
            }

            // Validasi batas periode penugasan yang sudah ada (PRD Bagian 8.4)
            if ($activity && ($this->filled('mulai') || $this->filled('selesai'))) {
                foreach ($activity->assignments as $assignment) {
                    $assignStart = $assignment->mulai ? Carbon::parse($assignment->mulai)->startOfDay() : $mulai;
                    $assignEnd = $assignment->selesai ? Carbon::parse($assignment->selesai)->startOfDay() : $selesai;

                    if ($assignStart && $mulai && $assignStart->lt($mulai)) {
                        $validator->errors()->add('mulai', 'Tanggal mulai kegiatan tidak boleh melewati tanggal mulai penugasan pegawai yang sudah ada.');
                        break;
                    }

                    if ($assignEnd && $selesai && $assignEnd->gt($selesai)) {
                        $validator->errors()->add('selesai', 'Tanggal selesai kegiatan tidak boleh lebih awal dari tanggal selesai penugasan pegawai yang sudah ada.');
                        break;
                    }
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nama.required' => 'Nama kegiatan wajib diisi.',
            'nama.max' => 'Nama kegiatan maksimal 200 karakter.',
            'jenis.required' => 'Jenis kegiatan wajib dipilih.',
            'jenis.in' => 'Jenis kegiatan tidak valid.',
            'team_id.required' => 'Tim penanggung jawab wajib dipilih.',
            'team_id.exists' => 'Tim penanggung jawab tidak ditemukan.',
            'mulai.required' => 'Tanggal mulai kegiatan wajib diisi.',
            'mulai.date' => 'Format tanggal mulai tidak valid.',
            'selesai.required' => 'Tanggal selesai kegiatan wajib diisi.',
            'selesai.date' => 'Format tanggal selesai tidak valid.',
            'bobot.min' => 'Bobot kegiatan minimal 0,50.',
            'bobot.max' => 'Bobot kegiatan maksimal 3,00.',
        ];
    }
}
