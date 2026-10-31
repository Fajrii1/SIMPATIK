<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', function () {
                return $this->user ? [
                    'id' => $this->user->id,
                    'nama' => $this->user->nama,
                    'nip' => $this->user->nip,
                    'email' => $this->user->email,
                ] : null;
            }),
            'aksi' => $this->aksi,
            'objek' => $this->objek,
            'objek_id' => $this->objek_id,
            'sebelum' => $this->sebelum,
            'sesudah' => $this->sesudah,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
