#!/usr/bin/env bash
#某天发现/home分区满了，想知道是哪个目录占了大头，使用该脚本可以帮你完成排序
du --max-depth=1 /home/ | sort -n -r
