#!/bin/bash

function postUpdate {
	rm -rf ../../../../app/webcms2 2> /dev/null
	rm -rf ../../../../www/admin-module 2> /dev/null

	cp -r libs/webcms2/webcms2 app/webcms2
	mkdir www/admin-module
	cp -r libs/webcms2/webcms2/AdminModule/client-side/* www/admin-module/
}

while [ "$task" != "q" ]; do
	
	if [ "$1" == "" ]; then

		echo "Choose command:"
		echo "1) First install"
		echo "2) Post installation"
		echo "q) Quit"

		echo $vypis
		vypis=""

		echo -n "Type command number>"
		read -s -n 1 task

	else task=$1
	fi
	
	if [ "$first" == "" ]; then
		first="nope"
	fi

	if [ "$task" == "1" ]; then
		
		# creates directories
		mkdir ../../../../www/upload
		mkdir ../../../../www/thumbnails
		
		# sets rights for temp directory
		chmod -R 777 ../../../../www/upload ../../../../www/thumbnails ../../../../temp ../../../../log ../../../../app/proxies ../../../../composer.lock ../../../../libs/composer ../../../../libs/webcms2 ../../../../libs/autoload.php
		chmod -R g+rwxs temp
		
		postUpdate

		# generate DB schema
		php ../../../../www/index.php --ansi orm:schema-tool:create

		# run initial SQL script
		php ../../../../www/index.php --ansi dbal:import install/initial.sql

		vypis="Installation has been executed. Choose another command or type 'q' to quit."
	
	elif [ "$task" == "2" ]; then
		
		postUpdate
				
		# generate DB schema
		php ../../../../www/index.php --ansi orm:schema-tool:update --force

		# generate proxies
		php ../../../../www/index.php --ansi orm:generate-proxies

		# run initial SQL script
		php ../../../../www/index.php --ansi dbal:import install/initial.sql

		vypis="System has been updated."

	elif [ "$task" == "q" ]; then

		echo ""	
		echo "Quitting application. Good bye!"
		sleep 1
		exit 0
	else

	   vypis="Bad parameter given."
	   sleep 1

	fi

if [ "$1" != "" ]; then
 echo $vypis
 sleep 1
 exit 0
else
 #clear
 echo ""
fi

done
