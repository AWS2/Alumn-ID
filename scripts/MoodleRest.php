<?php

class MoodleRest {
    public $url = NULL;
    private $token = NULL;
    private $type = "rest";

    public function __construct($url = NULL, $token = NULL){
        if(empty($token) and !empty($url)){
            if(!filter_var($url, FILTER_VALIDATE_URL) === FALSE){
                $this->baseUrl($url);
            }else{
                $this->token($url);
            }
        }elseif(!empty($token) and !empty($url)){
            $this->token($token);
            $this->baseUrl($url);
        }
    }

    public function token($data = NULL){
        if(empty($data)){ return $this->token; }
        $this->token = $data;
        return $this;
    }

    public function baseUrl($data = NULL){
        if(empty($data)){ return $this->url; }
        if(substr($data, -1) == "/"){ $data = substr($data, 0, -1); } // remove last slash
        $this->url = $data;
        return $this;
    }

    public function type($data = NULL){
        if(empty($data)){ return $this->type; }
        $this->type = strtolower($data);
        return $this;
    }

    public function query($type, $data){
        $url = $this->url . "/webservice/" .$this->type ."/server.php?"
        ."wstoken=" .$this->token ."&"
        ."wsfunction=" .$type;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        // curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type:  text/xml"));
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

        $result = curl_exec($ch);
        curl_close($ch);

        return $result;
    }
}

?>
