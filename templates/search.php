<?php
/**
 * Apartment filter results.
 *
 * Override by copying to yourtheme/solo-estate/search.php.
 *
 * @package SoloEstate
 *
 * @var object|null $project Fixed project, or null in the catalog.
 * @var array       $params  Filter values.
 * @var object[]    $rows    Apartments on this page.
 * @var int         $total   Total matches.
 * @var int         $pages   Number of pages.
 */

use SoloEstate\Frontend\Renderer;
use SoloEstate\Frontend\Search;
use SoloEstate\Nodes;
use SoloEstate\Settings;
use SoloEstate\Statuses;
use SoloEstate\Texts;

defined( 'ABSPATH' ) || exit;

$solo_estate_multi = ! $project && ! $params['project'];
// In the catalog a search started on a project page stays scoped to that project.
$solo_estate_scope = $project ? $project : ( $params['project'] ? Nodes::get( $params['project'] ) : null );
// Price column and currency switch only when an apartment in the results has a price.
$solo_estate_prices = Settings::get( 'show_prices' ) && array_filter(
	$rows,
	static function ( $row ) {
		return null !== Nodes::total_price( $row );
	}
);
?>
<nav class="solo-estate-crumbs">
	<a class="solo-estate-back" href="<?php echo esc_url( $solo_estate_scope ? Renderer::url( $solo_estate_scope ) : Renderer::root_url() ); ?>" aria-label="<?php echo esc_attr( Texts::get( 'back' ) ); ?>">←</a>
	<?php if ( Renderer::is_catalog() ) : ?>
		<a href="<?php echo esc_url( Renderer::root_url() ); ?>"><?php echo esc_html( Texts::get( 'projects' ) ); ?></a>
		<span class="solo-estate-crumbs__sep">/</span>
	<?php endif; ?>
	<?php if ( $solo_estate_scope ) : ?>
		<a href="<?php echo esc_url( Renderer::url( $solo_estate_scope ) ); ?>"><?php echo esc_html( Nodes::display_title( $solo_estate_scope ) ); ?></a>
		<span class="solo-estate-crumbs__sep">/</span>
	<?php endif; ?>
	<span aria-current="page"><?php echo esc_html( Texts::get( 'filter_title' ) ); ?></span>
</nav>

<?php echo Search::form( $solo_estate_scope && ! Renderer::is_catalog() ? $solo_estate_scope : $project, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

<header class="solo-estate-head">
	<h2 class="solo-estate-title"><?php echo esc_html( sprintf( Texts::get( 'found' ), $total ) ); ?></h2>
	<?php if ( $solo_estate_prices ) : ?>
		<?php echo Renderer::currency_switch(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	<?php endif; ?>
</header>

<?php if ( ! $rows ) : ?>
	<p class="solo-estate-empty"><?php echo esc_html( Texts::get( 'no_results' ) ); ?></p>
<?php else : ?>
	<div class="solo-estate-table-wrap">
		<table class="solo-estate-table solo-estate-results">
			<caption class="screen-reader-text"><?php echo esc_html( sprintf( Texts::get( 'found' ), $total ) ); ?></caption>
			<thead>
				<tr>
					<?php if ( $solo_estate_multi ) : ?>
						<th scope="col"><?php echo esc_html( Texts::get( 'project' ) ); ?></th>
					<?php endif; ?>
					<th scope="col"><?php echo esc_html( Texts::get( 'building' ) ); ?></th>
					<th scope="col"><?php echo esc_html( Texts::get( 'floor' ) ); ?></th>
					<th scope="col"><?php echo esc_html( Texts::get( 'flat' ) ); ?></th>
					<th scope="col"><?php echo esc_html( Texts::get( 'rooms' ) ); ?></th>
					<th scope="col"><?php echo esc_html( Texts::get( 'area' ) ); ?></th>
					<?php if ( $solo_estate_prices ) : ?>
						<th scope="col"><?php echo esc_html( Texts::get( 'price' ) ); ?></th>
					<?php endif; ?>
					<th scope="col"><?php echo esc_html( Texts::get( 'status' ) ); ?></th>
					<th scope="col"><span class="screen-reader-text"><?php echo esc_html( Texts::get( 'details' ) ); ?></span></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $solo_estate_flat ) : ?>
					<?php
					$solo_estate_chain = Nodes::ancestors( $solo_estate_flat );
					$solo_estate_by    = array();
					foreach ( $solo_estate_chain as $solo_estate_item ) {
						$solo_estate_by[ $solo_estate_item->level ] = $solo_estate_item;
					}
					$solo_estate_href = Renderer::url( $solo_estate_flat );
					?>
					<tr class="is-link" data-href="<?php echo esc_url( $solo_estate_href ); ?>">
						<?php if ( $solo_estate_multi ) : ?>
							<td><?php echo esc_html( isset( $solo_estate_by['project'] ) ? Nodes::display_title( $solo_estate_by['project'] ) : '' ); ?></td>
						<?php endif; ?>
						<td><?php echo esc_html( isset( $solo_estate_by['building'] ) ? Nodes::display_title( $solo_estate_by['building'] ) : '' ); ?></td>
						<td><?php echo esc_html( isset( $solo_estate_by['floor'] ) ? $solo_estate_by['floor']->number : '' ); ?></td>
						<?php // The number is the row's link (the "Details" column is hidden on phones). ?>
						<th scope="row"><a href="<?php echo esc_url( $solo_estate_href ); ?>" aria-label="<?php echo esc_attr( implode( ', ', array_filter( array( isset( $solo_estate_by['building'] ) ? Nodes::display_title( $solo_estate_by['building'] ) : '', Nodes::display_title( $solo_estate_flat ) ) ) ) ); ?>"><?php echo esc_html( '' !== $solo_estate_flat->number ? $solo_estate_flat->number : Nodes::display_title( $solo_estate_flat ) ); ?></a></th>
						<td><?php echo esc_html( null === $solo_estate_flat->rooms ? '—' : ( 0 === $solo_estate_flat->rooms ? Texts::get( 'studio' ) : $solo_estate_flat->rooms ) ); ?></td>
						<td><?php echo esc_html( Renderer::area( $solo_estate_flat->area ) ); ?></td>
						<?php if ( $solo_estate_prices ) : ?>
							<td><?php echo Renderer::price( Nodes::total_price( $solo_estate_flat ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
						<?php endif; ?>
						<td><?php echo Renderer::badge( Statuses::of( $solo_estate_flat->status_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
						<td class="solo-estate-table__action"><a href="<?php echo esc_url( $solo_estate_href ); ?>" tabindex="-1" aria-hidden="true"><?php echo esc_html( Texts::get( 'details' ) ); ?> →</a></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<?php if ( $pages > 1 ) : ?>
		<nav class="solo-estate-pages">
			<?php for ( $solo_estate_i = 1; $solo_estate_i <= $pages; $solo_estate_i++ ) : ?>
				<?php if ( $solo_estate_i === $params['page'] ) : ?>
					<span aria-current="page"><?php echo (int) $solo_estate_i; ?></span>
				<?php elseif ( $solo_estate_i <= 2 || $solo_estate_i > $pages - 2 || abs( $solo_estate_i - $params['page'] ) <= 1 ) : ?>
					<a href="<?php echo esc_url( Search::page_url( $solo_estate_i ) ); ?>"><?php echo (int) $solo_estate_i; ?></a>
				<?php elseif ( 3 === $solo_estate_i || $pages - 2 === $solo_estate_i ) : ?>
					<span class="solo-estate-pages__gap">…</span>
				<?php endif; ?>
			<?php endfor; ?>
		</nav>
	<?php endif; ?>
<?php endif; ?>
