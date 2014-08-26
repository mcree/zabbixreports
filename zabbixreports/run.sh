#!/bin/bash
app/console zabbixreports:main \
	--out /tmp/out.pdf \
	--template report1 \
	--param "FROM:2014-07-01 00:00:00" \
	--param "TO:2014-08-01 00:00:00"
