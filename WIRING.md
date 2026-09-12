# Wiring this sketch into a real extension

This is scaffolding, not a finished extension — treat file contents as a
starting point to review against your actual Cargo version, not
copy-paste-deploy.

## Suggested layout

New extension `SaintapediaCargoEmbed/`, sibling to `SaintapediaDrilldown/`,
depending on Cargo directly and on SaintapediaDrilldown only for its CSS
module (theme tokens):

```
SaintapediaCargoEmbed/
├── extension.json
├── i18n/en.json              # "saintapediacargoembed-seeall": "See all $1 results →"
├── includes/
│   └── MiniDrilldown/
│       ├── Hooks.php
│       ├── QueryService.php
│       └── Renderer.php
└── modules/
    └── ext.SaintapediaCargoEmbed.mini.css
```

## extension.json additions

```json
{
	"name": "SaintapediaCargoEmbed",
	"requires": {
		"MediaWiki": ">= 1.39",
		"extensions": {
			"Cargo": ">= 3.0"
		}
	},
	"Hooks": {
		"ParserFirstCallInit": "MediaWiki\\Extension\\SaintapediaCargoEmbed\\MiniDrilldown\\Hooks::onParserFirstCallInit"
	},
	"AutoloadNamespaces": {
		"MediaWiki\\Extension\\SaintapediaCargoEmbed\\": "includes/"
	},
	"MessagesDirs": {
		"SaintapediaCargoEmbed": [ "i18n" ]
	},
	"ResourceModules": {
		"ext.SaintapediaCargoEmbed.mini.styles": {
			"styles": [ "modules/ext.SaintapediaCargoEmbed.mini.css" ]
		}
	},
	"manifest_version": 2
}
```

SaintapediaDrilldown doesn't need to list this extension as a dependency at
all — the coupling only goes one direction (embed → drilldown's CSS module
name), so Drilldown stays exactly as self-contained as it is today.

## Before this is real

1. **Verify `CargoSQLQuery::newFromValues()` signature** against the
   installed Cargo version. Positional args in Cargo's internal query
   builder have shifted between releases; QueryService.php is the one file
   that absorbs that risk, by design.
2. **Confirm the page-name field convention** for tables where `_pageName`
   isn't what you want linked (e.g. tables declared with a different
   subject field).
3. **Decide the cache-invalidation story.** `updateCacheExpiry(0)` in
   Hooks.php is the safe-but-blunt option — every page using the tag never
   caches. If embeds end up on high-traffic portal pages, look at whether
   Cargo fires any hook on table rebuild/store you can hang a proper
   dependent-title invalidation off of, before shipping the blunt version
   wiki-wide.
4. **Facet field whitelist enforcement.** Right now `facets=""` is
   free-text from the wikitext attribute. If editors can add this tag
   themselves (not just admins via a curated template), consider capping
   `facets` server-side against a per-table allowlist in the JSON config,
   the same way you already gate `themeVars` tokens — otherwise someone
   drops `facets="EveryFieldOnTheTable"` onto a article and you're back to
   full Drilldown, just badly styled.
5. **Tests.** SaintapediaDrilldown has a `tests/` folder already — mirror
   that structure here, with QueryService tests run against a real Cargo
   table fixture rather than mocked, since the whole risk surface is
   "does this still match Cargo's actual query builder."
