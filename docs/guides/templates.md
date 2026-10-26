# HTML templates and HTMX with Dromos

Dromos doesn't come with a template engine, so you can use any of them. A controller renders a template to a string and returns it with `$response->html()`, which sets `Content-Type: text/html`.

The examples were tested with Twig 3.30, Latte 3.1 and PHP-DI 7.1 on PHP 8.4.

## Choosing an engine

| Engine | Why people pick it | Install |
|---|---|---|
| [Twig](https://twig.symfony.com/) | The most widely used standalone engine, and Symfony's. Template inheritance, escaping on by default, good editor support, and fast once compiled. | `composer require twig/twig` |
| [Latte](https://latte.nette.org/) | Escapes by context: it knows whether a value lands in HTML, an attribute, JavaScript or a URL. `n:` attributes keep loops and conditions on the tags they control. | `composer require latte/latte` |
| [Blade](https://laravel.com/docs/blade) | Familiar if your team knows Laravel. Usable on its own through `illuminate/view`, but that pulls in several Laravel packages, so it adds more weight than the others. | `composer require illuminate/view` |

Twig:

```twig
{% extends "layout.html.twig" %}

{% block content %}
  <h1>Books</h1>
  <ul>
    {% for book in books %}
      <li>{{ book.title }}</li>
    {% else %}
      <li>No books yet.</li>
    {% endfor %}
  </ul>
{% endblock %}
```

Latte:

```latte
<ul n:if="$books">
  <li n:foreach="$books as $book" title="{$book->title}">{$book->title}</li>
</ul>
```

## Wiring in Twig

Create the Twig environment once, with a cache directory so templates compile only once:

```php
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

$twig = new Environment(new FilesystemLoader(__DIR__ . '/templates'), [
    'cache' => __DIR__ . '/var/cache/twig',
]);
```

The controller asks for it in its constructor:

```php
use App\Repository\BookRepository;
use Dromos\Http\Response;
use Twig\Environment;

final class BookController
{
    public function __construct(private Environment $twig, private BookRepository $books) {}

    public function index(Response $response): Response
    {
        $books = $this->books->all();

        return $response->html($this->twig->render('books.html.twig', ['books' => $books]));
    }
}
```

Controllers get constructor arguments through a controller resolver, as described in [Using Doctrine with Dromos](doctrine.md#give-controllers-the-entitymanager). With PHP-DI it's one line:

```php
$container = new DI\Container([Environment::class => $twig]);

Router::setControllerResolver(new ContainerControllerResolver($container));
```

Latte works the same way: create one `Latte\Engine`, give it a temp directory with `setTempDirectory()`, and return `$latte->renderToString($file, $params)`.

Under OpenSwoole, create the engine once per worker and reuse it. Unlike Doctrine's `EntityManager`, a template engine keeps no state between requests, so sharing it is safe.

## HTMX

[HTMX](https://htmx.org/) lets HTML attributes fetch a fragment from the server and swap it into the page. You get pages that update in place without a JavaScript framework or a build step, and the server stays in charge of the HTML.

An HTMX page makes many small requests, one for each update, and that's the kind of traffic Dromos is fastest at.

This search box asks the server for a new list as you type:

```twig
{% extends "layout.html.twig" %}

{% block content %}
  <h1>Books</h1>
  <input type="search" name="q" hx-get="/shelf" hx-trigger="input changed delay:300ms" hx-target="#books">
  <div id="books">
    {% include "_book-list.html.twig" %}
  </div>
{% endblock %}
```

HTMX sends an `HX-Request: true` header with every request it makes, so one controller method can return the whole page on first load and only the list after that:

```php
use Dromos\Http\Request;
use Dromos\Http\Response;

public function index(Request $request, Response $response): Response
{
    $books = $this->books->search($request->getQueryParams()['q'] ?? '');

    $template = $request->getHeaderLine('HX-Request') === 'true'
        ? '_book-list.html.twig'
        : 'shelf.html.twig';

    return $response->html($this->twig->render($template, ['books' => $books]));
}
```

For small things that need no server at all, such as menus and toggles, [Alpine.js](https://alpinejs.dev/) pairs well with HTMX.

If you want a well-documented default, use Twig with HTMX. If you want the strongest escaping, use Latte with HTMX.
