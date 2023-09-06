const fs = require('fs');
const zip = require('gulp-zip');
const gulp = require('gulp');
const composer = require('gulp-composer');
const webpack = require('webpack-stream');
const {watch, series, parallel} = require('gulp');
const _ = require('lodash');
const path = require('path');
var rename = require("gulp-rename");

require('dotenv').config();

var dist = './dist';
process.env.DIST = dist;

async function cleanTask() {
	const del = await import('del');
	return del.deleteAsync(`${dist}/**`, {force: true});
}

function movePackageFiles() {
	return gulp
		.src(['./extensions/*'], {"nodir": true})
		.pipe(gulp.dest(`${dist}`));
}

function zipPackage() {
	return gulp.src([`${dist}/**`, `!${dist}/pkg_aesirx_analytics.zip`])
		.pipe(zip(`pkg_aesirx_analytics.zip`))
		.pipe(gulp.dest(`${dist}`))
}

function zipComponents() {
	const dirMain = './extensions/components';
	const readDirMain = fs.readdirSync(dirMain);
	let list = [];
	readDirMain.forEach((dirNext) => {
		if (fs.lstatSync(dirMain + "/" + dirNext).isDirectory()) {
			let fileName = dirNext.replace("com_", "");
			list.push(
				function () {
					return gulp.src(`./extensions/components/${dirNext}/**`, {"base": `./extensions/components/${dirNext}/`})
						.pipe(rename(function (path) {
							if (path.basename === fileName
								&& path.dirname === 'administrator'
								&& path.extname === '.xml') {
								path.dirname = '';
							}
						}))
						.pipe(zip(`${dirNext}.zip`))
						.pipe(gulp.dest(`${dist}/packages`))
				}
			);
		}
	});
	return list
}

const customMethods = {
	before_zip_system_aesirx_analytics() {
		return parallel(
			function () {
				return gulp
					.src(['./node_modules/aesirx-analytics/dist/analytics.js'])
					.pipe(gulp.dest(`./extensions/plugins/system/aesirx_analytics/media/assets/js`));
			},
			function () {
				return gulp
					.src('./assets/bi/index.tsx')
					.pipe(webpack(require('./webpack.config.js')))
					.pipe(gulp.dest(`./extensions/plugins/system/aesirx_analytics/media`));
			},
			function () {
				return gulp
					.src('./node_modules/aesirx-bi-app/public/assets/images/**')
					.pipe(gulp.dest(`./extensions/plugins/system/aesirx_analytics/media/assets/images`));
			},
			function () {
				return gulp
					.src('./node_modules/aesirx-bi-app/public/assets/data/**')
					.pipe(gulp.dest(`./extensions/plugins/system/aesirx_analytics/media/assets/data`));
			}
		);
	},
	async after_zip_system_aesirx_analytics() {
		const del = await import('del');
		return del.deleteAsync(`./extensions/plugins/system/aesirx_analytics/media`, {force: true});
	}
}

function zipPlugins() {
	const dirMain = './extensions/plugins';
	let list = [];
	fs.readdirSync(dirMain)
		.forEach((dirNext) => {
			if (fs.lstatSync(dirMain + "/" + dirNext).isDirectory()) {
				fs.readdirSync(dirMain + "/" + dirNext)
					.forEach((dirNext2) => {
						let steps = [];
						if (fs.lstatSync(`./extensions/plugins/${dirNext}/${dirNext2}/composer.json`).isFile()) {
							steps.push(function () {
								return composer({
									'working-dir': `./extensions/plugins/${dirNext}/${dirNext2}`,
									'no-dev': true,
								})
							});
						}

						let custom_function = `before_zip_${dirNext}_${dirNext2}`;

						if (typeof customMethods[custom_function] === 'function') {
							steps.push(customMethods[custom_function]());
						}

						steps.push(function () {
							return gulp.src(
								`./extensions/plugins/${dirNext}/${dirNext2}/**`,
								{"base": `./extensions/plugins/${dirNext}/${dirNext2}/`}
							)
								.pipe(zip(`plg_${dirNext}_${dirNext2}.zip`))
								.pipe(gulp.dest(`${dist}/packages`))
						});

						custom_function = `after_zip_${dirNext}_${dirNext2}`;

						if (typeof customMethods[custom_function] === 'function') {
							steps.push(customMethods[custom_function]);
						}

						list.push(series(...steps))
					});
			}
		});

	return list
}

function moveAnalyticJSTask() {
	return gulp
		.src(['./node_modules/aesirx-analytics/dist/analytics.js'])
		.pipe(gulp.dest(`${dist}/media/plg_system_aesirx_analytics/assets/js`));
}

function webpackBIApp() {
	return gulp
		.src('./assets/bi/index.tsx')
		.pipe(webpack(require('./webpack.config.js')))
		.pipe(gulp.dest(`${dist}/media/plg_system_aesirx_analytics`));
}

function webpackBIAppWatch() {
	return gulp
		.src('./assets/bi/index.tsx')
		.pipe(webpack(_.merge(require('./webpack.config.js'), {watch: true})))
		.pipe(gulp.dest(`${dist}/media/plg_system_aesirx_analytics`));
}

exports.zip = series(
	cleanTask,
	parallel(
		movePackageFiles,
		...zipComponents(),
		...zipPlugins(),
	),
	zipPackage,
);

Array.prototype.move = function (from, to) {
	this.splice(to, 0, this.splice(from, 1)[0]);
	return this;
};

exports.watch = series(
	async function (cb) {
		dist = process.env.WWW;
		process.env.DIST = dist;
		console.info(`Update all`);

		const del = await import('del');
		await del.deleteAsync([
			`${dist}/media/plg_system_aesirx_analytics/**`,
			`${dist}/plugins/system/aesirx_analytics/**`,
		], {force: true});
		cb();
	},
	parallel(
		moveAnalyticJSTask,
		function () {
			return gulp
				.src('./node_modules/aesirx-bi-app/public/assets/images/**')
				.pipe(gulp.dest(`${dist}/media/plg_system_aesirx_analytics/assets/images/`));
		},
		function () {
			return gulp
				.src('./node_modules/aesirx-bi-app/public/assets/data/**')
				.pipe(gulp.dest(`${dist}/media/plg_system_aesirx_analytics/assets/data/`));
		},
		webpackBIApp,
		function () {
			return gulp.src('./extensions/components/**', {"base": "./extensions/", "nodir": true})
				.pipe(rename(function (path) {
					let arr = path.dirname.split('/');
					arr.move(2, 0);
					path.dirname = arr.join('/')
				}))
				.pipe(gulp.dest(dist));
		},
		series(
			function () {
				return composer({
					'working-dir': './extensions/plugins/system/aesirx_analytics',
					'no-dev': true,
				})
			},
			function () {
				return gulp.src('./extensions/plugins/**', {"base": "./extensions/"})
					.pipe(gulp.dest(dist));
			},
			function () {
				return composer({
					'working-dir': './extensions/plugins/system/aesirx_analytics',
				})
			},
		),
	),
	parallel(
		webpackBIAppWatch,
		function (cb) {
			const pluginsWatcher = watch([
				'./extensions/plugins/**',
				'!./extensions/plugins/system/aesirx_analytics/vendor',
			]);

			pluginsWatcher.on('change', function (filePath, stats) {
				console.info(`File ${filePath} was changed`);
				return gulp.src(filePath, {"base": "./extensions/"})
					.pipe(gulp.dest(dist));
			});

			pluginsWatcher.on('add', function (filePath, stats) {
				console.info(`File ${filePath} was added`);
				return gulp.src(filePath, {"base": "./extensions/"})
					.pipe(gulp.dest(dist));
			});

			pluginsWatcher.on('unlink', async function (filePath, stats) {
				console.info(`File ${filePath} was removed`);
				const filePathFromSrc = path.relative(path.resolve('./extensions'), filePath);
				const destFilePath = path.resolve(dist, filePathFromSrc);
				const del = await import('del');
				return del.deleteAsync(destFilePath, {force: true});
			});

			cb();
		},
		function (cb) {
			const componentsWatcher = watch([
				'./extensions/components/**',
			]);

			componentsWatcher.on('change', function (filePath, stats) {
				console.info(`File ${filePath} was changed`);

				return gulp.src(filePath, {"base": "./extensions/"})
					.pipe(rename(function (path) {
						let arr = path.dirname.split('/');
						arr.move(2, 0);
						path.dirname = arr.join('/')
					}))
					.pipe(gulp.dest(dist));
			});

			componentsWatcher.on('add', function (filePath, stats) {
				console.info(`File ${filePath} was added`);
				return gulp.src(filePath, {"base": "./extensions/"})
					.pipe(rename(function (path) {
						let arr = path.dirname.split('/');
						arr.move(2, 0);
						path.dirname = arr.join('/')
					}))
					.pipe(gulp.dest(dist));
			});

			componentsWatcher.on('unlink', async function (filePath, stats) {
				console.info(`File ${filePath} was removed`);
				let filePathFromSrc = path.relative(path.resolve('./extensions'), filePath);
				let arr = filePathFromSrc.split('/');
				arr.move(2, 0);
				filePathFromSrc = arr.join('/')
				const destFilePath = path.resolve(dist, filePathFromSrc);

				const del = await import('del');
				return del.deleteAsync(destFilePath, {force: true});
			});

			cb();
		}
	),
);
