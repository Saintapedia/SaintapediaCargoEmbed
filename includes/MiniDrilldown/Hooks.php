<?php
/**
 * MiniDrilldown\Hooks
 *
 * Registers the <cargodrilldown> parser tag. Kept in its own namespace/file
 * (not includes/Hooks.php) so the "display wrapper for Special:Drilldown"
 * responsibility and the "new query-issuing embed" responsibility stay
 * physically separate — the whole point of NOT putting this in
 * SaintapediaDrilldown proper.
 *
 * Suggested home: a sibling extension (e.g. SaintapediaCargoEmbed) that
 * depends on Cargo and, optionally, on SaintapediaDrilldown's CSS module
 * (for the `compact` theme tokens). See extension.json notes at bottom.
 */

namespace MediaWiki\Extension\SaintapediaCargoEmbed\MiniDrilldown;

use Parser;
use PPFrame;

class Hooks {

	/**
	 * @param Parser $parser
	 */
	public static function onParserFirstCallInit( Parser $parser ) {
		$parser->setHook( 'cargodrilldown', [ self::class, 'renderTag' ] );
	}

	/**
	 * <cargodrilldown
	 *   table="Saints"
	 *   locked="Category=Martyrs"
	 *   facets="Century,Region"
	 *   limit="8"
	 *   theme="compact"
	 * />
	 *
	 * @param string|null $input Tag body (unused — this is a self-closing/attr-only tag)
	 * @param array $args Tag attributes
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @return string HTML
	 */
	public static function renderTag( $input, array $args, Parser $parser, PPFrame $frame ) {
		// Mark the page as depending on live data. This is the important line —
		// without it, ParserCache will happily serve a stale embed forever.
		// updateCacheExpiry(0) makes the page effectively uncacheable, which is
		// heavy-handed; a nicer follow-up is a ParserOutput "touched" link to a
		// synthetic title per (table, lockedFilters) so cache invalidates only
		// when that table's data actually changes (Cargo doesn't currently
		// expose a per-table "last updated" hook cleanly — worth checking
		// their Cargo\Hooks for a CargoTablesUpdated-style event before
		// assuming you need the blunt version below).
		$parser->getOutput()->updateCacheExpiry( 0 );

		$table = trim( $args['table'] ?? '' );
		if ( $table === '' ) {
			return self::errorHtml( 'cargodrilldown: "table" attribute is required.' );
		}

		$lockedFilters = self::parseLockedFilters( $args['locked'] ?? '' );
		$facetFields = array_filter( array_map( 'trim', explode( ',', $args['facets'] ?? '' ) ) );
		$limit = max( 1, min( 50, (int)( $args['limit'] ?? 8 ) ) );
		$theme = trim( $args['theme'] ?? 'compact' );

		$queryService = new QueryService();

		try {
			$queryResult = $queryService->run( $table, $lockedFilters, $facetFields, $limit );
		} catch ( \Exception $e ) {
			// Same posture as the DOM-selector warnings in the main extension:
			// fail loudly to devs, gracefully to readers.
			wfDebugLog( 'SaintapediaCargoEmbed',
				'cargodrilldown query failed for table=' . $table . ': ' . $e->getMessage() );
			return self::errorHtml( 'This catalog widget could not load right now.' );
		}

		$parser->getOutput()->addModuleStyles( [ 'ext.SaintapediaDrilldown.styles' ] );
		$parser->getOutput()->addModuleStyles( [ 'ext.SaintapediaCargoEmbed.mini.styles' ] );

		$renderer = new Renderer();
		return $renderer->render( $table, $lockedFilters, $queryResult, $theme );
	}

	/**
	 * "Category=Martyrs" or "Category=Martyrs;Century=3rd" -> [ 'Category' => 'Martyrs', ... ]
	 * Deliberately simple (no OR groups, no ranges) — locked filters are meant
	 * to be a small, fixed, curator-authored set, not a full facet language.
	 *
	 * @param string $raw
	 * @return array<string,string>
	 */
	private static function parseLockedFilters( string $raw ): array {
		$out = [];
		foreach ( explode( ';', $raw ) as $pair ) {
			$pair = trim( $pair );
			if ( $pair === '' ) {
				continue;
			}
			[ $field, $value ] = array_pad( explode( '=', $pair, 2 ), 2, '' );
			$field = trim( $field );
			$value = trim( $value );
			if ( $field !== '' && $value !== '' ) {
				$out[$field] = $value;
			}
		}
		return $out;
	}

	private static function errorHtml( string $message ): string {
		return '<div class="cargo-drilldown-mini cargo-drilldown-mini-error">'
			. htmlspecialchars( $message ) . '</div>';
	}
}
