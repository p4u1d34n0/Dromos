# Responses

Handlers return a `Dromos\Http\Response`. Ask for one by type and the router passes in a new instance:

```php
use Dromos\Http\Response;

Router::Get('/status', fn(Response $response) => $response->json(['ok' => true]));
```

Every helper returns a new Response; the original is never changed.

```php
$response->json(['success' => true]);         // 200, application/json
$response->json(['id' => 1], 202);            // any status
$response->text('Hello', 200);                // text/plain
$response->html('<h1>Hello</h1>');            // text/html; charset=UTF-8 (escape user input yourself)
$response->created(['id' => 1]);              // 201 with a JSON body
$response->noContent();                       // 204
$response->redirect('/login');                // 302 with a Location header
$response->withStatus(404)->getReasonPhrase(); // "Not Found"
```

`json()` throws a `JsonException` if the data can't be encoded, which the router turns into a 500.
