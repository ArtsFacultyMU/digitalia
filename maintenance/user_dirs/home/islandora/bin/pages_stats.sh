#!/bin/bash

# SOURCE_CSV is a CSV file with just one column with relative path to PDF file

SOURCE_CSV=$1
#SOURCE_CSV=/tmp/part.csv
PREFIX_URL=https://digilib.phil.muni.cz
TMPFILE=/tmp/tmp.pdf
COUNTFILE=/tmp/lastcount.txt
pages=0
documents=0

while read line
do
        echo $line | grep -q -e '_flysystem/fedora/pdf/'

        if [ $? -eq 0 ]
        then
                documents=$(( $documents + 1 ))
		            curl -s -o $TMPFILE ${PREFIX_URL}${line}
                cpages=`pdfinfo $TMPFILE | grep 'Pages:' | cut -c 17- | awk '{s+=$1} END {print s}'`
                if [ "$cpages" != "" ]
                then
                        pages=$(( $cpages + $pages ))
												echo "$line $documents $pages" | tee -a $COUNTFILE 
                fi
                rm -f $TMPFILE
        fi

done < $SOURCE_CSV

echo Total documents: $documents
echo Total pages: $pages
