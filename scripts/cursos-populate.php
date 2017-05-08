<?php

require 'MoodleRest.php';

$url = "";
$token = "";

if(empty($url) or empty($token)){
    echo "Por favor, configura las settings primero.";
    exit(-1);
}

$moodle = new MoodleRest($url, $token);
$moodle->type("rest");

$plantilla = json_decode(file_get_contents("courses.json"), TRUE);

foreach($plantilla as $curso){
    // Crear categoría
    $data = [
        "idnumber"      => $curso["id"],
        "name"          => $curso["name"],
        "description"   => $curso["name"],
        "parent"        => "0", // Sin padre
    ];

    $category = ["categories" => [0 => $data]];

    $res = $moodle->query("core_course_create_categories", $category);

    if(isset($res->exception)){
        error_log("Error al generar la categoría " .$curso["id"] .": " .$res->message);
        exit(-1);
    }

    $catid = $res[0]->id;
    echo "Categoría " .$curso["id"] ." creado: $catid\n";
}

?>
