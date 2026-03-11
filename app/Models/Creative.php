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

        // basic info
        'name',

        // content
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

        // raw meta payload
        'json_payload',

        // lifecycle
        'status'
    ];


    /*
    |--------------------------------------------------------------------------
    | DEFAULTS
    |--------------------------------------------------------------------------
    */

    protected $attributes = [
        'status' => self::STATUS_DRAFT
    ];


    /*
    |--------------------------------------------------------------------------
    | CASTS
    |--------------------------------------------------------------------------
    */

    protected $casts = [

        'json_payload' => 'array'

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
    | STORAGE URL
    |--------------------------------------------------------------------------
    */

    public function getImageUrlAttribute($value): ?string
    {
        if (!$value) {
            return null;
        }

        if (str_starts_with($value, 'http')) {
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