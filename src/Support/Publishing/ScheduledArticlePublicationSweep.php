<?php

declare(strict_types=1);

namespace Capell\Blog\Support\Publishing;

use Capell\Blog\Models\Article;
use Capell\Core\Support\Publishing\ScheduledPublicationRegistry;
use Capell\Frontend\Actions\PersistScheduledPublicationInvalidationCheckpointAction;
use Capell\Frontend\Settings\FrontendSettings;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use DateTimeInterface;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Spatie\LaravelSettings\SettingsRepositories\SettingsRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use WeakMap;

final class ScheduledArticlePublicationSweep
{
    private const string COMMAND = 'capell:invalidate-due-scheduled-publications';

    private const string LEGACY_CHECKPOINT_KEY = 'capell:blog:scheduled-article-invalidation-checkpoint';

    private const string LOCK_KEY = 'capell:blog:scheduled-article-publication-coverage';

    private const string COVERED_UNTIL = 'scheduled_article_publication_covered_until';

    private const string SHARED_CHECKPOINT = 'scheduled_publication_invalidation_checkpoint';

    /** @var WeakMap<InputInterface, CarbonImmutable> */
    private WeakMap $startedAt;

    public function __construct()
    {
        $this->startedAt = new WeakMap;
    }

    public function starting(CommandStarting $event): void
    {
        if ($event->command !== self::COMMAND || ! in_array(Article::class, resolve(ScheduledPublicationRegistry::class)->models(), true)) {
            return;
        }

        $lock = Cache::lock(self::LOCK_KEY, 30);
        try {
            $lock->block(5);
        } catch (LockTimeoutException) {
            // Another sweep holds Blog coverage. Leave it unpinned so this run cannot advance it; the next run rescans.
            Log::warning('Skipped Blog scheduled article coverage for this sweep because another process holds its lock.');

            return;
        }

        try {
            $now = CarbonImmutable::now()->startOfSecond();
            $repository = resolve(FrontendSettings::class)->getRepository();
            $shared = $repository->getPropertiesInGroup(FrontendSettings::group())[self::SHARED_CHECKPOINT] ?? null;
            $sharedCheckpoint = $this->validCheckpoint($shared, $now);
            $coveredUntil = $this->coveredUntil($repository, $now);
            $blogCheckpoint = $coveredUntil
                ?? $this->validCheckpoint(Cache::get(self::LEGACY_CHECKPOINT_KEY), $now)
                ?? $now->subHours(24);
            $checkpoint = $sharedCheckpoint !== null && $sharedCheckpoint->isBefore($blogCheckpoint)
                ? $sharedCheckpoint->toIso8601String()
                : $blogCheckpoint->toIso8601String();

            if ($coveredUntil === null) {
                // Keep the initial scan's lower bound even if it fails and a Blog-disabled sweep advances Core.
                $repository->updatePropertiesPayload('blog', [self::COVERED_UNTIL => $checkpoint]);
            }

            if ($shared !== $checkpoint) {
                PersistScheduledPublicationInvalidationCheckpointAction::run($checkpoint);
            }

            // Pin this command's lower bound even if another process advances the stored shared checkpoint.
            $settings = resolve(FrontendSettings::class);
            $settings->scheduled_publication_invalidation_checkpoint = $checkpoint;
            app()->instance(FrontendSettings::class, $settings);
            $this->startedAt[$event->input] = $now;
        } finally {
            $lock->release();
        }
    }

    public function finished(CommandFinished $event): void
    {
        $startedAt = $this->startedAt[$event->input] ?? null;
        if ($event->command !== self::COMMAND || $startedAt === null) {
            return;
        }

        unset($this->startedAt[$event->input]);
        app()->forgetInstance(FrontendSettings::class);

        if ($event->exitCode !== Command::SUCCESS) {
            return;
        }

        // The sweep samples its end after CommandStarting. This bound also covers successful idle sweeps.
        try {
            Cache::lock(self::LOCK_KEY, 30)->block(5, function () use ($startedAt): void {
                $now = CarbonImmutable::now();
                $repository = resolve(FrontendSettings::class)->getRepository();
                $coveredUntil = $this->coveredUntil($repository, $now);
                if (! $startedAt->isAfter($now) && ($coveredUntil === null || $startedAt->isAfter($coveredUntil))) {
                    $repository->updatePropertiesPayload('blog', [self::COVERED_UNTIL => $startedAt->toIso8601String()]);
                }
            });
        } catch (LockTimeoutException) {
            // Leaving coverage behind is safe: the next sweep rescans from the older bound.
            Log::warning('Did not advance Blog scheduled article coverage because another process holds its lock.');
        }
    }

    private function coveredUntil(SettingsRepository $repository, CarbonImmutable $now): ?CarbonImmutable
    {
        return $this->validCheckpoint($repository->getPropertiesInGroup('blog')[self::COVERED_UNTIL] ?? null, $now);
    }

    private function validCheckpoint(mixed $value, CarbonImmutable $now): ?CarbonImmutable
    {
        if (! is_string($value) || ! CarbonImmutable::hasFormat($value, DateTimeInterface::ATOM)) {
            return null;
        }

        try {
            $checkpoint = CarbonImmutable::createFromFormat(DateTimeInterface::ATOM, $value);
        } catch (InvalidFormatException) {
            return null;
        }

        if (! $checkpoint instanceof CarbonImmutable || $checkpoint->toIso8601String() !== $value || $checkpoint->isAfter($now)) {
            return null;
        }

        return $checkpoint;
    }
}
