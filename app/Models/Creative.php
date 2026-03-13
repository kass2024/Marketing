<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Creative extends Model
{
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | TABLE
    |--------------------------------------------------------------------------
    */

    protected $table = 'creatives';


    /*
    |--------------------------------------------------------------------------
    | STATUS
    |--------------------------------------------------------------------------
    */

    const STATUS_DRAFT    = 'DRAFT';
    const STATUS_ACTIVE   = 'ACTIVE';
    const STATUS_ARCHIVED = 'ARCHIVED';


    /*
    |--------------------------------------------------------------------------
    | CALL TO ACTION TYPES
    |--------------------------------------------------------------------------
    */

    const CTA_LEARN_MORE  = 'LEARN_MORE';
    const CTA_APPLY_NOW   = 'APPLY_NOW';
    const CTA_SIGN_UP     = 'SIGN_UP';
    const CTA_CONTACT_US  = 'CONTACT_US';
    const CTA_DOWNLOAD    = 'DOWNLOAD';
    const CTA_GET_OFFER   = 'GET_OFFER';


    /*
    |--------------------------------------------------------------------------
    | MASS ASSIGNMENT
    |--------------------------------------------------------------------------
    */

    protected $fillable = [

        // relationships
        'campaign_id',
        'adset_id',

        // Meta reference
        'meta_id',

        // creative data
        'name',
        'headline',
        'body',

        // media
        'image_url',
        'video_url',
        'image_hash',

        // CTA
        'call_to_action',

        // destination
        'destination_url',

        // raw Meta payload
        'json_payload',

        // lifecycle
        'status',

        // Meta delivery status
        'effective_status',
        'review_status',
        'review_feedback',

        // performance
        'impressions',
        'spend',

        // sync
        'last_synced_at'
    ];


    /*
    |--------------------------------------------------------------------------
    | DEFAULTS
    |--------------------------------------------------------------------------
    */

    protected $attributes = [

        'status' => self::STATUS_DRAFT,
        'impressions' => 0,
        'spend' => 0

    ];


    /*
    |--------------------------------------------------------------------------
    | CASTS
    |--------------------------------------------------------------------------
    */

    protected $casts = [

        'json_payload' => 'array',

        'impressions' => 'integer',

        'spend' => 'float',

        'last_synced_at' => 'datetime'

    ];


    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function adset(): BelongsTo
    {
        return $this->belongsTo(AdSet::class);
    }

    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class);
    }


    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ARCHIVED);
    }


    /*
    |--------------------------------------------------------------------------
    | MEDIA HELPERS
    |--------------------------------------------------------------------------
    */

    public function hasImage(): bool
    {
        return !empty($this->image_url);
    }

    public function hasVideo(): bool
    {
        return !empty($this->video_url);
    }

    public function getMediaTypeAttribute(): string
    {
        if ($this->video_url) {
            return 'video';
        }

        if ($this->image_url) {
            return 'image';
        }

        return 'none';
    }

    public function getMediaUrlAttribute(): ?string
    {
        return $this->video_url ?: $this->image_url;
    }


    /*
    |--------------------------------------------------------------------------
    | STORAGE URL ACCESSOR
    |--------------------------------------------------------------------------
    */

    public function getImageUrlAttribute($value): ?string
    {
        if (!$value) {
            return null;
        }

        if (
            str_starts_with($value, 'http') ||
            str_starts_with($value, '/storage')
        ) {
            return $value;
        }

        return Storage::url($value);
    }


    /*
    |--------------------------------------------------------------------------
    | PREVIEW DATA
    |--------------------------------------------------------------------------
    */

    public function getPreview(): array
    {
        return [

            'headline' => $this->headline,

            'body' => $this->body,

            'image_url' => $this->image_url,

            'video_url' => $this->video_url,

            'cta' => $this->call_to_action,

            'destination_url' => $this->destination_url,

            'status' => $this->status

        ];
    }


    /*
    |--------------------------------------------------------------------------
    | META PAYLOAD
    |--------------------------------------------------------------------------
    */

    public function getMetaPayload(): array
    {
        return $this->json_payload ?? [];
    }

    public function setMetaPayload(array $payload): void
    {
        $this->json_payload = $payload;
        $this->save();
    }


    /*
    |--------------------------------------------------------------------------
    | STATUS HELPERS
    |--------------------------------------------------------------------------
    */

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }


    /*
    |--------------------------------------------------------------------------
    | REVIEW HELPERS
    |--------------------------------------------------------------------------
    */

    public function isApproved(): bool
    {
        return $this->review_status === 'APPROVED';
    }

    public function isPendingReview(): bool
    {
        return $this->review_status === 'PENDING_REVIEW';
    }

    public function isRejected(): bool
    {
        return $this->review_status === 'DISAPPROVED';
    }


    /*
    |--------------------------------------------------------------------------
    | DELIVERY HELPERS
    |--------------------------------------------------------------------------
    */

    public function isDelivering(): bool
    {
        return $this->effective_status === 'ACTIVE';
    }

    public function isPaused(): bool
    {
        return $this->effective_status === 'PAUSED';
    }


    /*
    |--------------------------------------------------------------------------
    | CTA OPTIONS
    |--------------------------------------------------------------------------
    */

    public static function ctaOptions(): array
    {
        return [

            self::CTA_LEARN_MORE => 'Learn More',
            self::CTA_APPLY_NOW  => 'Apply Now',
            self::CTA_SIGN_UP    => 'Sign Up',
            self::CTA_CONTACT_US => 'Contact Us',
            self::CTA_DOWNLOAD   => 'Download',
            self::CTA_GET_OFFER  => 'Get Offer'

        ];
    }
}