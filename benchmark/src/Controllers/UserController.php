<?php

namespace Benchmark\Controllers;

use Dromos\Http\Request;
use Dromos\Http\Response;
use Dromos\Validation\Validator;

/**
 * In-memory user CRUD controller for benchmarking
 *
 * Stores user data in a static array that persists across requests in
 * the OpenSwoole long-running process. Pre-seeds 100 users on boot.
 */
class UserController
{
    private static array $users = [];
    private static int $nextId = 1;
    private static bool $booted = false;

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        self::$booted = true;

        for ($i = 1; $i <= 100; $i++) {
            self::$users[$i] = [
                'id' => $i,
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'created_at' => date('Y-m-d H:i:s'),
            ];
        }

        self::$nextId = 101;
    }

    public function index(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();
        $page = max(1, (int) ($query['page'] ?? 1));
        $limit = min(100, max(1, (int) ($query['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $items = array_slice(array_values(self::$users), $offset, $limit);

        return $response->json([
            'data' => $items,
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => count(self::$users),
            ],
        ]);
    }

    public function show(Request $request, Response $response): Response
    {
        $id = (int) $request->getAttribute('id');

        if (!isset(self::$users[$id])) {
            return $response->json(['error' => 'Not Found', 'status' => 404], 404);
        }

        return $response->json(['data' => self::$users[$id]]);
    }

    public function store(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];

        $validator = new Validator($data, [
            'name' => 'required|string|min:2|max:100',
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return $response->json(['errors' => $validator->errors()], 422);
        }

        $clean = $validator->validated();
        $id = self::$nextId++;
        self::$users[$id] = [
            'id' => $id,
            'name' => $clean['name'],
            'email' => $clean['email'],
            'created_at' => date('Y-m-d H:i:s'),
        ];

        return $response->created(['data' => self::$users[$id]]);
    }

    public function update(Request $request, Response $response): Response
    {
        $id = (int) $request->getAttribute('id');

        if (!isset(self::$users[$id])) {
            return $response->json(['error' => 'Not Found', 'status' => 404], 404);
        }

        $data = $request->getParsedBody() ?? [];

        $validator = new Validator($data, [
            'name' => 'required|string|min:2|max:100',
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return $response->json(['errors' => $validator->errors()], 422);
        }

        $clean = $validator->validated();
        self::$users[$id]['name'] = $clean['name'];
        self::$users[$id]['email'] = $clean['email'];

        return $response->json(['data' => self::$users[$id]]);
    }

    public function destroy(Request $request, Response $response): Response
    {
        $id = (int) $request->getAttribute('id');

        if (!isset(self::$users[$id])) {
            return $response->json(['error' => 'Not Found', 'status' => 404], 404);
        }

        unset(self::$users[$id]);

        return $response->noContent();
    }
}
