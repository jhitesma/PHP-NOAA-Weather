<?php
/* noaa.php requires xmlize as well, include it here if you like */
require('noaa.php');

/* create weather object */
$ww = new noaa_weather();

/* customize these URLs for your own local area(s) */
$ww->feed_current = 'http://w1.weather.gov/xml/current_obs/KNYL.xml';
$ww->feed_forecast = 'http://forecast.weather.gov/MapClick.php?lat=32.698762&lon=-114.6079&FcstType=dwml';
$ww->icon_url_path = 'http://cdn.wbur.org/images/weather/';

/* enable/disable current and/or forecast options */
$ww->do_current = 1;
$ww->do_forecast = 0;

/* get data */
$ww->get_weather();

/* print current conditions */
print 'current conditions' . "<br>\n";
print '<img border="0" src="' . $ww->current_icon_default . '" width="52" height="52" />' . "<br>\n";
print $ww->current_summary . "<br>\n";
print $ww->current_temp . "<br>\n";
print 'Wind: ' . $ww->current_wind . "<br>\n";
print 'Humidity: ' . $ww->current_humidity . "<br>\n";

/* print forecast via loop of array */
foreach($ww->forecast as $t) {
	$output .= '<li><img border="0" src="' . $t["icon_default"] . '" width="52" height="52" alt="' . $t["forecast"] . '" />' . $t["label"] . ' - ' . $t["forecast"] . '</li>';
}
print '<ul>' . $output . '</ul>';