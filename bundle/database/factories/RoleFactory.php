<?php

declare( strict_types = 1 );

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;


/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
	public function definition() : array
	{
		return [
			'name' => fake()->word(),
		];
	}
}
