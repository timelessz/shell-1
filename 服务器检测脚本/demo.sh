#!/usr/bin/env bash

for file in `find ./ -name robots.txt`
do
    echo ${file}
    sed '2 iUser-agent: Googlebot\nDisallow:/' -i ${file}
done
