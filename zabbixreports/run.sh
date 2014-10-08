#!/bin/bash
app/console zabbixreports:main \
	--out /tmp/out.pdf \
	--template report1 \
	--param "FROM:2014-10-01 00:00:00" \
	--param "TO:2014-10-07 00:00:00"
