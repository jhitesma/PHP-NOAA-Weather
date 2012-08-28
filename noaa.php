<?php
/*
	Class: 	noaa_weather

	Description
	----------------------------
	Gets current and forecast weather data from weather.gov.  Using weather.gov recommendations,
	a method is also provided which groups weather conditions and maps each group to a graphical weather icon.

	Dependencies
	----------------------------
	xmlize - https://github.com/rmccue/XMLize
	Used for de-serializing weather xml feeds

	Data sources
	----------------------------
	DW Namespace
		http://www.nws.noaa.gov/mdl/XML/Design/MDL_XML_Design.htm

	Current Conditions
		http://www.weather.gov/xml/current_obs/KBOS.xml

	Forecast
		http://forecast.weather.gov/MapClick.php?lat=42.35830&lon=-71.06030&FcstType=dwml

	Suggested Icon -> condition mappings
		http://www.weather.gov/xml/current_obs/weather.php
		http://www.crh.noaa.gov/riw/?n=forecast_icons

*/

require_once($home_path . 'includes/xmlize/xmlize.inc');

class noaa_weather {

	public $forecast = array();
	public $feed_current,$feed_forecase,$icon_url_path,$current_temp, $current_summary, $current_icon, $do_current=1, $do_forecast=0;
	private $xml, $min_temp, $max_temp, $min_label, $max_label, $main_label;

	public function get_weather() {
		/* Populates public properties for current temp/summary and the forecast assoc. array */
		if ($this->do_current) {$this->get_current();}
		if ($this->do_forecast) {$this->get_forecast();}
	}

	private function get_data($url)
	{
		$ch = curl_init();
		$timeout = 5;
		curl_setopt($ch,CURLOPT_URL,$url);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,$timeout);
		$data = curl_exec($ch);
		curl_close($ch);
		return $data;
	}

	private function get_xml($type) {
		switch($type) {
			case 'forecast':
				$url = $this->feed_forecast;
				break;
			case 'current':
				$url = $this->feed_current;
				break;
			default;
				$url = $this->feed_current;
				break;
		}
		$data = $this->get_data($url);
		$this->xml = xmlize($data);
	}

	/* In order to find out which section holds which layout, we need to find the layout-key child inside time-layout
	and match it to an attribute in the the data element */
	private function get_layout() {

		$time_layouts = $this->xml["dwml"]["#"]["data"][0]["#"]["time-layout"];
		$temps = $this->xml["dwml"]["#"]["data"][0]["#"]["parameters"][0]["#"]["temperature"];

		for($i = 0; $i < sizeof($time_layouts); $i++) {

			/* Find time_layout with more than 8 children, this is main layout */
			if( sizeof( $time_layouts[$i]["#"]["start-valid-time"] ) > 8 ) {
				$this->main_label = $i;
			}

			/* Now loop through temperature elements and match up with temperature layouts */
			for($j = 0; $j < sizeof($temps); $j++) {

				if( $temps[$j]["@"]["time-layout"] == $time_layouts[$i]["#"]["layout-key"][0]["#"] ) {
					switch( $temps[$j]["@"]["type"] ) {
						case "minimum":
							$this->min_temp = $j;
							$this->min_label = $i;
							break;
						case "maximum":
							$this->max_temp = $j;
							$this->max_label = $i;
						default:
							break;
					}
				}
			}
		}
	}

	/* Format xml as Forecast */
	private function get_forecast() {

		$this->get_xml('forecast');
		$this->get_layout();
		$x = $this->xml["dwml"]["#"]["data"][0]["#"];

		/* Labels, summary and forecasts each have 13 entries for current/day/night periods */
		$labels = $x["time-layout"][$this->main_label]["#"]["start-valid-time"];
		$summaries = $x["parameters"][0]["#"]["weather"]["0"]["#"]["weather-conditions"];
		$forecasts = $x["parameters"][0]["#"]["wordedForecast"]["0"]["#"]["text"];
		$icons = $x["parameters"][0]["#"]["conditions-icon"]["0"]["#"]["icon-link"];

		/*
			hi/low temps have 6-7 entries that match day or night periods, each in a different section of the xml doc
			get_layout() matches the labels with the temp elements and writes the indexes to the 4 variables below
		*/
		$high_temps = $x["parameters"][0]["#"]["temperature"][$this->max_temp]["#"]["value"];
		$high_labels = $x["time-layout"][$this->max_label]["#"]["start-valid-time"];
		$low_temps = $x["parameters"][0]["#"]["temperature"][$this->min_temp]["#"]["value"];
		$low_labels = $x["time-layout"][$this->min_label]["#"]["start-valid-time"];

		/* High/Low temperature temp arrays */
		$arr_high = array();
		$arr_low = array();

		/* Create label array for high temp */
		for($i = 0; $i < sizeof($high_labels); $i++) {
			$template = array(
				'label' => $high_labels[$i]["@"]["period-name"],
				'temp' => ''
			);
			array_push($arr_high, $template);
		}

		/* Add temps to high temp array */
		for($i = 0; $i < sizeof($high_temps); $i++) {
			$arr_high[$i]["temp"] = $high_temps[$i]["#"];
		}

		/* Create label array for low temp */
		for($i = 0; $i < sizeof($low_labels); $i++) {
			$template = array(
				'label' => $low_labels[$i]["@"]["period-name"],
				'temp' => ''
			);
			array_push($arr_low, $template);
		}

		/* Add temps to low temp array */
		for($i = 0; $i < sizeof($low_temps); $i++) {
			$arr_low[$i]["temp"] = $low_temps[$i]["#"];
		}

		/* Create MAIN array, add labels */
		for($i = 0; $i < sizeof($labels); $i++) {
			$template = array(
				'label' => $labels[$i]["@"]["period-name"],
				'summary' => '',
				'forecast' => '',
				'high' => '',
				'low' => '',
				'icon_default' => '',
				'icon_custom' => ''
			);
			array_push($this->forecast, $template);
		}

		/* add weather summary to main array  */
		for($i = 0; $i < sizeof($summaries); $i++) {
			$this->forecast[$i]["summary"] = $summaries[$i]["@"]["weather-summary"];
			$this->forecast[$i]["icon_default"] = $icons[$i]["#"];
			$this->forecast[$i]["icon_custom"] = $this->get_weather_icon( $summaries[$i]["@"]["weather-summary"] );
		}

		/* add forecast to main array  */
		for($i = 0; $i < sizeof($forecasts); $i++) {
			$this->forecast[$i]["forecast"] = $forecasts[$i]["#"];
		}

		/* Loop through each period in main array */
		for($i = 0; $i < sizeof($this->forecast); $i++) {

			/* Assign HIGH temp */
			foreach($arr_high as $h) {
				/* if temp label = main label, assign temp */
				if( $h["label"] == $this->forecast[$i]["label"] )
					$this->forecast[$i]["high"] = $h["temp"] . '&deg;';
			}

			/* Assign LOW temp */
			foreach($arr_low as $l) {
				/* if temp label = main label, assign temp */
				if( $l["label"] == $this->forecast[$i]["label"] )
					$this->forecast[$i]["low"] = $l["temp"] . '&deg;';

				/* Add low temps to non-Night entries */
				if( $l["label"] == $this->forecast[$i]["label"] . " Night" )
					$this->forecast[$i]["low"] = $l["temp"] . '&deg;';
			}
		}
	}


	/* Format xml as current conditions box */
	private function get_current() {

		$this->get_xml('current');
		$current = $this->xml["current_observation"];
		$this->current_temp = intval($current['#']['temp_f'][0]['#']) . '&deg;';
		$this->current_summary = $current['#']['weather'][0]['#'];
		$this->current_icon_default = $current['#']['icon_url_base'][0]['#'] . $current['#']['icon_url_name'][0]['#'];
		$this->current_icon_custom = $this->get_weather_icon( $current['#']['weather'][0]['#'] );
	}

	/*
		Map summary/conditions with graphical representation.
		Source: http://www.weather.gov/xml/current_obs/weather.php

		Evidently, the list provided in the link above is not complete.  I have discovered some basic summaries not included
		and have added them manually.  More summaries may needed to be added.
		TODO:  Change function to match words and choose icon
			E.g.  If summary contains "fog/haze/smoke", use x.jpg
	*/
	private function get_weather_icon($summary) {
		$icon = "";

		switch($summary) {
			/* Mostly Cloudy | Mostly Cloudy with Haze | Mostly Cloudy and Breezy */
			case "Mostly Cloudy":
			case "Mostly Cloudy with Haze":
			case "Mostly Cloudy and Breezy":
				$icon = "69.jpg";
				break;

			/* Fair | Clear | Fair with Haze | Clear with Haze | Fair and Breezy | Clear and Breezy */
			case "Fair":
			case "Clear":
			case "Fair with Haze":
			case "Clear with Haze":
			case "Fair and Breezy":
			case "Clear and Breezy":
			case "Sunny":
				$icon = "68.jpg";
				break;

			/* A Few Clouds | A Few Clouds with Haze | A Few Clouds and Breezy */
			case "A Few Clouds":
			case "A Few Clouds with Haze":
			case "A Few Clouds and Breezy":
			case "Partly Sunny": /* Added Manually */
			case "Mostly Clear":	/* Added manually */
				$icon = "66.jpg";
				break;

			/* Partly Cloudy | Partly Cloudy with Haze | Partly Cloudy and Breezy */
			case "Partly Cloudy":
			case "Partly Cloudy with Haze":
			case "Partly Cloudy and Breezy":
				$icon = "66.jpg";
				break;

			/* Overcast | Overcast with Haze | Overcast and Breezy */
			case "Overcast":
			case "Overcast with Haze":
			case "Overcast and Breezy":
				$icon = "67.jpg";
				break;

			/* Fog/Mist | Fog | Freezing Fog | Shallow Fog | Partial Fog | Patches of Fog | Fog in Vicinity
			Freezing Fog in Vicinity | Shallow Fog in Vicinity | Partial Fog in Vicinity | Patches of Fog in Vicinity
			Showers in Vicinity Fog | Light Freezing Fog | Heavy Freezing Fog */
			case "Fog/Mist":
			case "Fog":
			case "Freezing Fog":
			case "Shallow Fog":
			case "Partial Fog":
			case "Patches of Fog":
			case "Fog in Vicinity":
			case "Freezing Fog in Vicinity":
			case "Shallow Fog in Vicinity":
			case "Partial Fog in Vicinity":
			case "Patches of Fog in Vicinity":
			case "Showers in Vicinity Fog":
			case "Light Freezing Fog":
			case "Heavy Freezing Fog":
				$icon = "70.jpg";
				break;

			// Smoke
			case "Smoke":
				$icon = "70.jpg";
				break;

			/* Freezing Rain | Freezing Drizzle | Light Freezing Rain | Light Freezing Drizzle
				Heavy Freezing Rain | Heavy Freezing Drizzle | Freezing Rain in Vicinity | Freezing Drizzle in Vicinity */
			case "Freezing Rain":
			case "Freezing Drizzle":
			case "Light Freezing Rain":
			case "Light Freezing Drizzle":
			case "Heavy Freezing Rain":
			case "Heavy Freezing Drizzle":
			case "Freezing Rain in Vicinity":
			case "Freezing Drizzle in Vicinity":
				$icon = "76.jpg";
				break;


			/* Ice Pellets | Light Ice Pellets | Heavy Ice Pellets | Ice Pellets in Vicinity
				Showers Ice Pellets | Thunderstorm Ice Pellets | Ice Crystals | Hail | Small Hail/Snow Pellets
				Light Small Hail/Snow Pellets | Heavy small Hail/Snow Pellets | Showers Hail | Hail Showers */
			case "Ice Pellets":
			case "Light Ice Pellets":
			case "Heavy Ice Pellets":
			case "Ice Pellets in Vicinity":
			case "Showers Ice Pellets":
			case "Thunderstorm Ice Pellets":
			case "Ice Crystals":
			case "Hail":
			case "Small Hail/Snow Pellets":
			case "Light Small Hail/Snow Pellets":
			case "Heavy small Hail/Snow Pellets":
			case "Showers Hail":
			case "Hail Showers":
				$icon = "89.jpg";
				break;

			/* Freezing Rain Snow | Light Freezing Rain Snow | Heavy Freezing Rain Snow | Freezing Drizzle Snow
				Light Freezing Drizzle Snow | Heavy Freezing Drizzle Snow | Snow Freezing Rain | Light Snow Freezing Rain
				Heavy Snow Freezing Rain | Snow Freezing Drizzle | Light Snow Freezing Drizzle | Heavy Snow Freezing Drizzle */
			case "Freezing Rain Snow":
			case "Light Freezing Rain Snow":
			case "Heavy Freezing Rain Snow":
			case "Freezing Drizzle Snow":
			case "Light Freezing Drizzle Snow":
			case "Heavy Freezing Drizzle Snow":
			case "Snow Freezing Rain":
			case "Light Snow Freezing Rain":
			case "Heavy Snow Freezing Rain":
			case "Snow Freezing Drizzle":
			case "Light Snow Freezing Drizzle":
			case "Heavy Snow Freezing Drizzle":
				$icon = "79.jpg";
				break;

			/* Rain Ice Pellets | Light Rain Ice Pellets | Heavy Rain Ice Pellets | Drizzle Ice Pellets
				Light Drizzle Ice Pellets	|	Heavy Drizzle Ice Pellets | Ice Pellets Rain | Light Ice Pellets Rain
				Heavy Ice Pellets Rain | Ice Pellets Drizzle | Light Ice Pellets Drizzle | Heavy Ice Pellets Drizzle */
			case "Rain Ice Pellets":
			case "Light Rain Ice Pellets":
			case "Heavy Rain Ice Pellets":
			case "Drizzle Ice Pellets":
			case "Light Drizzle Ice Pellets":
			case "Heavy Drizzle Ice Pellets":
			case "Ice Pellets Rain":
			case "Light Ice Pellets Rain":
			case "Heavy Ice Pellets Rain":
			case "Ice Pellets Drizzle":
			case "Light Ice Pellets Drizzle":
			case "Heavy Ice Pellets Drizzle":
				$icon = "89.jpg";
				break;

			/* Rain Snow | Light Rain Snow | Heavy Rain Snow | Snow Rain | Light Snow Rain | Heavy Snow Rain | Drizzle Snow
				Light Drizzle Snow | Heavy Drizzle Snow | Snow Drizzle | Light Snow Drizzle | Heavy Drizzle Snow */
			case "Rain Snow":
			case "Light Rain Snow":
			case "Heavy Rain Snow":
			case "Snow Rain":
			case "Light Snow Rain":
			case "Heavy Snow Rain":
			case "Drizzle Snow":
			case "Light Drizzle Snow":
			case "Heavy Drizzle Snow":
			case "Snow Drizzle":
			case "Light Snow Drizzle":
			case "Heavy Drizzle Snow":
			case "Chance Rain/Snow": /* Added Manually */
				$icon = "79.jpg";
				break;

			/* Rain Showers | Light Rain Showers | Light Rain and Breezy | Heavy Rain Showers | Rain Showers in Vicinity
				Light Showers Rain | Heavy Showers Rain | Showers Rain | Showers Rain in Vicinity | Rain Showers Fog/Mist
				Light Rain Showers Fog/Mist | Heavy Rain Showers Fog/Mist | Rain Showers in Vicinity Fog/Mist
				Light Showers Rain Fog/Mist | Heavy Showers Rain Fog/Mist | Showers Rain Fog/Mist
				Showers Rain in Vicinity Fog/Mist */
			case "Rain Showers":
			case "Light Rain Showers":
			case "Light Rain and Breezy":
			case "Heavy Rain Showers":
			case "Rain Showers in Vicinity":
			case "Light Showers Rain":
			case "Heavy Showers Rain":
			case "Showers Rain":
			case "Showers Rain in Vicinity":
			case "Rain Showers Fog/Mist":
			case "Light Rain Showers Fog/Mist":
			case "Heavy Rain Showers Fog/Mist":
			case "Rain Showers in Vicinity Fog/Mist":
			case "Light Showers Rain Fog/Mist":
			case "Heavy Showers Rain Fog/Mist":
			case "Showers Rain Fog/Mist":
			case "Showers Rain in Vicinity Fog/Mist":
			case "Slight Chc Showers":	/* Added manually */
			case "Chance Showers": /* Added manually */
				$icon = "76.jpg";
				break;

			/* Thunderstorm | Thunderstorm Rain | Light Thunderstorm Rain | Heavy Thunderstorm Rain
				Thunderstorm Rain Fog/Mist | Light Thunderstorm Rain Fog/Mist | Heavy Thunderstorm Rain Fog and Windy
				Heavy Thunderstorm Rain Fog/Mist | Thunderstorm Showers in Vicinity | Light Thunderstorm Rain Haze
				Heavy Thunderstorm Rain Haze | Thunderstorm Fog | Light Thunderstorm Rain Fog | Heavy Thunderstorm Rain Fog
				Thunderstorm Light Rain | Thunderstorm Heavy Rain | Thunderstorm Rain Fog/Mist | Thunderstorm Light Rain Fog/Mist
				Thunderstorm Heavy Rain Fog/Mist | Thunderstorm in Vicinity Fog/Mist | Thunderstorm Showers in Vicinity
				Thunderstorm in Vicinity Haze | Thunderstorm Haze in Vicinity | Thunderstorm Light Rain Haze
				Thunderstorm Heavy Rain Haze | Thunderstorm Fog | Thunderstorm Light Rain Fog | Thunderstorm Heavy Rain Fog
				Thunderstorm Hail | Light Thunderstorm Rain Hail | Heavy Thunderstorm Rain Hail | Thunderstorm Rain Hail Fog/Mist
				Light Thunderstorm Rain Hail Fog/Mist | Heavy Thunderstorm Rain Hail Fog/Hail
				Thunderstorm Showers in Vicinity Hail | Light Thunderstorm Rain Hail Haze | Heavy Thunderstorm Rain Hail Haze
				Thunderstorm Hail Fog | Light Thunderstorm Rain Hail Fog | Heavy Thunderstorm Rain Hail Fog
				Thunderstorm Light Rain Hail | Thunderstorm Heavy Rain Hail | Thunderstorm Rain Hail Fog/Mist
				Thunderstorm Light Rain Hail Fog/Mist | Thunderstorm Heavy Rain Hail Fog/Mist | Thunderstorm in Vicinity Hail
				Thunderstorm in Vicinity Hail Haze | Thunderstorm Haze in Vicinity Hail | Thunderstorm Light Rain Hail Haze
				Thunderstorm Heavy Rain Hail Haze | Thunderstorm Hail Fog | Thunderstorm Light Rain Hail Fog
				Thunderstorm Heavy Rain Hail Fog | Thunderstorm Small Hail/Snow Pellets | Thunderstorm Rain Small Hail/Snow Pellets
				Light Thunderstorm Rain Small Hail/Snow Pellets | Heavy Thunderstorm Rain Small Hail/Snow Pellets */
			case "Thunderstorm":
			case "Thunderstorm Rain":
			case "Light Thunderstorm Rain":
			case "Heavy Thunderstorm Rain":
			case "Thunderstorm Rain Fog/Mist":
			case "Light Thunderstorm Rain Fog/Mist":
			case "Heavy Thunderstorm Rain Fog and Windy":
			case "Heavy Thunderstorm Rain Fog/Mist":
			case "Thunderstorm Showers in Vicinity":
			case "Light Thunderstorm Rain Haze":
			case "Heavy Thunderstorm Rain Haze":
			case "Thunderstorm Fog":
			case "Light Thunderstorm Rain Fog":
			case "Heavy Thunderstorm Rain Fog":
			case "Thunderstorm Light Rain":
			case "Thunderstorm Heavy Rain":
			case "Thunderstorm Rain Fog/Mist":
			case "Thunderstorm Light Rain Fog/Mist":
			case "Thunderstorm Heavy Rain Fog/Mist":
			case "Thunderstorm in Vicinity Fog/Mist":
			case "Thunderstorm Showers in Vicinity":
			case "Thunderstorm in Vicinity Haze":
			case "Thunderstorm Haze in Vicinity":
			case "Thunderstorm Light Rain Haze":
			case "Thunderstorm Heavy Rain Haze":
			case "Thunderstorm Fog":
			case "Thunderstorm Light Rain Fog":
			case "Thunderstorm Heavy Rain Fog":
			case "Thunderstorm Hail":
			case "Light Thunderstorm Rain Hail":
			case "Heavy Thunderstorm Rain Hail":
			case "Thunderstorm Rain Hail Fog/Mist":
			case "Light Thunderstorm Rain Hail Fog/Mist":
			case "Heavy Thunderstorm Rain Hail Fog/Hail":
			case "Thunderstorm Showers in Vicinity Hail":
			case "Light Thunderstorm Rain Hail Haze":
			case "Heavy Thunderstorm Rain Hail Haze":
			case "Thunderstorm Hail Fog":
			case "Light Thunderstorm Rain Hail Fog":
			case "Heavy Thunderstorm Rain Hail Fog":
			case "Thunderstorm Light Rain Hail":
			case "Thunderstorm Heavy Rain Hail":
			case "Thunderstorm Rain Hail Fog/Mist":
			case "Thunderstorm Light Rain Hail Fog/Mist":
			case "Thunderstorm Heavy Rain Hail Fog/Mist":
			case "Thunderstorm in Vicinity Hail":
			case "Thunderstorm in Vicinity Hail Haze":
			case "Thunderstorm Haze in Vicinity Hail":
			case "Thunderstorm Light Rain Hail Haze":
			case "Thunderstorm Heavy Rain Hail Haze":
			case "Thunderstorm Hail Fog":
			case "Thunderstorm Light Rain Hail Fog":
			case "Thunderstorm Heavy Rain Hail Fog":
			case "Thunderstorm Small Hail/Snow Pellets":
			case "Thunderstorm Rain Small Hail/Snow Pellets":
			case "Light Thunderstorm Rain Small Hail/Snow Pellets":
			case "Heavy Thunderstorm Rain Small Hail/Snow Pellets":
				$icon = "84.jpg";
				break;

			/* Snow | Light Snow | Heavy Snow | Snow Showers | Light Snow Showers | Heavy Snow Showers | Showers Snow
				Light Showers Snow | Heavy Showers Snow | Snow Fog/Mist | Light Snow Fog/Mist | Heavy Snow Fog/Mist
				Snow Showers Fog/Mist | Light Snow Showers Fog/Mist | Heavy Snow Showers Fog/Mist | Showers Snow Fog/Mist
				Light Showers Snow Fog/Mist | Heavy Showers Snow Fog/Mist | Snow Fog | Light Snow Fog | Heavy Snow Fog
				Snow Showers Fog | Light Snow Showers Fog | Heavy Snow Showers Fog | Showers Snow Fog | Light Showers Snow Fog
				Heavy Showers Snow Fog | Showers in Vicinity Snow | Snow Showers in Vicinity | Snow Showers in Vicinity Fog/Mist
				Snow Showers in Vicinity Fog | Low Drifting Snow | Blowing Snow | Snow Low Drifting Snow | Snow Blowing Snow
				Light Snow Low Drifting Snow | Light Snow Blowing Snow | Light Snow Blowing Snow Fog/Mist
				Heavy Snow Low Drifting Snow | Heavy Snow Blowing Snow | Thunderstorm Snow | Light Thunderstorm Snow
				Heavy Thunderstorm Snow | Snow Grains | Light Snow Grains | Heavy Snow Grains | Heavy Blowing Snow
				Blowing Snow in Vicinity */
			case "Snow":
			case "Heavy Snow":
			case "Heavy Snow Showers":
			case "Light Snow":
			case "Snow Showers":
			case "Light Snow Showers":
			case "Light Showers Snow":
			case "Light Snow Fog/Mist":
			case "Light Snow Showers Fog/Mist":
			case "Light Showers Snow Fog/Mist":
			case "Light Snow Fog":
			case "Light Snow Showers Fog":
			case "Light Showers Snow Fog":
			case "Light Snow Low Drifting Snow":
			case "Light Snow Blowing Snow":
			case "Light Snow Blowing Snow Fog/Mist":
			case "Light Thunderstorm Snow":
			case "Light Snow Grains":
				$icon = "77.jpg";
				break;

			case "Showers Snow":
			case "Heavy Showers Snow":
			case "Snow Fog/Mist":
			case "Heavy Snow Fog/Mist":
			case "Snow Showers Fog/Mist":
			case "Heavy Snow Showers Fog/Mist":
			case "Showers Snow Fog/Mist":
			case "Heavy Showers Snow Fog/Mist":
			case "Snow Fog":
			case "Heavy Snow Fog":
			case "Snow Showers Fog":
			case "Heavy Snow Showers Fog":
			case "Showers Snow Fog":
			case "Heavy Showers Snow Fog":
			case "Showers in Vicinity Snow":
			case "Snow Showers in Vicinity":
			case "Snow Showers in Vicinity Fog/Mist":
			case "Snow Showers in Vicinity Fog":
			case "Low Drifting Snow":
			case "Blowing Snow":
			case "Snow Low Drifting Snow":
			case "Snow Blowing Snow":
			case "Heavy Snow Low Drifting Snow":
			case "Heavy Snow Blowing Snow":
			case "Thunderstorm Snow":
			case "Heavy Thunderstorm Snow":
			case "Snow Grains":
			case "Heavy Snow Grains":
			case "Heavy Blowing Snow":
			case "Blowing Snow in Vicinity":
				$icon = "83.jpg";
				break;

			/* Windy | Breezy | Fair and Windy | A Few Clouds and Windy | Partly Cloudy and Windy
				Mostly Cloudy and Windy | Overcast and Windy */
			case "Windy":
			case "Breezy":
			case "Fair and Windy":
			case "A Few Clouds and Windy":
			case "Partly Cloudy and Windy":
			case "Mostly Cloudy and Windy":
			case "Overcast and Windy":
				$icon = "66.jpg";
				break;

			/* Showers in Vicinity | Showers in Vicinity Fog/Mist | Showers in Vicinity Fog | Showers in Vicinity Haze */
			case "Showers in Vicinity":
			case "Showers in Vicinity Fog/Mist":
			case "Showers in Vicinity Fog":
			case "Showers in Vicinity Haze":
				$icon = "76.jpg";
				break;

			/* Freezing Rain Rain | Light Freezing Rain Rain | Heavy Freezing Rain Rain | Rain Freezing Rain
				Light Rain Freezing Rain | Heavy Rain Freezing Rain | Freezing Drizzle Rain | Light Freezing Drizzle Rain
				Heavy Freezing Drizzle Rain | Rain Freezing Drizzle | Light Rain Freezing Drizzle | Heavy Rain Freezing Drizzle */
			case "Freezing Rain Rain":
			case "Light Freezing Rain Rain":
			case "Heavy Freezing Rain Rain":
			case "Rain Freezing Rain":
			case "Light Rain Freezing Rain":
			case "Heavy Rain Freezing Rain":
			case "Freezing Drizzle Rain":
			case "Light Freezing Drizzle Rain":
			case "Heavy Freezing Drizzle Rain":
			case "Rain Freezing Drizzle":
			case "Light Rain Freezing Drizzle":
			case "Heavy Rain Freezing Drizzle":
				$icon = "82.jpg";
				break;

			/* Thunderstorm in Vicinity | Thunderstorm in Vicinity Fog | Thunderstorm in Vicinity Haze */
			case "Thunderstorm in Vicinity":
			case "Thunderstorm in Vicinity Fog":
			case "Thunderstorm in Vicinity Haze":
				$icon = "84.jpg";
				break;

			/* Light Rain | Drizzle | Light Drizzle | Heavy Drizzle | Light Rain Fog/Mist | Drizzle Fog/Mist
				Light Drizzle Fog/Mist | Heavy Drizzle Fog/Mist | Light Rain Fog | Drizzle Fog | Light Drizzle Fog
				Heavy Drizzle Fog */
			case "Light Rain":
			case "Drizzle":
			case "Light Drizzle":
			case "Heavy Drizzle":
			case "Light Rain Fog/Mist":
			case "Drizzle Fog/Mist":
			case "Light Drizzle Fog/Mist":
			case "Heavy Drizzle Fog/Mist":
			case "Light Rain Fog":
			case "Drizzle Fog":
			case "Light Drizzle Fog":
			case "Heavy Drizzle Fog":
				$icon = "76.jpg";
				break;

			/* Rain | Heavy Rain | Rain Fog/Mist | Heavy Rain Fog/Mist | Rain Fog | Heavy Rain Fog */
			case "Rain":
			case "Heavy Rain":
			case "Rain Fog/Mist":
			case "Heavy Rain Fog/Mist":
			case "Rain Fog":
			case "Heavy Rain Fog":
				$icon = "82.jpg";
				break;

			/* Funnel Cloud | Funnel Cloud in Vicinity | Tornado/Water Spout */
			case "Funnel Cloud":
			case "Funnel Cloud in Vicinity":
			case "Tornado/Water Spout":
				$icon = "84.jpg";
				break;

			/* Dust | Low Drifting Dust | Blowing Dust | Sand | Blowing Sand | Low Drifting Sand | Dust/Sand Whirls
				Dust/Sand Whirls in Vicinity | Dust Storm | Heavy Dust Storm | Dust Storm in Vicinity | Sand Storm
				Heavy Sand Storm | Sand Storm in Vicinity */
			case "Dust":
			case "Low Drifting Dust":
			case "Blowing Dust":
			case "Sand":
			case "Blowing Sand":
			case "Low Drifting Sand":
			case "Dust/Sand Whirls":
			case "Dust/Sand Whirls in Vicinity":
			case "Dust Storm":
			case "Heavy Dust Storm":
			case "Dust Storm in Vicinity":
			case "Sand Storm":
			case "Heavy Sand Storm":
			case "Sand Storm in Vicinity":
				$icon = "70.jpg";
				break;

			/* Haze */
			case "Haze":
				$icon = "70.jpg";
				break;

			default:
				$icon = "66.jpg"; /* Partly cloudy */
				break;
		}

		return $this->icon_url_path . $icon;
	}
}
?>