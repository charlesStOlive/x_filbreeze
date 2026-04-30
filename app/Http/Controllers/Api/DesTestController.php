<?php

namespace App\Http\Controllers\Api;

use App\Models\DesTest;
use CharlesStOlive\FilamentPermissionManager\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DesTestController extends Controller
{
    public function index(): JsonResponse
    {
        if (! PermissionService::can('destest.viewany')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json(DesTest::all());
    }

    public function store(Request $request): JsonResponse
    {
        if (! PermissionService::can('destest.create')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $desTest = DesTest::create($validated);

        return response()->json($desTest, 201);
    }

    public function destroy(DesTest $desTest): JsonResponse
    {
        if (! PermissionService::can('destest.delete')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $desTest->delete();

        return response()->json(['message' => 'Deleted'], 200);
    }

    public function publish(DesTest $desTest): JsonResponse
    {
        if (! PermissionService::can('destest.publish')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $desTest->update(['published' => true]);

        return response()->json($desTest);
    }
}
