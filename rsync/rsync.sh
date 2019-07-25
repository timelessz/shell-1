#!/usr/bin/env bash
# 将备份列表文件的路径保存到变量中
backupf='/tmp/bckup.txt'
# 输入一个提示信息
echo "Shell Script Backup Your Files / Directories Using rsync"
# 检查是否输入了目标服务器，如果为空就再次提示用户输入
while [ x$desthost = "x" ]; do
# 提示用户输入目标服务器地址并保存到变量
read -p "Destination backup Server : " desthost
# 结束循环
done
# 检查是否输入了目标文件夹，如果为空就再次提示用户输入
while [ x$destpath = "x" ]; do
# 提示用户输入目标文件夹并保存到变量
read -p "Destination Folder : " destpath
# 结束循环
done
# 逐行读取备份列表文件
for line in `cat $backupf`
# 对每一行都进行处理
do
# 显示要被复制的文件/文件夹名称
echo "Copying $line ... "
# 通过 rsync 复制文件/文件夹到目标位置
rsync -ar "$line" "$desthost":"$destpath"
# 显示完成
echo "DONE"
# 结束
done