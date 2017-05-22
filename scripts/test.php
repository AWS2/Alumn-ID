<?php

require 'MoodleRest.php';

$config = require 'config.php';

if(empty($config["url"]) or empty($config["token"])){
    echo "Por favor, configura las settings primero.";
    exit(-1);
}

$moodle = new MoodleRest($config["url"], $config["token"]);
$moodle->type("rest");

$res = $moodle->query("core_webservice_get_site_info");

var_dump($res);

?>
