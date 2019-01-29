#!/usr/bin/env bash
status=$(ps aux | grep httpd | grep -v grep)
# 截取httpd进程，并把结果赋予变量status
if [[ -n "$status" ]]
# 如果status的值不为空，即httpd服务存在，则执行then中的命令
then
    echo "$(date) httpd is OK!" >> /home/wwwlogs/autostart-acc.log
# 将当前的正确状态记录入正确运行日志中
    echo 'ok';
else
    lnmp start
    /usr/local/memcached/bin/memcached -d -m 256 -l 127.0.0.1 -p 11211 -u root
    /usr/sbin/cron
    # httpd服务异常时，重新启动httpd服务，并将启动信息扔入null文件夹中
    echo "$(date) restart httpd!" >> /tmp/autostart-err.log
    # 重启httpd服务后，将错误信息记录入错误日志文件中
    echo '废了';
fi
