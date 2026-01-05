const path = require('path');
const TerserPlugin = require('terser-webpack-plugin');
const CssMinimizerPlugin = require('css-minimizer-webpack-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const RemoveEmptyScriptsPlugin = require('webpack-remove-empty-scripts');

module.exports = {
    mode: 'production',

    entry: {
        'js/dist/admin': './assets/js/src/admin.js',
        'js/dist/admin-product-manager': './assets/js/src/admin-product-manager.js',
        'js/dist/admin-settings': './assets/js/src/admin-settings.js',
        'css/dist/admin': './assets/css/src/admin.css',
        'css/dist/admin-product-manager': './assets/css/src/admin-product-manager.css',
        'css/dist/admin-wizard': './assets/css/src/admin-wizard.css',
        'css/dist/analytics-dashboard': './assets/css/src/analytics-dashboard.css',
        'css/dist/logs-viewer': './assets/css/src/logs-viewer.css',
    },

    output: {
        path: path.resolve(__dirname, 'assets'),
        filename: '[name].min.js',
        clean: false, // Don't clean to preserve CSS output
    },

    module: {
        rules: [
            {
                test: /\.js$/,
                exclude: /node_modules/,
                use: {
                    loader: 'babel-loader',
                    options: {
                        presets: ['@babel/preset-env']
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
