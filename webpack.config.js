const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const CopyWebpackPlugin = require('copy-webpack-plugin');
const path = require('path');
const RemoveEmptyScriptsPlugin = require('webpack-remove-empty-scripts');

module.exports = [
    {
        ...defaultConfig,
        entry: {
            ...defaultConfig.entry(),
            'css/admin': './src/css/admin.scss',
            'css/block-editor': './src/css/block-editor.scss',
            'css/media-library': './src/css/media-library.scss',
            'js/admin': './src/js/admin.js',
            'js/block-editor': './src/js/block-editor.js',
            'js/media-library': './src/js/media-library.js',
        },
        output: {
            ...defaultConfig.output,
            filename: '[name].js',
            path: __dirname + '/assets/',
        },
        plugins: [
            ...defaultConfig.plugins,
			// Copy images to the assets folder.
			new CopyWebpackPlugin({
				patterns: [
					// {
					// 	from: path.resolve(__dirname, 'src/images'),
					// 	to: path.resolve(__dirname, 'assets/images'),
					// },
					{
						from: path.resolve(__dirname, 'src/fonts'),
						to: path.resolve(__dirname, 'assets/fonts'),
					}
				]
			}),
            new RemoveEmptyScriptsPlugin({
                stage: RemoveEmptyScriptsPlugin.STAGE_AFTER_PROCESS_PLUGINS,
                remove: /\.(js)$/,
            }),
        ],
    },
];
