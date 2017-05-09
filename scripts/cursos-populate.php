<?php

require 'MoodleRest.php';

$config = require 'config.php';

if(empty($config["url"]) or empty($config["token"])){
    echo "Por favor, configura las settings primero.";
    exit(-1);
}

$moodle = new MoodleRest($config["url"], $config["token"]);
$moodle->type("rest");

$plantilla = json_decode(file_get_contents("courses.json"), TRUE);

foreach($plantilla as $cat){
    // Crear categoría
    $data = [
        "idnumber"      => $cat["id"],
        "name"          => $cat["name"],
        "description"   => $cat["name"],
        "parent"        => "0", // Sin padre
    ];

    $category = ["categories" => [0 => $data]];

    $res = $moodle->query("core_course_create_categories", $category);

    if(isset($res->exception)){
        if($res->errorcode == "categoryidnumbertaken"){
            error_log("Categoría " .$cat["id"] ." duplicada, omitiendo.");
            continue;
        }

        error_log("Error al generar la categoría " .$cat["id"] .": " .$res->message);
        exit(-1);
    }

    $catid = $res[0]->id;
    echo "Categoría " .$cat["id"] ." creado: $catid\n";

    if(!isset($cat["courses"])){ continue; }
    foreach($cat["courses"] as $id => $curso){
        // Crear curso
        $curid = $cat["id"] ."-" .$id;

        if(!isset($curso["name"])){
            error_log("El curso $curid no tiene nombre, omitiendo.");
            continue;
        }

        $sections = $curso;
        unset($sections["id"]);
        unset($sections["name"]);

        $data = [
            "fullname" => $curso["name"],
            "shortname" => $curid,
            "categoryid" => $catid,
            "visible" => 1, // TRUE
            "idnumber" => $curid,
            "format" => "topics", // Formato de temas
            "numsections" => max(count($sections), 1),
            "showgrades" => 1,
            "showreports" => 1,
            "groupmode" => 1, // no group, separate, visible
            "completionnotify" => 1
        ];

        $course = ["courses" => [0 => $data]];

        $res = $moodle->query("core_course_create_courses", $course);

        if(isset($res->exception)){
            error_log("Error al generar el curso " .$id .": " .$res->message);
            exit(-1);
        }

        $courseid = $res[0]->id;
        echo "Curso $courseid creado: " .$curso["name"] ."\n";
    }
}

?>
