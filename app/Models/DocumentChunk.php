<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class DocumentChunk extends Model
{
    /** @use HasFactory<\Database\Factories\DocumentChunkFactory> */
    use HasFactory;

    protected $hidden = ['embedding', 'search_vector'];

    protected $fillable = [
        'document_id',
        'chunk_index',
        'content',
        'embedding',
    ];

    protected function casts(): array
    {
        return [
            'embedding' => 'array',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Scope for PostgreSQL full-text keyword search using tsvector.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeKeywordSearch(Builder $query, string $searchQuery): Builder
    {
        if (DB::getDriverName() !== 'pgsql') {
            return $query->where('content', 'like', '%'.$searchQuery.'%');
        }

        $tsQuery = collect(preg_split('/\s+/', trim($searchQuery)))
            ->filter(fn (string $word): bool => mb_strlen($word) > 1)
            ->map(fn (string $word): string => $word.':*')
            ->implode(' | ');

        if ($tsQuery === '') {
            return $query;
        }

        return $query->whereRaw("search_vector @@ to_tsquery('simple', ?)", [$tsQuery]);
    }
}
