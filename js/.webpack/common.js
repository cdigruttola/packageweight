/**
 * Copyright since 2007 Carmine Di Gruttola
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 *  @author    cdigruttola <c.digruttola@hotmail.it>
 *  @copyright Copyright since 2007 Carmine Di Gruttola
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *
 */

const path = require('path');
const webpack = require('webpack');
const keepLicense = require('uglify-save-license');
const TerserPlugin = require('terser-webpack-plugin');
const {merge} = require('webpack-merge');

const psRootDir = path.resolve(process.env.PWD, '../../../admin-dev/themes/new-theme/');
const psJsDir = path.resolve(psRootDir, 'js');

module.exports = {
    entry: {
        index: '../js/admin/index',
    }, output: {
        path: path.resolve(__dirname, '../../views/js'), filename: '[name].bundle.js', publicPath: 'public',
    }, // devtool: 'source-map', // uncomment me to build source maps (really slow)
    resolve: {
        extensions: ['.js', '.ts'], alias: {
            '@PSJs': psJsDir, '@app': psJsDir + '/app', '@components': psJsDir + '/components', '@PSTypes': psJsDir + '/types',
        },
    }, module: {
        rules: [{
            test: /\.js$/, include: path.resolve(__dirname, 'js'), loader: 'esbuild-loader', options: {
                loader: 'jsx', target: 'es2015',
            },
        }, {
            test: /\.ts?$/, loader: 'ts-loader', options: {
                onlyCompileBundledFiles: true,
            }, exclude: /node_modules/,
        },],
    }, plugins: [],
}
