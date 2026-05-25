<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DirectConversation extends Model
{
    protected $fillable = [
        'user_one_id',
        'user_two_id',
        'last_message_at',
        'user_one_last_read_at',
        'user_two_last_read_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'user_one_last_read_at' => 'datetime',
            'user_two_last_read_at' => 'datetime',
        ];
    }

    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    public function userTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(DirectMessage::class)->orderBy('created_at');
    }

    public function latestMessage(): HasMany
    {
        return $this->hasMany(DirectMessage::class)->latest()->limit(1);
    }

    public function involves(int $userId): bool
    {
        return (int) $this->user_one_id === $userId || (int) $this->user_two_id === $userId;
    }

    public function otherParticipant(User $user): ?User
    {
        if ((int) $this->user_one_id === (int) $user->id) {
            return $this->userTwo;
        }

        if ((int) $this->user_two_id === (int) $user->id) {
            return $this->userOne;
        }

        return null;
    }

    public static function findOrCreateBetween(int $userA, int $userB): self
    {
        [$one, $two] = $userA < $userB ? [$userA, $userB] : [$userB, $userA];

        return self::query()->firstOrCreate(
            ['user_one_id' => $one, 'user_two_id' => $two],
        );
    }

    public function lastReadAtFor(User $user): ?\Illuminate\Support\Carbon
    {
        if ((int) $this->user_one_id === (int) $user->id) {
            return $this->user_one_last_read_at;
        }

        if ((int) $this->user_two_id === (int) $user->id) {
            return $this->user_two_last_read_at;
        }

        return null;
    }

    public function markReadFor(User $user): void
    {
        $now = now();
        if ((int) $this->user_one_id === (int) $user->id) {
            $this->forceFill(['user_one_last_read_at' => $now])->save();
        } elseif ((int) $this->user_two_id === (int) $user->id) {
            $this->forceFill(['user_two_last_read_at' => $now])->save();
        }
    }

    public function unreadCountFor(User $user): int
    {
        $query = $this->messages()->where('user_id', '!=', $user->id);
        $lastRead = $this->lastReadAtFor($user);

        if ($lastRead) {
            $query->where('created_at', '>', $lastRead);
        }

        return (int) $query->count();
    }
}
