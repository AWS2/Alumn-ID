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

$u = 1;
foreach($users as $user){
    echo str_pad($u, 4, " ", STR_PAD_LEFT) ." ";

    // Obtener datos de LDAP
    $rdn = explode(",", $user);
    $field = array_shift($rdn);
    $path = implode(",", $rdn);

    $res = ldap_search($ldap, $path, "($field)");
    if(!$res){
        echo "X\n";
        $u++;
        continue;
    }

    $info = ldap_get_entries($ldap, $res);
    $info = $info[0];

    echo $info["cn"] ."\n";
    $u++;

    // Si no existe, skip.

    // Buscar Usuario con idnumber o correo, si existe skip.
    // Generar nombre de usuario. Si existe en Moodle, probar +1, +2, +3...

    // Registrar usuario en Moodle.
}

ldap_close($ldap);

?>
