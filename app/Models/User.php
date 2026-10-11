<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

/**
 * @property Role $role
 * @property bool $is_active
 * @property-read string $name
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Role and activation are never mass assignable; they are set explicitly by the registrar.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'student',
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Display name, e.g. "Juan D. Dela Cruz".
     *
     * @return Attribute<string, never>
     */
    protected function name(): Attribute
    {
        return Attribute::get(fn (): string => $this->fullName());
    }

    public function fullName(): string
    {
        $middle = filled($this->middle_name)
            ? Str::upper(Str::substr($this->middle_name, 0, 1)).'. '
            : '';

        return "{$this->first_name} {$middle}{$this->last_name}";
    }

    public function initials(): string
    {
        return Str::upper(Str::substr($this->first_name, 0, 1).Str::substr($this->last_name, 0, 1));
    }

    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }
}
