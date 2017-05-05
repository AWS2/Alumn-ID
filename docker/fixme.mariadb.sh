#!/bin/bash
# Set Moodle required settings.
docker-compose exec mariadb sed -i '/default_storage_engine/ a\
innodb_file_format = Barracuda\
innodb_file_per_table = 1\
innodb_large_prefix\
character-set-server = utf8\
collation-server = utf8_unicode_ci
' /etc/mysql/my.cnf