<?php

use Galaxia\Director;
use Galaxia\Flash;
use GalaxiaEditor\input\Input;


$editor->view = 'image/resize/resize';



// item validation

foreach ($inputs as $inputKey => $input) {
    if (!isset($_POST[$input['name']])) {
        $input['errors'][] = 'Required.';
        continue;
    }
    $value = $_POST[$input['name']];
    $input = Input::validate($input, $value);
    $inputs[$inputKey] = $input;
}

foreach ($inputs as $input) {
    foreach ($input['errors'] as $msg) {
        Flash::error($msg, 'form', $input['name']);
        if ($input['lang']) {
            $langSelectClass[$input['lang']] = 'btn-red';
        }
    }
}

if (Flash::hasError()) return;




// resize images

$_FILES['images']['name'][0] = $imgSlug;
$uploaded = $app->imageUpload([$app->dirImage . $imgSlug . '/' . $imgSlug . $img['ext'] => $imgSlug], true, $_POST['resize']);




// finish

$app->cacheDelete(['app', 'fastroute']);
$app->cacheDelete('editor', 'imageList-' . $pgSlug);
Director::redirect('edit/' . $pgSlug . '/' . $imgSlug);
