<?php
/*数据库配置*/
$dbconfig=array(
    'host' => getenv('DB_HOST') ?: '101.42.200.84', //数据库服务器，支持环境变量 DB_HOST
    'port' => getenv('DB_PORT') ? (int)getenv('DB_PORT') : 3306, //数据库端口，支持环境变量 DB_PORT
    'user' => getenv('DB_USER') ?: 'root', //数据库用户名，支持环境变量 DB_USER
    'pwd' => getenv('DB_PASSWORD') ?: 'gkmzjaznXX55', //数据库密码，支持环境变量 DB_PASSWORD
    'dbname' => getenv('DB_NAME') ?: 'netcccyun' //数据库名，支持环境变量 DB_NAME
);
