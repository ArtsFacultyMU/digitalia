#!/bin/bash

# SOURCE_CSV is a CSV file with just one column with relative path to PDF file

SOURCE_CSV=$1
#SOURCE_CSV=/tmp/part.csv
PREFIX_URL=http://digilib-devel.phil.muni.cz
TMPFILE=tmp.pdf
pages=0

while read line
do
        echo $line | grep -q -e '_flysystem/fedora/pdf/'

        if [ $? -eq 0 ]
        then
		curl -o $TMPFILE ${PREFIX_URL}${line}
                cpages=`pdfinfo $TMPFILE | grep 'Pages:' | cut -c 17- | awk '{s+=$1} END {print s}'`
                if [ "$cpages" != "" ]
                then
                        pages=$(( $cpages + $pages ))
                fi
                rm -f $TMPFILE
        fi

done < $SOURCE_CSV

echo Total pages: $pages
