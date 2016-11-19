<?php
/*
simple PHP script to fetch an image from FFFFOUND and post to wherever
I use it to post to instagram, so there's a step in here to resize the image
*/
$fffound_url = 'http://feeds.feedburner.com/ffffound/everyone';

//get xml
$feed = file_get_contents($fffound_url);
$fffound_feed = simplexml_load_string($feed) or die("Error: Cannot create object");

$first_entry = $fffound_feed->channel->item[0];
//get the text where the real image url is
$url_txt = (string) $first_entry->description;

$image_url = '';
$doc = new DOMDocument();
@$doc->loadHTML($url_txt);
$tags = $doc->getElementsByTagName('img');
foreach ($tags as $tag) {
  $image_url = $tag->getAttribute('src');
	continue;
}

//download first image
$image = file_get_contents($image_url);
$title = (string) $first_entry->title;
$author = (string) $first_entry->author;
print_r($title);

//crop and convert first image
$im = imagecreatefromstring($image);
$image_file = 'test.jpg';

$oldW = imagesx( $im );
$oldH = imagesy( $im );
$thumbSize = 1600;
$limiting_dim = 0;

if ($oldH > $oldW) {
  /* Portrait */
  $limiting_dim = $oldW;
} else {
  /* Landscape */
  $limiting_dim = $oldH;
}
/* Create the New Image */
$new = imagecreatetruecolor( $thumbSize , $thumbSize );
/* Transcribe the Source Image into the New (Square) Image */
imagecopyresampled(
  $new,
  $im,
  0,
  0,
  ($oldW-$limiting_dim ) / 2,
  ($oldH-$limiting_dim ) / 2,
  $thumbSize,
  $thumbSize,
  $limiting_dim,
  $limiting_dim
);
imagejpeg($new, $image_file);

exec('php postimage.php ' . $image_file . ' "Quoted from: ' . $title . ' #FFFFOUND "');

unlink($image_file);
