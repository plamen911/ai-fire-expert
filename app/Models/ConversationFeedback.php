<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationFeedback extends Model
{
    /** @use HasFactory<\Database\Factories\ConversationFeedbackFactory> */
    use HasFactory;

    protected $table = 'conversation_feedback';

    protected $fillable = [
        'conversation_id',
        'user_id',
        'message_index',
        'is_positive',
    ];

    protected function casts(): array
    {
        return [
            'is_positive' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
