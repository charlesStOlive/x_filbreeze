<?php

namespace App\Http\Controllers\Api;

use App\Models\DesTest;
use CharlesStOlive\FilamentPermissionManager\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class DesTestController extends Controller
{
    public function index(): JsonResponse
    {
        abort_unless(PermissionService::can('destest.viewany'), 403);

        return response()->json(DesTest::all());
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(PermissionService::can('destest.create'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        return response()->json(DesTest::create($validated), 201);
    }

    public function destroy(DesTest $desTest): Response
    {
        abort_unless(PermissionService::can('destest.delete'), 403);

        $desTest->delete();

        return response()->noContent();
    }

    public function publish(DesTest $desTest): JsonResponse
    {
        abort_unless(PermissionService::can('destest.publish'), 403);

        $desTest->update(['published' => true]);

        return response()->json($desTest);
    }
}
