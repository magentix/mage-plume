# Magentix_Security

Content Security Policy header and Magento 2 security workarounds for two active attack vectors.

## Content Security Policy header

The `Magentix\Security\Observer\Csp` observer (`controller_front_send_response_before` event, frontend
area) adds a `Content-Security-Policy` header to every frontend response. The policy is read from the
`magentix_security/csp/policy` configuration path (default scope: same policy on every store view). An empty
value sends no header.

The policy is not editable in the admin: it is a developer setting, versioned with the code and identical on
every environment.

### Default policy

The policy is empty by default (`etc/config.xml`): no `Content-Security-Policy` header is sent until a policy
is set.

A minimal policy for a standard Magento store, without any external service:

```
default-src 'self'; script-src 'self' 'unsafe-eval' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:
```

### Custom policy

Write the policy on a single line, directives separated by `; `. Override it in `app/etc/config.php`, which is
versioned and deployed with the project:

```bash
bin/magento config:set --lock-config magentix_security/csp/policy "default-src 'self' *.cloudflare.com; script-src 'self' 'unsafe-eval' 'unsafe-inline' *.cloudflare.com; style-src 'self' 'unsafe-inline'; img-src 'self' *.openstreetmap.org data:"
bin/magento cache:clean config full_page
```

The command writes the value in `app/etc/config.php`, then commit the file. The file can also be edited by hand:
run `bin/magento app:config:import` afterwards (`setup:upgrade` also does it), otherwise Magento answers
"The configuration file has changed" on every page.

```php
'system' => [
    'default' => [
        'magentix_security' => [
            'csp' => [
                'policy' => 'default-src \'self\' *.cloudflare.com; script-src ...'
            ]
        ]
    ]
]
```

Avoid `config:set` without `--lock-config`: the value would be saved in the database of the current environment
only. A value in `app/etc/config.php` takes precedence over the database and over `etc/config.xml`. Set an empty
value to remove the header.

On this store, the policy in `app/etc/config.php` allows `*.cloudflare.com` (Cloudflare Turnstile) and
`*.openstreetmap.org` (pickup point maps). Any new external script, style or image source must be added there.

## SessionReaper

Attackers exploit the `customer/address_file/upload` endpoint to upload PHP-serialized payloads
that write webshells to `pub/`. No authentication is required — only a valid form key, obtainable
with a single GET request.

This module disables the endpoint entirely by intercepting the upload controller and returning a 403.

## PolyShell (APSB25-94)

The Magento REST API accepts image uploads for product custom options. The native validator checks
MIME type but not the file extension, allowing polyglot files (valid image + embedded PHP) with a
`.php` extension to be stored and executed.

This module enforces an extension allowlist (`jpg`, `jpeg`, `gif`, `png`) at both the validator
and uploader level.

Custom option file uploads through the API are also blocked entirely, as this feature is only
used by attackers. Customers can still upload files from the product page.

## License

BSD 2-Clause, see [LICENCE](LICENCE).
