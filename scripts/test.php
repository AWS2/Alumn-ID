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

if(isset($res->exception) and $res->exception == "webservice_access_exception"){
	echo "No tienes permiso para cargar esta información.\n"
		."Agrega la siguiente función: core_webservice_get_site_info\n\n";
	exit(-1);
}

$required = [
	"core_user_get_users_by_field",
	"core_user_create_users",
	"core_user_get_users_by_field",
	"enrol_manual_enrol_users",
	"core_course_create_categories",
	"core_course_create_courses",
	"core_course_get_contents",
	"core_update_inplace_editable",
	"core_group_create_groups",
	"core_group_add_group_members",
];

$funcs = array();
foreach($res->functions as $f){ $funcs[] = $f->name; }

foreach($required as $r){
	$str = "- $r";
	echo str_pad($str, 40, " ", STR_PAD_RIGHT) .":";
	echo (in_array($r, $funcs) ? "OK" : "NO");
	echo "\n";
}

?>
