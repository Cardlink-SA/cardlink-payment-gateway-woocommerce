const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const WooCommerceDependencyExtractionWebpackPlugin = require('@woocommerce/dependency-extraction-webpack-plugin');
const path = require('path');

const wcDepMap = {
    '@woocommerce/blocks-registry': ['wc', 'wcBlocksRegistry'],
    '@woocommerce/settings': ['wc', 'wcSettings'],
    '@woocommerce/block-data': ['wc', 'wcBlocksData'],
};

const wcHandleMap = {
    '@woocommerce/blocks-registry': 'wc-blocks-registry',
    '@woocommerce/settings': 'wc-settings',
    '@woocommerce/block-data': 'wc-blocks-data-store',
};

const requestToExternal = (request) => wcDepMap[request];
const requestToHandle = (request) => wcHandleMap[request];

module.exports = {
    ...defaultConfig,
    entry: {
        'cardlink-block': path.resolve(__dirname, 'assets/js/blocks/cardlink-block.js'),
        'iris-block': path.resolve(__dirname, 'assets/js/blocks/iris-block.js'),
    },
    output: {
        path: path.resolve(__dirname, 'assets/js/blocks/build'),
        filename: '[name].min.js',
    },
    plugins: [
        ...defaultConfig.plugins.filter(
            (plugin) => plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
        ),
        new WooCommerceDependencyExtractionWebpackPlugin({
            requestToExternal,
            requestToHandle,
        }),
    ],
};
