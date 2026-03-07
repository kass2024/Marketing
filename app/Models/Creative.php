<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Creative extends Model
{
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | Table
    |--------------------------------------------------------------------------
    */

    protected $table = 'creatives';


    /*
    |--------------------------------------------------------------------------
    | Status Constants
    |--------------------------------------------------------------------------
    */

    const STATUS_ACTIVE   = 'ACTIVE';
    const STATUS_DRAFT    = 'DRAFT';
    const STATUS_ARCHIVED = 'ARCHIVED';


    /*
    |--------------------------------------------------------------------------
    | CTA Constants
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
    | Mass Assignment
    |--------------------------------------------------------------------------
    */

    protected $fillable = [

        'meta_id',
        'name',

        // creative content
        'title',
        'body',

        // media
        'image_hash',
        'image_url',
        'video_url',

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
    | Default Attributes
    |--------------------------------------------------------------------------
    */

    protected $attributes = [
        'status' => self::STATUS_DRAFT
    ];


    /*
    |--------------------------------------------------------------------------
    | Casting
    |--------------------------------------------------------------------------
    */

    protected $casts = [
        'json_payload' => 'array'
    ];


    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class);
    }


    /*
    |--------------------------------------------------------------------------
    | Query Scopes
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
    | Media Helpers
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
    | Storage Helper
    |--------------------------------------------------------------------------
    */

    public function getImageAttribute(): ?string
    {
        if (!$this->image_url) {
            return null;
        }

        if (str_starts_with($this->image_url, 'http')) {
            return $this->image_url;
        }

        return Storage::url($this->image_url);
    }


    /*
    |--------------------------------------------------------------------------
    | Preview Helpers
    |--------------------------------------------------------------------------
    */

    public function getHeadlineAttribute(): string
    {
        return $this->title ?? '';
    }

    public function getDescriptionAttribute(): string
    {
        return $this->body ?? '';
    }

    public function getPreviewData(): array
    {
        return [
            'title'            => $this->title,
            'body'             => $this->body,
            'image_url'        => $this->image_url,
            'video_url'        => $this->video_url,
            'call_to_action'   => $this->call_to_action,
            'destination_url'  => $this->destination_url,
            'status'           => $this->status
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Meta API Helpers
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
    | Status Helpers
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
    | CTA Options
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