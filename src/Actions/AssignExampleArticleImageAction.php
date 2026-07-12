<?php

declare(strict_types=1);

namespace Capell\Blog\Actions;

use Capell\Blog\Models\Article;
use Lorisleiva\Actions\Concerns\AsAction;

final class AssignExampleArticleImageAction
{
    use AsAction;

    /**
     * @var list<string>
     */
    private const array ExampleImageUrls = [
        'https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=1200&q=80',
        'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=1200&q=80',
        'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=1200&q=80',
        'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1200&q=80',
        'https://images.unsplash.com/photo-1498050108023-c5249f4df085?auto=format&fit=crop&w=1200&q=80',
    ];

    public function handle(Article $article): Article
    {
        if (is_string($article->getMeta('image_source.url'))) {
            return $article;
        }

        $meta = $article->meta ?? [];
        $index = max(0, ((int) $article->getKey()) - 1) % count(self::ExampleImageUrls);

        $article->forceFill([
            'meta' => [
                ...$meta,
                'image_source' => [
                    'type' => 'url',
                    'url' => self::ExampleImageUrls[$index],
                ],
            ],
        ])->save();

        return $article;
    }
}
