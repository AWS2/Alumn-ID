<?php

/* TODO
- Coger grupos establecidos en un array.
- Hacer query de todos los grupos para sacar todos los miembros en un array total
- Hacer array unique para quitar repes.
- Sacar datos de contacto básicos de todos los UID que hay, y registrarlos en el Moodle.
- LDAP[uidNumber] = Moodle[idnumber], NO Moodle[id].

*/

require 'LdapUtils.php';
require 'MoodleRest.php';

$config = require 'config.php';

$ldapt = new LdapUtils($config["ldap"]["domain"]);

$xml = simplexml_load_file("dades-saga.xml");
$groups = array();

foreach($xml->grups->grup as $grup){
    $groups[] = strval($grup["nom"]);
}

$groups = array_unique($groups);
sort($groups);

$ldap = ldap_connect($config["ldap"]["host"]);
if(!$ldap){
    echo "No se ha podido establecer la conexión al servidor LDAP.";
    exit(-1);
}

ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);

$ldapb = ldap_bind($ldap, $config["ldap"]["user"], $config["ldap"]["password"]);
if(!$ldapb){
    echo "No se ha podido bindear al servidor LDAP.";
    exit(-1);
}

echo "Cargados " .count($groups) . " grupos.\n";

$users = array();
$g = 1;
foreach($groups as $grup){
    echo str_pad($g, 4, " ", STR_PAD_LEFT) ." ";

    $res = ldap_search($ldap, $ldapt->path("ou=Groups", TRUE), "(cn=$grup)");
    if(!$res){
        echo "X\n";
        $g++;
        continue;
    }

    $info = ldap_get_entries($ldap, $res);
    if($info["count"] == 0){
        echo "X\n";
        $g++;
        continue;
    }

    for($i = 0; $i < $info[0]["member"]["count"]; $i++){
        $users[] = $info[0]["member"][$i];
    }

    echo $info[0]["member"]["count"] ."\n";
    $g++;
}

$users = array_unique($users);
sort($users);

// Lookup users

echo "Buscando y matriculando " .count($users) ." usuarios...\n";

$moodle = new MoodleRest($config["url"], $config["token"]);
$moodle->type("rest");

function user_exists($user, $field = "username"){
	global $moodle;

	$search = [
    	"field" => $field,
    	"values" => [$user]
    ];

    $res = $moodle->query("core_user_get_users_by_field", $search);
    return (count($res) > 0);
}

$u = 1;
foreach($users as $user){
    echo str_pad($u, 4, " ", STR_PAD_LEFT) ." ";

    $u++;

    // Obtener datos de LDAP
    $rdn = explode(",", $user);
    $field = array_shift($rdn);
    $path = implode(",", $rdn);

    $res = ldap_search($ldap, $path, "($field)");

    // Si no existe, skip.
    if(!$res){
        echo "-\n";
        // $u++;
        continue;
    }

    $info = ldap_get_entries($ldap, $res);

    if($info["count"] == 0){
        echo "-\n";
        continue;
    }

    $info = $info[0];

    echo $info["cn"];

    // Buscar Usuario con idnumber o correo, si existe skip.
    if(user_exists($info["uidNumber"], "idnumber")){
    	echo "R\n";
    	continue;
    }

    if(isset($info["email"]) and user_exists($info["email"], "email")){
    	echo "E\n";
    	continue;
    }

    // Generar nombre de usuario.
    $username = $info["uid"];

    // Si existe en Moodle, probar +1, +2, +3...
    if(user_exists($username)){
    	$j = 1;
    	do{
    		$username = $info["uid"] .$j;
    		$j++;
    	}while(user_exists($username));

    	echo " - cambio usuario a $username.";
    	// TODO UPDATE LDAP
    }

    // Registrar usuario en Moodle.
    $cols = [
    	// "username" => "uid",
    	"firstname" => "givenName",
    	"lastname" => "sn",
    	"email" => "email",
    	"idnumber" => "uidNumber",
    ];

    $data = array();
    foreach($cols as $m => $l){
    	if(!isset($info[$l])){ continue; }
    	$data[$m] = $info[$l];
    }

    $data["username"] = $username;
    $data["password"] = "not cached";
    $data["auth"] = "ldap";
    $data["createpassword"] = (int) FALSE;
    $data["lang"] = "es";

    $data = ["users" => [$data]];
    $res = $moodle->query("core_user_create_users", $data);
}

ldap_close($ldap);

?>
