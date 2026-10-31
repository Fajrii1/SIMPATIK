<?php

namespace Database\Factories;

use App\Enums\PeranPengguna;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'peran' => PeranPengguna::PEGAWAI,
            'nip' => fake()->numerify('19##########10##'),
            'jabatan' => 'Statistisi Pertama',
            'aktif' => true,
            'must_change_password' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Admin role state.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'peran' => PeranPengguna::ADMIN,
            'must_change_password' => false,
        ]);
    }

    /**
     * Pimpinan role state.
     */
    public function pimpinan(): static
    {
        return $this->state(fn (array $attributes) => [
            'peran' => PeranPengguna::PIMPINAN,
        ]);
    }
}
