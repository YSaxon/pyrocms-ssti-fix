# 🛡️ PyroCMS SSTI Fix

**Drop-in security fix for CVE-2023-29689** - Server-Side Template Injection leading to Remote Code Execution in PyroCMS 3.9.

## The Problem

PyroCMS allows admin users to edit templates stored in the database. Without sandboxing, attackers with admin access can inject malicious Twig code:

```twig
{{['id']|map('system')|join}}
```

This executes arbitrary system commands. The upstream maintainers consider this "working as intended" since admin users are trusted - but in multi-tenant or enterprise environments, "admin" ≠ "trusted with shell access".

## The Solution

This package automatically sandboxes user-editable templates while leaving legitimate theme/addon templates unrestricted. It uses Twig's `SourcePolicyInterface` (contributed upstream by the author of this package) to selectively apply restrictions.

## Installation

```bash
composer require ysaxon/pyrocms-ssti-fix
```

Unfortunately, due to PyroCMS [disabling autodiscovery](https://github.com/pyrocms/pyrocms/commit/978bbb63c9b871df85bf6ba98756fbd621bff4ec) you will need to add the serviceProvider yourself.

You can do that with
```bash
sed -i "/App\\\Providers\\\AppServiceProvider::class,/a \        YSaxon\\\PyroCmsSstiFix\\\SandboxServiceProvider::class," config/app.php
```

## Requirements

- PHP 7.4+ or 8.0+
- Twig 2.16+ or 3.9+ (for `SourcePolicyInterface` support)
- Laravel 6.0+ / PyroCMS 3.x

## How It Works

```
┌─────────────────────────────────────────────────────────────┐
│                     Twig Render Request                      │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                    StorageSourcePolicy                       │
│                                                              │
│  Is template from storage path (database/user-editable)?     │
│                                                              │
│     YES ──────────────────►  Enable Sandbox                  │
│      │                       - Block: map, filter, reduce    │
│      │                       - Block: dangerous tags         │
│      │                       - Whitelist safe operations     │
│                                                              │
│     NO ───────────────────►  No Sandbox                      │
│                              (Theme/addon templates work     │
│                               normally)                      │
└─────────────────────────────────────────────────────────────┘
```

## Configuration (Optional)

Default settings are secure and work for most installations. To customize:

```bash
php artisan vendor:publish --tag=pyrocms-ssti-fix-config
```

This creates `config/pyrocms-ssti-fix.php`:

```php
return [
    // Master switch
    'enabled' => env('PYROCMS_SSTI_FIX_ENABLED', true),

    // Override auto-detected storage path
    'storage_path' => env('PYROCMS_SSTI_FIX_STORAGE_PATH', null),

    // Customize allowed tags/filters/functions/methods/properties
    'policy' => [
        'tags' => [SecurityPolicyDefaults::INCLUDE_DEFAULTS],
        'filters' => [SecurityPolicyDefaults::INCLUDE_DEFAULTS],
        // ... see config file for full options
    ],
];
```

## What's Blocked

The default security policy **blocks** these dangerous features in sandboxed templates:

### Filters (RCE vectors)
- `map` - `{{['cmd']|map('system')}}` executes shell commands
- `filter` - Can call arbitrary PHP functions
- `reduce` - Can call arbitrary PHP functions

### Tags (inclusion attacks)
- `include`, `extends`, `block`, `macro`, `import`, `embed`, `use`

### Functions
- `source` - Reads arbitrary file contents
- `include` - Includes other templates
- `template_from_string` - Creates templates from strings

## What's Allowed

Safe operations remain available in sandboxed templates:

```twig
{# Variables #}
{{ entry.title }}
{{ user.name|upper }}

{# Loops and conditionals #}
{% for item in items %}
    {% if item.active %}
        {{ item.name }}
    {% endif %}
{% endfor %}

{# Safe filters #}
{{ text|escape }}
{{ date|date('Y-m-d') }}
{{ items|length }}
{{ name|lower|trim }}

{# Safe functions #}
{{ max(a, b) }}
{{ random(['red', 'blue', 'green']) }}
```

## Extending the Whitelist

If your admin templates legitimately need additional features:

```php
// config/pyrocms-ssti-fix.php
'policy' => [
    'filters' => [
        SecurityPolicyDefaults::INCLUDE_DEFAULTS,
        'my_custom_filter',  // Add specific filter
    ],
    'methods' => [
        SecurityPolicyDefaults::INCLUDE_DEFAULTS,
        'App\Models\Post' => ['getTitle', 'getSummary'],
    ],
],
```

## Testing the Fix

After installation, verify the exploit is blocked:

1. Go to PyroCMS admin → Users → Roles
2. Edit a role's description field
3. Enter: `{{['id']|map('system')|join}}`
4. Save and view

**Before fix:** Shows output of `id` command (or crashes)
**After fix:** Shows error or `[rendering failed: Filter "map" is not allowed.]`

## Troubleshooting

### "Twig not found in container"

The package couldn't find Twig. This usually means:
- PyroCMS/streams-platform isn't fully loaded yet
- You're not running in a PyroCMS environment

Enable debug mode to see details:
```env
PYROCMS_SSTI_FIX_DEBUG=true
```

### Legitimate templates breaking

If admin-editable templates use features that are now blocked:

1. Check logs for which feature was blocked
2. Add it to the whitelist in config (if safe)
3. Or refactor the template to use allowed features

### Performance concerns

The package caches all path checks and method/property lookups. In `auto` mode, only storage-path templates incur sandbox overhead.

## Security Notes

- This package **does not** fix the underlying architectural issue (Twig rendering user input)
- It mitigates exploitation by restricting what sandboxed templates can do
- Admin users can still create annoying templates; they just can't achieve RCE
- Consider additional hardening (WAF rules, CSP, etc.) for defense in depth

## Credits

- **Yaakov Saxon** - Package author, `SourcePolicyInterface` contributor to Twig
- **Twig PR #3893** - Upstream contribution enabling selective sandbox

## License

MIT License - see [LICENSE](LICENSE) file.

## Related

- [CVE-2023-29689](https://nvd.nist.gov/vuln/detail/CVE-2023-29689) - NVD entry
- [GHSA-w7vm-4v3j-vgpw](https://github.com/advisories/GHSA-w7vm-4v3j-vgpw) - GitHub Advisory
- [Twig Sandbox Documentation](https://twig.symfony.com/doc/3.x/api.html#sandbox-extension)
