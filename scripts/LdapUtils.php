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
        if(!empty($set)){
            $this->domain = $set;
            $this->dc = $this->domain2DC($set);
        }
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
		$this->path = $path .$this->path;
    }

	/**
	 * Reinicia la ruta a la raiz.
	 */
    public function resetPath(){
		$this->path = "";
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
		return $this->generateLdif($rdn, $data, "top");
    }

	/**
	 * Genera un LDIF según los datos.
	 * @param  string $rdn     RDN Completo.
	 * @param  array $data    Array de datos.
	 * @param  array/string $classes Array o string de clases. Opcional.
	 * @return string          LDIF generado.
	 */
	private function generateLdif($rdn, $data, $classes = NULL){
		$str = "dn: $rdn\n";
		if(!empty($classes)){
			if(is_string($classes)){ $classes = [$classes]; }
			foreach($classes as $class){
				$str .= "objectClass: $class\n";
			}
		}
		foreach($data as $k => $v){
			$str .= "$k: $v\n";
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
    public function XMLParser($selector, $assocs){
    	$ret = array();
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
