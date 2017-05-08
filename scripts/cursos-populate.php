<?php

require 'MoodleRest.php';

$url = "";
$token = "";

$moodle = new MoodleRest($url, $token);
$moodle->type("rest");

// TODO Fill
$plantilla = array();

foreach($plantilla as $curso){
    // Crear categoría
    // name, parent, idnumber, description
    $data = [
        "idnumber" => $curso["id"],
        "name" => $curso["name"],
        "description" => $curso["name"],
    ];

    $res = $moodle->query("core_course_create_categories", $data);
    if(!$res){
        error_log("Error al generar la categoría " .$curso["id"] .".");
        die();
    }
}

?>
