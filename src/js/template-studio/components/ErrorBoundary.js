/**
 * Shows a message instead of a blank screen if the Studio fails while running.
 */
const { Component, createElement: el } = wp.element;
const { Button, Notice } = wp.components;
const { __ } = wp.i18n;

export default class ErrorBoundary extends Component {
	constructor( props ) {
		super( props );
		this.state = { failed: false };
	}

	static getDerivedStateFromError() {
		return { failed: true };
	}

	componentDidCatch( error ) {
		window.console.error( 'Template Studio:', error );
	}

	render() {
		if ( ! this.state.failed ) {
			return this.props.children;
		}

		return el(
			Notice,
			{ status: 'error', isDismissible: false },
			el(
				'p',
				null,
				__(
					'The Template Studio ran into a problem. Your last saved version is safe.',
					'artificial-image-generator'
				)
			),
			el(
				'p',
				null,
				el(
					Button,
					{
						variant: 'secondary',
						onClick: () => window.location.reload(),
					},
					__( 'Reload the Studio', 'artificial-image-generator' )
				)
			)
		);
	}
}
