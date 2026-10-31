<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'tipe' => $this->tipe,
            'pesan' => $this->pesan,
            'tautan' => $this->tautan,
            'is_read' => $this->isRead(),
            'dibaca_pada' => $this->dibaca_pada?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
