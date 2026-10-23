<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: fn () => require __DIR__.'/../routes/webhooks.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustHosts(at: fn () => ['^'.preg_quote((string) parse_url(config('app.url'), PHP_URL_HOST), '/').'$'], subdomains: false);
        $middleware->web(append: [
            AuthenticateSession::class,
            SecurityHeaders::class,
            HandleInertiaRequests::class,
        ]);
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()?->role === 'admin' ? '/admin' : '/dashboard');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['token', 'quote_token', 'selection_password', 'delivery_password', 'gallery_password', 'selection_source', 'delivery_source']);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(function (ValidationException $exception, Request $request) {
            // Inertia navigation and background reads do not provide a reliable
            // previous form URL under our no-referrer policy.
            $target = match ($request->route()?->getName()) {
                'projects.store' => route('projects.create'),
                'projects.update', 'projects.command' => route('projects.show', $request->route('project')),
                default => null,
            };
            if ($request->isMethod('POST') && in_array($request->path(), ['login', 'register', 'forgot-password'], true)) {
                $target = url('/'.$request->path());
            }
            if ($target !== null) {
                $exception->redirectTo($target);
            }

            return null;
        });
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            $status = $response->getStatusCode();
            if (! in_array($status, [403, 404, 419, 429, 500, 503], true) || $request->expectsJson()
                || ! $request->acceptsHtml() || $request->is('g/*/media/*', 'g/*/preview/*', 'g/*/archive', 'g/*/archive/*')) {
                return $response;
            }

            // Only safe status codes enter the page. Exception text and resource details stay private.
            return app(SecurityHeaders::class)->handle($request, function () use ($request, $response, $status) {
                $page = Inertia::render('public/error', ['statusCode' => $status, 'clientGallery' => $request->is('g/*')])
                    ->toResponse($request)->setStatusCode($status);
                foreach (['Retry-After', 'Allow'] as $header) {
                    if ($response->headers->has($header)) {
                        $page->headers->set($header, $response->headers->get($header));
                    }
                }

                return $page;
            });
        });
    })->create();
