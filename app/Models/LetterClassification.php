<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LetterClassification extends Model
{
    public const SCOPE_PEMDA = 'pemda';

    public const SCOPE_KEMENDAGRI = 'kemendagri';

    /**
     * @var list<string>
     */
    public const SCOPES = [self::SCOPE_PEMDA, self::SCOPE_KEMENDAGRI];

    /**
     * @var array<string, string>
     */
    public const SCOPE_LABELS = [
        self::SCOPE_PEMDA => 'Pemerintah Daerah',
        self::SCOPE_KEMENDAGRI => 'Kementerian Dalam Negeri',
    ];

    /**
     * @var array<int, string>
     */
    public const LEVEL_LABELS = [
        1 => 'Kategori',
        2 => 'Sub Kategori',
        3 => 'Sub-sub Kategori',
        4 => 'Rincian',
    ];

    /**
     * @var array<int, string>
     */
    public const LEVEL_SHORT_LABELS = [
        1 => 'Kategori',
        2 => 'Sub',
        3 => 'Sub-sub',
        4 => 'Rincian',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'scope',
        'code',
        'name',
        'parent_id',
        'level',
        'sort_order',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Get the parent classification.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Get the child classifications.
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Scope a query to a classification scope (pemda / kemendagri).
     */
    public function scopeForScope(Builder $query, string $scope): Builder
    {
        return $query->where('scope', $scope);
    }

    /**
     * Order the classification in the same order as the Permendagri attachment.
     */
    public function scopeInDocumentOrder(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('code');
    }

    /**
     * Get a human readable label for the hierarchy level.
     */
    public function levelLabel(): string
    {
        return self::LEVEL_LABELS[$this->level] ?? 'Klasifikasi';
    }
}
