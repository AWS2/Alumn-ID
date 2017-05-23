<?php

require 'MoodleRest.php';

$config = require 'config.php';

$moodle = new MoodleRest($config["url"], $config["token"]);
$moodle->type("rest");

$mdl["iduser"] = 17179975059;
$mdl["course"] = 165;

$search = [
	"field" => "idnumber",
	"values" => [$mdl["iduser"]]
];

$user = $moodle->query("core_user_get_users_by_field", $search, "id");

if(count($user) != 1){ exit(); }

$user = $user[0];

// "gradereport_user_get_grade_items"
// "gradereport_overview_get_course_grades"

$res = $moodle->query("core_course_get_contents", ["courseid" => $mdl["course"]]);

$acts = array();

foreach($res as $topic){
	foreach($topic->modules as $mod){
		$acts[$mod->id] = $mod->name;
	}
}

$res = $moodle->query("gradereport_user_get_grade_items", ["userid" => $user, "courseid" => $mdl["iduser"]]);

$grades = $res->usergrades[0]->gradeitems;

foreach($grades as $grade){
	echo $grade->percentageformatted ." - ";
	if(isset($acts[$grade->id])){
		echo $acts[$grade->id];
	}else{
		echo $grade->id;
	}

	echo "\n";
}

?>
