<?php

namespace TwoWee\Laravel\Http\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

trait AuthorizesResources
{
    /**
     * Check if the current user is authorized for an ability on the resource model.
     * Returns null if authorized, or a 403 JsonResponse if denied.
     *
     * If no policy is registered for the model, access is allowed (opt-in security).
     */
    protected function authorize(string $resourceClass, string $ability, ?Model $model = null): ?JsonResponse
    {
        $modelClass = $resourceClass::getModel();

        // No policy registered → allow (opt-in)
        if (Gate::getPolicyFor($modelClass) === null) {
            return null;
        }

        $user = Auth::guard('twowee')->user();
        $target = $model ?? $modelClass;

        if (Gate::forUser($user)->allows($ability, $target)) {
            return null;
        }

        return response()->json(['error' => 'Unauthorized'], 403);
    }
}
