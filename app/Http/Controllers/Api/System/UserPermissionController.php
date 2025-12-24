<?php

namespace App\Http\Controllers\Api\System;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class UserPermissionController extends Controller
{
    /**
     * PUT /users/{user}/permissions
     *
     * Sync direct permissions for a user (user-centric override).
     * Roles (if any) are not required and are not modified.
     */
    public function sync(Request $request, User $user): JsonResponse
    {
        $this->authorize('permission.assign');

        $validated = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string'],
        ]);

        $requested = array_values(array_unique(array_map('strval', $validated['permissions'])));

        $existing = Permission::query()
            ->whereIn('name', $requested)
            ->pluck('name')
            ->all();

        $missing = array_values(array_diff($requested, $existing));
        if ($missing !== []) {
            return response()->json([
                'message' => 'Unknown permissions provided.',
                'missing' => $missing,
            ], 422);
        }

        $user->syncPermissions($existing);

        return response()->json([
            'data' => [
                'user_id' => $user->id,
                'direct_permissions' => $user->getDirectPermissions()->pluck('name')->values(),
            ],
        ]);
    }

    /**
     * POST /users/{user}/permissions/copy-from/{source}
     *
     * Copy direct permissions from a source user.
     */
    public function copyFrom(User $user, User $source): JsonResponse
    {
        $this->authorize('permission.copy');

        $user->copyPermissionsFrom($source);

        return response()->json([
            'data' => [
                'user_id' => $user->id,
                'direct_permissions' => $user->getDirectPermissions()->pluck('name')->values(),
            ],
        ]);
    }
}
