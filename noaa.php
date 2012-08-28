<?php
/*
	Class: 	noaa_weather

	Description
	----------------------------
	Gets current and forecast weather data from weather.gov.  Using weather.gov recommendations,
	a method is also provided which groups weather conditions and maps each group to a graphical weather icon.

	Credits
	----------------------------
	Orignally created by:
	wfsmith@gmail.com
	https://github.com/wfsmith/PHP-NOAA-Weather
	
	Heavily modified by:
	Jason Hitesman (jhitesma@gmail.com) for MGM Internet Solutions (http://www.mgmdesign.com)
	
	Caching code inspired by:
	http://mayavps.com/articles/google-weather-api-class-for-php-with-caching/


	Data sources
	----------------------------
	DW Namespace
		http://www.nws.noaa.gov/mdl/XML/Design/MDL_XML_Design.htm

	Current Conditions
		http://www.weather.gov/xml/current_obs/KNYL.xml

	Forecast
		http://forecast.weather.gov/MapClick.php?lat=32.698762&lon=-114.6079&FcstType=dwml

	Suggested Icon -> condition mappings
		http://www.weather.gov/xml/current_obs/weather.php
		http://www.crh.noaa.gov/riw/?n=forecast_icons

*/

class noaa_weather {

	public $feed_current = 'http://www.weather.gov/xml/current_obs/KNYL.xml';
	public $feed_forecast = 'http://forecast.weather.gov/MapClick.php?lat=32.698762&lon=-114.6079&FcstType=dwml';
	public $do_current = 1;
	public $do_forecast = 1;
	public $current;
	public $forecast;

	public $xml;

	private $now;
	private $cache_path;
	private $xml_cache_time;

	public function __construct() {

		// Define variables
		$this->cache_path = 'cache/';
		$this->xml_cache_time = 15*60; // 15 minutes
		
		if(!is_dir($this->cache_path)) {
			mkdir($this->cache_path);
		}
		
		$this->now = time();
		$this->cache_time = $this->now;
	}

	public function get_weather() {
		/* Populates public properties for current temp/summary and the forecast assoc. array */
		if ($this->do_current) {$this->get_current();}
		if ($this->do_forecast) {$this->get_forecast();}
	}

	private function get_data($url)
	{
		$cache_path = $this->cache_path;
		if(!is_dir($cache_path)) {
			mkdir($cache_path);
		}
		$md5 = md5($url);

		$local_xml_path = "$cache_path$md5.xml";

		$now = $this->now;
		$file = $local_xml_path;
		$ext = 'xml';

		if(file_exists($file)) {
			if(filemtime($file)+$this->xml_cache_time >= $now) {
				# IF LAST CHECK NOT EXPIRED
				$this->local_xml_path = ($ext=='xml') ? $file : FALSE;
				$this->cache_time = filemtime($file);
				return new SimpleXMLElement($file, NULL, TRUE);
				break;
			}
		}

		if($file_string = @file_get_contents($url)) {
			$file_string = utf8_encode($file_string);
			if($xml = new SimpleXMLElement($file_string)) {
				file_put_contents($local_xml_path, $file_string);
				$this->local_xml_path = $local_xml_path;
				return $xml;
			}
		}

		file_put_contents($local_xml_path, "!valid($url)");
		return FALSE;
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
		$this->xml = $this->get_data($url);
	}

	/* Format xml as Forecast */
	private function get_forecast() {
		$this->get_xml('forecast');
		$this->forecast = $this->xml;
	}

	/* Format xml as current conditions box */
	private function get_current() {
		$this->get_xml('current');
		$this->current = $this->xml;
	}

}
?>