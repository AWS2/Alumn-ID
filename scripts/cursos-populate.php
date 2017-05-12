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
            // "numsections" => max(count($ufs), 1), // DEPRECATED
            "showgrades" => 1,
            "showreports" => 1,
            "groupmode" => 1, // no group, separate, visible
            "completionnotify" => 1,
            "courseformatoptions" => [
                ["name" => "numsections", "value" => max(count($ufs), 1)],
                // ["name" => "sagaid", "value" => strval($course["id"])]
            ],
        ];

        $data = ["courses" => [0 => $data]];
        $res = $moodle->query("core_course_create_courses", $data);

        if(isset($res->exception)){
            error_log("Error al generar el curso " .$id .": " .$res->message);
            exit(-1);
        }

        $courseid = $res[0]->id;
        echo "Curso $courseid creado: " .strval($course["nom"]) ."\n";

        // TODO WIP - Not working.
        // No se generan todos los topics, por lo tanto siempre aparece 1.
        // Sólo se generan cuando "un usuario mira el curso".

        // Editar los titulos de los Topics y ponerles el título de la UF.
        $data = ["courseid" => $courseid];
        $topics = $moodle->query("core_course_get_contents", $data, "id");
        array_shift($topics); // Extrae el primer Topic, es GENERAL. No nos sirve.
        $topics = array_values($topics); // Reiniciar las keys del array.

        // Si se ha reconocido más de una UF
        if(count($ufs) >= 1){
            // Poner los nombres de las UFs en los section de cada curso.
            // NOTE: Código no probado debido a bug de Moodle que no crea los sections.
            // En principio debería funcionar.
            for($i = 0; $i < count($ufs); $i++){
                if(!isset($topics[$i])){ continue; }

                $data = [
                    "component" => "format_topics",
                    "itemtype" => "sectionname",
                    "itemid" => $topics[$i],
                    "value" => strval($ufs[$i]["nom"]),
                ];
                $res = $moodle->query("core_update_inplace_editable", $data);
            }
        }

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

    $profes = array();
    $ufs = array();
    $profe_uf = array();
    foreach($grup->continguts->contingut as $contingut){
        $profe = strval($contingut["professor"]);
        $uf = strval($contingut["contingut"]);

        $profes[] = $profe; // Cargar los profes del grupo
        $ufs[] = $uf;    // Cargar UFs que hay en la clase.

        $profe_uf[$profe][] = $uf;
    }

    // Quitar duplicados.
    $profes = array_unique($profes);
    $ufs = array_unique($ufs);

    // Cargar cursos a los que se asocian.
    // UF -> Curso.
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
    if(count($courses_unique) == 0){
        echo "Ignorando. No hay cursos disponibles.\n";
        continue;
    }

    // Procesar sólo si hay cursos para matricular, o dará error.

    // Crear lista de Profe -> cursos
    $profe_enrol = array();
    foreach($profe_uf as $profe => $profe_ufs){
        foreach($profe_ufs as $uf){
            if(isset($courses[$uf])){
                $profe_enrol[$profe][] = $courses[$uf];
            }
        }
        if(!empty($profe_enrol[$profe])){
            $profe_enrol[$profe] = array_unique($profe_enrol[$profe]);
        }
    }

    // TODO Asociar via LDAP con el ID respectivo del alumno para poder matricularlo.

    // Crear cursos en cada grupo.
    $groups = array();
    foreach($courses_unique as $course){
        $groups["groups"][] = [
            "courseid" => $course,
            "name" => strval($grup["nom"]),
            "description" => strval($grup["codi"]),
        ];
    }

    // Rellenar con Curso -> Grupo
    $groups = $moodle->query("core_group_create_groups", $groups, "id", "courseid");

    $i = 1;
    foreach($alumnes as $alumne){
        echo str_pad($i, 4, " ", STR_PAD_LEFT) ." ";
        foreach($courses_unique as $course){
            // Matricular al usuario en el curso.
            $enrol = [
                "roleid" => $config["student"],
                "userid" => $alumne,
                "courseid" => $course,
                "timestart" => strtotime($config["from"]),
                "timeend" => strtotime($config["to"]),
            ];

            $enrol = ["enrolments" => [0 => $enrol]];
            $res = $moodle->query("enrol_manual_enrol_users", $enrol);

            // ------------------
            // Agregar usuario al grupo.

            if(isset($groups[$course])){
                $enrol = [
                    "groupid" => $groups[$course],
                    "userid" => $alumne
                ];

                $enrol = ["members" => [0 => $enrol]];
                $res = $moodle->query("core_group_add_group_members", $enrol);

                echo "#";
            }else{
                echo "-";
            }

        }
        echo "\n";
        $i++;
    }

    echo "Matriculando " .count($profes) ." profes del grupo " .strval($grup["nom"]) .".\n";

    $i = 1;
    foreach($profe_enrol as $profe => $cursos){
        echo str_pad($i, 4, " ", STR_PAD_LEFT) ." ";
        foreach($cursos as $course){
            // Matricular al usuario en el curso.
            $enrol = [
                "roleid" => $config["teacher"],
                "userid" => $profe,
                "courseid" => $course,
                "timestart" => strtotime($config["from"]),
                "timeend" => strtotime($config["to"]),
            ];

            $enrol = ["enrolments" => [0 => $enrol]];
            $res = $moodle->query("enrol_manual_enrol_users", $enrol);

            // Técnicamente el profesor no será matriculado en un grupo.

            echo "#";
        }
        echo "\n";
        $i++;
    }
}


?>
