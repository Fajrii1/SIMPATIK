<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WeeklyIssueResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'activity_id' => $this->activity_id,
            'kegiatan' => $this->whenLoaded('activity', function () {
                return $this->activity ? [
                    'id' => $this->activity->id,
                    'nama' => $this->activity->nama,
                ] : null;
            }),
            'team_id' => $this->team_id,
            'team' => $this->whenLoaded('team', function () {
                return $this->team ? [
                    'id' => $this->team->id,
                    'nama' => $this->team->nama,
                ] : null;
            }),
            'pelapor_id' => $this->pelapor_id,
            'pelapor' => $this->whenLoaded('pelapor', function () {
                return $this->pelapor ? [
                    'id' => $this->pelapor->id,
                    'nama' => $this->pelapor->nama,
                    'nip' => $this->pelapor->nip,
                ] : null;
            }),
            'pic_id' => $this->pic_id,
            'pic' => $this->whenLoaded('pic', function () {
                return $this->pic ? [
                    'id' => $this->pic->id,
                    'nama' => $this->pic->nama,
                    'nip' => $this->pic->nip,
                ] : null;
            }),
            'minggu_mulai' => $this->minggu_mulai?->format('Y-m-d'),
            'kendala' => $this->kendala,
            'solusi' => $this->solusi,
            'tindak_lanjut' => $this->tindak_lanjut,
            'tenggat' => $this->tenggat?->format('Y-m-d'),
            'status' => $this->status->value,
            'is_overdue' => $this->isOverdue(),
            'catatan_penutupan' => $this->catatan_penutupan,
            'ditutup_pada' => $this->ditutup_pada?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
