const path = require('path');
const TerserPlugin = require('terser-webpack-plugin');
const CssMinimizerPlugin = require('css-minimizer-webpack-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const RemoveEmptyScriptsPlugin = require('webpack-remove-empty-scripts');

module.exports = {
    mode: 'production',

    entry: {
        // React admin app: one bundle that renders the screen named in the
        // PHP-localized boot object. React / i18n / api-fetch resolve to the
        // WordPress script globals (see externals); @carticy/admin-ui is the
        // shared design system compiled from the Composer-installed package.
        'js/dist/admin-app': './assets/js/src/admin-app/index.jsx',
        // Plugin-specific admin styles (app shell + screen helper classes). The
        // design-system tokens/components/chrome ship from vendor/carticy/admin-ui.
        'css/dist/admin': './assets/css/src/admin.css',
    },

    output: {
        path: path.resolve(__dirname, 'assets'),
        filename: '[name].min.js',
        clean: false, // Don't clean to preserve CSS output
    },

    externals: {
        '@wordpress/api-fetch': 'wp.apiFetch',
        '@wordpress/element': 'wp.element',
        '@wordpress/i18n': 'wp.i18n',
    },

    resolve: {
        extensions: ['.js', '.jsx'],
        alias: {
            '@carticy/admin-ui': path.resolve(__dirname, 'vendor/carticy/admin-ui/js/src'),
        },
    },

    module: {
        rules: [
            {
                test: /\.jsx?$/,
                exclude: /node_modules/,
                use: {
                    loader: 'babel-loader',
                    options: {
                        presets: [
                            ['@babel/preset-env', { targets: 'defaults' }],
                            ['@babel/preset-react', {
                                runtime: 'classic',
                                pragma: 'wp.element.createElement',
                                pragmaFrag: 'wp.element.Fragment',
                            }],
                        ],
                    }
                }
            },
            {
                test: /\.css$/,
                use: [
                    MiniCssExtractPlugin.loader,
                    'css-loader'
                ]
            }
        ]
    },

    plugins: [
        new RemoveEmptyScriptsPlugin(),
        new MiniCssExtractPlugin({
            filename: '[name].min.css'
        })
    ],

    optimization: {
        minimize: true,
        minimizer: [
            new TerserPlugin({
                terserOptions: {
                    compress: {
                        drop_console: true,      // Remove console.log statements
                        drop_debugger: true,     // Remove debugger statements
                        dead_code: true,         // Remove unreachable code
                        unused: true,            // Remove unused variables
                    },
                    mangle: {
                        reserved: ['jQuery', '$', 'wp', 'wc'], // Don't mangle WordPress/WooCommerce globals
                    },
                    format: {
                        comments: false,         // Remove all comments
                    },
                },
                extractComments: false,          // Don't extract comments to separate file
            }),
            new CssMinimizerPlugin({
                minimizerOptions: {
                    preset: [
                        'default',
                        {
                            discardComments: { removeAll: true }, // Remove comments
                        },
                    ],
                },
            }),
        ],
    },

    // Source maps for debugging (disable in production)
    devtool: false,

    // Performance hints
    performance: {
        hints: false,
        maxEntrypointSize: 512000,
        maxAssetSize: 512000
    }
};
