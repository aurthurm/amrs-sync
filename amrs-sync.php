<?php

$remoteHost       = "127.0.0.1";  //MySQL Server    
$userName         = "root";       //MySQL Username     
$password         = "zaq12345";   //MySQL Password     
$amrsDb           = "amrs";       //MySQL Database Name  


$localhost       = "127.0.0.1";  //MySQL Server    
$localUserName   = "root";       //MySQL Username     
$localPassword   = "zaq12345";   //MySQL Password     
$amrsTempDb      = 'amrs_temp';  //MySQL Database Name  

// if (isset($argc) && $argc > 1) {
//     $amrsTempDb = $argv[1]; // who
// }
// else{
//     $amrsTempDb = 'amrs_temp';

// }

$flag = 0;

$localMysql =  mysqli_connect($localhost, $localUserName, $localPassword, $amrsTempDb) or die("Couldn't connect to MySQL:<br>" . mysqli_error() . "<br>" . mysqli_errno());
$dyDb = mysqli_select_db($localMysql, $amrsTempDb) or die("Couldn't select database:<br>" . mysqli_error() . "<br>" . mysqli_errno());

$remoteMysql = mysqli_connect($remoteHost, $userName, $password, $amrsDb) or die("Couldn't connect to MySQL:<br>" . mysqli_error() . "<br>" . mysqli_errno());
$Db = mysqli_select_db($remoteMysql, $amrsDb) or die("Couldn't select database:<br>" . mysqli_error() . "<br>" . mysqli_errno());

$remoteMysql->autocommit(FALSE);

// $localMysql->begin_transaction(MYSQLI_TRANS_START_READ_ONLY); //whonet db
// $remoteMysql->begin_transaction(MYSQLI_TRANS_START_READ_ONLY); //amrs db

$dysql = 'SHOW TABLES';
$dysqlResult = $localMysql->query($dysql);

$sqlDrugs = 'SELECT distinct WHON5_TEST FROM r_drugs_ranges WHERE GUIDELINES LIKE "CLSI19" and HOST like "human"';
$sqlDrugsResult = $remoteMysql->query($sqlDrugs);

$fullDruglist = array();
$amrcolumnHeaders = array();
$sqlAmr = "select * from amr_surveillance limit 1";
$sqlAmrantibiotics = "select * from amr_antibiotics limit 1";
$amrResult = $remoteMysql->query($sqlAmr);
$amrAntiResult = $remoteMysql->query($sqlAmrantibiotics);

for ($p = 0; $p < mysqli_num_fields($amrResult); $p++) {  //   amr_surveillance table columns
    array_push($amrcolumnHeaders, mysqli_fetch_field_direct($amrResult, $p)->name);
}

while ($row1 = $sqlDrugsResult->fetch_row()) {
    array_push($fullDruglist, strtolower($row1[0]));
}

if (mysqli_num_rows($dysqlResult)) {
    while ($dyrow = $dysqlResult->fetch_row()) //whonet tables
    {
        $amrsurInsRowcount = 0; //for total no of rows (amr surveillance)
        $amrantibioticInsRowcount = 0; // for total no of rows (amr antibiotics)
        $InsertedRowamr = 0; //for inserted no of rows (amr surveillance)
        $InsertedRowant = 0; //for inserted no of rows (amr antibiotics)
        $columnHeaders = array();
        $intersectArray = array();
        $diffArray = array();
        // $dyrow[0] = 'w0195who_tst';
        if (strpos($dyrow[0], '_interpretations') === false) {
            // $dyrow[0] = 'w0195who_tst';
            // print_r($dyrow[0]);
            $sql = "select * from " . $dyrow[0];

            // $amrRowcount = mysqli_num_rows($amrResult);
            // $amrAntibioticsRowcount = mysqli_num_rows($amrAntiResult);

            $result = $localMysql->query($sql);

            for ($i = 0; $i < mysqli_num_fields($result); $i++) {  //   whonet table columns
                array_push($columnHeaders, mysqli_fetch_field_direct($result, $i)->name);
            }

            $intersectArray = array_intersect($fullDruglist, $columnHeaders); // matched columns for antibiotics
            $diffArray = array_diff($columnHeaders, $intersectArray); //matched columns for amr surveillance

            for ($s = 0; $s < count($diffArray); $s++) {
                $showColumn = 'SHOW COLUMNS FROM amr_surveillance LIKE "' . $diffArray[$s] . '"';
                $showColumnResult = $remoteMysql->query($showColumn);
                // print_r($showColumnResult);
                if (mysqli_num_rows($showColumnResult) == 0) {
                    $alterAmrsur = 'ALTER TABLE amr_surveillance ADD COLUMN ' . $diffArray[$s] . ' VARCHAR(1000) DEFAULT NULL ';
                    $remoteMysql->query($alterAmrsur);
                    // print_r($alterAmrsur);
                    // print_r("\n");
                }
            }

            foreach ($result as $row) {
                $interpret = 'select * from ' . $dyrow[0] . '_interpretations where PATIENT_ID = "' . $row['patient_id'] . '" and SPEC_NUM = "' . $row['spec_num'] . '" ';
                $insertQuery = "";
                $insertValues = "";
                foreach ($diffArray as $j) {
                    if ($insertQuery != "") {
                        if ($row[$j] == "0000-00-00") {
                            $row[$j] = "";
                        }
                        if ($row[$j] != "") {
                            $insertQuery = $insertQuery . "," . $j;
                            $insertValues = $insertValues . "," . "'" . $row[$j] . "'";
                        }
                    } else {
                        if ($row[$j] == "0000-00-00") {
                            $row[$j] = "";
                        }
                        if ($row[$j] != "") {
                            $insertQuery = $j;
                            $insertValues = "'" . $row[$j] . "'";
                        }
                    }
                }
                $amrsurInsertSql = "INSERT into amr_surveillance (" . $insertQuery . ") VALUES (" . $insertValues . ")";
                $InsertorNot = $remoteMysql->query($amrsurInsertSql);
                $amrRowcount = $remoteMysql->insert_id;
                $amrsurInsRowcount++;
                $file_data = $amrsurInsRowcount . "\t" . $amrsurInsertSql . "\n";
                if (!$InsertorNot)
                    file_put_contents('log/amrsSur.txt', $file_data, FILE_APPEND);
                else
                    $InsertedRowamr++;
                // print_r($amrsurInsRowcount."\t");
                // print_r($InsertorNot);
                // print_r("\n");

                foreach ($intersectArray as $k) {
                    if ($row[$k]) {

                        $interpretVal = isset($interpret[$k]) ? $interpret[$k] : null;
                        echo $amrAntibiotics = 'INSERT into amr_antibiotics(`amr_id`, `antibiotic`, `value`, `interpretation`) VALUES ("' . $amrRowcount . '","' . $k . '","' . $row[$k] . '", "' . $interpretVal . '" )';
                        $InsertorNotAntibiotic = $remoteMysql->query($amrAntibiotics);
                        $amrAntibioticsRowcount = $remoteMysql->insert_id;
                        $amrantibioticInsRowcount++;
                        $file_data_antibiotic = $amrantibioticInsRowcount . "\t" . $amrAntibiotics . "\n";
                        if (!$InsertorNotAntibiotic)
                            file_put_contents('log/amrsAntibiotic.txt', $file_data_antibiotic, FILE_APPEND);
                        else
                            $InsertedRowant++;
                        // print_r($amrAntibiotics);
                        // print_r("\n");

                    }
                }
            }
        }
        $amrsurEventType = "Splitting Whonet DB to amr_surveillance table";
        $amrsurLog = "rows inserted in amr_surveillance table-" . $InsertedRowamr;
        $amrsurResource = $dyrow[0];
        $addon = date('Y-m-d H:i:s');
        $eventLogamr = 'INSERT into event_log(event_type,action,resource_name,added_on,actor)VALUES("' . $amrsurEventType . '","' . $amrsurLog . '","' . $amrsurResource . '","' . $addon . '","admin")';
        $remoteMysql->query($eventLogamr);

        $amrantibioticEventType = "Splitting Whonet DB to amr_antibiotics tables";
        $amrantibioticLog = "rows inserted in amr_antibiotics table-" . $InsertedRowant;
        $amrantibioticResource = $dyrow[0];
        $eventLogAntibiotic = 'INSERT into event_log(event_type,action,resource_name,added_on,actor)VALUES("' . $amrantibioticEventType . '","' . $amrantibioticLog . '","' . $amrantibioticResource . '","' . $addon . '","admin")';
        $remoteMysql->query($eventLogAntibiotic);
    }
}
// print_r($lastInsertId);

// amrs db
$remoteMysql->commit();
$remoteMysql->close();

// whonet db
// $localMysql->commit();
// $localMysql->close();
