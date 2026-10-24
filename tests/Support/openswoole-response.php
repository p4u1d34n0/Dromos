<?php

declare(strict_types=1);

namespace OpenSwoole\Http;

// A stand-in for OpenSwoole's response when the extension isn't loaded. It
// records calls, and like the real one, header() replaces an earlier value
// for the same name.
if (!class_exists(Response::class, false)) {
    final class Response
    {
        public ?int $status = null;
        public ?string $reason = null;

        /** @var array<string, string|list<string>> */
        public array $headers = [];

        /** @var list<string> */
        public array $writes = [];

        /** @var list<string|null> */
        public array $ends = [];

        public function status(int $statusCode, string $reason = ''): bool
        {
            $this->status = $statusCode;
            $this->reason = $reason;
            return true;
        }

        /** @param string|list<string> $value */
        public function header(string $key, string|array $value, bool $format = true): bool
        {
            $this->headers[$key] = $value;
            return true;
        }

        public function write(string $data): bool
        {
            $this->writes[] = $data;
            return true;
        }

        public function end(?string $data = null): bool
        {
            $this->ends[] = $data;
            return true;
        }
    }
}
