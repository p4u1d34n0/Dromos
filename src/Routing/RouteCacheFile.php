<?php

declare(strict_types=1);

namespace Dromos\Routing;

use Closure;
use Throwable;

/**
 * Stores compiled routes in a PHP file that returns an array, so opcache can
 * keep it in shared memory and loading it costs almost nothing.
 *
 * - Writes are atomic (temporary file + rename), so a request never reads a
 *   half-written file.
 * - A missing, corrupt, outdated or legacy (JSON) file is treated as a miss
 *   and rewritten. Cache problems never fail a request: a failed write is
 *   logged and the routes are compiled as normal.
 *
 * The file is executed with `require`, so keep it in a directory that only
 * the application can write to.
 */
final class RouteCacheFile
{
    /** Bump when the stored format changes, so older files are rebuilt. */
    private const FORMAT_VERSION = 3;

    public function __construct(private readonly string $path)
    {
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * The compiled table for these routes: from the cache file if it was
     * built from the same routes, otherwise compiled now and saved.
     *
     * @param array<string, array<string, Closure|array{0:class-string,1:string}>> $routes Handlers by method, then route URL
     * @param Closure(array<string, array<string, mixed>>): CompiledRoutes          $compile
     */
    public function remember(array $routes, Closure $compile): CompiledRoutes
    {
        $signature = CachedRoutes::signatureOf($routes);
        $current = $this->current($signature);
        if ($current !== null) {
            return $current[0];
        }

        $table = $compile($routes);
        $this->save(new CachedRoutes($signature, $table, CachedRoutes::handlersOf($routes)));
        return $table;
    }

    /**
     * The cached entry, or null if there is no usable cache file.
     */
    public function load(): ?CachedRoutes
    {
        $data = $this->read(false);
        if ($data === null) {
            return null;
        }
        return new CachedRoutes($data['signature'], CompiledRoutes::fromArray($data['table']), $data['handlers'], $data['middleware']);
    }

    /**
     * The compiled table, handlers and middleware stored for routes with this
     * signature, or null if the file holds no usable entry for them. Given
     * null, for a cache pinned by opcache (see isPinnedByOpcache()), it
     * returns whatever routes the file holds.
     *
     * Unlike load(), this builds no CachedRoutes: Router::loadRoutes() calls
     * it on every request, and the object would cost more than reading the
     * file from opcache.
     *
     * @return array{0: CompiledRoutes, 1: array<string, array<string, array{0:class-string,1:string}>>, 2: array<string, list<class-string>>}|null
     */
    public function current(?string $signature): ?array
    {
        $data = $this->read($signature === null);
        if ($data === null || ($signature !== null && $data['signature'] !== $signature)) {
            return null;
        }
        return [CompiledRoutes::fromArray($data['table']), $data['handlers'], $data['middleware']];
    }

    /**
     * The file's data, or null if it's missing, corrupt, outdated or a
     * legacy (JSON) file.
     *
     * Only save() writes a file with this format version, and it writes every
     * entry at once (see save()), so a file with the version and a signature
     * holds the rest; checking each entry would cost more than reading it.
     *
     * @param bool $pinned The file is known to be in opcache, so it's one of ours
     *
     * @return array{signature: string, table: array<string, array{exact: array<string, int>, prefixes: array<string, string[]>, parameterised: array<int, array{0:string,1:string[],2:int}>}>, handlers: array<string, array<string, array{0:class-string,1:string}>>, middleware: array<string, list<class-string>>}|null
     */
    private function read(bool $pinned): ?array
    {
        if (!$pinned && !$this->isPhpFile()) {
            return null;
        }

        try {
            $data = require $this->path;
        } catch (Throwable) {
            return null; // e.g. a corrupt file; it will be rewritten
        }

        if (!is_array($data) || ($data['version'] ?? null) !== self::FORMAT_VERSION || !is_string($data['signature'] ?? null)) {
            return null;
        }

        /** @var array{signature: string, table: array<string, array{exact: array<string, int>, prefixes: array<string, string[]>, parameterised: array<int, array{0:string,1:string[],2:int}>}>, handlers: array<string, array<string, array{0:class-string,1:string}>>, middleware: array<string, list<class-string>>} $data */
        return $data;
    }

    /**
     * Write the entry atomically. Returns false (and logs why) if it couldn't.
     */
    public function save(CachedRoutes $routes): bool
    {
        $payload = [
            'version'   => self::FORMAT_VERSION,
            'signature' => $routes->signature,
            'table'     => $routes->table->toArray(),
            'handlers'  => $routes->handlers,
            'middleware' => $routes->middleware,
        ];
        $code = "<?php\n\n// Route cache generated by Dromos. Do not edit: delete it to rebuild.\n\nreturn "
            . var_export($payload, true) . ";\n";

        $temporary = $this->path . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (@file_put_contents($temporary, $code, LOCK_EX) === false) {
            return $this->failed('could not write ' . $temporary);
        }
        @chmod($temporary, 0644);
        if (!@rename($temporary, $this->path)) {
            @unlink($temporary);
            return $this->failed('could not replace ' . $this->path);
        }

        $this->invalidateOpcache();
        return true;
    }

    /**
     * True if opcache holds this file and the routes file it was built from,
     * as current: PHP would run the routes file as it was when this cache was
     * built, so it needn't be checked.
     *
     * opcache decides what's current as it does for all code. With
     * opcache.validate_timestamps on, it checks a file's timestamp at most
     * every opcache.revalidate_freq seconds; with it off, as in most
     * production setups, a file stays current until it's invalidated or
     * opcache is reset. Either way, Dromos sees a changed routes file when
     * PHP would see changed code.
     *
     * It also means the file was read before, so it's one of ours (see
     * isPhpFile()).
     */
    public function isPinnedByOpcache(string $routesFile): bool
    {
        return function_exists('opcache_is_script_cached')
            && @opcache_is_script_cached($this->path)
            && @opcache_is_script_cached($routesFile);
    }

    /**
     * Compile the routes file into opcache, without running it, so the next
     * requests find this cache pinned (see isPinnedByOpcache()). For after
     * the routes file was checked and this cache found current: a cache hit
     * doesn't run the routes file, so opcache wouldn't otherwise hold it.
     */
    public function pinWith(string $routesFile): void
    {
        if (
            function_exists('opcache_compile_file')
            && @opcache_is_script_cached($this->path)
            && !@opcache_is_script_cached($routesFile)
        ) {
            @opcache_compile_file($routesFile);
        }
    }

    public function clear(): void
    {
        if (is_file($this->path)) {
            @unlink($this->path);
        }
        $this->invalidateOpcache();
    }

    /**
     * True if the file exists and starts with "<?php". Requiring anything
     * else, such as a cache file from Dromos 1.1 (JSON), would print it.
     */
    private function isPhpFile(): bool
    {
        // Only files that passed this check are ever required, so a file opcache
        // already holds is one of ours, and the file system needn't be touched
        // (save() and clear() invalidate opcache's copy)
        if (function_exists('opcache_is_script_cached') && @opcache_is_script_cached($this->path)) {
            return true;
        }
        if (!is_file($this->path)) {
            return false;
        }
        $handle = @fopen($this->path, 'rb');
        if ($handle === false) {
            return false;
        }
        $start = fread($handle, 5);
        fclose($handle);
        return $start === '<?php';
    }

    private function invalidateOpcache(): void
    {
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($this->path, true);
        }
    }

    private function failed(string $reason): bool
    {
        error_log('[Dromos] Route cache not saved: ' . $reason);
        return false;
    }
}
