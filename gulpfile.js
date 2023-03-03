const zip = require("gulp-zip")
const gulp = require('gulp')

async function cleanTask() {
    const del = await import("del")
    return del.deleteAsync('./dist/plugin/**', {force:true});
}

function moveMediaFolderTask() {
    return gulp.src([
        './media/plg_system_aesirx_analytics/**',
        '!./media/plg_system_aesirx_analytics/src/**'
    ]).pipe(gulp.dest('./dist/plugin/media'))
}

function movePluginFolderTask() {
    return gulp.src([
        './plugins/system/aesirx_analytics/**',
    ]).pipe(gulp.dest('./dist/plugin'))
}

function compressTask() {
    return gulp.src('./dist/plugin/**')
        .pipe(zip('plg_system_aesirx_analytics.zip'))
        .pipe(gulp.dest('./dist'));
}

exports.zip = gulp.series(
    cleanTask,
    gulp.parallel(
        moveMediaFolderTask,
        movePluginFolderTask
    ),
    compressTask,
    cleanTask
);
