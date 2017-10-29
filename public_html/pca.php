<?php
$auth_token = 'DOLORSITAmETjhDvECx7L01G0hEq67qVu1f9UARNlzvLjjoXdYuOAXl_fBpLfQDLKl3j02lH1Ci7iqtJRD4zQFw_SNis9Hsv9c';
$filename = 'pca.json';
if (file_exists($filename) && (time()-720 > filemtime($filename))) {
    echo file_get_contents($filename);
} else {
    output_pca($filename);
}

function output_pca($filename) {
    $html = file_get_contents('https://wiki.pnut.io/PCA');
    $doc = new DOMDocument();
    $doc->loadHTML($html);
    $tables = $doc->getElementsByTagName('table');

    $pca = array();

    foreach ($tables as $table) {
       if ($table->hasAttribute('class') && $table->getAttribute('class') == 'wikitable') {
            foreach ($table->childNodes as $childNode) {
                $entry = $childNode->getElementsByTagName('td');
                if ($entry->length > 0) {
                    $achievement["pca"] = preg_replace('/\s+/', '', $entry->item(0)->textContent);
                    $achievement["emoji"] = preg_replace('/\s+/', '', $entry->item(1)->textContent);
                    $achievement["post_count"] = preg_replace('/\s+/', '', $entry->item(2)->textContent);
                    $achievement["inventor"] = preg_replace('/\s+/', '', $entry->item(3)->textContent);
                    $pca[] = $achievement;
                }           
            }
       }
    }
    $myfile = fopen($filename, "w");
    $file_content = json_encode($pca);
    fwrite($myfile, $file_content);
    fclose($myfile);
    echo $file_content;
}
?>