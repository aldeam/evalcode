#! /bin/bash
# Script básico de instalación de las herramientas necesarias para el módulo EvalCode

VERSION="jdk1.8.0_131"
DIR=`pwd`
MYJAVA=`which java`
JAVA_INSTALL_DIR="/usr/java/jdk1.8.0_131/"
if [[ $EUID -ne 0 ]]; then
   echo "This script must be run as root" 1>&2
   exit 1
fi

tar xzf evalcode-1.0.tar.gz

if [ -z  $MYJAVA ] && [ ! -d "$JAVA_INSTALL_DIR" ]; then
	echo "Installing java $VERSION..."
	mkdir -p /usr/java
	cd /usr/java
	wget --no-cookies --no-check-certificate --header "Cookie: gpw_e24=http%3A%2F%2Fwww.oracle.com%2F; oraclelicense=accept-securebackup-cookie" "http://download.oracle.com/otn-pub/java/jdk/8u131-b11/d54c1d3a095b4ff2b6607d096fa80163/jdk-8u131-linux-i586.tar.gz"
	tar xzf jdk-8u131-linux-i586.tar.gz
else 
	echo "Detected java instalation"
	#MYJAVA=`java -version`
	#if [ ! -z $MYJAVA ]; then
	#	java -version
	#fi
	#TODO: Check if installed version is compatible with EvalCode
fi
cd $DIR
cp -fr evalcode/configuration_files/* /usr/java/jdk1.8.0_131/lib

	
