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

$categories = $moodle->query("core_course_get_categories", $data, "id");
sort($categories);

for($i = 0; $i < count($categories); $i++){
    $data = ["categories" => [0 => ["id" => $categories[$i], "recursive" => 1]]];
    $query = $moodle->query("core_course_delete_categories", $data);
    echo round(($i + 1) / count($categories) * 100) ."% - Categoría $categories[$i] borrada.\n";
}

?>
