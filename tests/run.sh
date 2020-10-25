#!/bin/sh
dir=$(cd `dirname $0` && pwd)
"$dir/../vendor/bin/tester" -C --setup "$dir/inc/setup.php" $@
