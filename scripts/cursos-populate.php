<?php

require 'MoodleRest.php';

$config = require 'config.php';

if(empty($config["url"]) or empty($config["token"])){
    echo "Por favor, configura las settings primero.";
    exit(-1);
}

$moodle = new MoodleRest($config["url"], $config["token"]);
$moodle->type("rest");

$xml = simplexml_load_file("dades-saga.xml");
$xmldata = array();

// PASO 1. Crear categorias y cursos.
// Crear una categoría (conjunto de cursos) como grado.
foreach($xml->{'plans-estudi'}->{'pla-estudis'} as $titulo){

    // Si no es Grado Medio o Superior, ignorar. BATX y ESO dan problemas.
    if(!in_array(strval($titulo["etapa"]), ["CFPM", "CFPS"])){ continue; }

    $tituloid = strval($titulo["etapa"]) ."-" .strval($titulo["subetapa"]);
    $data = [
        'idnumber'      => $tituloid,
        'name'          => strval($titulo["nom"]),
        'description'   => strval($titulo["nom"]),
        'parent'        => 0 // General de Moodle;
    ];

    $category = ["categories" => [0 => $data]];
    $res = $moodle->query("core_course_create_categories", $category);

    if(isset($res->exception)){
        if($res->errorcode == "categoryidnumbertaken"){
            error_log("Categoría $tituloid duplicada, omitiendo.");
            continue;
        }

        error_log("Error al generar la categoría $tituloid : " .$res->message);
        exit(-1);
    }

    $catid = $res[0]->id;
    $xmldata["category"][strval($titulo["id"])] = $catid;

    echo "Categoría $tituloid creado: $catid\n";

    $courses = array();
    // Listar los módulos MP que vamos a crear como cursos.
    foreach($titulo->contingut as $contingut){
        if($contingut['categoria'] != 'Mòdul'){ continue; }
        $codi = trim(strval($contingut['codi']));
        if($codi == "DUA"){ continue; } // Dual
        $courses[$codi] = $contingut; // Agregar todo el valor XML.
    }

    ksort($courses); // Ordenar por Key - codi.

    // Procesar por cada modulo
    foreach($courses as $codi => $course){

        // Sacar datos de todas las UFs.
        $ufs = array();
        foreach($titulo->contingut as $contingut){
            if(
                $contingut['categoria'] != 'Crèdit' or
                $contingut['tipus'] != 'Lectiu'
            ){ continue; }

            $codiuf = strval($contingut['codi']);

            // Si comienza por el mismo código, agregar
            if(strpos($codiuf, $codi) === 0){
                $ufs[$codiuf] = $contingut; // Agregar todo el valor XML.
            }
        }

        ksort($ufs); // Ordenar por Key - UFs.

        $data = [
            "fullname" => strval($course["nom"]),
            "shortname" => strval($titulo["subetapa"]) ."-" .strval($course["codi"]),
            "idnumber" => strval($titulo["subetapa"]) ."-" .strval($course["codi"]),
            "categoryid" => $catid,
            "visible" => 1, // TRUE
            "format" => "topics", // Formato de temas
            "numsections" => max(count($ufs), 1),
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
        echo "Curso $courseid creado: " .strval($course["nom"]) ."\n";

        // IDs de las UFs asociadas a este curso de Moodle.
        $ufids = array();
        foreach($ufs as $uf){ $ufids[] = strval($uf["id"]); }

        $xmldata["courses"][$courseid] = $ufids;
    }
}

// TODO ESO
// Tiene que haber 4 categorías ESO 1,2,3,4.
// Identificar por el último dígito numérico del "codi".
// No hay UFs, por lo tanto no conocemos contenido. Todos tendrán 10 temas.

// PASO 2. Crear grupos y matricular alumnos y profes.
foreach($xml->{'grups'}->{'grup'} as $grup){
    // Cargar los alumnos del grupo
    $alumnes = array();
    foreach($grup->alumnes->alumne as $alumne){ $alumnes[] = strval($alumne["id"]); }

    // Cargar UFs que hay en la clase.
    $ufs = array();
    foreach($grup->continguts->contingut as $contingut){
        $ufs[] = strval($contingut["contingut"]);
    }

    $ufs = array_unique($ufs);

    // Cargar cursos a los que se asocian.
    $courses = array();
    foreach($ufs as $uf){
        foreach($xmldata["courses"] as $courseid => $ufids){
            if(in_array($uf, $ufids)){
                $courses[$uf] = $courseid;
                break;
            }
        }
    }

    // Grupos a los que se van a matricular los alumnos.
    $courses_unique = array_unique(array_values($courses));

    echo "Matriculando " .count($alumnes) ." del grupo " .strval($grup["nom"]) ." en " .count($courses_unique) ." cursos.\n";
    // TODO Asociar via LDAP con el ID respectivo del alumno para poder matricularlo.
}


?>
