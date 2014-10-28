#!/bin/bash
app/console zabbixreports:main \
	--out /tmp/out.pdf \
	--template templates/FSZEK-hu/report.html.twig \
	--param "FROM:2014-10-01 00:00:00" \
	--param "TO:2014-10-27 00:00:00"
