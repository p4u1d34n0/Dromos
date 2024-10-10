```php
$response = new Dromos\HTTP\Response();
$response->json(['success' => true], 200);
```

```php
$response = new Dromos\HTTP\Response();
$response->send('<h1>Hello World</h1>', 200);
```

```php
$response = new Dromos\HTTP\Response();
$response->sendStatus(404); // Outputs 'Not Found'
```