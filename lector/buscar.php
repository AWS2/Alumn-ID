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

$name = $info[0]["cn"];

// INSERT en DB.

$time = (microtime() - $mt) * 1000;

http_response_code(200);
echo json_encode(array("status" => "ok", "time" => $time, "name" => $name));
die();


?>
