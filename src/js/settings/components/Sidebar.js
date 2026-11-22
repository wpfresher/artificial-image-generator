const { __ } = wp.i18n;

const PLUGINS = [
	{
		title: __( 'Forming Form Builder', 'artificial-image-generator' ),
		desc: __(
			'Contact forms built from native blocks in the block editor',
			'artificial-image-generator'
		),
		url: 'https://wordpress.org/plugins/forming-form-builder/',
	},
	{
		title: __( 'AI Content Writer', 'artificial-image-generator' ),
		desc: __(
			'Automatic Content Generator and Auto Poster',
			'artificial-image-generator'
		),
		url: 'https://wordpress.org/plugins/ai-content-writer/',
	},
	{
		title: __( 'Sokket – MCP Server', 'artificial-image-generator' ),
		desc: __(
			'Connect Claude, ChatGPT, Cursor & Any AI Agent to WordPress',
			'artificial-image-generator'
		),
		url: 'https://wordpress.org/plugins/sokket-site-connector-for-mcp/',
	},
	{
		title: __( 'Bloqra Blocks', 'artificial-image-generator' ),
		desc: __(
			'Gutenberg Blocks & Page Builder for Block Editor',
			'artificial-image-generator'
		),
		url: 'https://wordpress.org/plugins/bloqra/',
	},
	{
		title: __( 'UTM Manager', 'artificial-image-generator' ),
		desc: __(
			'Track UTM Parameters & Lead Sources',
			'artificial-image-generator'
		),
		url: 'https://wordpress.org/plugins/utm-manager/',
	},
	{
		title: __( 'Send Emails', 'artificial-image-generator' ),
		desc: __(
			'Newsletters, Automation & Email Marketing for WordPress',
			'artificial-image-generator'
		),
		url: 'https://wordpress.org/plugins/send-emails/',
	},
	{
		title: __( 'Bidra', 'artificial-image-generator' ),
		desc: __( 'Auctions for WooCommerce', 'artificial-image-generator' ),
		url: 'https://wordpress.org/plugins/bidra-auctions-for-woocommerce/',
	},
];

const PRO_FEATURES = [
	__( 'Rating, Progress bar and Badge layers', 'artificial-image-generator' ),
	__(
		'Review, sale, course, recipe and price tag designs',
		'artificial-image-generator'
	),
	__(
		'WooCommerce price, sale and rating merge tags',
		'artificial-image-generator'
	),
	__( 'Priority support and updates', 'artificial-image-generator' ),
];

function External( { href, className, children } ) {
	return (
		<a
			href={ href }
			className={ className }
			target="_blank"
			rel="noopener noreferrer"
		>
			{ children }
		</a>
	);
}

export default function Sidebar( { config } ) {
	const links = config.links || {};

	return (
		<aside className="aimg-settings__aside">
			{ ! config.proActive && links.pro && (
				<div className="aimg-settings__aside-card aimg-settings__aside-card--pro">
					<span className="aimg-settings__aside-badge">
						<span
							className="dashicons dashicons-star-filled"
							aria-hidden="true"
						/>
						{ __( 'Go Pro', 'artificial-image-generator' ) }
					</span>
					<h3>
						{ __(
							'Make richer images in fewer clicks',
							'artificial-image-generator'
						) }
					</h3>
					<p>
						{ __(
							'Image Generator Pro adds premium layers and ready-made designs to the Template Studio.',
							'artificial-image-generator'
						) }
					</p>
					<ul className="aimg-settings__aside-list">
						{ PRO_FEATURES.map( ( feature ) => (
							<li key={ feature }>
								<span
									className="dashicons dashicons-yes"
									aria-hidden="true"
								/>
								{ feature }
							</li>
						) ) }
					</ul>
					<External
						href={ links.pro }
						className="components-button is-primary aimg-settings__aside-button"
					>
						{ __( 'Upgrade to Pro', 'artificial-image-generator' ) }
					</External>
				</div>
			) }

			<div className="aimg-settings__aside-card">
				<h3>
					<span
						className="dashicons dashicons-sos"
						aria-hidden="true"
					/>
					{ __(
						'Support & Documentation',
						'artificial-image-generator'
					) }
				</h3>
				<p>
					{ __(
						'Have a question or run into an issue? Browse the docs or reach out. We are happy to help.',
						'artificial-image-generator'
					) }
				</p>
				<div className="aimg-settings__aside-links">
					{ links.docs && (
						<External href={ links.docs }>
							{ __(
								'Read the docs',
								'artificial-image-generator'
							) }{ ' ' }
							→
						</External>
					) }
					{ links.support && (
						<External href={ links.support }>
							{ __(
								'Get support',
								'artificial-image-generator'
							) }{ ' ' }
							→
						</External>
					) }
					{ links.review && (
						<External href={ links.review }>
							{ __(
								'Leave a review',
								'artificial-image-generator'
							) }{ ' ' }
							→
						</External>
					) }
				</div>
			</div>

			<div className="aimg-settings__aside-card">
				<h3>
					<span
						className="dashicons dashicons-admin-plugins"
						aria-hidden="true"
					/>
					{ __(
						'Popular plugins you may like',
						'artificial-image-generator'
					) }
				</h3>
				<ul className="aimg-settings__aside-plugins">
					{ PLUGINS.map( ( plugin ) => (
						<li key={ plugin.url }>
							<External href={ plugin.url }>
								<strong>{ plugin.title }</strong>
								<span>{ plugin.desc }</span>
							</External>
						</li>
					) ) }
				</ul>
			</div>
		</aside>
	);
}
