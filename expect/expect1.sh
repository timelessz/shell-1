#!/usr/bin/expect 
set timeout 3
spawn su
expect "password"
send "201671zhuang\r"
interact