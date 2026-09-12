<?php
/**
 * MiniDrilldown\QueryService
 *
 * The ONLY file in this feature allowed to know about Cargo's internal
 * query-building classes. Everything else talks to this class's plain-array
 * return value, never to Cargo directly.
 *
 * VERIFY BEFORE SHIPPING: Cargo does not document CargoSQLQuery as public
 * API. Class/method names below are written against the shape Cargo's own
 * Special:Drilldown and #cargo_query use internally, but pin and test
 * against the exact Cargo version this wiki runs — the same caution your
 * README already gives for the .drilldown-filters DOM contract applies
 * here, one layer down the stack.
 *
 * Confirmed against wikimedia/mediawiki-extensions-Cargo (master,
 * includes/CargoSQLQuery.php) on 2026-09-12:
 *   - newFromValues( $tablesStr, $fieldsStr, $whereStr, $joinOnStr,
 *     $groupByStr, $havingStr, $orderByStr, $limitStr, $offsetStr,
 *     $allowFieldEscaping = false )
 *   - Field aliasing in $fieldsStr uses "Field=Alias" (like #cargo_query),
 *     NOT SQL's "Field AS Alias" — "AS" is not special-cased anywhere in
 *     setAliasedFieldNames() and would just become part of a literal,
 *     useless alias string.
 *   - run() HTML-escapes every non-DateTime value before returning it
 *     (CargoSQLQuery::run(), htmlspecialchars( $curValue )). Callers of
 *     this class get already-decoded plain values instead (see
 *     decodeRows() below) so they aren't double-escaped by Renderer.php,
 *     and so page names round-trip correctly through Title::newFromText().
 * Re-check this comment against that file if Cargo is upgraded — the
 * calls below are only correct against this positional order/behavior.
 */

namespace MediaWiki\Extension\SaintapediaCargoEmbed\MiniDrilldown;

use CargoSQLQuery;
use CargoUtils;

class QueryService {

	/**
	 * @param string $table Cargo table name
	 * @param array<string,string> $lockedFilters e.g. [ 'Category' => 'Martyrs' ]
	 * @param string[] $facetFields Fields to compute value+count facets for
	 * @param int $limit Max result rows to return for the preview list
	 * @return array{
	 *   totalCount:int,
	 *   results:array<int,array<string,mixed>>,
	 *   facets:array<string,array<int,array{value:string,count:int}>>
	 * }
	 */
	public function run( string $table, array $lockedFilters, array $facetFields, int $limit ): array {
		$whereStr = $this->buildWhereClause( $lockedFilters );

		return [
			'totalCount' => $this->getTotalCount( $table, $whereStr ),
			'results' => $this->getResultPreview( $table, $whereStr, $limit ),
			'facets' => $this->getFacetCounts( $table, $whereStr, $facetFields ),
		];
	}

	/**
	 * Locked filters are always exact-match, always AND'ed. No LIKE, no
	 * ranges, no OR — that's what keeps this safe to build from a small
	 * pipe-attribute string instead of a real query language.
	 */
	private function buildWhereClause( array $lockedFilters ): string {
		if ( $lockedFilters === [] ) {
			return '';
		}
		$parts = [];
		foreach ( $lockedFilters as $field => $value ) {
			// CargoUtils::escapedTableName / field-quoting helpers exist for this
			// purpose in Cargo core — reuse them rather than hand-rolling
			// escaping. Confirm exact helper name against installed version;
			// as a floor, never string-concatenate $value without going
			// through the DB connection's addQuotes().
			$db = CargoUtils::getDB();
			$parts[] = $db->addIdentifierQuotes( $field ) . ' = ' . $db->addQuotes( $value );
		}
		return implode( ' AND ', $parts );
	}

	private function getTotalCount( string $table, string $whereStr ): int {
		$query = CargoSQLQuery::newFromValues(
			$table,
			'COUNT(*)=total',
			$whereStr,
			null, // joinOnStr
			null, // groupByStr
			null, // havingStr
			null, // orderByStr
			null, // limitStr
			null  // offsetStr
		);
		$rows = $query->run();
		return (int)( $rows[0]['total'] ?? 0 );
	}

	private function getResultPreview( string $table, string $whereStr, int $limit ): array {
		// Field list intentionally minimal — this is a teaser card, not a
		// full result table. Page title/name field is whatever Cargo's
		// convention is for this table (commonly `_pageName`).
		$query = CargoSQLQuery::newFromValues(
			$table,
			'_pageName, _pageTitle',
			$whereStr,
			null, // joinOnStr
			null, // groupByStr
			null, // havingStr
			null, // orderByStr
			(string)$limit,
			null  // offsetStr
		);
		return $this->decodeRows( $query->run() );
	}

	/**
	 * One query per facet field. For a small whitelist (2-4 fields, which is
	 * the whole point of "facets are a subset") this is fine; do not let
	 * $facetFields grow into "every filterable field on the table" or this
	 * becomes N separate GROUP BY queries per page view.
	 */
	private function getFacetCounts( string $table, string $whereStr, array $facetFields ): array {
		$facets = [];
		foreach ( $facetFields as $field ) {
			$query = CargoSQLQuery::newFromValues(
				$table,
				$field . ', COUNT(*)=cnt',
				$whereStr,
				null,          // joinOnStr
				$field,        // groupByStr — required for the COUNT(*) to mean anything
				null,          // havingStr
				'cnt DESC',    // orderByStr — show the most common values first
				'20',          // limitStr — cap facet values shown per field in the mini widget
				null           // offsetStr
			);
			$rows = $this->decodeRows( $query->run() );
			$facets[$field] = array_map( static function ( $row ) use ( $field ) {
				return [ 'value' => (string)$row[$field], 'count' => (int)$row['cnt'] ];
			}, $rows );
		}
		return $facets;
	}

	/**
	 * CargoSQLQuery::run() HTML-escapes every string value it returns
	 * (see class docblock above). Undo that here, once, so the rest of
	 * this extension works with plain values and does its own escaping
	 * exactly once, at render time.
	 */
	private function decodeRows( array $rows ): array {
		return array_map( static function ( array $row ): array {
			return array_map( static function ( $value ) {
				return is_string( $value ) ? htmlspecialchars_decode( $value, ENT_QUOTES ) : $value;
			}, $row );
		}, $rows );
	}
}
