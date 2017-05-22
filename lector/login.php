<?php

$mt = microtime();

$config = require 'config.php';
require 'LdapUtils.php';

$ldapt = new LdapUtils($config["ldap"]["domain"]);

if(!isset($_GET['id'])){ die(); }

$id = $_GET['id'];

if(strlen($id) == 40){
    $pass = $id;
}else{
    $pass = "T:" .$id;
    $pass = sha1($pass);
}

$pass = "{SHA}" .base64_encode(hex2bin($pass));

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

// ------

$res = ldap_search($ldap, $ldapt->path("ou=Users", TRUE), "(userPassword=$pass)");
if(!$res){
    http_response_code(404);
    echo json_encode(array("status" => "error", "data" => "not_found"));
    die();
}

$info = ldap_get_entries($ldap, $res);
if($info["count"] != 1){
    http_response_code(404);
    echo json_encode(array("status" => "error", "data" => "not_found"));
    die();
}

$name = $info[0]["cn"][0];
$idal = $info[0]["uidNumber"][0];

$data = [
    'id_ldap' => $idal,
    'origenlector' => $_SERVER['REMOTE_ADDR'],
    'dia_hora' => date("Y-m-d H:i:s"),
    'id_tarjeta' => $id
];

$sql = "INSERT INTO admincenter_files_fitxatge (" .implode(",", array_keys($data)) .") "
        ."VALUES ('" .implode("', '", array_values($data)) ."');";

$mysql = new mysqli($config["mysql"]["host"], $config["mysql"]["username"], $config["mysql"]["passwd"], $config["mysql"]["dbname"]);

if($mysql->connect_error){
    http_response_code(500);
    echo json_encode(array("status" => "error", "data" => "db_error"));
    die();
}

$mysql->query($sql);

$time = (microtime() - $mt) * 1000;
http_response_code(200);
echo json_encode(array("status" => "ok", "time" => $time, "name" => $name));
die();


?>
