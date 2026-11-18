<?php
$h = fopen('C:\Users\PC\.gemini\antigravity-ide\brain\f4e93233-e991-43d5-bb59-76746a5edc3e\.user_uploaded\media_1790058908092.csv', 'r');
$c = 0;
while($d = fgetcsv($h)){
    $c++;
    print "Row $c: " . implode(" | ", array_slice($d, 0, 5)) . "\n";
    if($c == 10) break;
}
fclose($h);
