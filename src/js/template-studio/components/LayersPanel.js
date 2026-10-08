/**
 * Layers panel: the stack (topmost first), selection, visibility, order, delete and add.
 */
import { STORE } from '../store';
import { addableTypes, createLayer, layerName, typeLabel } from '../registry';

const { createElement: el } = wp.element;
const { Button, DropdownMenu } = wp.components;
const { useSelect, useDispatch } = wp.data;
const { __ } = wp.i18n;

export default function LayersPanel( { data } ) {
	const { layers, selectedId, canvas } = useSelect( ( select ) => {
		const state = select( STORE ).getState();
		return {
			layers: state.document.layers,
			selectedId: state.selectedId,
			canvas: state.document.canvas,
		};
	}, [] );
	const {
		select,
		updateLayer,
		moveLayer,
		removeLayer,
		addLayer,
		duplicateLayer,
	} = useDispatch( STORE );

	const types = addableTypes( data.capabilities.layerTypes );

	return el(
		'div',
		{ className: 'aimg-studio__layers' },
		el(
			'div',
			{ className: 'aimg-studio__panel-head' },
			el( 'h2', null, __( 'Layers', 'artificial-image-generator' ) ),
			el( DropdownMenu, {
				icon: 'plus',
				label: __( 'Add layer', 'artificial-image-generator' ),
				controls: types.map( ( type ) => ( {
					title: typeLabel( type ),
					onClick: () =>
						addLayer( createLayer( type, canvas, data.settings ) ),
				} ) ),
			} )
		),
		layers.length === 0 &&
			el(
				'p',
				{ className: 'aimg-studio__empty' },
				__(
					'No layers yet. Add one with +.',
					'artificial-image-generator'
				)
			),
		el(
			'ul',
			{ className: 'aimg-studio__layer-list', role: 'listbox' },
			[ ...layers ].reverse().map( ( layer, index ) => {
				const isSelected = layer.id === selectedId;
				return el(
					'li',
					{
						key: layer.id,
						className:
							'aimg-studio__layer' +
							( isSelected ? ' is-selected' : '' ) +
							( layer.visible ? '' : ' is-hidden' ),
						role: 'option',
						'aria-selected': isSelected,
					},
					el(
						Button,
						{
							className: 'aimg-studio__layer-name',
							onClick: () => select( layer.id ),
						},
						el(
							'span',
							{ className: 'aimg-studio__layer-type' },
							typeLabel( layer.type )
						),
						el( 'span', null, layerName( layer ) )
					),
					el( Button, {
						icon: layer.visible ? 'visibility' : 'hidden',
						label: layer.visible
							? __( 'Hide', 'artificial-image-generator' )
							: __( 'Show', 'artificial-image-generator' ),
						onClick: () =>
							updateLayer( layer.id, {
								visible: ! layer.visible,
							} ),
					} ),
					el( Button, {
						icon: 'admin-page',
						label: __(
							'Duplicate (Ctrl+D)',
							'artificial-image-generator'
						),
						onClick: () => duplicateLayer( layer.id ),
					} ),
					el( Button, {
						icon: 'arrow-up-alt2',
						label: __( 'Move up', 'artificial-image-generator' ),
						disabled: index === 0,
						onClick: () => moveLayer( layer.id, 1 ),
					} ),
					el( Button, {
						icon: 'arrow-down-alt2',
						label: __( 'Move down', 'artificial-image-generator' ),
						disabled: index === layers.length - 1,
						onClick: () => moveLayer( layer.id, -1 ),
					} ),
					el( Button, {
						icon: 'trash',
						label: __(
							'Delete layer',
							'artificial-image-generator'
						),
						isDestructive: true,
						onClick: () => removeLayer( layer.id ),
					} )
				);
			} )
		)
	);
}
