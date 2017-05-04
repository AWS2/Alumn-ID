<?php

$xml = simplexml_load_file("dades-saga.xml");

$clases = array();

foreach($xml->grups->grup as $grup){
    $etapa = strval($grup['etapa']);
    if(!in_array($etapa, ["CFPM", "CFPS"])){ continue; }

    $nom = strval($grup['nom']);
    $alumnes = array();
    foreach($grup->alumnes->alumne as $alumne){
        $alumnes[] = intval($alumne['id']);
    }

    $continguts = array();
    foreach($grup->continguts->contingut as $asig){
        $profe = intval($asig['professor']);
        $uf = intval($asig['contingut']);

        // NOTA! --------
        // Un profe está matriculado por UF, no por MP.
        // Matricular a todos los que coincidan dentro del MP.
        $continguts[$uf][] = $profe;
    }

    $clases[$nom] = [
        'alumnes' => $alumnes,
        'continguts' => $continguts
    ];
}

var_dump($clases);

?>