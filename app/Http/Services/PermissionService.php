<?php

namespace App\Http\Services;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;

class PermissionService extends Service
{
    public function index()
    {
        // Generate Permissions
        $this->generatePermissionsForAllModels();

        $permissions = Permission::all();

        return $permissions;
    }

    /**
     * Convert model name to permission-friendly format
     * Example: UserSubscriptionPlan -> user subscription plans
     */
    private function formatModelNameForPermission($modelName)
    {
        // Split camelCase/PascalCase into words
        $words = preg_split('/(?=[A-Z])/', $modelName, -1, PREG_SPLIT_NO_EMPTY);

        // Convert to lowercase
        $words = array_map('strtolower', $words);

        // Pluralize the last word
        if (! empty($words)) {
            $lastIndex = count($words) - 1;
            $words[$lastIndex] = Str::plural($words[$lastIndex]);
        }

        // Join with spaces
        return implode(' ', $words);
    }

    /**
     * Get all Eloquent models from the app/Models directory
     */
    public function getAllModels()
    {
        $models = [];
        $modelsPath = app_path('Models');

        if (! File::exists($modelsPath)) {
            return $models;
        }

        $files = File::allFiles($modelsPath);

        foreach ($files as $file) {
            $namespace = 'App\\Models\\';
            $className = $namespace . pathinfo($file->getFilename(), PATHINFO_FILENAME);

            // Check if class exists and is an Eloquent model
            if (class_exists($className)) {
                $reflection = new ReflectionClass($className);

                // Check if it extends Eloquent Model and is not abstract
                if ($reflection->isSubclassOf(Model::class) && ! $reflection->isAbstract()) {
                    $models[] = [
                        'class' => $className,
                        'name' => $reflection->getShortName(),
                        'table' => (new $className)->getTable(),
                    ];
                }
            }
        }

        return collect($models)->sortBy('name')->values()->all();
    }

    /**
     * Generate permissions for all models
     */
    public function generatePermissionsForAllModels()
    {
        $models = $this->getAllModels();
        $createdPermissions = [];

        // Standard CRUD permissions
        $actions = ['view', 'create', 'update', 'delete'];

        foreach ($models as $model) {
            $formattedModelName = $this->formatModelNameForPermission($model['name']);

            foreach ($actions as $action) {
                $permissionName = "{$action} {$formattedModelName}";

                // Create permission if it doesn't exist
                $permission = Permission::updateOrCreate(
                    ['name' => $permissionName],
                    [
                        'guard_name' => 'web',
                    ]
                );

                $createdPermissions[] = $permission;
            }
        }

        return [
            'models' => $models,
            'permissions' => $createdPermissions,
            'total_permissions' => count($createdPermissions),
        ];
    }

    /**
     * Generate permissions for a specific model
     */
    public function generatePermissionsForModel($modelName)
    {
        $actions = ['view', 'create', 'update', 'delete'];
        $createdPermissions = [];

        $formattedModelName = $this->formatModelNameForPermission($modelName);

        foreach ($actions as $action) {
            $permissionName = "{$action} {$formattedModelName}";

            $permission = Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);

            $createdPermissions[] = $permission;
        }

        return $createdPermissions;
    }

    /**
     * Get all model names as a simple array
     */
    public function getModelNames()
    {
        return collect($this->getAllModels())->pluck('name')->toArray();
    }

    /**
     * Sync permissions - remove unused ones, add missing ones
     */
    public function syncPermissions()
    {
        $models = $this->getAllModels();
        $actions = ['view', 'create', 'update', 'delete'];

        $expectedPermissions = [];

        // Generate expected permission names
        foreach ($models as $model) {
            $formattedModelName = $this->formatModelNameForPermission($model['name']);
            foreach ($actions as $action) {
                $expectedPermissions[] = "{$action} {$formattedModelName}";
            }
        }

        // Create missing permissions
        foreach ($expectedPermissions as $permissionName) {
            Permission::updateOrCreate(
                ['name' => $permissionName],
                [
                    'guard_name' => 'web',
                ]
            );
        }

        // Optionally remove permissions that don't match any model
        // This is commented out for safety - uncomment if you want to clean up
        /*
        $modelPermissions = Permission::whereIn('name', $expectedPermissions)->get();
        $orphanedPermissions = Permission::whereNotIn('name', $expectedPermissions)
            ->where('name', 'like', 'view %')
            ->orWhere('name', 'like', 'create %')
            ->orWhere('name', 'like', 'update %')
            ->orWhere('name', 'like', 'delete %')
            ->get();

        foreach ($orphanedPermissions as $permission) {
            $permission->delete();
        }
        */

        return [
            'expected_permissions' => count($expectedPermissions),
            'models_found' => count($models),
        ];
    }
}
