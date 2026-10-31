<?php

namespace App\Models;

use App\Enums\PeranPengguna;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * Atribut yang dapat diisi massal.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nama',
        'email',
        'password',
        'peran',
        'nip',
        'jabatan',
        'aktif',
        'must_change_password',
    ];

    /**
     * Atribut yang disembunyikan saat serialisasi.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Type casting atribut.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'peran' => PeranPengguna::class,
            'aktif' => 'boolean',
            'must_change_password' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /**
     * Tim yang diketuai oleh pengguna ini.
     */
    public function ledTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'ketua_id');
    }

    /**
     * Tim di mana pengguna terdaftar sebagai anggota.
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members', 'user_id', 'team_id')
            ->withTimestamps();
    }

    /**
     * Seluruh penugasan yang diterima pengguna.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * Kendala mingguan yang dilaporkan oleh pengguna.
     */
    public function reportedIssues(): HasMany
    {
        return $this->hasMany(WeeklyIssue::class, 'pelapor_id');
    }

    /**
     * Kendala mingguan di mana pengguna ditunjuk sebagai PIC.
     */
    public function assignedIssues(): HasMany
    {
        return $this->hasMany(WeeklyIssue::class, 'pic_id');
    }

    /**
     * Notifikasi yang diterima pengguna.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Riwayat log audit yang dilakukan pengguna.
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Cek apakah peran admin.
     */
    public function isAdmin(): bool
    {
        return $this->peran === PeranPengguna::ADMIN;
    }

    /**
     * Cek apakah peran pimpinan.
     */
    public function isPimpinan(): bool
    {
        return $this->peran === PeranPengguna::PIMPINAN;
    }

    /**
     * Cek apakah peran pegawai.
     */
    public function isPegawai(): bool
    {
        return $this->peran === PeranPengguna::PEGAWAI;
    }

    /**
     * Cek apakah pengguna merupakan ketua pada tim tertentu.
     */
    public function isKetuaOf(int|Team $team): bool
    {
        $teamId = $team instanceof Team ? $team->id : $team;

        return $this->ledTeams()->where('id', $teamId)->exists();
    }
}
