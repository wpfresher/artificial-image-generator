const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const CopyWebpackPlugin = require('copy-webpack-plugin');
const path = require('path');
const RemoveEmptyScriptsPlugin = require('webpack-remove-empty-scripts');

module.exports = [
    {
        ...defaultConfig,
        entry: {
            ...defaultConfig.entry(),
            'css/admin': './resources/css/admin.scss',
            'js/admin': './resources/js/admin.js',
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
					// 	from: path.resolve(__dirname, 'resources/images'),
					// 	to: path.resolve(__dirname, 'assets/images'),
					// },
					{
						from: path.resolve(__dirname, 'resources/fonts'),
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
