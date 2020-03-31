#!/bin/sh
echo 'Content-type: text/html'
echo ''
echo '<html><head><title>Copy Large Images to Original Images</title>'
echo '</head>'
echo '<body bgcolor="#BBBBBB">'
echo '<h1 align=center>Copy Large Images to Original Images</h1>'
echo '<pre>'
cd ../images
cp large/* original
echo '</pre>'
echo '</body></html>'

