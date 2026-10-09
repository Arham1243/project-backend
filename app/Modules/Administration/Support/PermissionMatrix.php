<?php

namespace App\Modules\Administration\Support;

final class PermissionMatrix
{
    private const ALL_ACTIONS = ['view', 'create', 'edit', 'delete'];

    /** Actions that require view when enabled. */
    private const IMPLY_VIEW = ['create', 'edit', 'delete'];

    public static function applicableActionsFor(string $entity): array
    {
        $definitions = config('permission_matrix.entity_actions', []);
        $default = config('permission_matrix.default_actions', self::ALL_ACTIONS);

        return $definitions[$entity] ?? $default;
    }

    public static function isExcludedEntity(string $entity): bool
    {
        return in_array($entity, config('permission_matrix.excluded_entities', []), true);
    }

    public static function isKnownEntity(string $entity): bool
    {
        if (self::isExcludedEntity($entity)) {
            return false;
        }

        $definitions = config('permission_matrix.entity_actions', []);
        $fullCrud = config('permission_matrix.full_crud_entities', []);

        return in_array($entity, $fullCrud, true)
            || array_key_exists($entity, $definitions);
    }

    public static function isApplicablePermissionName(string $permissionName): bool
    {
        $parts = explode('.', $permissionName);
        $action = array_pop($parts);
        $entity = implode('.', $parts);

        if (! self::isKnownEntity($entity)) {
            return false;
        }

        return in_array($action, self::applicableActionsFor($entity), true);
    }

    /**
     * @return list<string>
     */
    public static function allDefinedPermissionNames(): array
    {
        $defaultActions = config('permission_matrix.default_actions', self::ALL_ACTIONS);
        $entityActions = config('permission_matrix.entity_actions', []);
        $fullCrud = config('permission_matrix.full_crud_entities', []);

        $names = [];

        foreach ($fullCrud as $entity) {
            if (! is_string($entity) || $entity === '') {
                continue;
            }
            foreach ($defaultActions as $action) {
                $names[] = "{$entity}.{$action}";
            }
        }

        foreach ($entityActions as $entity => $actions) {
            if (! is_string($entity) || $entity === '' || ! is_array($actions)) {
                continue;
            }
            foreach ($actions as $action) {
                if (is_string($action) && $action !== '') {
                    $names[] = "{$entity}.{$action}";
                }
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @param  array<string, array<string, bool|null>>  $matrix
     * @return array<string, array<string, bool|null>>
     */
    public static function normalizeRoleMatrix(array $matrix): array
    {
        foreach ($matrix as $entity => $actions) {
            if (! self::isKnownEntity($entity)) {
                unset($matrix[$entity]);
                continue;
            }

            if (self::isExcludedEntity($entity)) {
                unset($matrix[$entity]);
                continue;
            }

            foreach (self::ALL_ACTIONS as $action) {
                if (! in_array($action, self::applicableActionsFor($entity), true)) {
                    $matrix[$entity][$action] = null;
                }
            }

            $matrix[$entity] = self::applyDependenciesForEntity(
                $entity,
                $matrix[$entity]
            );
        }

        return $matrix;
    }

    /**
     * @param  array<string, bool|null>  $actions
     * @return array<string, bool|null>
     */
    public static function applyDependenciesForEntity(string $entity, array $actions): array
    {
        $applicable = self::applicableActionsFor($entity);

        $requiresView = false;
        foreach (self::IMPLY_VIEW as $action) {
            if (
                in_array($action, $applicable, true)
                && ($actions[$action] ?? null) === true
            ) {
                $requiresView = true;
                break;
            }
        }

        if ($requiresView && in_array('view', $applicable, true)) {
            $actions['view'] = true;
        }

        if (
            in_array('view', $applicable, true)
            && ($actions['view'] ?? null) === false
        ) {
            foreach (self::IMPLY_VIEW as $action) {
                if (in_array($action, $applicable, true)) {
                    $actions[$action] = false;
                }
            }
        }

        return $actions;
    }

    /**
     * @param  array<int, string>  $permissionNames
     * @return array<int, string>
     */
    public static function expandPermissionNames(array $permissionNames): array
    {
        $expanded = [];

        foreach ($permissionNames as $name) {
            if (! is_string($name) || $name === '') {
                continue;
            }

            $expanded[] = $name;

            $parts = explode('.', $name);
            $action = array_pop($parts);
            if (! in_array($action, self::IMPLY_VIEW, true)) {
                continue;
            }

            $entity = implode('.', $parts);
            $viewName = "{$entity}.view";

            if (
                self::isApplicablePermissionName($viewName)
                && ! in_array($viewName, $expanded, true)
            ) {
                $expanded[] = $viewName;
            }
        }

        return array_values(array_unique($expanded));
    }
}
