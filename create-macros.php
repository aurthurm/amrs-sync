<?php

$whonetPath = "/Users/amitdugar/Downloads/WHONET";

$whonetMacroFolder = "Macros";
$whonetOutputFolder = "Output";
$labname = "labname";

// Priority Pathogens
$organisms = "eco, kpn, aba, sau, spn, sal";

// Uncomment the following line if you want to include all pathogens
// $organisms =  "ALL";


$dir = opendir(realpath($whonetPath. DIRECTORY_SEPARATOR. $whonetOutputFolder));
clearstatcache();
$yesdate = strtotime("-1 days");


$dataFiles = array();

while (false != ($file = readdir($dir))) {
    if (in_array($file, array('.', '..', '.DS_Store'))) {
        continue;
    }

    if (substr($file, -4) == ".dbf") {
        if (filemtime($whonetPath. DIRECTORY_SEPARATOR. $whonetOutputFolder . DIRECTORY_SEPARATOR . $file) >= $yesdate) {
            $dataFiles[] =  "Data file = $file";
        }
    }
}


$dataFileString = implode(PHP_EOL,$dataFiles);

$resultsMacroContent = "Macro Name = {$labname}_results
Laboratory = $labname
Study = Isolate Listing
Study Antibiotics = All
Organisms = $organisms
Separate Files = True
{$dataFileString}
Output = DBASE File ({$labname}_results.dbf)";


$interpretationMacroContent = "Macro Name = {$labname}_interpretations
Laboratory = $labname
Study = Isolate Listing
Study Antibiotics = All
Options, Isolate Listing:  Test interpretations = True
Organisms = $organisms
Separate Files = True
{$dataFileString}
Output = DBASE File ({$labname}_interpretations.dbf)";


$fp = fopen($whonetPath . DIRECTORY_SEPARATOR . $whonetMacroFolder . DIRECTORY_SEPARATOR . $labname . "_" . "results.mcr", "wb");
fwrite($fp, $resultsMacroContent);
fclose($fp);

$fp = fopen($whonetPath . DIRECTORY_SEPARATOR . $whonetMacroFolder . DIRECTORY_SEPARATOR . $labname . "_" . "interpretations.mcr", "wb");
fwrite($fp, $interpretationMacroContent);
fclose($fp);
