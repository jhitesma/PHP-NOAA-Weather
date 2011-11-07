<?php

/* noaa.php requires xmlize as well, include it here if you like */
require('noaa.php');

/* create weather object */
$ww = new noaa_weather();

/* customize these URLs for your own local area(s) */
$ww->feed_current = 'http://www.weather.gov/xml/current_obs/KBOS.xml';
$ww->feed_forecast = 'http://forecast.weather.gov/MapClick.php?lat=42.35830&lon=-71.06030&FcstType=dwml';

/* get data */
$ww->get_weather();


/* 
<<<<<<< HEAD
<<<<<<< HEAD
	print "current conditions" 	
=======
	print "current conditions" 
	
	For current 
	
	
>>>>>>> 1e9223d... changed icon handling. added example.php
=======
	print "current conditions" 	
>>>>>>> a4ef820... cleaned up comments
*/
$icon = $ww->current_icon_default;
$summary = $ww->current_summary;
$temp = $ww->current_temp;
	
print '<img border="0" src="' . $icon . '" width="52" height="52" />';
print $summary;
print $temp;


<<<<<<< HEAD
<<<<<<< HEAD
/* 
	print forecast via loop of array
*/

=======
>>>>>>> 1e9223d... changed icon handling. added example.php
=======
/* 
	print forecast via loop of array
*/
>>>>>>> a4ef820... cleaned up comments
foreach($ww->forecast as $t) {
	$output .= '<li><img border="0" src="' . $t["icon_default"] . '" width="52" height="52" alt="' . $t["forecast"] . '" />' . $t["label"] . ' - ' . $t["forecast"] . '</li>';
}

print '<ul>' . $output . '</ul>';
