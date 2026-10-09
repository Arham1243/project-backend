<?php

use App\Modules\Administration\Http\Middleware\AuthAnyGuardMiddleware;
use App\Modules\Administration\Http\Middleware\CheckIfUserIsActive;
use App\Modules\Administration\Http\Middleware\RolePermissionMiddleware;
use App\Support\ScheduledJobRegistrar;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens(except: [
            'broadcasting/auth',
        ]);
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('api/*', 'broadcasting/auth')) {
                return null;
            }

            $url = config('app.frontend_url');

            return is_string($url) && $url !== '' ? $url : '/';
        });
        $middleware->alias([
            'auth.any' => AuthAnyGuardMiddleware::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'role.permission' => RolePermissionMiddleware::class,
            'check.active' => CheckIfUserIsActive::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        app(ScheduledJobRegistrar::class)->register($schedule);
    })
    ->withEvents(discover: [
        __DIR__.'/../app/Listeners',
        __DIR__.'/../app/Modules/Reports/Listeners',
    ])
    ->withExceptions(function (Exceptions $exceptions) {
        $wantsJsonApi = static fn (Request $request): bool => $request->is('api/*', 'broadcasting/auth');

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e) => $wantsJsonApi($request)
        );

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($wantsJsonApi) {
            if ($wantsJsonApi($request)) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            return null;
        });

        $exceptions->render(function (ValidationException $e, Request $request) use ($wantsJsonApi) {
            if ($wantsJsonApi($request)) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            }

            return null;
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($wantsJsonApi) {
            if (! $wantsJsonApi($request)) {
                return null;
            }

            $status = $e->getStatusCode();
            $message = $e->getMessage();

            if ($status === 404 && ! config('app.debug')) {
                $message = 'Not Found';
            } elseif ($status >= 500 && ! config('app.debug')) {
                $message = 'Server Error';
            }

            if ($message === '') {
                $message = Response::$statusTexts[$status] ?? 'Error';
            }

            return response()->json([
                'message' => $message,
            ], $status, $e->getHeaders());
        });

        $exceptions->render(function (Throwable $e, Request $request) use ($wantsJsonApi) {
            if (! $wantsJsonApi($request)) {
                return null;
            }

            if ($e instanceof AuthenticationException
                || $e instanceof ValidationException
                || $e instanceof HttpExceptionInterface) {
                return null;
            }

            $message = config('app.debug') ? $e->getMessage() : 'Server Error';

            return response()->json([
                'message' => $message,
            ], 500);
        });

        $exceptions->respond(function (Response $response, Throwable $e, Request $request) use ($wantsJsonApi) {
            if (! $wantsJsonApi($request)) {
                return $response;
            }

            return app(HandleCors::class)->handle(
                $request,
                static fn () => $response
            );
        });
    })
    ->create();
