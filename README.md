

## Installation

Install the [composer package]:

    composer require serviceinfoadn/parsedown

Or download the [latest release] and include `Parsedown.php`


## Example

```php
$Parsedown = new Parsedown();

echo $Parsedown->text('Hello _Parsedown_!'); # prints: <p>Hello <em>Parsedown</em>!</p>
```

You can also parse inline markdown only:

```php
echo $Parsedown->line('Hello _Parsedown_!'); # prints: Hello <em>Parsedown</em>!
```

More examples in [the wiki](https://github.com/erusev/parsedown/wiki/) and in [this video tutorial](http://youtu.be/wYZBY8DEikI).

## Security

Parsedown is capable of escaping user-input within the HTML that it generates. Additionally Parsedown will apply sanitisation to additional scripting vectors (such as scripting link destinations) that are introduced by the markdown syntax itself.

To tell Parsedown that it is processing untrusted user-input, use the following:

```php
$Parsedown->setSafeMode(true);
```

If instead, you wish to allow HTML within untrusted user-input, but still want output to be free from XSS it is recommended that you make use of a HTML sanitiser that allows HTML tags to be whitelisted, like [HTML Purifier](http://htmlpurifier.org/).

In both cases you should strongly consider employing defence-in-depth measures, like [deploying a Content-Security-Policy](https://scotthelme.co.uk/content-security-policy-an-introduction/) (a browser security feature) so that your page is likely to be safe even if an attacker finds a vulnerability in one of the first lines of defence above.

Safe mode does not necessarily yield safe results when using extensions to Parsedown. Extensions should be evaluated on their own to determine their specific safety against XSS.

## Escaping HTML

> WARNING: This method is not safe from XSS!

If you wish to escape HTML in trusted input, you can use the following:

```php
$Parsedown->setMarkupEscaped(true);
```

Beware that this still allows users to insert unsafe scripting vectors, ex: `[xss](javascript:alert%281%29)`.

## Questions

**How does Parsedown work?**

It tries to read Markdown like a human. First, it looks at the lines. It’s interested in how the lines start. This helps it recognise blocks. It knows, for example, that if a line starts with a `-` then perhaps it belongs to a list. Once it recognises the blocks, it continues to the content. As it reads, it watches out for special characters. This helps it recognise inline elements (or inlines).

We call this approach "line based". We believe that Parsedown is the first Markdown parser to use it. Since the release of Parsedown, other developers have used the same approach to develop other Markdown parsers in PHP and in other languages.

**Is it compliant with CommonMark?**

It passes most of the CommonMark tests. Most of the tests that don't pass deal with cases that are quite uncommon. Still, as CommonMark matures, compliance should improve.

**Who uses it?**

[Laravel Framework](https://laravel.com/), [Bolt CMS](http://bolt.cm/), [Grav CMS](http://getgrav.org/), [Herbie CMS](http://www.getherbie.org/), [Kirby CMS](http://getkirby.com/), [October CMS](http://octobercms.com/), [Pico CMS](http://picocms.org), [Statamic CMS](http://www.statamic.com/), [phpDocumentor](http://www.phpdoc.org/), [RaspberryPi.org](http://www.raspberrypi.org/), [Symfony Demo](https://github.com/symfony/demo) and [more](https://packagist.org/packages/erusev/parsedown/dependents).

**How can I help?**

Use it, star it, share it and if you feel generous, [donate](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=528P3NZQMP8N2).

**What else should I know?**

I also make [Nota](https://nota.md/) — a writing app designed for Markdown files :)
