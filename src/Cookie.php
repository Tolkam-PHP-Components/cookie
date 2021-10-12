<?php declare(strict_types=1);

namespace Tolkam\Cookie;

use DateTime;
use DateTimeInterface;
use InvalidArgumentException;

class Cookie
{
    public const SAME_SITE_NONE   = 'None';
    public const SAME_SITE_LAX    = 'Lax';
    public const SAME_SITE_STRICT = 'Strict';
    
    private const KNOWN_SAME_SITE = [
        self::SAME_SITE_NONE,
        self::SAME_SITE_LAX,
        self::SAME_SITE_STRICT,
    ];
    
    /**
     * @var string
     */
    protected string $name;
    
    /**
     * @var string|null
     */
    protected ?string $value;
    
    /**
     * @var string|null
     */
    protected ?string $domain;
    
    /**
     * @var int|null
     */
    protected ?int $maxAge;
    
    /**
     * @var string|null
     */
    protected ?string $path;
    
    /**
     * @var bool|null
     */
    protected ?bool $secure;
    
    /**
     * @var bool
     */
    protected ?bool $httpOnly;
    
    /**
     * @var string|null
     */
    protected ?string $sameSite;
    
    /**
     * @param string      $name
     * @param string|null $value
     * @param string|null $domain
     * @param string|null $path
     * @param int|null    $maxAge
     * @param bool        $secure
     * @param bool        $httpOnly
     * @param string|null $sameSite
     */
    private function __construct(
        string $name,
        ?string $value,
        ?string $domain,
        ?string $path,
        ?int $maxAge,
        ?bool $secure,
        ?bool $httpOnly,
        ?string $sameSite
    ) {
        if (empty($name)) {
            throw new InvalidArgumentException('The cookie name cannot be empty');
        }
        
        $this->name = $name;
        $this->value = $value;
        $this->domain = $domain;
        $this->path = $path;
        $this->maxAge = $maxAge;
        $this->secure = $secure;
        $this->httpOnly = $httpOnly;
        $this->sameSite = $sameSite;
    }
    
    /**
     * @param string $name
     * @param array  $options
     *
     * @return self
     */
    public static function create(string $name, array $options): self
    {
        $options = array_replace([
            'value' => null,
            'domain' => null,
            'path' => '/',
            'maxAge' => null,
            'secure' => true,
            'httpOnly' => true,
            'sameSite' => self::SAME_SITE_LAX,
        ], $options);
        
        return new self(
            $name,
            $options['value'],
            $options['domain'],
            $options['path'],
            $options['maxAge'],
            $options['secure'],
            $options['httpOnly'],
            $options['sameSite']
        );
    }
    
    /**
     * Creates instance from raw string
     *
     * @param string $cookie
     *
     * @return static
     */
    public static function fromString(string $cookie): self
    {
        parse_str(strtr($cookie, ['&' => '%26', '+' => '%2B', ';' => '&']), $parsed);
        
        $name = key($parsed);
        $value = $parsed[$name];
        unset($parsed[$name]);
        
        $booleans = ['secure', 'httponly'];
        foreach ($parsed as $k => $v) {
            unset($parsed[$k]);
            $k = strtolower($k);
            
            if (in_array($k, $booleans, true)) {
                $v = true;
            }
            
            if ($k === 'samesite') {
                $v = ucfirst(strtolower($v));
            }
            
            $parsed[$k] = $v;
        }
        
        // max-age takes precedence
        if (isset($parsed['max-age'])) {
            $parsed['max-age'] = (int) $parsed['max-age'];
        }
        elseif (isset($parsed['expires'])) {
            $parsed['max-age'] = self::fromRFC7231($parsed['expires']) - time();
        }
        
        return static::create($name, [
            'value' => $value,
            'domain' => $parsed['domain'] ?? null,
            'path' => $parsed['path'] ?? null,
            'maxAge' => $parsed['max-age'] ?? null,
            'secure' => $parsed['secure'] ?? null,
            'httpOnly' => $parsed['httponly'] ?? null,
            'sameSite' => $parsed['samesite'] ?? null,
        ]);
    }
    
    /**
     * @param string|null $value
     *
     * @return static
     */
    public function withValue(?string $value): self
    {
        $cookie = clone $this;
        $cookie->value = $value;
        
        return $cookie;
    }
    
    /**
     * @param string|null $domain
     *
     * @return static
     */
    public function withDomain(?string $domain): self
    {
        $cookie = clone $this;
        $cookie->domain = $domain;
        
        return $cookie;
    }
    
    /**
     * @param int|null $maxAge
     *
     * @return static
     */
    public function withMaxAge(?int $maxAge): self
    {
        $cookie = clone $this;
        $cookie->maxAge = $maxAge > 0 ? $maxAge : 0;
        
        return $cookie;
    }
    
    /**
     * @param string $path
     *
     * @return static
     */
    public function withPath(string $path): self
    {
        $cookie = clone $this;
        $cookie->path = '' === $path ? '/' : $path;
        
        return $cookie;
    }
    
    /**
     * @param bool $secure
     *
     * @return static
     */
    public function withSecure(bool $secure = true): self
    {
        $cookie = clone $this;
        $cookie->secure = $secure;
        
        return $cookie;
    }
    
    /**
     * @param bool $httpOnly
     *
     * @return static
     */
    public function withHttpOnly(bool $httpOnly = true): self
    {
        $cookie = clone $this;
        $cookie->httpOnly = $httpOnly;
        
        return $cookie;
    }
    
    /**
     * @param string|null $sameSite
     *
     * @return static
     */
    public function withSameSite(?string $sameSite): self
    {
        if ($sameSite !== null && !in_array($sameSite, self::KNOWN_SAME_SITE, true)) {
            throw new InvalidArgumentException(
                'The "SameSite" parameter value is not valid'
            );
        }
        
        $cookie = clone $this;
        $cookie->sameSite = $sameSite;
        
        return $cookie;
    }
    
    /**
     * Returns the cookie as a string
     *
     * @return string
     */
    public function __toString()
    {
        $cookie = [
            $this->getName() => urlencode($this->getValue() ?? ''),
        ];
        
        if ($domain = $this->getDomain()) {
            $cookie['Domain'] = $domain;
        }
        
        if ($path = $this->getPath()) {
            $cookie['Path'] = $path;
        }
        
        $maxAge = $this->getMaxAge();
        if ($maxAge !== null) {
            $cookie['Max-Age'] = $maxAge;
        }
        
        if ($this->isSecure()) {
            $cookie['Secure'] = true;
        }
        
        if ($this->isHttpOnly()) {
            $cookie['HttpOnly'] = true;
        }
        
        if ($sameSite = $this->getSameSite()) {
            $cookie['SameSite'] = $sameSite;
        }
        
        foreach ($cookie as $k => $v) {
            unset($cookie[$k]);
            $cookie[$k] = $k . '=' . $v;
        }
        
        return implode('; ', $cookie);
    }
    
    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }
    
    /**
     * @return string|null
     */
    public function getValue(): ?string
    {
        return $this->value;
    }
    
    /**
     * @return string|null
     */
    public function getDomain(): ?string
    {
        return $this->domain;
    }
    
    /**
     * @return int|null
     */
    public function getMaxAge(): ?int
    {
        return $this->maxAge;
    }
    
    /**
     * @return string|null
     */
    public function getPath(): ?string
    {
        return $this->path;
    }
    
    /**
     * @return bool
     */
    public function isSecure(): bool
    {
        return !!$this->secure;
    }
    
    /**
     * @return bool
     */
    public function isHttpOnly(): bool
    {
        return !!$this->httpOnly;
    }
    
    /**
     * @return string|null
     */
    public function getSameSite(): ?string
    {
        return $this->sameSite;
    }
    
    /**
     * Converts RFC7231 time to timestamp
     *
     * @param string $time
     *
     * @return int
     */
    private static function fromRFC7231(string $time): int
    {
        $time = str_replace('-', '', $time);
        
        return DateTime::createFromFormat(DateTimeInterface::RFC7231, $time)
            ->getTimestamp();
    }
}
