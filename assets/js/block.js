/**
 * "Solo Estate project" block editor UI. Plain ES5 + wp globals, no build step.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var ServerSideRender = wp.serverSideRender;
	var projects = ( window.soloEstateBlock && window.soloEstateBlock.projects ) || [];

	var options = [ { value: 0, label: __( 'All projects (catalog)', 'solo-estate' ) } ].concat( projects );

	wp.blocks.registerBlockType( 'solo-estate/project', {
		apiVersion: 2,
		title: __( 'Solo Estate project', 'solo-estate' ),
		description: __( 'Interactive building and apartment selector.', 'solo-estate' ),
		icon: 'building',
		category: 'widgets',
		attributes: { projectId: { type: 'number', default: 0 } },
		edit: function ( props ) {
			var projectId = props.attributes.projectId;
			var select = el( SelectControl, {
				label: __( 'Project', 'solo-estate' ),
				value: projectId,
				options: options,
				onChange: function ( value ) {
					props.setAttributes( { projectId: parseInt( value, 10 ) || 0 } );
				}
			} );

			return el(
				'div',
				useBlockProps(),
				el( InspectorControls, {}, el( PanelBody, { title: __( 'Settings', 'solo-estate' ) }, select ) ),
				el( ServerSideRender, { block: 'solo-estate/project', attributes: props.attributes } )
			);
		},
		save: function () {
			return null;
		}
	} );
}( window.wp ) );
