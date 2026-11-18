<?php

declare(strict_types=1);

namespace Capell\Blog\Data;

use Illuminate\Support\Str;
use Spatie\LaravelData\Data;

/**
 * Public-safe description of a blog author archive subject.
 *
 * The users table owned by Core carries no public slug column, so the public
 * identity of an author is derived from their display name plus a keyed token
 * of their user id. The token never changes when the author is renamed, so a
 * published author address keeps resolving, and it keeps two authors with the
 * same name apart. `userId` is kept for server-side query scoping only and
 * must never reach rendered output.
 */
final class BlogAuthorData extends Data
{
    private const int TOKEN_LENGTH = 10;

    public function __construct(
        public int $userId,
        public string $slug,
        public string $name,
        public string $stableSlug = '',
    ) {}

    /**
     * The rename-proof public slug: the display-name slug followed by a keyed
     * token of the user id. The id is not recoverable from the token.
     */
    public static function stableSlugFor(int $userId, string $name): string
    {
        $nameSlug = self::slugForName($name);
        $token = self::tokenFor($userId);

        return $nameSlug === '' ? 'author-' . $token : $nameSlug . '-' . $token;
    }

    /**
     * The token carried by a stable slug, or null for a plain name slug.
     */
    public static function tokenFromSlug(string $slug): ?string
    {
        return preg_match('/-([0-9a-f]{' . self::TOKEN_LENGTH . '})$/', $slug, $matches) === 1 ? $matches[1] : null;
    }

    public static function tokenFor(int $userId): string
    {
        $key = config('app.key');

        return substr(hash_hmac('sha256', 'capell-blog-author:' . $userId, is_string($key) ? $key : ''), 0, self::TOKEN_LENGTH);
    }

    /**
     * Derive the public archive slug for an author display name.
     */
    public static function slugForName(string $name): string
    {
        return Str::slug(trim($name));
    }
}
