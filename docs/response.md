```php
$response = new Dromos\Http\Response();
$response->json(['success' => true], 200);
```

```php
$response = new Dromos\Http\Response();
$response->send('<h1>Hello Peoples ;P</h1>', 200);
```

```php
$response = new Dromos\Http\Response();
$response->sendStatus(404); // Outputs 'Not Found'
```