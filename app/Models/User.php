<?php

namespace App\Models;

use App\Actions\Auth\SendVerificationCode;
use App\Enums\AcademicStatus;
use App\Enums\ActivityCategory;
use App\Enums\IdentityStatus;
use App\Enums\Role;
use App\Models\Concerns\LogsActivity;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * @property Role $role
 * @property bool $is_active
 * @property bool $email_notifications
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
    use HasFactory, LogsActivity, Notifiable;

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
        'email_notifications' => true,
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
            'email_notifications' => 'boolean',
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
        // Self-registered requesters give their name in My Profile after signing up (REQ-01-01).
        if (blank($this->first_name) && blank($this->last_name)) {
            return $this->email;
        }

        $middle = filled($this->middle_name)
            ? Str::upper(Str::substr($this->middle_name, 0, 1)).'. '
            : '';

        return trim("{$this->first_name} {$middle}{$this->last_name}");
    }

    /**
     * The opening line of emails: "Hello Juan," or just "Hello," before a name is given.
     */
    public function greeting(): string
    {
        return filled($this->first_name) ? "Hello {$this->first_name}," : 'Hello,';
    }

    /**
     * Whether request, reminder and ID-review emails go out (REQ-06-16). Verification codes and
     * password resets ignore this.
     */
    public function wantsEmailNotifications(): bool
    {
        return $this->email_notifications;
    }

    /**
     * Email the one-time code that confirms the address (REQ-01-23), sent on sign-up; replaces
     * Laravel's verification link.
     */
    public function sendEmailVerificationNotification(): void
    {
        app(SendVerificationCode::class)->handle($this);
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
            'Valid ID' => $this->needsValidId(),
            'Data privacy consent' => $this->privacy_consented_at === null,
        ]));
    }

    /**
     * The identity check is with the Registrar or passed, so My Profile is read-only until a
     * rejection (REQ-01-30). A profile still missing an entry stays editable, so nobody is stuck.
     */
    public function hasLockedProfile(): bool
    {
        return ! $this->needsValidId() && $this->missingProfileItems() === [];
    }

    /**
     * No ID awaits or has passed verification, so My Profile asks for one (REQ-01-22).
     */
    public function needsValidId(): bool
    {
        return ! in_array($this->identity_status, [IdentityStatus::Pending, IdentityStatus::Verified], true);
    }

    /**
     * A verified name matches the ID, so only the registrar changes it (REQ-01-27).
     */
    public function hasLockedName(): bool
    {
        return $this->identity_status === IdentityStatus::Verified
            && filled($this->first_name)
            && filled($this->last_name);
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
        if (blank($this->first_name) && blank($this->last_name)) {
            return Str::upper(Str::substr($this->email, 0, 1));
        }

        return Str::upper(Str::substr((string) $this->first_name, 0, 1).Str::substr((string) $this->last_name, 0, 1));
    }

    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function activityCategory(): ActivityCategory
    {
        return ActivityCategory::Account;
    }

    public function activityNoun(): string
    {
        return 'account';
    }

    public function activityLabel(): string
    {
        return "{$this->name} ({$this->email})";
    }

    /**
     * The Google link and the identity status (logged with the ID itself) stay out (REQ-14-08).
     *
     * @return list<string>
     */
    protected function activityIgnored(): array
    {
        return ['google_id', 'identity_status', 'identity_verified_at'];
    }

    /** Someone who registers or resets a forgotten password is not signed in yet: they acted themselves. */
    protected function activityActor(string $event): ?User
    {
        $user = Auth::user();

        if ($user instanceof User) {
            return $user;
        }

        return ! app()->runningInConsole() && ($event === 'created' || $this->wasChanged('password')) ? $this : null;
    }

    /**
     * A password change is logged, never the password (REQ-14-08).
     *
     * @param  array<string, array{0: ?string, 1: ?string}>  $changes
     */
    protected function shouldLogActivity(string $event, array $changes): bool
    {
        return $event !== 'updated' || $changes !== [] || $this->wasChanged('password');
    }

    /**
     * @param  array<string, array{0: ?string, 1: ?string}>  $changes
     */
    protected function activityDescription(string $event, array $changes, ?User $actor): string
    {
        $self = $actor?->is($this) ?? false;
        $fields = array_keys($changes);

        if ($event === 'created') {
            return $self
                ? "Created an account ({$this->email})"
                : "Created the {$this->role->label()} account of {$this->activityLabel()}";
        }

        return match (true) {
            $fields === [] && $self => 'Changed their password',
            $fields === [] => "Set a new password for {$this->activityLabel()}",
            $fields === ['email_verified_at'] && $self => 'Verified their email address',
            $fields === ['is_active'] => ($this->is_active ? 'Reactivated' : 'Deactivated')." the account of {$this->activityLabel()}",
            in_array('role', $fields, true) => "Changed the role of {$this->activityLabel()} to {$this->role->label()}",
            $self => 'Updated their profile'.($this->wasChanged('password') ? ' and password' : ''),
            default => "Updated the account of {$this->activityLabel()}".($this->wasChanged('password') ? ' and set a new password' : ''),
        };
    }
}
