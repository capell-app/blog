<?php

declare(strict_types=1);

namespace Capell\Blog\Data;

use Capell\Blog\Actions\GenerateArchiveUrlAction;
use Capell\Core\Models\PageUrl;
use Illuminate\Support\Facades\Date;
use Spatie\LaravelData\Data;

final class ArchiveLinkData extends Data
{
    public function __construct(
        public readonly string $url,
        public readonly string $label,
        public readonly int $count,
        public readonly bool $active,
    ) {}

    public static function fromArchive(PageUrl $pageUrl, ArchiveMonthData $archive, mixed $activeArchive): self
    {
        return new self(
            url: GenerateArchiveUrlAction::run($pageUrl, $archive),
            label: Date::parse(sprintf('%d-%02d-01', $archive->year, $archive->month))->format('F Y'),
            count: $archive->total,
            active: is_object($activeArchive)
                && isset($activeArchive->month, $activeArchive->year)
                && $activeArchive->month === $archive->month
                && $activeArchive->year === $archive->year,
        );
    }
}
