<?php

require_once 'LdapUtils.php';

$xml = simplexml_load_file("dades-saga.xml");

$assocs = [
	"id" => "uidNumber", // uid
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

$GID_Users = 500;
$GID_Person = 501;

$alumnes	= $ldap->XMLParser($xml->alumnes->alumne, $assocs);
$pares		= $ldap->XMLParser($xml->{"tutors-legals"}->{"tutor-legal"}, $assocs);
$profes		= $ldap->XMLParser($xml->personal->personal, $assocs);

echo $ldap->createOU("Groups", FALSE);
echo $ldap->createOU("Users", TRUE);
	echo $ldap->createOU("Alumnes", FALSE);
	echo $ldap->createOU("Professors", FALSE);
	echo $ldap->createOU("Pares", FALSE);

$ldap->cd("ou=Groups", TRUE);
echo $ldap->createPosixGroup("Users", $GID_Users, ["description" => "Usuarios que pueden acceder."]);
echo $ldap->createPosixGroup("Person", $GID_Person, ["description" => "Usuarios de acceso limitado."]);

$ldap->cd("ou=Users,ou=Alumnes", TRUE);
foreach($alumnes as $alumne){
	fixvals($alumne);

	if(isset($alumne["seeAlso"])){
		$final = array();
		if(!is_array($alumne["seeAlso"])){ $alumne["seeAlso"] = [$alumne["seeAlso"]]; }
		foreach($alumne["seeAlso"] as $also){
			$final[] = "uidNumber=$also,ou=Pares,ou=Users," .$ldap->domain(TRUE);
		}
		$alumne["seeAlso"] = $final;
	}

	$alumne["uid"] = $ldap->sanitizeStr($alumne["uid"]);
	$alumne["gidNumber"] = $GID_Users;
	$alumne["userPassword"] = $ldap->hash($alumne["uid"], "sha1");

	echo $ldap->createUser($alumne, "uidNumber", "persona");
}

$ldap->cd("ou=Users,ou=Pares", TRUE);
foreach($pares as $pare){
	fixvals($pare);

	$pare["gidNumber"] = $GID_Person;
	$pare["uid"] = $ldap->sanitizeStr($pare["uid"]);
	$pare["userPassword"] = $ldap->hash($pare["uid"], "sha1");

	echo $ldap->createUser($pare, "uidNumber", "persona");
}

$ldap->cd("ou=Users,ou=Professors", TRUE);
foreach($profes as $profe){
	fixvals($profe);

	$profe["gidNumber"] = $GID_Users;
	$profe["uid"] = $ldap->sanitizeStr($profe["uid"]);
	$profe["userPassword"] = $ldap->hash($profe["uid"], "sha1");

	echo $ldap->createUser($profe, "uidNumber", "persona");
}

// ------------------------

$ldap->cd("ou=Groups", TRUE);

$assocs = [
	"nom" => "cn",
	"id" => "businessCategory",
	"codi" => "description",
];

foreach($xml->grups->grup as $grup){
	$clase = $ldap->XMLParser($grup, $assocs, TRUE);
	$clase = $clase[0];
	$alumnes = array();

	foreach($grup->alumnes->alumne as $alumne){
		$alumnes[] = strval($alumne["id"]);
	}

	$alumnes = $ldap->generateMultipath($alumnes, "uid", "ou=Alumnes,ou=Users," .$ldap->domain(TRUE));
	echo $ldap->createGroupOfNames($clase["cn"], $alumnes, $clase);
}


// ------------------------

$ldap->cd("ou=Groups", TRUE);

$pares_id = array_column($pares, "uidNumber");
$pares_id = $ldap->generateMultipath($pares_id, "uidNumber", $ldap->path("cn=Pares,ou=Users", TRUE));

$profes_id = array_column($profes, "uidNumber");
$profes_id = $ldap->generateMultipath($profes_id, "uidNumber", $ldap->path("cn=Pares,ou=Users"), TRUE);

echo $ldap->createGroupOfNames("Pares", $pares_id, ["description" => "Pares que poden accedir a la aplicacio de gestio."]);
echo $ldap->createGroupOfNames("Profes", $profes_id, ["description" => "Profes que poden accedir a la aplicacio de gestio."]);

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

	$data["cn"] = $data["givenName"] ." " .$data["sn"];
	$data["uid"] = $user;
}

?>
