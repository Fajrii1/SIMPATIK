<?php

namespace App\Http\Resources;

use App\Support\AssignmentVisibility;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssignmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $bobot = (float) ($this->activity?->bobot ?? 1.00);
        $canViewMetrics = AssignmentVisibility::canViewMetrics($request->user(), $this->resource);
        $totalPoin = $canViewMetrics ? round(((float) $this->volume) * $bobot, 2) : null;

        return [
            'id' => $this->id,
            'activity_id' => $this->activity_id,
            'activity' => new ActivityResource($this->whenLoaded('activity')),
            'user_id' => $this->user_id,
            'user' => new UserResource($this->whenLoaded('user')),
            'peran' => $this->peran,
            'volume' => $canViewMetrics ? (float) $this->volume : null,
            'satuan' => $canViewMetrics ? $this->satuan : null,
            'mulai' => $this->mulai?->format('Y-m-d'),
            'selesai' => $this->selesai?->format('Y-m-d'),
            'effective_mulai' => $this->getEffectiveMulai()?->format('Y-m-d'),
            'effective_selesai' => $this->getEffectiveSelesai()?->format('Y-m-d'),
            'poin_total' => $totalPoin,
            'metrics_visible' => $canViewMetrics,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
