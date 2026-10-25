# Query parameters

Ask for the request by type and read the query string from it:

```php
use Dromos\Http\Request;
use Dromos\Http\Response;

// GET /search?name=Deano
Router::Get('/search', function (Request $request, Response $response) {
    $name = $request->getQueryParams()['name'] ?? null;
    return $response->json(['name' => $name]);
});
```

In tests, build a request with `Request::create()` instead of PHP's globals:

```php
$request = Request::create('GET', '/search?name=Deano', queryParams: ['name' => 'Deano']);
$request->getQueryParams()['name']; // "Deano"
```
