<?php

declare(strict_types=1);

namespace Bench;

/**
 * The routes every contender registers: per resource, an index, a show, a
 * nested item and a store route, in that order, all served by controllers.
 *
 * Handlers return JSON: {"ok":true} for index and store, {"id":..} for show
 * and {"id":..,"item":..} for item.
 */
final class RouteSet
{
    public const ACTIONS = ['index', 'show', 'item', 'store'];

    public function __construct(public readonly int $resources)
    {
    }

    /**
     * @return \Generator<int, array{0:string,1:string,2:string}> [method, path with {placeholders}, action]
     */
    public function routes(): \Generator
    {
        for ($i = 0; $i < $this->resources; $i++) {
            yield ['GET', "/res$i", 'index'];
            yield ['GET', "/res$i/{id}", 'show'];
            yield ['GET', "/res$i/{id}/items/{item}", 'item'];
            yield ['POST', "/res$i", 'store'];
        }
    }

    public function count(): int
    {
        return $this->resources * 4;
    }

    /**
     * The requests timed for this set, with the body each must return
     * (null: any body, the status alone is checked).
     *
     * @return array<string, array{0:string,1:string,2:int,3:?string}> name => [method, path, status, body]
     */
    public function requests(): array
    {
        $last = $this->resources - 1;
        return [
            'static, first'  => ['GET', '/res0', 200, '{"ok":true}'],
            'static, last'   => ['GET', "/res$last", 200, '{"ok":true}'],
            'param, last'    => ['GET', "/res$last/42", 200, '{"id":"42"}'],
            '2 params, last' => ['GET', "/res$last/42/items/7", 200, '{"id":"42","item":"7"}'],
            'POST, last'     => ['POST', "/res$last", 200, '{"ok":true}'],
            '404'            => ['GET', '/nope/nope/nope', 404, null],
        ];
    }

    /**
     * Rewrite "{name}" placeholders in a path, e.g. to Flight's "@name".
     *
     * @param callable(string): string $placeholder Receives the name
     */
    public static function rewrite(string $path, callable $placeholder): string
    {
        return (string) preg_replace_callback('/\{(\w+)\}/', fn(array $m) => $placeholder($m[1]), $path);
    }
}
