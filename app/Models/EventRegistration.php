<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EventRegistration extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['access_hash', 'recovery_hash', 'enrollment_key'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function charge(): HasOne
    {
        return $this->hasOne(Cobranca::class);
    }

    public function aluno(): BelongsTo
    {
        return $this->belongsTo(Aluno::class);
    }

    protected function casts(): array
    {
        return ['gift_snapshot' => 'array', 'gift_revealed_at' => 'datetime', 'gift_delivered_at' => 'datetime', 'marketing_opt_in' => 'boolean', 'checked_in_at' => 'datetime', 'privacy_accepted_at' => 'datetime'];
    }

    public function participantPayload(): array
    {
        return ['payment' => $this->charge ? ['status' => $this->charge->status, 'value' => (float) $this->charge->valor, 'description' => $this->charge->descricao, 'refundReview' => (bool) $this->charge->refund_review_required] : null, 'name' => $this->name, 'registeredAt' => $this->created_at->toIso8601String(), 'checkedInAt' => $this->checked_in_at?->toIso8601String(),
            'attendanceSource' => $this->attendance_source, 'rating' => $this->rating, 'feedback' => $this->feedback,
            'marketingOptIn' => $this->marketing_opt_in];
    }
}
