#!/bin/bash

FILE1="/var/log/apache2/error.log"
FILE2="/var/log/httpd/error.log"
FILE3="/var/log/httpd-error.log"

if [ -f $FILE1 ]; then
    cat $FILE1
    exit
fi
   
if [ -f $FILE2 ]; then
    cat $FILE2
    exit
fi

if [ -f $FILE3 ]; then
    cat $FILE3
    exit
fi

echo "Datei error.log nicht gefunden oder Datei nicht lesbar vom Webserver."
exit 1