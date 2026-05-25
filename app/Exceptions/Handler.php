<?php

namespace App\Exceptions;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use PDOException;
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

    public function render($request, Throwable $e)
    {
        if ($this->isDatabaseConnectionError($e)) {
            $this->clearSessionWithoutDatabase($request);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Database is temporarily unavailable. Please try again in a moment.',
                ], 503);
            }

            return redirect()
                ->to('/login')
                ->withErrors([
                    'email' => 'MySQL is not running or .env database settings are wrong. Restart MySQL on the server, then try again.',
                ]);
        }

        return parent::render($request, $e);
    }

    protected function isDatabaseConnectionError(Throwable $e): bool
    {
        if ($e instanceof QueryException || $e instanceof PDOException) {
            $message = $e->getMessage();

            return str_contains($message, 'Connection refused')
                || str_contains($message, '[2002]')
                || str_contains($message, 'No connection could be made')
                || str_contains($message, 'server has gone away');
        }

        return false;
    }

    protected function clearSessionWithoutDatabase(Request $request): void
    {
        if (! $request->hasSession()) {
            return;
        }

        try {
            $request->session()->flush();
            $request->session()->regenerate(true);
        } catch (Throwable $ignored) {
            //
        }
    }
}
