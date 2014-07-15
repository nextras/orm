#!/bin/sh
dir=$(cd `dirname $0` && pwd)
$dir/../vendor/bin/tester -c $dir/php.ini $dir/cases/ --setup $dir/inc/setup.php $@
