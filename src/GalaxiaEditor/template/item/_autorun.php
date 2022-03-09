<?php


// variables

use Galaxia\Flash;
use Galaxia\G;
use Galaxia\Sql;
use Galaxia\Text;
use GalaxiaEditor\E;
use GalaxiaEditor\input\Input;


E::$uniqueId     = uniqid(true);
E::$item         = &E::$section['gcItem'];

foreach (G::locales() as $lang => $locale) {
    E::$langSelectClass[$lang] = '';
}
E::$includeTrix   = true;
$querySelectWhere = [E::$item['gcTable'] => [E::$item['gcTable'] . 'Id' => '=']];




// skip for new item page

if (E::$itemId == 'new') return;




// restrict edit acces to only own user

if (E::$item['gcUpdateOnlyOwn'] ?? false) {
    if (!G::isDev() && G::$me->id != E::$itemId) {
        Flash::error(Text::t('Redirected. You don\'t have access to that page.'));
        G::redirect('/edit/' . G::$editor->homeSlug);
    }
}




// item validation

$query = Sql::selectOne(E::$item['gcSelect']);
$query .= Sql::selectWhere($querySelectWhere);
$query .= Sql::selectLimit(0, 1);

$stmt = G::prepare($query);
$stmt->bind_param('d', E::$itemId);
$stmt->bind_result($itemExists);
$stmt->execute();
$stmt->fetch();
$stmt->close();

if (!$itemExists) {
    Flash::error(sprintf(Text::t('%s with id %s does not exist.'), Text::t(E::$section['gcTitleSingle']), Text::h(E::$itemId)));
    G::redirect('edit/' . E::$pgSlug);
}




// query item

$query = Sql::select(E::$item['gcSelect']);
$query .= Sql::selectLeftJoinUsing(E::$item['gcSelectLJoin'] ?? []);
$query .= Sql::selectWhere($querySelectWhere);

$stmt = G::prepare($query);
$stmt->bind_param('d', E::$itemId);
$stmt->execute();
$result = $stmt->get_result();
$data   = $result->fetch_assoc();
$stmt->close();

E::$item['data'] = array_map(function($value) {
    return ($value === null) ? null : strval($value);
}, $data);




// query extras

$extras = [];
foreach (E::$item['gcSelectExtra'] as $table => $cols) {
    $query = Sql::select([$table => $cols]);
    $query .= Sql::selectOrderBy([$table => [$cols[1] => 'ASC']]);
    $stmt  = G::prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($extraData = $result->fetch_assoc()) {
        $extraData        = array_map('strval', $extraData);
        $extras[$table][] = $extraData;
    }
    $stmt->close();
}



foreach (E::$item['gcInputsWhere'] as $colKey => $col) {
    if (!isset(E::$item['data'][$colKey])) continue;
    foreach ($col as $colVal => $inputs) {
        if (E::$item['data'][$colKey] != $colVal) continue;
        foreach ($inputs as $inputKey => $input) {
            if (!isset(E::$item['gcInputs'][$inputKey])) continue;
            E::$item['gcInputs'][$inputKey] = array_merge(E::$item['gcInputs'][$inputKey], $input);
        }
    }
}


foreach (E::$item['gcInputs'] as $inputKey => $input) {
    if (empty($input)) continue;
    if ($input['type'] == 'status' && !isset($input['options'][E::$item['data'][$inputKey]])) continue;

    $input = Input::prepare($input, $extras);

    if ($input['type'] == 'password' || str_starts_with($inputKey, 'password')) E::$passwordColsFound = true;
    if (isset($input['lang']) && count(G::langs()) > 1) E::$showSwitchesLang = true;

    $inputNew = [
        'label'      => $input['label'] ?? E::$section['gcColNames'][$inputKey] ?? $inputKey,
        'name'       => 'item[' . $inputKey . ']',
        'nameFromDb' => $inputKey,
    ];

    if (!E::$firstStatus && $input['type'] == 'status') E::$firstStatus = $inputKey;

    if (array_key_exists($inputKey, E::$item['data'])) {
        $value = E::$item['data'][$inputKey];
        if ($input['type'] == 'timestamp') $value = date('Y-m-d H:i:s', E::$item['data'][$inputKey]);
        if (str_starts_with($inputKey, 'password')) $value = '';
        $inputNew['value']       = $value;
        $inputNew['valueFromDb'] = $value;
    }

    E::$item['inputs'][$inputKey] = array_merge($input, $inputNew);
}




foreach (E::$item['gcInfo'] as $inputKey => $input) {
    E::$item['gcInfo'][$inputKey]['label'] = E::$section['gcColNames'][$inputKey] ?? $inputKey;

    $value = E::$item['data'][$inputKey];
    if ($input['type'] == 'timestamp')
        if (!empty($value)) $value = Text::formatDate(E::$item['data'][$inputKey], 'd MMM y - HH:mm');
    E::$item['gcInfo'][$inputKey]['value'] = $value;
}


$titleTemp = 'Item';
if (is_array(E::$item['gcColKey'])) {
    foreach (E::$item['gcColKey'] as $i => $val) {
        if (empty(E::$item['data'][$val] ?? '')) continue;
        E::$item['gcColKey'] = E::$item['gcColKey'][$i];
        break;
    }
    if (is_array(E::$item['gcColKey'])) E::$item['gcColKey'] = E::$item['gcColKey'][array_key_first(E::$item['gcColKey'])];
}

$titleTemp = E::$item['data'][E::$item['gcColKey']];
if (empty($titleTemp)) $titleTemp = E::$itemId;
if (str_starts_with(E::$item['gcColKey'], 'timestamp')) $titleTemp = Text::formatDate($titleTemp, 'd MMM y - HH:mm');
E::$pgTitle = Text::t(E::$section['gcTitleSingle']) . ': ' . $titleTemp;
E::$hdTitle = Text::t('Editing') . ': ' . E::$pgTitle;




// add redirect module

if (E::$item['gcRedirect']) {
    $table = E::$section['gcItem']['gcTable'];

    E::$section['gcItem']['gcModules'][] = [
        'gcTable'               => $table . 'Redirect',
        'gcModuleType'          => 'fields',
        'gcModuleTitle'         => '',
        'gcModuleShowUnused'    => ['gcPerms' => ['dev']],
        'gcModuleDeleteIfEmpty' => [$table . 'RedirectSlug'],
        'gcModuleMultiple'      => ['Redirect' => ['reorder' => false, 'unique' => [$table . 'RedirectSlug'], 'label' => 'Redirects']],

        'gcSelect'        => [$table . 'Redirect' => [$table . 'RedirectId', 'fieldKey', $table . 'RedirectSlug', 'position']],
        'gcSelectLJoin'   => [],
        'gcSelectOrderBy' => [],
        'gcSelectExtra'   => [],
        'gcUpdate'        => [$table . 'Redirect' => [$table . 'RedirectSlug']],

        'gcInputs' => [$table . 'RedirectSlug' => ['label' => 'Slug', 'type' => 'slug', 'dbUnique' => true]],

        'gcInputsWhereCol' => [
            'Redirect' => [$table . 'RedirectSlug' => ['type' => 'text']],
        ],

        'gcInputsWhereParent' => [],
    ];
}


// query modules

E::$modules = &E::$section['gcItem']['gcModules'];

foreach (E::$modules as E::$moduleKey => &E::$module) {
    switch (E::$module['gcModuleType']) {
        case 'fields':
            include G::$editor->dirView . 'item/modules/fields.php';
            break;
        default:
            geErrorPage(500, 'invalid module');
            break;
    }
}

