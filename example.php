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
print '<img border="0" src="' . $ww->current->icon_url_base . '/' . $ww->current->icon_url_name . '" width="52" height="52" />' . "<br>\n";
print $ww->current->weather . "<br>\n";
print $ww->current->temperature_string . "<br>\n";
print 'Wind: ' . $ww->current->wind_string . "<br>\n";
print 'Humidity: ' . $ww->current->relative_humidity . "<br>\n";

print '<hr>';
?>
		<div id="weather_test">
<!--			<img src="/art/weather-placeholder.gif" alt="weather-placeholder.gif" width="390" height="97" border="0" /> -->
			<div class="weather" style="float:left; border-right:1px solid #CCCCCC; margin:10px 10px 10px 0px; padding:0px 0px; 0px 0px;">
				<!--<h3>Current:</h3>-->
				<div class="condition" style="text-align:left; width:185px;">
					<div style="float:left; width:60px; margin:0px 10px 0px 0px;">
						<img src="<?=$ww->current->icon_url_base?>/<?=$ww->current->icon_url_name?>" alt="weather"?>
					</div>
					<div style="float:left; width:115px;">
						<span style="font-size:8pt; line-height:10pt;"><b>Current Weather</b></span><br>
						<span style="font-size:16pt; line-height:18pt;"><b><?= $ww->current->temp_f ?>&deg;F</b></span><br>
						<span style="font-size:8pt; line-height:10pt;">
							<?= $ww->current->weather ?><br>
							Wind: <?=$ww->current->wind_dir?> <?=$ww->current->wind_mph?> MPH<br>
							Humidity: <?= $ww->current->relative_humidity ?>
						</span>
					</div>
					<div style="clear:both;"></div>
				</div>
			</div>
			<!--<h3>Forecast</h3>-->
			<? 
			$fc = 1;
			foreach ($weather_data['forecast_conditions'] as $forecast) { 
				if ($fc>1) { 
					?>
					<div class="weather" style="display:inline; text-align:center; margin:10px 5px; width:65px; float:left;">
					<div><span style="font-size:8pt; line-height:12pt;"><b><?= $forecast['day_of_week']; ?></b></span></div>
					<img src="<?= str_replace('icons/m','/art/weather/small',$forecast['icon'])?>" alt="weather"?>
					<br>
						<span class="condition">
							<span style="color:#000000; font-size:9pt;"><?= $forecast['high']['f'] ?>&deg;</span> <span style="color:#6699CC; font-size:9pt;"><?= $forecast['low']['f'] ?>&deg;</span>
							<!--<?= $forecast->condition['data'] ?>-->
						</span>
					</div>
				<? } 
				$fc=$fc+1; 
			} ?>
		</div>
	</div>
<?


print '<br clear="all"><hr>';
print 'All available current conditions:' . "<br>";
foreach($ww->current as $title => $value) {
	print '<b>' . $title . ':</b>&nbsp;' . $value . "<br>\n";
}







/* print forecast via loop of array */
foreach($ww->forecast as $t) {
	$output .= '<li><img border="0" src="' . $t["icon_default"] . '" width="52" height="52" alt="' . $t["forecast"] . '" />' . $t["label"] . ' - ' . $t["forecast"] . '</li>';
}
print '<ul>' . $output . '</ul>';