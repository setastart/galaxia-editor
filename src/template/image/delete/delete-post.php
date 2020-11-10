<?php

use Galaxia\AppImage;
use Galaxia\Director;


$editor->view = 'image/delete/delete';




// item validation

if (!AppImage::delete($app->dirImage, $imgSlug)) {
    error('image-delete-post - Unable to delete image: ' . h($imgSlug));
    return;
}




// finish

$app->cacheDelete(['app', 'fastroute']);
$app->cacheDelete('editor', 'imageList-' . $pgSlug);
info('Deleted image: ' . h($imgSlug));
Director::redirect('edit/' . $pgSlug);
