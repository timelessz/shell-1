#!/usr/bin/expect  -f

spawn su - nginx
expect "password:"
send "testr"
interact