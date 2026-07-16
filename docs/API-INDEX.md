# API indexes

A PressGang package can ship a machine-readable index of its public API at
`docs/api-index.json`. Agents read it (and `pressgang_docs_search` queries it)
to get correct signatures and WordPress-doc links for the exact version a theme
has installed — no guessing, no network call.

One generator produces all of them, so every index has the same shape:
`wp capstan make api-index`, driven by a per-package `api-index.php` manifest.

## The manifest — `api-index.php`

A package ships an `api-index.php` in its root returning the manifest. It names
*what* to index; the generator handles *how* (signatures, docblock parsing).

```php
use PressGang\Quartermaster\Quartermaster;

return [
    'package'       => 'pressgang/quartermaster',
    'version'       => '0.1.0',                            // optional — derived from installed metadata when omitted
    'entrypoint'    => Quartermaster::class,
    'principles'    => ['Args-first; outputs plain WP_Query arrays', /* … */],
    'annotate_args' => true,                              // opt in to the Sets:/query-args convention
    'reads_globals' => ['paged' => true],                 // method => reads request globals
    'groups'        => [
        // group label => [class, [method, …]]
        'Bootstrap' => [Quartermaster::class, ['posts', 'terms', 'prepare']],
        // …
    ],
];
```

## Generating

```bash
wp capstan make api-index            # preview (method count, groups, any change)
wp capstan make api-index --force    # write docs/api-index.json
```

Wire it into the package's composer scripts so it stays current:

```json
"scripts": { "api-index": "wp capstan make api-index --force" }
```

### Versioning

`version` is optional. Omit it and the generator stamps the installed package
version (the git tag Composer resolved), falling back to `dev` in a working
clone with no derivable tag — so there is nothing to hand-maintain. Because
`docs/api-index.json` is a **committed** artifact, keep an explicit `version` if
you want the file to read as a specific release without regenerating; otherwise
regenerate at release time and let it derive.

## The schema (`docs/api-index.json`)

```jsonc
{
  "package": "pressgang/quartermaster",
  "version": "0.1.0",
  "generated_at": "2026-07-16T00:00:00Z",
  "entrypoint": "PressGang\\Quartermaster\\Quartermaster",
  "principles": ["…"],
  "methods": [
    {
      "name": "whereMeta",
      "signature": "whereMeta(string $key, mixed $value, string $compare = '='): self",
      "group": "Meta query",
      "sets_args": ["meta_query"],        // from a `Sets: …` docblock line
      "reads_globals": false,
      "wp_docs": ["https://developer.wordpress.org/…"],  // links found in the docblock
      "notes": "First prose line of the method docblock."
    }
  ]
}
```

`sets_args`, `wp_docs`, and `notes` come from each method's docblock — a `Sets:`
line, any `developer.wordpress.org` / `timber.github.io` links, and the first
prose line. Indexes over 256 KB or with invalid JSON are skipped by readers, so
an index is always an enhancement, never a requirement.
