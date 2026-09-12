# SaintapediaCargoEmbed

A MediaWiki extension that renders a scaled-down [Cargo](https://www.mediawiki.org/wiki/Extension:Cargo)
drilldown — with some filters locked in — as a `<cargodrilldown>` tag you can
drop into regular wikitext content, instead of only being able to view a
drilldown on its own `Special:Drilldown` page.

It's a sibling to [SaintapediaDrilldown](https://github.com/Saintapedia/SaintapediaDrilldown),
reusing that extension's CSS theme tokens, but architecturally unrelated:
Drilldown reshapes `Special:Drilldown`'s own DOM output, while this extension
issues its own Cargo queries directly.

## Usage

```wikitext
<cargodrilldown
  table="Saints"
  locked="Category=Martyrs"
  facets="Century,Region"
  limit="8"
  theme="compact"
/>
```

| Attribute | Required | Description |
|---|---|---|
| `table` | yes | Cargo table name to query. |
| `locked` | no | `;`-separated `Field=Value` pairs, always exact-match and AND'ed. Rendered as non-removable chips — this is what makes the widget "scoped" rather than a full drilldown. |
| `facets` | no | Comma-separated list of fields to show value+count facets for. Keep this small; each facet field is its own `GROUP BY` query. |
| `limit` | no | Max preview rows shown (1–50, default 8). |
| `theme` | no | CSS theme class, default `compact`. |

Facet links and the "See all N results →" link both point at the real
`Special:Drilldown` for the table, with the locked filters carried over as
URL parameters — clicking through always lands on the full, unrestricted
drilldown.

## Requirements

- MediaWiki >= 1.39
- [Cargo](https://www.mediawiki.org/wiki/Extension:Cargo) >= 3.0

## Installation

Clone into your `extensions/` directory as `SaintapediaCargoEmbed` and add to
`LocalSettings.php`:

```php
wfLoadExtension( 'SaintapediaCargoEmbed' );
```

## Architecture

- `includes/MiniDrilldown/QueryService.php` is the only file that talks to
  Cargo's internal, undocumented `CargoSQLQuery` query builder — everything
  else in the extension works with its plain-array return value. If Cargo's
  internals change, this is the one file that needs fixing.
- `includes/MiniDrilldown/Renderer.php` emits markup using the same CSS
  classes as `ext.SaintapediaDrilldown.css` (`.cargo-drilldown-layout` plus
  theme classes), so it inherits Drilldown's existing theme system without
  needing any JavaScript.
- `includes/MiniDrilldown/Hooks.php` registers the parser tag and marks
  pages using it as uncacheable (`updateCacheExpiry(0)`) — see "Known
  limitations" below.

## Known limitations

See [WIRING.md](WIRING.md) for the full scaffolding notes. In short:

- **Cache invalidation is blunt.** Any page using `<cargodrilldown>` never
  uses the parser cache. Fine for low-traffic pages; worth revisiting before
  putting this on high-traffic portal pages.
- **The `facets` attribute is not yet whitelisted server-side.** If editors
  can add this tag themselves (not just via a curated admin template), a
  per-table allowlist should cap which fields can be requested.
- **No automated tests yet.** The extension's correctness depends entirely
  on matching Cargo's actual (undocumented) `CargoSQLQuery` API for the
  installed Cargo version — see the verification notes at the top of
  `QueryService.php`.

## License

GPL-2.0-or-later
