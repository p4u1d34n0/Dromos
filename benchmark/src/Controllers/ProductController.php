<?php

namespace Benchmark\Controllers;

use Dromos\Http\Request;
use Dromos\Http\Response;
use Dromos\Validation\Validator;

/**
 * In-memory product CRUD controller for benchmarking
 *
 * Stores product data in a static array that persists across requests in
 * the OpenSwoole long-running process. Pre-seeds 200 products on boot.
 */
class ProductController
{
    private const CATEGORIES = ['electronics', 'clothing', 'food', 'books', 'other'];

    private static array $products = [];
    private static int $nextId = 1;
    private static bool $booted = false;

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        self::$booted = true;

        $categoryCount = count(self::CATEGORIES);

        for ($i = 1; $i <= 200; $i++) {
            self::$products[$i] = [
                'id' => $i,
                'name' => "Product {$i}",
                'price' => round(fmod($i * 9.99, 999.99), 2),
                'category' => self::CATEGORIES[($i - 1) % $categoryCount],
                'created_at' => date('Y-m-d H:i:s'),
            ];
        }

        self::$nextId = 201;
    }

    public function index(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();
        $page = max(1, (int) ($query['page'] ?? 1));
        $limit = min(100, max(1, (int) ($query['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $items = array_slice(array_values(self::$products), $offset, $limit);

        return $response->json([
            'data' => $items,
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => count(self::$products),
            ],
        ]);
    }

    public function show(Request $request, Response $response): Response
    {
        $id = (int) $request->getAttribute('id');

        if (!isset(self::$products[$id])) {
            return $response->json(['error' => 'Not Found', 'status' => 404], 404);
        }

        return $response->json(['data' => self::$products[$id]]);
    }

    public function store(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];

        $validator = new Validator($data, [
            'name' => 'required|string|min:2|max:200',
            'price' => 'required|numeric|min:0',
            'category' => 'required|in:electronics,clothing,food,books,other',
        ]);

        if ($validator->fails()) {
            return $response->json(['errors' => $validator->errors()], 422);
        }

        $clean = $validator->validated();
        $id = self::$nextId++;
        self::$products[$id] = [
            'id' => $id,
            'name' => $clean['name'],
            'price' => (float) $clean['price'],
            'category' => $clean['category'],
            'created_at' => date('Y-m-d H:i:s'),
        ];

        return $response->created(['data' => self::$products[$id]]);
    }

    public function update(Request $request, Response $response): Response
    {
        $id = (int) $request->getAttribute('id');

        if (!isset(self::$products[$id])) {
            return $response->json(['error' => 'Not Found', 'status' => 404], 404);
        }

        $data = $request->getParsedBody() ?? [];

        $validator = new Validator($data, [
            'name' => 'required|string|min:2|max:200',
            'price' => 'required|numeric|min:0',
            'category' => 'required|in:electronics,clothing,food,books,other',
        ]);

        if ($validator->fails()) {
            return $response->json(['errors' => $validator->errors()], 422);
        }

        $clean = $validator->validated();
        self::$products[$id]['name'] = $clean['name'];
        self::$products[$id]['price'] = (float) $clean['price'];
        self::$products[$id]['category'] = $clean['category'];

        return $response->json(['data' => self::$products[$id]]);
    }

    public function destroy(Request $request, Response $response): Response
    {
        $id = (int) $request->getAttribute('id');

        if (!isset(self::$products[$id])) {
            return $response->json(['error' => 'Not Found', 'status' => 404], 404);
        }

        unset(self::$products[$id]);

        return $response->noContent();
    }
}
