<?php
/**
 * All projects: cards with a tab per project status (e.g. Ongoing / Completed). The apartment
 * filter lives on the project pages.
 *
 * Override by copying to yourtheme/solo-estate/catalog.php.
 *
 * @package SoloEstate
 *
 * @var object[] $projects Projects.
 */

use SoloEstate\Frontend\Renderer;
use SoloEstate\Nodes;
use SoloEstate\Statuses;
use SoloEstate\Texts;

defined( 'ABSPATH' ) || exit;

// Tabs: "All" (open on entry), then the project statuses in Settings order. Projects
// without a status show under every tab.
$solo_estate_tabs = count( Statuses::for_scope( 'project' ) ) > 1 ? Statuses::for_scope( 'project' ) : array();
?>

<?php if ( ! $projects ) : ?>
	<p class="solo-estate-empty"><?php echo esc_html( Texts::get( 'no_items' ) ); ?></p>
<?php else : ?>
	<?php if ( $solo_estate_tabs ) : ?>
		<div class="solo-estate-tabs" role="group" data-solo-estate-tabs>
			<button type="button" class="is-active" aria-pressed="true" data-tab="all"><?php echo esc_html( Texts::get( 'all' ) ); ?></button>
			<?php foreach ( $solo_estate_tabs as $solo_estate_status ) : ?>
				<button type="button" aria-pressed="false" data-tab="<?php echo esc_attr( $solo_estate_status->id ); ?>"><?php echo esc_html( Statuses::title( $solo_estate_status ) ); ?></button>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<ul class="solo-estate-projects">
		<?php foreach ( $projects as $solo_estate_project ) : ?>
			<?php
			$solo_estate_status = Renderer::project_status( $solo_estate_project );
			$solo_estate_state  = Renderer::state( $solo_estate_project );
			$solo_estate_image  = $solo_estate_project->image2_id ? $solo_estate_project->image2_id : $solo_estate_project->image_id;
			$solo_estate_open   = Renderer::is_open( $solo_estate_project );
			$solo_estate_tag    = $solo_estate_open ? 'a' : 'div';
			?>
			<li data-status="<?php echo esc_attr( $solo_estate_status ? $solo_estate_status->id : 0 ); ?>">
				<<?php echo esc_attr( $solo_estate_tag ); ?> class="solo-estate-project"<?php echo $solo_estate_open ? ' href="' . esc_url( Renderer::url( $solo_estate_project ) ) . '"' : ''; ?>>
					<span class="solo-estate-project__media">
						<?php if ( $solo_estate_image ) : ?>
							<?php echo wp_get_attachment_image( $solo_estate_image, 'large', false, array( 'alt' => Nodes::display_title( $solo_estate_project ), 'loading' => 'lazy' ) ); ?>
						<?php endif; ?>
						<?php if ( $solo_estate_state['sold_out'] ) : ?>
							<span class="solo-estate-project__soldout"><?php echo esc_html( Texts::get( 'sold_out' ) ); ?></span>
						<?php endif; ?>
					</span>
					<span class="solo-estate-project__body">
						<span class="solo-estate-project__title"><?php echo esc_html( Nodes::display_title( $solo_estate_project ) ); ?></span>
						<?php if ( $solo_estate_status ) : ?>
							<span class="solo-estate-project__status"><?php echo esc_html( Statuses::title( $solo_estate_status ) ); ?></span>
						<?php endif; ?>
					</span>
				</<?php echo esc_attr( $solo_estate_tag ); ?>>
			</li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>
