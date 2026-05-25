<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_MEMBER = 'member';

    public const ROLES = [
        self::ROLE_ADMIN => 'Administrateur',
        self::ROLE_MEMBER => 'Membre',
    ];

    protected $appends = ['pseudo', 'is_admin'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function ownedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'owner_id');
    }

    public function projects(): BelongsToMany
    {
        return $this
            ->belongsToMany(Project::class)
            ->withPivot('role', 'joined_at')
            ->withTimestamps();
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assignee_id');
    }

    public function initials(): string
    {
        return collect(explode(' ', trim($this->name)))
            ->map(fn (string $part) => mb_substr($part, 0, 1))
            ->take(2)
            ->implode('');
    }

    protected function pseudo(): Attribute
    {
        return Attribute::get(fn () => Str::before($this->email, '@'));
    }

    protected function isAdmin(): Attribute
    {
        return Attribute::get(fn () => $this->role === self::ROLE_ADMIN);
    }
}
