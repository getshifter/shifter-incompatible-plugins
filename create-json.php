<?php
$unrecommendedPlugins = [];
$url = 'https://support.getshifter.io/articles/2833720-unrecommended-wordpress-plugins';
$dom = new DOMDocument;
@$dom->loadHTMLFile($url);
$xpath = new DOMXPath($dom);
$xpathQuery = '//article[@dir="ltr"]/ul[2]/li';
foreach ($xpath->query($xpathQuery) as $node) {
    $unrecommendedPlugin = strtolower(str_replace(' ', '-', $node->nodeValue));
    if (! in_array($unrecommendedPlugin, $unrecommendedPlugins)) {
        $unrecommendedPlugins[] = $unrecommendedPlugin;
    }
}
echo json_encode($unrecommendedPlugins);