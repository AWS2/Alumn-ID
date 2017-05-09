<?php

require 'MoodleRest.php';

$config = require 'config.php';

if(empty($config["url"]) or empty($config["token"])){
    echo "Por favor, configura las settings primero.";
    exit(-1);
}

$moodle = new MoodleRest($config["url"], $config["token"]);
$moodle->type("rest");

$data = ["criteria" => [0 => ["key" => "parent", "value" => 0]]];

$categories = $moodle->query("core_course_get_categories", $data);
$categories = array_column($categories, 'id');

foreach($categories as $cat){
    $data = ["categories" => [0 => ["id" => $cat, "recursive" => 1]]];
    $query = $moodle->query("core_course_delete_categories", $data);

    if($query !== NULL and isset($query->exception)){
        echo "Error al borrar $cat: " .$query->errorcode ."\n";
    }else{
        echo "Categoría $cat borrada.\n";
    }
}

?>
