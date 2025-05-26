<?php

namespace App\Exceptions;

use Exception;

class ERetailException extends Exception
{
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Renderizar la excepción para respuestas HTTP
     */
    public function render($request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => true,
                'message' => $this->getMessage()
            ], 500);
        }

        return redirect()->back()
            ->with('error', 'Error de comunicación con eRetail: ' . $this->getMessage())
            ->withInput();
    }
}