<?php
$url = 'https://support.getshifter.io/articles/3086606-incompatible-wordpress-plugins';
$incompatiblePlugins = [
    'supportUrl' => $url,
    'plugins' => [],
];
$dom = new DOMDocument;
@$dom->loadHTMLFile($url);
$xpath = new DOMXPath($dom);
$xpathQuery = '//article[@dir="ltr"]/ul[2]/li';
foreach ($xpath->query($xpathQuery) as $node) {
    $incompatiblePlugin = strtolower(str_replace(' ', '-', $node->nodeValue));
    if (! in_array($incompatiblePlugin, $incompatiblePlugins)) {
        $incompatiblePlugins['plugins'][] = $incompatiblePlugin;
    }
}
echo json_encode($incompatiblePlugins);