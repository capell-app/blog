<?php

declare(strict_types=1);

namespace Capell\Blog\Actions;

use Capell\Blog\Models\Article;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

final class AssignExampleArticleImageAction
{
    use AsFake;
    use AsObject;

    public const string PUBLISH_TAG = 'capell-blog-example-images';

    public const string PUBLIC_PATH = 'vendor/capell-blog/demo-media';

    /**
     * Capell-owned example images shipped in publishes/demo-media and recorded
     * in the package's demo-assets.json.
     */
    private const int EXAMPLE_IMAGE_COUNT = 5;

    /**
     * Root-relative, so the image loads from whichever domain serves the page.
     */
    public static function exampleImageUrl(int $number): string
    {
        $assetUrl = config('app.asset_url');
        $prefix = is_string($assetUrl) ? parse_url($assetUrl, PHP_URL_PATH) : null;
        $prefix = is_string($prefix) ? trim($prefix, '/') : '';

        return ($prefix === '' ? '' : '/' . $prefix) . '/' . self::PUBLIC_PATH . '/blog-examples-' . $number . '.webp';
    }

    public function handle(Article $article): Article
    {
        if (is_string($article->getMeta('image_source.url'))) {
            return $article;
        }

        $meta = $article->meta ?? [];
        $index = max(0, ((int) $article->getKey()) - 1) % self::EXAMPLE_IMAGE_COUNT;

        $article->forceFill([
            'meta' => [
                ...$meta,
                'image_source' => [
                    'type' => 'url',
                    'url' => self::exampleImageUrl($index + 1),
                ],
            ],
        ])->save();

        return $article;
    }
}
