<?php

require_once 'LdapUtils.php';

$xml = simplexml_load_file("dades-saga.xml");

$assocs = [
	"id" => "uid", // uidNumber
	"nom" => "givenName",
	"cognom1" => "sn",
	"cognom2" => "sn",
	"adreca" => "street",
	"codipostal" => "postalCode", // organizationalPerson
	"nomlocalitat" => "l",
	"tutor1" => "seeAlso",
	"tutor2" => "seeAlso",
	"provincia" => "st",
	"documentidentitat" => "DNI",
	"sexe" => "gender",
	"datanaixement" => "birthdate"
	// userClass = student, parent,
];

function displayldif($array, $ou = "users", $dom = "ester.cat"){
	$uid = "alguno";
	if(isset($array["uid"])){ $uid = $array["uid"]; }
	$dn = "uid=$uid,ou=$ou," .domain2dc($dom);

	// posixAccount inetOrgPerson organizationalPerson top
	$defclass = ["persona"];

	$str = "dn: " .$dn ."\n";
	// $str .= "changetype: add\n"; // HACK
	foreach($defclass as $class){ $str .= "objectClass: $class\n"; }

	foreach($array as $k => $v){
		if(!is_array($v)){ $v = [$v]; }
		foreach($v as $v1){
			$str .= "$k: $v1\n";
		}
	}
	return $str;
}

function fixvals(&$data){
	if(isset($data["gender"])){
		// if($data["gender"] == "H"){ $data["gender"] = "MALE"; }
		// elseif($data["gender"] == "D"){ $data["gender"] = "FEMALE"; }
	}
	if(isset($data["birthdate"])){
		// $data["birthdate"] = date("Y-m-d", strtotime($data["birthdate"]));
		$birth = explode("/", $data["birthdate"]);
		$birth = array_reverse($birth);
		$data["birthdate"] = implode("-", $birth);
	}

	// ---------------
	$user = "";
	// Juan Ignacio
	foreach(explode(" ", $data["givenName"]) as $n){
		$user .= substr($n, 0, 1);
	}
	$ape = $data["sn"];
	if(is_array($ape)){ $ape = $ape[0]; }
	// TODO FIXME si el apellido es "de la Rosa" ?
	$ape = str_replace(" ", "", $ape);
	$user .= $ape;

	$user = strtolower(trim($user));
	$user = sanstr($user); // Remove accents
	// $data["uid"] = $user; // HACK
	// unset($user);
	unset($ape);
	// ---------------

	if(isset($data["sn"]) && is_array($data["sn"])){
		$data["sn"] = trim(implode(" ", $data["sn"]));
	}
	if(isset($data["st"])){
		if($data["st"] == "08"){ $data["st"] = "BARCELONA"; }
	}
	if(isset($data["seeAlso"])){
		$final = array();
		if(!is_array($data["seeAlso"])){ $data["seeAlso"] = [$data["seeAlso"]]; }
		foreach($data["seeAlso"] as $also){
			$final[] = "uid=$also,ou=users," .domain2dc("ester.cat");
		}
		$data["seeAlso"] = $final;
	}

	$data["cn"][] = $data["givenName"] ." " .$data["sn"];
	$data["cn"][] = $user;
}

function domain2dc($dom){
	if(is_string($dom)){ $dom = explode(".", $dom); }
	foreach($dom as $i => $d){ $dom[$i] = "dc=$d"; }
	return implode(",", $dom);
}

function createou($ou){
	// TODO
}


// dataparser($xml->personal[0]);
$alumnes = dataparser($xml->alumnes->alumne);
dataparser($xml->{"tutors-legals"}->{"tutor-legal"});

?>
