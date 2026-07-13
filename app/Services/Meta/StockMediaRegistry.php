<?php

namespace App\Services\Meta;

class StockMediaRegistry
{
    /**
     * Ready-to-use ad images grouped by Meta format.
     *
     * @return array<string, array<int, array{id: string, label: string, path: string, url: string, category: string, format: string, width: int, height: int}>>
     */
    public static function byFormat(): array
    {
        StockMediaGenerator::ensureAssets();

        $items = [
            // Parrot Canada branded flyers (user-provided)
            ['id' => 'parrot_jobs_4x5', 'label' => 'Parrot — Jobs in Canada (4:5)', 'path' => 'img/stock-ads/parrot-jobs-4x5.png', 'category' => 'Parrot Canada', 'format' => 'feed_4x5'],
            ['id' => 'parrot_jobs_9x16', 'label' => 'Parrot — Jobs in Canada (9:16)', 'path' => 'img/stock-ads/parrot-jobs-9x16.png', 'category' => 'Parrot Canada', 'format' => 'story_9x16'],
            ['id' => 'parrot_jobs_191', 'label' => 'Parrot — Jobs in Canada (1:1)', 'path' => 'img/stock-ads/parrot-jobs-1x1.png', 'category' => 'Parrot Canada', 'format' => 'square_1x1'],
            // Generic templates
            ['id' => 'jobs_canada', 'label' => 'Jobs in Canada', 'path' => 'img/stock-ads/jobs-canada.png', 'category' => 'Immigration', 'format' => 'feed_4x5'],
            ['id' => 'study_abroad', 'label' => 'Study Abroad', 'path' => 'img/stock-ads/study-abroad.png', 'category' => 'Education', 'format' => 'feed_4x5'],
            ['id' => 'consultation', 'label' => 'Free Consultation', 'path' => 'img/stock-ads/consultation.png', 'category' => 'Services', 'format' => 'feed_4x5'],
            ['id' => 'real_estate', 'label' => 'Real Estate', 'path' => 'img/stock-ads/real-estate.png', 'category' => 'Property', 'format' => 'feed_4x5'],
            ['id' => 'elearning', 'label' => 'Online Course', 'path' => 'img/stock-ads/elearning.png', 'category' => 'Education', 'format' => 'square_1x1'],
            ['id' => 'cleaning', 'label' => 'Cleaning Service', 'path' => 'img/stock-ads/cleaning.png', 'category' => 'Home', 'format' => 'square_1x1'],
            ['id' => 'event', 'label' => 'Event Promotion', 'path' => 'img/stock-ads/event.png', 'category' => 'Events', 'format' => 'story_9x16'],
            ['id' => 'product_sale', 'label' => 'Product Sale', 'path' => 'img/stock-ads/product-sale.png', 'category' => 'Retail', 'format' => 'square_1x1'],
        ];

        $grouped = [];
        foreach ($items as $item) {
            $abs = public_path($item['path']);
            $size = is_file($abs) ? @getimagesize($abs) : false;
            $item['width'] = is_array($size) ? $size[0] : 0;
            $item['height'] = is_array($size) ? $size[1] : 0;
            $item['url'] = asset($item['path']);
            $fmt = $item['format'];
            $grouped[$fmt][] = $item;
        }

        return $grouped;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function images(): array
    {
        return array_merge(...array_values(self::byFormat()));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forFormat(string $format): array
    {
        return self::byFormat()[$format] ?? [];
    }

    public static function find(string $id): ?array
    {
        foreach (self::images() as $image) {
            if ($image['id'] === $id) {
                return $image;
            }
        }

        return null;
    }

    public static function absolutePath(string $id): ?string
    {
        $image = self::find($id);
        if (! $image) {
            return null;
        }

        return public_path($image['path']);
    }
}
