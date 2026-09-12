<?php
/**
 * MiniDrilldown\Renderer
 *
 * Emits markup that speaks the SAME CSS contract as
 * ext.SaintapediaDrilldown.css (.cargo-drilldown-layout + theme classes),
 * plus one new modifier (.cargo-drilldown-mini) and its own small stylesheet
 * for the things a mini widget needs that the sidebar layout doesn't:
 * a compact card shell, non-removable locked chips, and a "See all N results"
 * link out to the real Special:Drilldown with the same filters pre-applied.
 *
 * No JS module is required for this to render correctly — that's deliberate.
 */

namespace MediaWiki\Extension\SaintapediaCargoEmbed\MiniDrilldown;

use SpecialPage;

class Renderer {

	public function render( string $table, array $lockedFilters, array $queryResult, string $theme ): string {
		$html = '<div class="cargo-drilldown-layout cargo-drilldown-mini cargo-theme-'
			. htmlspecialchars( $theme ) . '">';

		$html .= $this->renderLockedChips( $lockedFilters );
		$html .= $this->renderFacets( $table, $lockedFilters, $queryResult['facets'] );
		$html .= $this->renderResults( $queryResult['results'] );
		$html .= $this->renderSeeAllLink( $table, $lockedFilters, $queryResult['totalCount'] );

		$html .= '</div>';
		return $html;
	}

	/**
	 * Locked filters render as chips with NO remove control — visually
	 * consistent with the removable chips in Special:Drilldown, but the
	 * missing "×" is the entire enforcement mechanism for "this data point
	 * is locked." (Design rule #1 from the plan: locked filters are data,
	 * not decoration — they're already baked into the SQL by QueryService;
	 * this is just making that visible to the reader.)
	 */
	private function renderLockedChips( array $lockedFilters ): string {
		if ( $lockedFilters === [] ) {
			return '';
		}
		$chips = [];
		foreach ( $lockedFilters as $field => $value ) {
			$chips[] = '<span class="cargo-chip cargo-chip-locked">'
				. htmlspecialchars( $field ) . ': ' . htmlspecialchars( $value )
				. '</span>';
		}
		return '<div class="cargo-active-filters">' . implode( '', $chips ) . '</div>';
	}

	/**
	 * Facet links point at Special:Drilldown with the locked filters
	 * carried over as URL params, so clicking through a facet in the mini
	 * widget lands the reader on the FULL drilldown, already scoped and
	 * already wearing your sidebar theme.
	 */
	private function renderFacets( string $table, array $lockedFilters, array $facets ): string {
		if ( $facets === [] ) {
			return '';
		}
		$html = '<div class="drilldown-filters cargo-drilldown-mini-facets">';
		foreach ( $facets as $field => $values ) {
			$html .= '<div class="cargo-mini-facet-group"><strong>'
				. htmlspecialchars( $field ) . '</strong><ul>';
			foreach ( $values as $entry ) {
				$params = $lockedFilters;
				$params[$field] = $entry['value'];
				$url = SpecialPage::getTitleFor( 'Drilldown', $table )->getLocalURL( $params );
				$html .= '<li><a href="' . htmlspecialchars( $url ) . '">'
					. htmlspecialchars( $entry['value'] )
					. ' <span class="cargo-mini-facet-count">(' . (int)$entry['count'] . ')</span></a></li>';
			}
			$html .= '</ul></div>';
		}
		$html .= '</div>';
		return $html;
	}

	private function renderResults( array $results ): string {
		if ( $results === [] ) {
			return '<p class="cargo-drilldown-mini-empty">No matching pages.</p>';
		}
		$html = '<ul class="drilldown-results-content cargo-drilldown-mini-results">';
		foreach ( $results as $row ) {
			$pageName = $row['_pageName'] ?? $row['_pageTitle'] ?? '';
			$title = $pageName !== '' ? \Title::newFromText( $pageName ) : null;
			if ( !$title ) {
				// Skip rows Cargo returned with an unparseable/empty page name
				// rather than fataling on Title::newFromText() returning null.
				continue;
			}
			$html .= '<li><a href="' . htmlspecialchars( $title->getLocalURL() )
				. '">' . htmlspecialchars( $pageName ) . '</a></li>';
		}
		$html .= '</ul>';
		return $html;
	}

	private function renderSeeAllLink( string $table, array $lockedFilters, int $totalCount ): string {
		$url = SpecialPage::getTitleFor( 'Drilldown', $table )->getLocalURL( $lockedFilters );
		return '<p class="cargo-drilldown-mini-seeall"><a href="' . htmlspecialchars( $url ) . '">'
			. wfMessage( 'saintapediacargoembed-seeall' )->numParams( $totalCount )->escaped()
			. '</a></p>';
	}
}
