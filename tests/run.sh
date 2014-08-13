#!/bin/sh
dir=$(cd `dirname $0` && pwd)
$dir/../vendor/bin/tester -c $dir/php.ini --setup $dir/inc/setup.php $@
