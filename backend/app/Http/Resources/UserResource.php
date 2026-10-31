<?php

namespace App\Http\Resources;

use App\Enums\PeranPengguna;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama' => $this->nama,
            'email' => $this->email,
            'peran' => $this->peran instanceof PeranPengguna ? $this->peran->value : $this->peran,
            'nip' => $this->nip,
            'jabatan' => $this->jabatan,
            'aktif' => (bool) $this->aktif,
            'must_change_password' => (bool) $this->must_change_password,
            'teams' => $this->whenLoaded('teams', function () {
                return $this->teams->map(function ($team) {
                    return [
                        'id' => $team->id,
                        'nama' => $team->nama,
                        'aktif' => (bool) $team->aktif,
                    ];
                });
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
