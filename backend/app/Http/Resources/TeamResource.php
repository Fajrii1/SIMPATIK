<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
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
            'ketua_id' => $this->ketua_id,
            'ketua' => new UserResource($this->whenLoaded('ketua')),
            'aktif' => (bool) $this->aktif,
            'members_count' => $this->members_count ?? $this->whenLoaded('members', fn () => $this->members->count()),
            'members' => UserResource::collection($this->whenLoaded('members')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
