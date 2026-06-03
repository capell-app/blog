<?php

declare(strict_types=1);

namespace Capell\Blog\Support\PublishingStudio\Concerns;

use Capell\PublishingStudio\Actions\CopyOnWriteAction;
use Capell\PublishingStudio\Models\Workspace;
use Capell\PublishingStudio\WorkspaceContext;
use Capell\PublishingStudio\WorkspaceContextScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Keeps articles workspace-aware when Publishing Studio is installed without
 * requiring the premium package for ordinary Blog installs.
 *
 * @phpstan-require-extends Model
 */
trait BelongsToOptionalWorkspace
{
    private const string COPY_ON_WRITE_ACTION = CopyOnWriteAction::class;

    private const string WORKSPACE_CONTEXT = WorkspaceContext::class;

    private const string WORKSPACE_CONTEXT_SCOPE = WorkspaceContextScope::class;

    private const string WORKSPACE_MODEL = Workspace::class;

    public static function bootBelongsToOptionalWorkspace(): void
    {
        if (class_exists(self::WORKSPACE_CONTEXT_SCOPE)) {
            $workspaceContextScopeClass = self::WORKSPACE_CONTEXT_SCOPE;

            static::addGlobalScope(new $workspaceContextScopeClass);
        }

        static::creating(static function (Model $record): void {
            $workspaceContextClass = self::WORKSPACE_CONTEXT;

            if (! class_exists($workspaceContextClass)) {
                return;
            }

            $activeWorkspaceId = $workspaceContextClass::currentId();

            if ($activeWorkspaceId === null) {
                return;
            }

            $currentWorkspaceId = $record->getAttribute('workspace_id');

            if ($currentWorkspaceId === null || (int) $currentWorkspaceId === 0) {
                $record->setAttribute('workspace_id', $activeWorkspaceId);
            }
        });

        static::saving(static function (Model $record): ?bool {
            $workspaceContextClass = self::WORKSPACE_CONTEXT;
            $copyOnWriteActionClass = self::COPY_ON_WRITE_ACTION;

            if (! class_exists($workspaceContextClass) || ! class_exists($copyOnWriteActionClass)) {
                return null;
            }

            $activeWorkspace = $workspaceContextClass::current();

            if (! self::isPublishingStudioWorkspace($activeWorkspace)) {
                return null;
            }

            if (! $activeWorkspace instanceof Workspace) {
                return null;
            }

            if (! $record->exists) {
                return null;
            }

            if ((int) $record->getAttribute('workspace_id') !== 0) {
                return null;
            }

            if (! $record->isDirty()) {
                return null;
            }

            (new $copyOnWriteActionClass)->cloneForEdit($record, $activeWorkspace);

            return false;
        });

        static::deleting(static function (Model $record): ?bool {
            $workspaceContextClass = self::WORKSPACE_CONTEXT;
            $copyOnWriteActionClass = self::COPY_ON_WRITE_ACTION;

            if (! class_exists($workspaceContextClass) || ! class_exists($copyOnWriteActionClass)) {
                return null;
            }

            $activeWorkspace = $workspaceContextClass::current();

            if (! self::isPublishingStudioWorkspace($activeWorkspace)) {
                return null;
            }

            if (! $activeWorkspace instanceof Workspace) {
                return null;
            }

            if (! $record->exists) {
                return null;
            }

            if ((int) $record->getAttribute('workspace_id') !== 0) {
                return null;
            }

            (new $copyOnWriteActionClass)->cloneForDelete($record, $activeWorkspace);

            return false;
        });
    }

    /** @return BelongsTo<Model, $this> */
    public function workspace(): BelongsTo
    {
        /** @var class-string<Model> $workspaceModelClass */
        $workspaceModelClass = self::WORKSPACE_MODEL;

        return $this->belongsTo($workspaceModelClass);
    }

    public function isLive(): bool
    {
        return (int) $this->getAttribute('workspace_id') === 0;
    }

    public function isInWorkspace(): bool
    {
        return (int) $this->getAttribute('workspace_id') > 0;
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function scopeLive(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('workspace_id'), 0);
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function scopeInWorkspace(Builder $query, Model|int $workspace): Builder
    {
        $workspaceId = $workspace instanceof Model ? $workspace->getKey() : $workspace;

        return $query->where($this->qualifyColumn('workspace_id'), $workspaceId);
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function scopeForContext(Builder $query, Model|int|null $workspace): Builder
    {
        $workspaceColumn = $this->qualifyColumn('workspace_id');

        if ($workspace === null) {
            return $query->where($workspaceColumn, 0);
        }

        $workspaceId = $workspace instanceof Model ? $workspace->getKey() : $workspace;
        $shadowedColumn = $this->qualifyColumn('shadowed_by_workspace_id');

        return $query->where(
            static function (Builder $inner) use ($workspaceColumn, $shadowedColumn, $workspaceId): void {
                $inner->where($workspaceColumn, $workspaceId)
                    ->orWhere(
                        static function (Builder $liveBranch) use ($workspaceColumn, $shadowedColumn, $workspaceId): void {
                            $liveBranch->where($workspaceColumn, 0)
                                ->where($shadowedColumn, '!=', $workspaceId);
                        },
                    );
            },
        );
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function scopeWithoutWorkspaceScope(Builder $query): Builder
    {
        if (! class_exists(self::WORKSPACE_CONTEXT_SCOPE)) {
            return $query;
        }

        return $query->withoutGlobalScope(self::WORKSPACE_CONTEXT_SCOPE);
    }

    private static function isPublishingStudioWorkspace(mixed $workspace): bool
    {
        $workspaceModelClass = self::WORKSPACE_MODEL;

        return class_exists($workspaceModelClass) && $workspace instanceof $workspaceModelClass;
    }
}
