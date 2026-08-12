<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $exception)
    {
        if ($request->is('api/*')) {

            // Default
            $statusCode = 500;
            $message    = 'Server Error';

            // HTTP exception (404, 403, 401, dll)
            if ($exception instanceof HttpExceptionInterface) {
                $statusCode = $exception->getStatusCode();
                $message    = $exception->getMessage() ?: 'HTTP Error';
            }

            // Validation error (422)
            if ($exception instanceof ValidationException) {
                return response()->json([
                    'code'    => 422,
                    'message' => 'Validation error',
                    'errors'  => $exception->errors(),
                ], 422);
            }

            return response()->json([
                'code'    => $statusCode,
                'message' => $message,
                'line'    => config('app.debug') ? $exception->getLine() : null,
                'trace'   => config('app.debug') ? $exception->getTrace() : null,
            ], $statusCode);
        }

        return parent::render($request, $exception);
    }
}
