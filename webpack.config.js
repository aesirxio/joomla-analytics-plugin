const path = require('path');
const { ProvidePlugin } = require('webpack');
const TerserPlugin = require('terser-webpack-plugin');
const WebpackAssetsManifest = require('webpack-assets-manifest');
const { GitRevisionPlugin } = require('git-revision-webpack-plugin');

module.exports = {
  mode: 'production',
  entry: {
    plugin: './assets/plugin/index.tsx',
    bi: './assets/bi/index.tsx',
  },
  module: {
    rules: [
      {
        test: /\.tsx?$/,
        use: 'ts-loader',
      },
      {
        test: /\.(sa|sc|c)ss$/,
        use: ['style-loader', 'css-loader', 'sass-loader'],
      },
      {
        test: /\.(gif|png|jpe?g|svg)$/i,
        type: 'asset/resource',
        generator: {
          filename: 'image/[contenthash][ext][query]',
        },
      },
      {
        test: /\.(woff|woff2|eot|ttf|otf)$/,
        type: 'asset/resource',
        generator: {
          filename: 'fonts/[contenthash][ext][query]',
        },
      },
    ],
  },

  plugins: [
    new ProvidePlugin({
      process: 'process/browser',
    }),
   
    new WebpackAssetsManifest({
      entrypoints: true,
    }),
  ],

  output: {
    filename: 'assets/bi/js/[contenthash].js',
    clean: true,
  },

  optimization: {
    minimizer: [
      new TerserPlugin({
        terserOptions: {
          compress: {
            drop_console: true,
          },
          format: {
            comments: false,
          },
        },
        extractComments: false,
      }),
    ],
    splitChunks: {
      chunks: 'all',
    },
  },
  resolve: {
    alias: {
      react$: require.resolve(path.resolve(__dirname, './node_modules/react')),
    },
    extensions: ['.tsx', '.ts', '.js'],
    fallback: { 'process/browser': require.resolve('process/browser') },
  },
};
