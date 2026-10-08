<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Bentuk respons yang sudah diasumsikan sipenamas_v2_frontend/src/services/api/apiClient.js:
 * sukses -> { success, data, message }; gagal -> dibaca sebagai data.message + data.errors.
 */
class ApiResponse
{
    public static function success(mixed $data = null, ?string $message = null, int $status = 200): JsonResponse
    {
        return response()->json(array_filter([
            'success' => true,
            'data' => $data,
            'message' => $message,
        ], fn ($value, $key) => $key !== 'message' || $value !== null, ARRAY_FILTER_USE_BOTH), $status);
    }

    public static function error(string $message, int $status = 400, ?array $errors = null): JsonResponse
    {
        return response()->json(array_filter([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], fn ($value) => $value !== null), $status);
    }
}
