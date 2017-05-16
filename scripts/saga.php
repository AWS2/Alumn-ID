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

$ldap = new LdapUtils("ester.cat");

$alumnes	= $ldap->XMLParser($xml->alumnes->alumne, $assocs);
$pares		= $ldap->XMLParser($xml->{"tutors-legals"}->{"tutor-legal"}, $assocs);
$profes		= $ldap->XMLParser($xml->personal->personal, $assocs);

echo $ldap->createOU("Groups", FALSE);
echo $ldap->createOU("Users", TRUE);
	echo $ldap->createOU("Alumnes", FALSE);
	echo $ldap->createOU("Professors", FALSE);
	echo $ldap->createOU("Pares", FALSE);

$ldap->cd("ou=Users,ou=Alumnes", TRUE);
foreach($alumnes as $alumne){
	fixvals($alumne);

	if(isset($alumne["seeAlso"])){
		$final = array();
		if(!is_array($alumne["seeAlso"])){ $alumne["seeAlso"] = [$alumne["seeAlso"]]; }
		foreach($alumne["seeAlso"] as $also){
			$final[] = "uid=$also,ou=Pares,ou=Users," .$ldap->domain(TRUE);
		}
		$alumne["seeAlso"] = $final;
	}

	echo $ldap->createUser($alumne, "uid", "persona");
}

$ldap->cd("ou=Users,ou=Pares", TRUE);
foreach($pares as $pare){
	fixvals($pare);
	echo $ldap->createUser($pare, "uid", "persona");
}

$ldap->cd("ou=Users,ou=Professors", TRUE);
foreach($profes as $profe){
	fixvals($profe);
	echo $ldap->createUser($profe, "uid", "persona");
}

// ------------------------

$ldap->cd("ou=Groups", TRUE);

$assocs = [
	"nom" => "cn",
	"id" => "businessCategory"
];

foreach($xml->grups->grup as $grup){
	$clase = $ldap->XMLParser($grup, $assocs);
	$alumnes = array();

	foreach($grup->alumnes->alumne as $alumne){
		$alumnes[] = strval($alumne["id"]);
	}

	$alumnes = $ldap->generateMultipath($alumnes, "uid", "ou=Alumnes,ou=Users," .$ldap->domain(TRUE));
	echo $ldap->createGroupOfNames($clase["cn"], $alumnes, $clase);
}


// ------------------------

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
	// $user = sanstr($user); // Remove accents
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

	$data["cn"][] = $data["givenName"] ." " .$data["sn"];
	$data["cn"][] = $user;
}

?>
