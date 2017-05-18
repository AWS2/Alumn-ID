<?php

class LdapUtils {

	private $domain;
    private $dc;
	private $path;

	public function __construct($domain = NULL){
		if(!empty($domain)){ $this->domain($domain); }
	}

	/**
	 * Establece u obtiene el dominio para las conexiones.
	 * @param  string $set Dominio a registrar.
	 * @return string      Si $set es NULL, devuelve el dominio actual.
	 */
    public function domain($set = NULL){
        if(!empty($set) and $set !== TRUE){
            $this->domain = $set;
            $this->dc = $this->domain2DC($set);
        }
		if($set === TRUE){ return $this->dc; }
        return $this->domain;
    }

	/**
	 * Sanitiza una string quitando acentos.
	 * @param  string $t Texto original
	 * @return string
	 */
	public function sanitizeStr($t){
		$t = str_replace(["à", "è", "ò", "ù"], ["a", "e", "o", "u"], $t);
		$t = str_replace(["À", "È", "Ò", "Ù"], ["A", "E", "O", "U"], $t);
		$t = str_replace(["á", "é", "í", "ó", "ú"], ["a", "e", "i", "o", "u"], $t);
		$t = str_replace(["Á", "É", "Í", "Ó", "Ú"], ["A", "E", "I", "O", "U"], $t);
		return $t;
	}

	/**
	 * Devuelve la ruta completa.
	 * @return string
	 */
    public function pwd(){
		$path = $this->path;
		if(!empty($path)){ $path .= ","; }
		return $path .$this->dc;
    }

    public function cd($path, $absolute = FALSE){
		if($absolute){ $this->resetPath(); }
		if(!empty($this->path)){
			$this->path = "," .$this->path;
		}
		// Girar datos
		if(is_string($path)){ $path = explode(",", $path); }
		$path = array_reverse($path);
		$path = implode(",", $path);

		$this->path = $path .$this->path;
		return $this;
    }

	public function path($access, $addDomain = TRUE, $reverse = FALSE){
		if(is_string($access)){ $access = explode(",", $access); }
		$final = array();
		if($reverse){ $access = array_reverse($access); }
		foreach($access as $a){
			if(empty($a)){ continue; }
			$a = str_replace(",", "", $a);
			$final[] = trim($a);
		}
		if($addDomain){
			$access = explode(",", $this->domain(TRUE));
			foreach($access as $a){
				$final[] = $a;
			}
		}

		return implode(",", $final);
	}

	public function hash($password, $type = "sha", $salt = NULL){
		// Add option to function.
		if(!empty($salt) && !empty($type) && strtolower($salt) == "ssha"){
			// Function called as $pass, $salt, $type. Reorder.
			$salt = $type;
			$password = [$password, $salt];
			$type = "ssha";
		}
		$allowed = ["sha", "sha1", "sha512", "ssha", "md5"];
		$type = strtolower($type);
		if(!in_array($type, $allowed)){ return FALSE; }

		$hash = "";

		switch ($type) {
			case 'sha1':
			case 'sha':
				$type = "sha";
				$hash = hex2bin(sha1($password));
			break;
			case 'ssha':
				if(empty($salt)){
					$salt = mt_rand(10000000, 99999999);
				}
				if(is_array($password) and count($password) == 2){
					$salt = $password[1];
					$password = $password[0];
				}
				$hash = sha1($password . $salt) . $salt;
			break;

			case 'sha512':
				if(function_exists("hash")){
					$hash = hex2bin(hash('sha512', $password));
				}else{
					return $this->hash($password, "sha1");
				}
			break;

			case 'md5':
				$hash = hex2bin(md5($password));
			break;
		}

		if(empty($hash)){ return NULL; }

		// No todos los hashes codifican en binario,
		// asi que delegamos la funcion hex2bin a cada algoritmo.
		$hash = base64_encode($hash);

		return '{' .strtoupper($type) .'}' .$hash;
	}

	/**
	 * Reinicia la ruta a la raiz.
	 */
    public function resetPath(){
		$this->path = "";
		return $this;
    }

	/**
	 * Genera LDIF para crear una OU.
	 * @param  string  $name  Nombre de la OU.
	 * @param  boolean $enter Entrar al path después de crear la OU.
	 * @return string         LDIF generdo de la OU.
	 */
    public function createOU($name, $enter = TRUE){
		$path = $this->path;
		$rdn = "ou=$name," .$this->pwd();
		$data = ["ou" => $name];
		if($enter){ $this->cd("ou=$name"); }
		return $this->generateLdif($rdn, $data, ["organizationalUnit", "top"]);
    }

	public function createUser($data, $rdn = NULL, $classes = NULL){
		if(empty($classes)){ $classes = array(); }
		elseif(is_string($classes)){ $classes = [$classes]; }
		$classes[] = "posixAccount";
		$classes[] = "inetOrgPerson";
		$classes = array_unique($classes);

		if(empty($rdn)){
			if(!isset($data["cn"])){ return FALSE; }
			$rdn = $data["cn"] ."," .$this->pwd();
		}elseif(strpos($rdn, "=") === FALSE){
			if(!isset($data[$rdn])){ return FALSE; }
			$rdn = $rdn ."=" .$data[$rdn] ."," .$this->pwd();
		}elseif(strpos($rdn, ",") !== FALSE){
			$rdn = $rdn . "," .$this->pwd();
		}

		if(!isset($data["homeDirectory"])){
			$data["homeDirectory"] = "/home/" .$data["uid"];
		}

		$must = ["cn", "sn", "uid", "gidNumber", "homeDirectory"];
		foreach($must as $m){
			if(!isset($data[$m])){ return FALSE; }
		}

		return $this->generateLdif($rdn, $data, $classes);
	}

	public function addMemberGroup($members, $path = NULL){
		if(!is_array($members)){ $members = [$members]; }
		return $this->__genericMemberToGroup($members, TRUE, $path);
	}

	public function deleteMemberGroup($members, $path){
		if(!is_array($members)){ $members = [$members]; }
		return $this->__genericMemberToGroup($members, FALSE, $path);
	}

	private function __genericMemberToGroup($members, $fieldAction, $path = NULL){
		$data = array();
		if(empty($path)){ $path = $this->pwd(); }

		if($fieldAction === TRUE){ $fieldAction = "add"; }
		elseif($fieldAction === FALSE){ $fieldAction = "delete"; }

		foreach($members as $member){
			$data["member"][] = $member;
		}

		$data["changeType"] = "modify";
		$data[$fieldAction] = "member";

		return $this->generateLdif($path, $data);
	}

	public function createPosixGroup($cn, $gidNumber = NULL, $extra = NULL){
		if(is_array($cn)){
			if(isset($cn["gidNumber"])){
				$gidNumber = $cn["gidNumber"];
				unset($cn["gidNumber"]);
			}
			if(isset($cn["cn"])){
				$extra = $cn;
				$cn = $cn["cn"];
				unset($extra["cn"]);
			}
		}

		$gidNumber = intval($gidNumber);
		if(empty($gidNumber)){ return FALSE; }

		if(empty($extra)){ $extra = array(); }
		$extra["cn"] = $cn;
		$extra["gidNumber"] = $gidNumber;

		$rdn = "cn=$cn," .$this->pwd();

		return $this->generateLdif($rdn, $extra, ["posixGroup", "top"]);
	}

	public function createGroupOfNames($cn, $members, $extra = NULL){
		// cn, member => RDN, businessCategory, description, o, ou, owner -> RDN, seeAlso -> RDN

		if(is_string($members)){
			$members = $this->generateMultipath($members, "cn", TRUE);
			/* if(strpos($members, "=") === FALSE){
				// Try to fix by adding member to this path.
				$members = "cn=$members," .$this->pwd();
			} */
		}

		if(empty($extra)){ $extra = array(); }
		$extra["member"] = $members;
		$extra["cn"] = $cn;

		$rdn = "cn=$cn," .$this->pwd();
		return $this->generateLdif($rdn, $extra, ["groupOfNames", "top"]);
	}

	public function addMemberGroup($members, $path = NULL){
		if(!is_array($members)){ $members = [$members]; }
		return $this->__genericMemberToGroup($members, TRUE, $path);
	}

	public function deleteMemberGroup($members, $path){
		if(!is_array($members)){ $members = [$members]; }
		return $this->__genericMemberToGroup($members, FALSE, $path);
	}

	private function __genericMemberToGroup($members, $fieldAction, $path = NULL){
		$data = array();
		if(empty($path)){ $path = $this->pwd(); }

		if($fieldAction === TRUE){ $fieldAction = "add"; }
		elseif($fieldAction === FALSE){ $fieldAction = "delete"; }

		foreach($members as $member){
			$data["member"][] = $member;
		}

		$data["changeType"] = "modify";
		$data[$fieldAction] = "member";

		return $this->generateLdif($path, $data);
	}

	public function generateMultipath($content, $rdn = NULL, $path = NULL){
		if(empty($path) or $path === TRUE){ $path = $this->pwd(); }
		if(is_string($content)){ $content = [$content]; }
		$data = array();

		foreach($content as $user){
			$rdn_set = $rdn;

			if(strpos($user, "=") !== FALSE){
				$user = explode("=", $user);
				$rdn_set = $user[0];
				$user = $user[1]; // array_pop($user);
			}
			$user = str_replace(",", "", $user);
			if(empty($rdn_set)){
				$rdn_set = "cn";
				// $rdn = "cn"; // Set default?
			}

			$data[] = $rdn_set ."=" .$user ."," .$path;
		}

		return $data;
	}

	/**
	 * Genera un LDIF según los datos.
	 * @param  string $rdn     RDN Completo.
	 * @param  array $data    Array de datos.
	 * @param  array/string $classes Array o string de clases. Opcional.
	 * @return string          LDIF generado.
	 */
	private function generateLdif($rdn, $data, $classes = NULL){
		if(strpos($rdn, ",") === FALSE){
			$rdn = $rdn ."," .$this->pwd();
		}
		$str = "dn: $rdn\n";
		if(!empty($classes)){
			if(is_string($classes)){ $classes = [$classes]; }
			foreach($classes as $class){
				$str .= "objectClass: $class\n";
			}
		}
		foreach($data as $k => $v){
			if(!is_array($v)){ $v = [$v]; }
			foreach($v as $vu){
				$str .= "$k: $vu\n";
			}
		}

		$str .= "\n";
		return $str;
	}

	/**
	 * Convierte un dominio string a sintáxis DC LDAP.
	 * @param  string/array $dom Dominio a convertir
	 * @return string      Dominio en formato DC.
	 */
    public function domain2DC($dom){
    	if(is_string($dom)){ $dom = explode(".", $dom); }
    	foreach($dom as $i => $d){ $dom[$i] = "dc=$d"; }
    	return implode(",", $dom);
    }

	/**
	 * Genera un LDIF en base a los datos XML proporcionados.
	 * @param XML $selector Selector XML que contiene los datos.
	 * @param array $assocs Array asociativo para convertir los datos.
	 * @return array
	 */
    public function XMLParser($selector, $assocs, $unique = FALSE){
    	$ret = array();
		if($unique){ $selector = [$selector]; }
    	foreach($selector as $p){
			$data = array();
	        $vals = current($p->attributes());
    		foreach($vals as $k => $v){
    			if(in_array($k, array_keys($assocs))){
    				$v = trim($v); // HACK

    				if(isset($data[$assocs[$k]])){
    					if(!is_array($data[$assocs[$k]])){
    						$data[$assocs[$k]] = [ $data[$assocs[$k]] ];
    					}
    					$data[$assocs[$k]][] = $v;
    				}else{
    					$data[$assocs[$k]] = $v;
    				}
    			}
    		}
    		$ret[] = $data;
    	}
    	return $ret;
    }
}

 ?>
