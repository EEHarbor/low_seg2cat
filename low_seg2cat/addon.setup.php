<?php

require_once 'autoload.php';
$addonJson = json_decode(file_get_contents(__DIR__ . '/addon.json'));

return array(
    'name'              => $addonJson->name,
    'description'       => $addonJson->description,
    'version'           => $addonJson->version,
    'namespace'         => $addonJson->namespace,
    'author'            => 'EEHarbor',
    'author_url'        => 'http://eeharbor.com/low_seg2cat',
    'docs_url'          => 'http://eeharbor.com/low_seg2cat/documentation',
    'settings_exist'    => true,
);
