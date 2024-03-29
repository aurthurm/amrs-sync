<?php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config.php';

use XBase\Table;
use XBase\Record;
use Ulrichsg\Getopt\Getopt;
use Ulrichsg\Getopt\Option;


$mysqli          = new mysqli($localhost, $localUserName, $localPassword, $amrsTempDb, $localPort);

$knownDateFields = array('date_birth', 'spec_date', 'date_data');


function usage($errormessage = "error")
{
    global $argv;
    echo "\n$errormessage\n\n";
    echo "Usage: $argv[0] [-e encoding] [-b batchsize] [-d destinationdir] source_file [another_source_file [...]]\n\n";
    echo "Default encoding is utf-8, often used encoding in dbf files is CP1250, or CP1251.\n";
    echo "Default batch size is 1000 rows inserted at once.\n";
    echo "Default destination directory is source file's one.\n\n";
}

$encOption = new Option('e', 'encoding', Getopt::REQUIRED_ARGUMENT);
$batchOption = new Option('b', 'batchsize', Getopt::REQUIRED_ARGUMENT);
$destdirOption = new Option('d', 'destinationdir', Getopt::REQUIRED_ARGUMENT);
$getopt = new Getopt([$encOption, $batchOption, $destdirOption]);
$getopt->parse();
$encoding = $getopt["encoding"] ? $getopt["encoding"] : "UTF-8";
$batchSize = $getopt["batchsize"] ? $getopt["batchsize"] : 1000;
$destDir = $getopt["destinationdir"] ? $getopt["destinationdir"] : false;
$operands = $getopt->getOperands();

if (count($operands) == 0) {
    usage("Missing parameters");
    exit;
}

if ($destDir && !is_writable($destDir)) {
    echo "Destination directory {$destDir} does not exist or is not writable!\n";
    exit;
}

foreach ($operands as $sourcefile) {
    //TODO: make the code objective
    $pathInfo = pathinfo($sourcefile);
    $destinationfile = ($destDir ? $destDir : $pathInfo['dirname']) . "/" . $pathInfo['filename'] . ".sql";
    $destination = fopen($destinationfile, 'w');
    $source = new Table($sourcefile, null, $encoding);

    error_log("Processing " . $source->getRecordCount() . " records from file $sourcefile using $encoding encoding" . PHP_EOL);

    $tableName = basename(strtolower($source->getName()), ".dbf");
    $tableName = str_replace("-", "_", $tableName);

    $dropString = "DROP TABLE IF EXISTS " . escName($tableName) . ";";
    if (!$mysqli->query($dropString)) {
        echo "ERROR: Could not execute \"$dropString\". " . $mysqli->error;
    }
    $createString = "CREATE TABLE IF NOT EXISTS " . escName($tableName) . "(";
    foreach ($source->getColumns() as $column) {
        $type = mapTypeToSql($column->getType(), $column->getLength(), $column->getDecimalCount());
        if (($column->getType() == Record::DBFFIELD_TYPE_MEMO)
            || ($column->getName() == "_nullflags")
            || ($type === false)
        ) {
            continue;
        }
        $createString .= "" . ($column->getName()) . " $type,\n";
    }
    $createString = substr($createString, 0, -2) . ") CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    //fwrite($destination, $createString);

    if (!$mysqli->query($createString)) {
        echo "ERROR: Could not execute \"$createString\". " . $mysqli->error;
    }

    $rows = 0;
    while ($record = $source->nextRecord()) {
        if ($record->isDeleted()) {
            continue;
        }
        if ($rows == 0) {
            $insertLine = "INSERT INTO " . escName($tableName) . " VALUES \n";
        } else {
            $insertLine .= ",\n";
        }
        $row = "\t(";
        foreach ($source->getColumns() as $column) {
            $type = mapTypeToSql($column->getType(), $column->getLength(), $column->getDecimalCount());
            if (($column->getType() == Record::DBFFIELD_TYPE_MEMO)
                || ($column->getName() == "_nullflags")
                || ($type === false)
            ) {
                continue;
            }
            $cell = $record->getObject($column);

            if ((in_array($column->getName(), $knownDateFields)) && $cell) {
                $cell = date('Y-m-d', $cell - 3600);
            } else if (($column->getType() == Record::DBFFIELD_TYPE_DATETIME) && $cell) {
                $cell = date('Y-m-d H:i:s', $cell - 3600);
            }
            $row .= "\"" . addslashes($cell) . "\",";
        }
        $row = substr($row, 0, -1) . ")";
        $insertLine .= $row;
        if ($rows + 1 == $batchSize) {
            $insertLine .= ";\n\n";
            $rows = 0;
            fwrite($destination, $insertLine);
            $insertLine = "";
        } else {
            $rows++;
        }
    }
    if (!empty($insertLine)) {
        $insertLine .= ";\n\n";
        //fwrite($destination, $insertLine);




        //

        //echo $insertLine;die;
        if (!$mysqli->query($insertLine)) {
            echo "ERROR: Could not execute \"$createString\". " . $mysqli->error;
        }
    }
    fclose($destination);
    error_log("Completed conversion" . PHP_EOL);
}

function mapTypeToSql($type_short, $length, $decimal)
{
    $types = [
        Record::DBFFIELD_TYPE_MEMO => "TEXT",                        // Memo type field
        Record::DBFFIELD_TYPE_CHAR => "VARCHAR(255)",            // Character field
        Record::DBFFIELD_TYPE_DOUBLE => "DOUBLE($length,$decimal)",  // Double
        Record::DBFFIELD_TYPE_NUMERIC => "INTEGER",                  // Numeric
        Record::DBFFIELD_TYPE_FLOATING => "FLOAT($length,$decimal)", // Floating point
        Record::DBFFIELD_TYPE_DATE => "DATE",                        // Date
        Record::DBFFIELD_TYPE_LOGICAL => "TINYINT(1)",               // Logical - ? Y y N n T t F f (? when not initialized).
        Record::DBFFIELD_TYPE_DATETIME => "DATETIME",                // DateTime
        Record::DBFFIELD_TYPE_INDEX => "INTEGER",                    // Index
    ];

    if (array_key_exists($type_short, $types)) {
        return $types[$type_short];
    }
    return false;
}

$mysqli->close;

function escName($name)
{
    return "`" . $name . "`";
}
