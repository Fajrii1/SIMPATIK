<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    /**
     * Uji mutasi pengguna tercatat tanpa menyimpan kredensial.
     */
    public function test_user_create_and_update_are_audited_without_credentials(): void
    {
        $admin = User::where('email', 'admin@contoh.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $email = 'audit_pengguna@contoh.test';

        $createResponse = $this->postJson('/api/users', [
            'nama' => 'Pengguna Audit',
            'email' => $email,
            'peran' => 'pegawai',
            'password' => 'rahasia-audit-123',
        ]);

        $createResponse->assertStatus(201);
        $userId = $createResponse->json('data.id');
        $createdUser = User::findOrFail($userId);

        try {
            $createLog = AuditLog::where('objek', 'User')
                ->where('objek_id', $userId)
                ->where('aksi', 'create')
                ->latest('id')
                ->first();

            $this->assertNotNull($createLog);
            $this->assertNull($createLog->sebelum);
            $this->assertIsArray($createLog->sesudah);
            $this->assertArrayNotHasKey('password', $createLog->sesudah);
            $this->assertStringNotContainsString('rahasia-audit-123', json_encode($createLog->sesudah));
            $this->assertStringNotContainsString($createdUser->password, json_encode($createLog->sesudah));

            $this->putJson("/api/users/{$userId}", ['nama' => 'Pengguna Audit Diubah'])
                ->assertStatus(200);

            $updateLog = AuditLog::where('objek', 'User')
                ->where('objek_id', $userId)
                ->where('aksi', 'update')
                ->latest('id')
                ->first();

            $this->assertNotNull($updateLog);
            $this->assertSame('Pengguna Audit', $updateLog->sebelum['nama']);
            $this->assertSame('Pengguna Audit Diubah', $updateLog->sesudah['nama']);
            $this->assertArrayNotHasKey('password', $updateLog->sebelum);
            $this->assertArrayNotHasKey('password', $updateLog->sesudah);
        } finally {
            $createdUser->forceDelete();
        }
    }

    /**
     * Uji mutasi tim dan perubahan ketua tercatat di audit log.
     */
    public function test_team_mutations_are_audited(): void
    {
        $admin = User::where('email', 'admin@contoh.test')->firstOrFail();
        $ketua1 = User::where('email', 'ketua1@contoh.test')->firstOrFail();
        $ketua2 = User::where('email', 'ketua2@contoh.test')->firstOrFail();
        $pegawai = User::where('email', 'pegawai01@contoh.test')->firstOrFail();

        Sanctum::actingAs($admin);

        $createResponse = $this->postJson('/api/teams', [
            'nama' => 'Tim Audit Fase 2',
            'ketua_id' => $ketua1->id,
        ]);

        $createResponse->assertStatus(201);
        $teamId = $createResponse->json('data.id');
        $team = Team::findOrFail($teamId);

        try {
            $this->assertAuditExists('create', 'Team', $teamId);

            $this->putJson("/api/teams/{$teamId}", [
                'ketua_id' => $ketua2->id,
            ])->assertStatus(200);

            $this->assertAuditExists('update_ketua', 'Team', $teamId);

            $this->postJson("/api/teams/{$teamId}/members", [
                'user_id' => $pegawai->id,
            ])->assertStatus(201);

            $this->assertAuditExists('add_member', 'Team', $teamId);

            $this->deleteJson("/api/teams/{$teamId}/members/{$pegawai->id}")
                ->assertStatus(200);

            $this->assertAuditExists('remove_member', 'Team', $teamId);

            $this->deleteJson("/api/teams/{$teamId}")->assertStatus(200);

            $this->assertAuditExists('delete', 'Team', $teamId);
        } finally {
            $team->members()->detach();
            $team->forceDelete();
        }
    }

    /**
     * Uji perubahan pengaturan mencatat nilai lama dan nilai baru.
     */
    public function test_setting_update_audits_old_and_new_values(): void
    {
        $admin = User::where('email', 'admin@contoh.test')->firstOrFail();
        Sanctum::actingAs($admin);

        $original = Setting::getVal('ambang_beban_bulanan', '100');

        try {
            $this->putJson('/api/settings', [
                'ambang_beban_bulanan' => 125,
            ])->assertStatus(200);

            $log = AuditLog::where('objek', 'Setting')
                ->where('aksi', 'update')
                ->latest('id')
                ->first();

            $this->assertNotNull($log);
            $this->assertSame('ambang_beban_bulanan', $log->sebelum['kunci']);
            $this->assertSame((string) $original, $log->sebelum['nilai']);
            $this->assertSame('125', $log->sesudah['nilai']);
        } finally {
            Setting::setVal('ambang_beban_bulanan', $original);
        }
    }

    private function assertAuditExists(string $aksi, string $objek, int $objekId): void
    {
        $this->assertTrue(
            AuditLog::where('aksi', $aksi)
                ->where('objek', $objek)
                ->where('objek_id', $objekId)
                ->exists(),
            "Audit [{$aksi}] untuk [{$objek}:{$objekId}] tidak ditemukan."
        );
    }
}
