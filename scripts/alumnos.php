<?php

$xml = simplexml_load_file("dades-saga.xml");

$alumnes = array();
$cols = ["username", "firstname", "lastname", "email", "idnumber", "profile_field_genre", "profile_field_birthdate", "country", "auth"];
$users = array();

foreach($xml->alumnes->alumne as $alumne){
    $data = [
        "idnumber" => strval($alumne["id"]),
        "firstname" => strval($alumne["nom"]),
        "lastname" => trim(strval($alumne["cognom1"]) ." " .strval(@$alumne["cognom2"])),
        "password" => "changeme",
        "profile_field_genre" => strval($alumne["sexe"]),
        "country" => "ES",
        "auth" => "ldap",
    ];

    if(isset($alumne->contacte)){
        foreach($alumne->contacte as $contacte){
            $tipus = strtoupper(strval($contacte["tipus"]));
            if($tipus == "EMAIL"){
                $data["email"] = strtolower(strval($contacte["contacte"]));
                break;
            }
        }
    }

    // Birthdate
    $birth = explode("/", strval($alumne["datanaixement"]));
    $birth = array_reverse($birth);
    $data["profile_field_birthdate"] = implode("-", $birth);

    $username = $data["firstname"] .".";
    $surnames = explode(" ", $data["lastname"]);
    $username .= $surnames[0];

    if(in_array($username, $users)){
        $i = 0;
        $usernamefix = $username;
        while(in_array($usernamefix, $users)){
            $usernamefix = $username .$i;
            $i++;
        }
        $username = $usernamefix;
    }

    $data["username"] = strtolower($username);
    $data["username"] = str_replace(["á", "é", "í", "ó", "ú"], ["a", "e", "i", "o", "u"], $data["username"]);
    $data["username"] = str_replace(["à", "è", "ì", "ò", "ù"], ["a", "e", "i", "o", "u"], $data["username"]);
    $alumnes[] = $data;
}

echo implode(",", $cols) ."\n";
foreach($alumnes as $alumne){
    foreach($cols as $col){
        if(isset($alumne[$col])){
            echo $alumne[$col];
        }
        echo ",";
    }
    
    echo "\n";
}
