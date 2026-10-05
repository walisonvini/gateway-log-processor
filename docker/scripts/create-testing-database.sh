#!/bin/bash
set -e

# Cria o banco usado pelos testes. O nome é fixo porque o phpunit.xml aponta para ele.
mysql -u root -p"${MYSQL_ROOT_PASSWORD}" <<-EOSQL
CREATE DATABASE IF NOT EXISTS testing;
GRANT ALL PRIVILEGES ON testing.* TO '${MYSQL_USER}'@'%';
FLUSH PRIVILEGES;
EOSQL
