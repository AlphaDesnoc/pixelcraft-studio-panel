<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'project_id',
    'rank_id',
    'parent_id',
    'uploader_id',
    'type',
    'name',
    'path',
    'mime',
    'size',
])]
class FileNode extends Model
{
    public const TYPE_FOLDER = 'folder';

    public const TYPE_FILE = 'file';

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function rank(): BelongsTo
    {
        return $this->belongsTo(Rank::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    public function isFolder(): bool
    {
        return $this->type === self::TYPE_FOLDER;
    }

    public function isFile(): bool
    {
        return $this->type === self::TYPE_FILE;
    }
}
