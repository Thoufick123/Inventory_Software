<?php
/**
 * Request Helper
 * Provides utility methods to inspect HTTP requests.
 */

class Request {
    /**
     * Check if the current request was initiated via AJAX / Fetch.
     */
    public static function isAjax(): bool {
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json'));
    }

    /**
     * Get the HTTP request method.
     */
    public static function method(): string {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * Check if request method is POST.
     */
    public static function isPost(): bool {
        return self::method() === 'POST';
    }

    /**
     * Check if request method is GET.
     */
    public static function isGet(): bool {
        return self::method() === 'GET';
    }
}
