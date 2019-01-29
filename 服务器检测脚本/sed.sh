#!/usr/bin/env bash

for file in `find ./ -name 'demo.php'`
do
    echo ${file}
    str="if (!preg_match('/^[A-Za-z](\w|\.)*$/', \$controller)) {throw new HttpException(404, 'controller not exists:' . \$controller);}"
    sed -i "376a\ ${str}" ${file}
done