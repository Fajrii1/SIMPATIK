<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
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
            'jenis' => $this->jenis,
            'team_id' => $this->team_id,
            'team' => new TeamResource($this->whenLoaded('team')),
            'mulai' => $this->mulai?->format('Y-m-d'),
            'selesai' => $this->selesai?->format('Y-m-d'),
            'bobot' => (float) $this->bobot,
            'kegiatan_besar' => (bool) $this->kegiatan_besar,
            'selesai_manual' => (bool) $this->selesai_manual,
            'selesai_pada' => $this->selesai_pada?->toISOString(),
            'status' => $this->status,
            'keterangan' => $this->keterangan,
            'assignments_count' => $this->assignments_count ?? $this->whenLoaded('assignments', fn () => $this->assignments->count()),
            'assignments' => AssignmentResource::collection($this->whenLoaded('assignments')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
