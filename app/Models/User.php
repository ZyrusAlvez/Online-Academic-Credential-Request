<?php

namespace App\Models;

use App\Enums\AcademicStatus;
use App\Enums\IdentityStatus;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

/**
 * @property Role $role
 * @property bool $is_active
 * @property ?string $contact_number
 * @property ?AcademicStatus $academic_status
 * @property ?int $college_id
 * @property ?string $program
 * @property ?string $student_number
 * @property ?int $attended_from
 * @property ?int $attended_to
 * @property IdentityStatus $identity_status
 * @property-read string $name
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Role, activation and identity status are never mass assignable; the registrar sets them.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'contact_number',
        'password',
        'academic_status',
        'college_id',
        'program',
        'student_number',
        'attended_from',
        'attended_to',
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
        'identity_status' => 'unsubmitted',
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
            'privacy_consented_at' => 'datetime',
            'academic_status' => AcademicStatus::class,
            'attended_from' => 'integer',
            'attended_to' => 'integer',
            'identity_status' => IdentityStatus::class,
            'identity_verified_at' => 'datetime',
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

    /**
     * The college/office a department staff member belongs to.
     *
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * A requester's college (spec 01).
     *
     * @return BelongsTo<Department, $this>
     */
    public function college(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'college_id');
    }

    /**
     * @return HasMany<IdentityDocument, $this>
     */
    public function identityDocuments(): HasMany
    {
        return $this->hasMany(IdentityDocument::class)->latest('id');
    }

    /**
     * @return HasOne<IdentityDocument, $this>
     */
    public function latestIdentityDocument(): HasOne
    {
        return $this->hasOne(IdentityDocument::class)->latestOfMany();
    }

    public function hasAcademicProfile(): bool
    {
        return $this->academic_status !== null
            && $this->college_id !== null
            && filled($this->program)
            && $this->attended_from !== null
            && $this->attended_to !== null;
    }

    /**
     * What a requester still has to give before they can request credentials (REQ-01-18).
     *
     * @return list<string>
     */
    public function missingProfileItems(): array
    {
        return array_keys(array_filter([
            'Full name' => blank($this->first_name) || blank($this->last_name),
            'Contact number' => blank($this->contact_number),
            'Academic information' => ! $this->hasAcademicProfile(),
            'Valid ID' => ! in_array($this->identity_status, [IdentityStatus::Pending, IdentityStatus::Verified], true),
            'Data privacy consent' => $this->privacy_consented_at === null,
        ]));
    }

    /**
     * Ready to request credentials: profile filled in, privacy consent given and an ID pending or
     * verified (REQ-01-18).
     */
    public function canRequestCredentials(): bool
    {
        return $this->missingProfileItems() === [];
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
