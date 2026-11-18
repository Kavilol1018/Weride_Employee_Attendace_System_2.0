<?php
$h = fopen('C:\Users\PC\.gemini\antigravity-ide\brain\f4e93233-e991-43d5-bb59-76746a5edc3e\.user_uploaded\media_1790058908092.csv', 'r');
$c = 0;
while($d = fgetcsv($h)){
    $c++;
    print "Row $c col 0: " . $d[0] . "\n";
    print "Row $c col 1: " . (isset($d[1]) ? $d[1] : 'null') . "\n";
    if($c == 4) break;
}
fclose($h);
