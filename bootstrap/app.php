<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*')
        );

        $exceptions->render(function (\Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null; // laisser le handler par défaut gérer
            }

            $status = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;

            // Codes machine lisibles
            $errorCode = match ($status) {
                401     => 'UNAUTHENTICATED',
                403     => 'FORBIDDEN',
                404     => 'NOT_FOUND',
                422     => 'VALIDATION_ERROR',
                429     => 'TOO_MANY_REQUESTS',
                default => 'SERVER_ERROR',
            };

            $payload = [
                'status'  => $status,
                'error'   => $errorCode,
                'message' => $e->getMessage() ?: \Symfony\Component\HttpFoundation\Response::$statusTexts[$status] ?? 'Error',
            ];

            // Ajouter le détail des erreurs de validation
            if ($e instanceof \Illuminate\Validation\ValidationException) {
                $payload['message'] = 'The given data was invalid.';
                $payload['errors']  = $e->errors();
            }

            // 404 model binding → message propre
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                $payload['message'] = 'Resource not found.';
            }

            return response()->json($payload, $status);
        });
    })->create();
