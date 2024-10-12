## USecase for query params

# Example with default request handling:

```php
$request = new Dromos\HTTP\Request();
echo $request->getQueryParam('name');
```

# Example with custom parameters (for testing):
```php
$customParams = [
    'query' => ['name' => 'Deano'],
    'body' => ['email' => 'deano@example.com'],
    'files' => []
];
$request = new Dromos\HTTP\Request($customParams);
echo $request->getQueryParam('name'); // Outputs 'Deano'
```