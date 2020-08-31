<?php


$pgTitle = t('+ Add') . ' ' . t($geConf[$pgSlug]['gcTitleSingle']);
$hdTitle = t('+ Add') . ' ' . t($geConf[$pgSlug]['gcTitleSingle']);


// query extras

$extras = [];
foreach ($item['gcSelectExtra'] as $table => $cols) {
    $query = querySelect([$table => $cols]);
    $query .= querySelectOrderBy([$table => [$cols[1] => 'ASC']]);

    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($extraData = $result->fetch_assoc()) {
        $extraData = array_map('strval', $extraData);
        $extras[$table][] = $extraData;
    }
    $stmt->close();
}




foreach ($item['gcInputs'] as $inputKey => $input) {
    $input = prepareInput($input, $extras);

    $item['inputs'][$inputKey] = array_merge($input, [
        'label'       => $input['label'] ?? $geConf[$pgSlug]['gcColNames'][$inputKey] ?? $inputKey,
        'name'        => 'item[' . $inputKey . ']',
        'nameFromDb'  => $inputKey,
    ]);
    if ($input['type'] == 'timestamp') $item['inputs'][$inputKey]['value'] = date('Y-m-d 00:00');
    if ($input['type'] == 'datetime')  $item['inputs'][$inputKey]['value'] = date('Y-m-d 00:00');

    if (isset($input['lang'])) $showSwitchesLang = true;
}
