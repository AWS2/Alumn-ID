# Alumn@ID

Proyecto de fin de curso realizado en el centro [Esteve Terradas](http://esteveterradas.cat/).

## Componentes
El proyecto utiliza componentes de software libre, entre ellos:
- [OpenLDAP](https://github.com/openldap/openldap), para la fuente de datos de contacto.
- [Django](https://github.com/django/django), para la gestión de datos interna.
- [Moodle](https://github.com/moodle/moodle), la plataforma de aprendizaje para institutos y escuelas.
- [nfcpy](https://github.com/nfcpy/nfcpy) y [libnfc](https://github.com/nfc-tools/libnfc), para proveer las funciones de lectura de tarjetas NFC.

## Historia
El proyecto nace de @duhow y @amadoarnau, cada uno ha tenido ideas separadas pero relacionadas, que al final se han unido y se desarrollarán en conjunto.

## Objetivos
Los objetivos principales son los siguientes:
- [x] Fichaje de alumnos y profesores al entrar al centro, a través de un lector NFC.
- [ ] El alumno y sus tutores legales / padres, podrán ver las notas del alumno a través de la plataforma - desarrollada en Django.
- [x] Creación automática de los servidores de datos y cursos de Moodle a través de scripts / parsers / Moodle API, con origen de datos XML [SAGA](http://educacio.gencat.cat/portal/page/portal/Educacio/PCentrePrivat/PCPInici/PCPGestioAdministrativa/PCPAccesSAGA).

## Extras
Otras ideas planteadas son:
- [ ] Guardar la ubicación del personal / alumnado a través de puntos detectores WiFi / Bluetooth
- [ ] Investigar sobre el funcionamiento de los Beacons Bluetooth, para poder hacer fichaje desde el móvil directamente o similar.
