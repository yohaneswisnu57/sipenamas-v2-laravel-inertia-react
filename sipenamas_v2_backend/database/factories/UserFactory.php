<?php

namespace Database\Factories;

use App\Models\User;
use App\Support\Legacy\Dencoder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'kodeperson' => $this->faker->unique()->numerify('P#####'),
            'nama' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'is_external' => false,
        ];
    }

    /**
     * External account authenticated via person.PASWET (Dencoder), not SSO.
     */
    public function external(string $password = 'password'): static
    {
        return $this->state(fn () => [
            'is_external' => true,
            'paswet' => Dencoder::encode3t($password),
        ]);
    }
}
