<?php

namespace TrivoLink\UnifiedApi;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;

class Envelope implements Responsable
{
    public function __construct(
        public readonly mixed $data = null,
        public readonly ?string $message = null,
        public readonly array $meta = [],
        public readonly ?array $errors = null,
        public readonly int $status = 200,
    ) {}

    /**
     * Envelope for a successful page/data response.
     */
    public static function success(mixed $data, array $meta = [], ?string $message = null): self
    {
        return new self(data: $data, meta: $meta, message: $message);
    }

    /**
     * Envelope replacing a redirect response for unified clients, so
     * native HTTP clients never silently follow redirects as GETs.
     */
    public static function redirect(string $url, ?string $message = null): self
    {
        return new self(
            message: $message,
            meta: ['redirect' => $url],
            status: (int) config('unified-api.redirect_status', 200),
        );
    }

    /**
     * Envelope for a rendered error response, preserving its status.
     */
    public static function error(string $message, ?array $errors = null, int $status = 400): self
    {
        return new self(message: $message, errors: $errors, status: $status);
    }

    public function toResponse($request): JsonResponse
    {
        $payload = [
            'data' => $this->data,
            // meta must always decode as an object; a plain empty array
            // would encode as [] and break clients that parse it as a map.
            'meta' => $this->meta === [] ? new \stdClass : $this->meta,
            'message' => $this->message,
            'version' => (string) config('unified-api.version', 'v1'),
        ];

        if ($this->errors !== null) {
            $payload['errors'] = $this->errors;
        }

        return new JsonResponse($payload, $this->status);
    }
}
