<?php

$xml = simplexml_load_file("dades-saga.xml");

$cursos = array();

foreach($xml->{'plans-estudi'}->{'pla-estudis'} as $curs){
    $etapa = strval($curs['etapa']);
    if(!in_array($etapa, ["CFPM", "CFPS"])){ continue; }

    $categoria = strval($curs['nom']);
    $data["name"] = $categoria;
    $data["id"] = strval($curs['subetapa']);

    $moduls = array();

    foreach($curs->contingut as $content){
        if($content['categoria'] != 'Mòdul'){ continue; }
        $codi = intval($content['codi']);
        $nom = strval($content['nom']);
        $moduls[$codi] = ["name" => $nom];
    }

    foreach($curs->contingut as $content){
        if($content['categoria'] != 'Crèdit' or
            $content['tipus'] != 'Lectiu'){ continue; }

            $codicred = substr($content['codi'], 0, 3);
        $codicred = intval($codicred);

        $codi = substr($content['codi'], -2);
        $codi = intval($codi);

        $nom = strval($content['nom']);

        $moduls[$codicred][$codi] = $nom;
    }

    $data["courses"] = $moduls;

    $cursos[] = $data;
}

echo json_encode($cursos);
