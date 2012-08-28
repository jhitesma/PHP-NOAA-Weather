<?php

/* noaa.php requires xmlize as well, include it here if you like */
require('noaa.php');

/* create weather object */
$ww = new noaa_weather();

/* customize these URLs for your own local area(s) */
$ww->feed_current = 'http://www.weather.gov/xml/current_obs/KNYL.xml';
$ww->feed_forecast = 'http://forecast.weather.gov/MapClick.php?lat=32.698762&lon=-114.6079&FcstType=dwml';

/* get data */
$ww->get_weather();

$icon = $ww->current_icon_default;
$summary = $ww->current_summary;
$temp = $ww->current_temp;
	
print "current conditions";
print '<img border="0" src="' . $icon . '" width="52" height="52" />';
print $summary;
print $temp;

/* 
	print forecast via loop of array
*/
foreach($ww->forecast as $t) {
	$output .= '<li><img border="0" src="' . $t["icon_default"] . '" width="52" height="52" alt="' . $t["forecast"] . '" />' . $t["label"] . ' - ' . $t["forecast"] . '</li>';
}

print '<ul>' . $output . '</ul>';