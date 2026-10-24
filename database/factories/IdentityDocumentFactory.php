<?php

namespace Database\Factories;

use App\Enums\ReviewStatus;
use App\Models\IdentityDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IdentityDocument>
 */
class IdentityDocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'id_type' => 'Passport',
            'path' => 'identity-documents/0/'.fake()->uuid().'.png',
            'original_name' => 'passport.png',
            'mime_type' => 'image/png',
            'size' => 1024,
            'status' => ReviewStatus::Pending,
        ];
    }
}
