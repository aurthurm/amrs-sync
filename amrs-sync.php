<?php

$DB_Server = "127.0.0.1"; //MySQL Server    
$DB_Username = "root"; //MySQL Username     
$DB_Password = "zaq12345";             //MySQL Password     
$DB_DBName = "amrs";         //MySQL Database Name  
$DB_TBLName = "amr_surveillance"; //MySQL Table Name   
//$filename = "./excelfilename";         //File Name

if (isset($argc) && $argc > 1) {
    $dbName = $argv[1]; // who
}
else{
    $dbName = 'amrs_temp';
    //echo "Please enter db name";
    //die;
}

$flag = 0;
$dyConnect =  mysqli_connect($DB_Server, $DB_Username, $DB_Password,$dbName) or die("Couldn't connect to MySQL:<br>" . mysqli_error() . "<br>" . mysqli_errno());
$dyDb = mysqli_select_db($dyConnect,$dbName) or die("Couldn't select database:<br>" . mysqli_error(). "<br>" . mysqli_errno());
$Connect = mysqli_connect($DB_Server, $DB_Username, $DB_Password,$DB_DBName) or die("Couldn't connect to MySQL:<br>" . mysqli_error() . "<br>" . mysqli_errno());
$Db = mysqli_select_db($Connect,$DB_DBName) or die("Couldn't select database:<br>" . mysqli_error(). "<br>" . mysqli_errno());

$Connect->autocommit(FALSE);
// $dyConnect->begin_transaction(MYSQLI_TRANS_START_READ_ONLY); //whonet db
// $Connect->begin_transaction(MYSQLI_TRANS_START_READ_ONLY); //amrs db

$dysql = 'SHOW TABLES';
$dysqlResult = $dyConnect->query($dysql);

$sqlDrugs = 'SELECT distinct WHON5_TEST FROM r_drugs_ranges WHERE GUIDELINES LIKE "CLSI19" and HOST like "human"';
$sqlDrugsResult = $Connect->query($sqlDrugs);

$fullDruglist = array();
$amrcolumnHeaders = array();
$sqlAmr = "select * from amr_surveillance limit 1";
$sqlAmrantibiotics = "select * from amr_antibiotics limit 1";
$amrResult = $Connect->query($sqlAmr);
$amrAntiResult = $Connect->query($sqlAmrantibiotics);

for ($p = 0; $p < mysqli_num_fields($amrResult); $p++) {  //   amr_surveillance table columns
    array_push($amrcolumnHeaders,mysqli_fetch_field_direct($amrResult,$p)->name);
}

while($row1 = $sqlDrugsResult->fetch_row())
{
    array_push($fullDruglist,strtolower($row1[0]));
}

if(mysqli_num_rows($dysqlResult))
{
    while($dyrow = $dysqlResult->fetch_row()) //whonet tables
    {
        $amrsurInsRowcount = 0; //for total no of rows (amr surveillance)
        $amrantibioticInsRowcount = 0;// for total no of rows (amr antibiotics)
        $InsertedRowamr = 0; //for inserted no of rows (amr surveillance)
        $InsertedRowant = 0; //for inserted no of rows (amr antibiotics)
        $columnHeaders = array();
        $intersectArray = array();
        $diffArray = array();
        // $dyrow[0] = 'w0195who_tst';
        if (strpos($dyrow[0], '_interpretations') === false) {
            // $dyrow[0] = 'w0195who_tst';
            // print_r($dyrow[0]);
            $sql = "select * from ".$dyrow[0];
            
            // $amrRowcount = mysqli_num_rows($amrResult);
            // $amrAntibioticsRowcount = mysqli_num_rows($amrAntiResult);

            $result = $dyConnect->query($sql);

            for ($i = 0; $i < mysqli_num_fields($result); $i++) {  //   whonet table columns
                array_push($columnHeaders,mysqli_fetch_field_direct($result,$i)->name);
            }

            $intersectArray = array_intersect($fullDruglist,$columnHeaders); // matched columns for antibiotics
            $diffArray = array_diff($columnHeaders,$intersectArray); //matched columns for amr surveillance
            
            for($s = 0; $s < count($diffArray); $s++){
                $showColumn = 'SHOW COLUMNS FROM amr_surveillance LIKE "'.$diffArray[$s].'"';
                $showColumnResult = $Connect->query($showColumn);
                // print_r($showColumnResult);
                if(mysqli_num_rows($showColumnResult) == 0){
                    $alterAmrsur = 'ALTER TABLE amr_surveillance ADD COLUMN '.$diffArray[$s].' VARCHAR(1000) DEFAULT NULL ';
                    $Connect->query($alterAmrsur);
                    // print_r($alterAmrsur);
                    // print_r("\n");
                }
            }

            foreach($result as $row){
                $interpret = 'select * from '.$dyrow[0].'_interpretations where PATIENT_ID = "'.$row['patient_id'].'" and SPEC_NUM = "'.$row['spec_num'].'" ';
                $insertQuery = "";
                $insertValues = "";
                foreach($diffArray as $j){
                    if($insertQuery != ""){
                        if($row[$j] == "0000-00-00"){
                            $row[$j] = "";
                        }
                        if($row[$j]!=""){
                            $insertQuery = $insertQuery.",".$j;
                            $insertValues = $insertValues.","."'".$row[$j]."'";
                        }
                    }
                    else{
                        if($row[$j] == "0000-00-00"){
                            $row[$j] = "";
                        }
                        if($row[$j]!=""){
                            $insertQuery = $j;
                            $insertValues = "'".$row[$j]."'";
                        }
                    }
                }
                $amrsurInsertSql = "INSERT into amr_surveillance (".$insertQuery.") VALUES (".$insertValues.")";
                $InsertorNot = $Connect->query($amrsurInsertSql);
                $amrRowcount = $Connect->insert_id;
                $amrsurInsRowcount++;
                $file_data = $amrsurInsRowcount."\t".$amrsurInsertSql."\n";
                if(!$InsertorNot)
                    file_put_contents('log/amrsSur.txt',$file_data,FILE_APPEND);
                else
                    $InsertedRowamr++;
                // print_r($amrsurInsRowcount."\t");
                // print_r($InsertorNot);
                // print_r("\n");
                
                foreach($intersectArray as $k){
                    if($row[$k]){
                        
                            $interpretVal = isset($interpret[$k]) ? $interpret[$k] : null;
                            echo $amrAntibiotics = 'INSERT into amr_antibiotics(`amr_id`, `antibiotic`, `value`, `interpretation`) VALUES ("'.$amrRowcount.'","'.$k.'","'.$row[$k].'", "'.$interpretVal.'" )';
                            $InsertorNotAntibiotic = $Connect->query($amrAntibiotics);
                            $amrAntibioticsRowcount = $Connect->insert_id;
                            $amrantibioticInsRowcount++;
                            $file_data_antibiotic = $amrantibioticInsRowcount."\t".$amrAntibiotics."\n";
                            if(!$InsertorNotAntibiotic)
                                file_put_contents('log/amrsAntibiotic.txt',$file_data_antibiotic,FILE_APPEND);
                            else
                                $InsertedRowant++;
                            // print_r($amrAntibiotics);
                            // print_r("\n");
                        
                    }
                }
            }
        }
        $amrsurEventType = "Splitting Whonet DB to amr_surveillance table";
        $amrsurLog = "rows inserted in amr_surveillance table-".$InsertedRowamr;
        $amrsurResource = $dyrow[0];
        $addon = date('Y-m-d H:i:s');
        $eventLogamr = 'INSERT into event_log(event_type,action,resource_name,added_on,actor)VALUES("'.$amrsurEventType.'","'.$amrsurLog.'","'.$amrsurResource.'","'.$addon.'","admin")';
        $Connect->query($eventLogamr);
        
        $amrantibioticEventType = "Splitting Whonet DB to amr_antibiotics tables";
        $amrantibioticLog = "rows inserted in amr_antibiotics table-".$InsertedRowant;
        $amrantibioticResource = $dyrow[0];
        $eventLogAntibiotic = 'INSERT into event_log(event_type,action,resource_name,added_on,actor)VALUES("'.$amrantibioticEventType.'","'.$amrantibioticLog.'","'.$amrantibioticResource.'","'.$addon.'","admin")';
        $Connect->query($eventLogAntibiotic);
    }
}
// print_r($lastInsertId);

// amrs db
$Connect->commit();
$Connect->close();

// whonet db
// $dyConnect->commit();
// $dyConnect->close();
