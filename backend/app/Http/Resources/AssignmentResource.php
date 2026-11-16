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
        $canViewMetrics = AssignmentVisibility::canViewMetrics($request->user(), $this->resource);

        return [
            'id' => $this->id,
            'activity_id' => $this->activity_id,
            'activity' => new ActivityResource($this->whenLoaded('activity')),
            'user_id' => $this->user_id,
            'user' => new UserResource($this->whenLoaded('user')),
            'peran' => $this->peran,
            'jam_per_hari' => $canViewMetrics ? (float) $this->jam_per_hari : null,
            'volume' => $canViewMetrics && $this->volume !== null ? (float) $this->volume : null,
            'satuan' => $canViewMetrics ? $this->satuan : null,
            'mulai' => $this->mulai?->format('Y-m-d'),
            'selesai' => $this->selesai?->format('Y-m-d'),
            'effective_mulai' => $this->getEffectiveMulai()?->format('Y-m-d'),
            'effective_selesai' => $this->getEffectiveSelesai()?->format('Y-m-d'),
            'metrics_visible' => $canViewMetrics,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
