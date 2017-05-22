<?php

class MoodleRest {
    public $url = NULL;
    private $token = NULL;
    private $type = "rest";
    private $debug = FALSE;
    private $last_result = NULL;

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

    public function debug($set = TRUE){
        $this->debug = (bool) $set;
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

    public function query($type, $data = NULL, $column = NULL, $column_key = NULL){
        $url = $this->url . "/webservice/" .$this->type ."/server.php?"
        ."wstoken=" .$this->token ."&"
        ."wsfunction=" .$type ."&"
        ."moodlewsrestformat=json";

		$post = NULL;
		if(!empty($data)){
			$post = http_build_query($data);
		}

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        // curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type:  text/xml"));
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        // curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        // curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

        if($this->debug){
            echo "$type - $post\n";
        }

        $result = curl_exec($ch);
        curl_close($ch);

        $json = json_decode($result);
        $this->last_result = $json;

        if(!empty($column)){
            return $this->return_array($column, $column_key, $json);
        }

        return $json;
    }

    public function return_array($column, $column_key = NULL, $data = NULL){
        if(empty($data)){ $data = $this->last_result; }
        if(empty($data)){ return array(); }

        $ret = array();

        foreach($data as $v){
            if(!empty($column_key)){
                if(isset($v->{$column_key})){
                    $ret[$v->{$column_key}] = $v->{$column};
                }
            }elseif(isset($v->{$column})){
                $ret[] = $v->{$column};
            }
        }

        return $ret;
    }
}

?>
