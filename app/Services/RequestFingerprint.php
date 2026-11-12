<?php

namespace App\Services;

use JsonException;

final class RequestFingerprint
{
    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws JsonException
     */
    public function make(string $method, string $path, array $payload): string
    {
        $canonical = $this->canonicalize($payload);
        $body = json_encode($canonical, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);

        return hash('sha256', strtoupper($method)."\n".$path."\n".$body);
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
