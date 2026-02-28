@echo off
if exist C:\xampp\apache\logs\error.log (
	type C:\xampp\apache\logs\error.log
	exit 0
)

if exist D:\xampp\apache\logs\error.log (
	type D:\xampp\apache\logs\error.log
	exit 0
)

echo "Datei error.log nicht gefunden oder Datei nicht lesbar vom Webserver."
exit 1