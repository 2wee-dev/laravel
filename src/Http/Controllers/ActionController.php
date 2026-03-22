<?php

namespace TwoWee\Laravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use TwoWee\Laravel\TwoWee;

class ActionController extends Controller
{
    public function __construct(
        protected TwoWee $twoWee,
    ) {}

    public function execute(Request $request, string $resource, string $id, string $actionId): JsonResponse
    {
        $resourceClass = $this->twoWee->getResource($resource);

        if ($resourceClass === null) {
            return response()->json(['error' => 'Resource not found'], 404);
        }

        $model = $resourceClass::findRecord($id);

        if ($model === null) {
            return response()->json([
                'success' => false,
                'message' => null,
                'error' => 'Record not found.',
                'screen' => null,
            ], 404);
        }

        $fields = $request->input('fields', []);

        $result = $resourceClass::handleAction($actionId, $model, $fields);

        return response()->json($result);
    }

    public function executeGlobal(Request $request, string $resource, string $actionId): JsonResponse
    {
        $resourceClass = $this->twoWee->getResource($resource);

        if ($resourceClass === null) {
            return response()->json(['error' => 'Resource not found'], 404);
        }

        $fields = $request->input('fields', []);

        $result = $resourceClass::handleAction($actionId, null, $fields);

        return response()->json($result);
    }
}
