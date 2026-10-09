<?php

namespace App\Support;

class ModulePermissionConfig
{
    /**
     * Merge per-module permission manifests into the base permission_matrix config.
     *
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    public static function merge(array $base): array
    {
        $entityActions = [];
        $disabled = [];
        $fullCrud = [];
        $sections = [];

        foreach (glob(app_path('Modules/*/Config/permissions.php')) ?: [] as $path) {
            if (preg_match('#/Modules/_[^/]+/#', str_replace('\\', '/', $path)) === 1) {
                continue;
            }

            /** @var mixed $fragment */
            $fragment = require $path;
            if (! is_array($fragment)) {
                continue;
            }

            $entityActions = array_merge(
                $entityActions,
                $fragment['entity_actions'] ?? []
            );
            $disabled = array_merge(
                $disabled,
                $fragment['disabled_matrix_actions'] ?? []
            );

            foreach ($fragment['full_crud_entities'] ?? [] as $entity) {
                if (is_string($entity) && $entity !== '') {
                    $fullCrud[] = $entity;
                }
            }

            foreach ($fragment['matrix_sections'] ?? [] as $section) {
                if (is_array($section)) {
                    $sections[] = $section;
                }
            }
        }

        usort(
            $sections,
            fn (array $a, array $b): int => ($a['order'] ?? 0) <=> ($b['order'] ?? 0)
        );

        return array_merge($base, [
            'entity_actions' => $entityActions,
            'disabled_matrix_actions' => $disabled,
            'full_crud_entities' => $fullCrud,
            'matrix_sections' => $sections,
        ]);
    }
}
