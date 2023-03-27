const zip = require("gulp-zip");
const gulp = require("gulp");

async function cleanTask() {
  const del = await import("del");
  return del.deleteAsync("./dist/plugin/**", { force: true });
}

function moveMediaFolderTask() {
  return gulp
    .src(["./node_modules/aesirx-analytics/build/analytics.js"])
    .pipe(gulp.dest("./dist/plugin/media/js"));
}

function movePluginFolderTask() {
  return gulp
    .src(["./plugins/system/aesirx_analytics/**"])
    .pipe(gulp.dest("./dist/plugin"));
}

function compressTask() {
  return gulp
    .src("./dist/plugin/**")
    .pipe(zip("plg_system_aesirx_analytics.zip"))
    .pipe(gulp.dest("./dist"));
}

exports.zip = gulp.series(
  cleanTask,
  gulp.parallel(moveMediaFolderTask, movePluginFolderTask),
  compressTask,
  cleanTask
);
