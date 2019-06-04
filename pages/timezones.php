<?php

$zones = [];
$now = time();
$utc = new DateTime("now", new DateTimeZone("UTC"));

foreach (DateTimeZone::listIdentifiers() as $id) {
  $tz = new DateTimeZone($id);
  $date = $this->formatUserDate($now, $id);
  $time = $this->formatUserTime($now, $id);
  $offset = $tz->getOffset($utc);
  $sign = ($offset < 0) ? '-' : '+';
  $hours = (int)abs($offset / (60 * 60));
  $hours = str_pad($hours, 2, '0', STR_PAD_LEFT);
  $minutes = (int)abs($offset / 60);
  $minutes = $minutes % 60;
  $minutes = str_pad($minutes, 2, '0', STR_PAD_LEFT);

  $zones[] = [
    'id' => $id,
    'offset' => "{$sign}{$hours}:{$minutes}",
    'date' => $date,
    'time' => $time
  ];
}

$this->vars['zones'] = $zones;
