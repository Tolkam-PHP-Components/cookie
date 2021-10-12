# tolkam/cookie

Cookies manipulation helper.

## Documentation

The code is rather self-explanatory and API is intended to be as simple as possible. Please, read the sources/Docblock if you have any questions. See [Usage](#usage) for quick start.

## Usage

````php
use Tolkam\Cookie\Cookie;

$cookie = Cookie::create('my_cookie', [
    'value' => 'myValue',
    'domain' => null,
    'path' => '/',
    'maxAge' => 86400,
    'secure' => true,
    'httpOnly' => true,
    'sameSite' => Cookie::SAME_SITE_LAX,
]);

echo $cookie . "\n";
echo Cookie::fromString((string) $cookie)->getValue();
````

## License

Proprietary / Unlicensed 🤷
