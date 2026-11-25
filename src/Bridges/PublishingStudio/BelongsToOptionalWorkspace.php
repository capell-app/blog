<?php

declare(strict_types=1);

namespace Capell\Blog\Bridges\PublishingStudio;

use Capell\Core\Facades\CapellCore;
use Capell\PublishingStudio\Actions\CopyOnWriteAction;
use Capell\PublishingStudio\Models\Workspace;
use Capell\PublishingStudio\WorkspaceContext;
use Capell\PublishingStudio\WorkspaceContextScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Keeps articles workspace-aware when Publishing Studio is installed without
 * requiring the premium package for ordinary Blog installs.
 *
 * @phpstan-require-extends Model
 */
trait BelongsToOptionalWorkspace
{
    public static function bootBelongsToOptionalWorkspace(): void
    {
        if (! CapellCore::isPackageAvailable('capell-app/publishing-studio')) {
            return;
        }

        static::addGlobalScope(new WorkspaceContextScope);

        static::creating(static function (Model $record): void {
            $activeWorkspaceId = WorkspaceContext::currentId();

            if ($activeWorkspaceId === null) {
                return;
            }

            $currentWorkspaceId = $record->getAttribute('workspace_id');

            if ($currentWorkspaceId === null || (int) $currentWorkspaceId === 0) {
                $record->setAttribute('workspace_id', $activeWorkspaceId);
            }
        });

        static::saving(static function (Model $record): ?bool {
            $activeWorkspace = WorkspaceContext::current();

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

            (new CopyOnWriteAction)->cloneForEdit($record, $activeWorkspace);

            return false;
        });

        static::deleting(static function (Model $record): ?bool {
            $activeWorkspace = WorkspaceContext::current();

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

            (new CopyOnWriteAction)->cloneForDelete($record, $activeWorkspace);

            return false;
        });
    }

    /** @return BelongsTo<Model, $this> */
    public function workspace(): BelongsTo
    {
        if (! CapellCore::isPackageAvailable('capell-app/publishing-studio')) {
            throw new LogicException('Publishing Studio must be installed before reading a record\'s workspace.');
        }

        /** @var class-string<Model> $workspaceModelClass */
        $workspaceModelClass = Workspace::class;

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
        if (! CapellCore::isPackageAvailable('capell-app/publishing-studio')) {
            return $query;
        }

        return $query->withoutGlobalScope(WorkspaceContextScope::class);
    }

    private static function isPublishingStudioWorkspace(mixed $workspace): bool
    {
        if (! CapellCore::isPackageAvailable('capell-app/publishing-studio')) {
            return false;
        }

        return $workspace instanceof Workspace;
    }
}
