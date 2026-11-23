#!/bin/sh
set -eu

mysql --protocol=socket -uroot -p"${MYSQL_ROOT_PASSWORD}" <<SQL
CREATE DATABASE IF NOT EXISTS rozh_gallery_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON \`rozh_gallery_testing\`.* TO '${MYSQL_USER}'@'%';
FLUSH PRIVILEGES;
SQL
