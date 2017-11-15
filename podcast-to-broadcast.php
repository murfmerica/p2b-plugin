<?php
/*
* Plugin Name: IFLM Podcast to Broadcast Schedule
* Description: Read a subsplash podcast RSS feed to display future broadcasts listed in that podcast RSS feed.
* Version: 1.0
* Author: Will Murphy
* Author URI: http://insight.org
*/

function formatDateRange($d1, $d2) {
    if ($d1 === $d2 ) {
        # Same day
        return date('M. j, Y', strtotime("$d1"));
    } elseif (date('Y-m', strtotime("$d1")) === date('Y-m', strtotime("$d2"))) {
        # Same calendar month
        return date('M. j-', strtotime("$d1")) . date('j, Y', strtotime("$d2"));
    } elseif(date('Y', strtotime("$d1")) === date('Y', strtotime("$d2"))) {
		# Same calendar year
		return date('M. j-', strtotime("$d1")) . date('M. j, Y', strtotime("$d2"));
	} else {
        # General case (spans calendar years)
        return date('M. j, Y-', strtotime("$d1")) . date('M. j, Y', strtotime("$d2"));
    }
}

// Example code taken from https://www.inkthemes.com/learn-how-to-create-shortcodes-in-wordpress-plugin-with-examples/ (Example 3)
function iflm_p2b_schedule_shortcode($atts=[], $content=null)
{
	$iflm_broadcast_schedule = "";
	$iflm_p2b_atts = shortcode_atts([
		'feed' => '',
		'series' => '',
	], $atts, $tag);
	$iflm_p2b_url = esc_html__($iflm_p2b_atts['feed'], 'iflm_p2b_schedule');
	$iflm_p2b_series = esc_html__($iflm_p2b_atts['series'], 'iflm_p2b_schedule');
	
	if($iflm_p2b_series){
		$series_title = "<br>Series: $iflm_p2b_series";
	}
	
	
	
	$iflm_p2b_feed = simplexml_load_file($iflm_p2b_url);
	$iflm_p2b_channel = $iflm_p2b_feed->channel;
	$iflm_p2b_html_tr = "";


	if($iflm_p2b_channel) {
		$channel_title = $iflm_p2b_channel->title;	
		$channel_description = $iflm_p2b_channel->description;
		$channel_link = $iflm_p2b_channel->link;
		$channel_language = $iflm_p2b_channel->language;
		$channel_items = $iflm_p2b_channel->item;

		if($channel_items) {
			// Extract Podcast Items so we can sort by pubDate:
			$arr_items = [];

			foreach($channel_items as $item) {
				// Gather iTunes information:
				$item_itunes = $item->children('http://www.itunes.com/dtds/podcast-1.0.dtd');

				// Set variables for this $item:
				$title = $item->title;
				$subtitle = $item_itunes->subtitle;
				$summary = $item_itunes->summary;
				$author = $item_itunes->author;
				$enclosure_atts = $item->enclosure->attributes();
				$enclosure_url = $enclosure_atts->url;
				$guid = $item->guid;
				$pubDate = $item->pubDate;
				$itunes_order = $item_itunes->order;
				/* Testing variables
				$now = date('Ymd.His', strtotime("now"));
				$this_pubDate = date('Ymd.His', strtotime($pubDate));
				*/

				// Populate date into new array:
				$arr_item = array("title"=>"$title","subtitle"=>"$subtitle","summary"=>"$summary","author"=>"$author","enclosure_url"=>"$enclosure_url","pubDate"=>"$pubDate","order"=>"$itunes_order");

				// Push array item into new multidimensional array:
				$arr_items[] = $arr_item;
			}

			// Sort new array by pubDate:
			usort($arr_items, function($a,$b) {
				return strtotime($a["pubDate"])-strtotime($b["pubDate"]);
			});


			// Replace data in $channel_items array:
			$channel_items = $arr_items;
			$message_title = "";
			$airDate1 = "";
			$airDate2 = "";
			$i = 0;

			// Build Broadcast Schedule:
			foreach($channel_items as $item) {
				// Set variables for this $item:
				$title = $item["title"];
				$subtitle = $item["subtitle"];
				$summary = $item["summary"];
				$author = $item["author"];
				$enclosure_url = $item["enclosure_url"];
				$pubDate = $item["pubDate"];
				$order = $item["order"];
				$now = date('Ymd.His', strtotime("now")); // server time
				$now = date('Ymd.His', strtotime(current_time( 'mysql' ))); // wordpress time
				$this_pubDate = date('Ymd.His', strtotime($pubDate));

				if($now < $this_pubDate){ // finding only future broadcasts for Broadcast Schedule

					if($title == $message_title){
						// 2nd or 3rd part/broadcast of the same message
						$airDate2 = date('Y-m-d', strtotime($pubDate));
					} else {
						// new message
						$airDate1 = date('Y-m-d', strtotime($pubDate));

						if($message_title == "" || $airDate2 < $airDate1) {
							$airDate2 = date('Y-m-d', strtotime($pubDate));
						}
					}

					$airDate = formatDateRange($airDate1,$airDate2);

					// Build TR with $airDate and $message_title if next broadcast is NOT the same as current broadcast:
					if($channel_items[($i+1)]["title"] != $title) {
						$iflm_p2b_html_tr .= "
			<tr>
				<td class='noRef'>$airDate</td>
				<td><strong>$title</strong>$series_title</td>
			</tr>";
					}

					// Save current broadcast title in $message_title:
					$message_title = $title;
				}
				// advance iteration counter:
				$i++;
			}	
		}
	}

	if($iflm_p2b_html_tr != "") {
		$iflm_p2b_html = "<table class='table'>
		<thead>
			<tr>
				<th>Air Date</th>
				<th>Message</th>
			</tr>
		</thead>
		<tbody>
	$iflm_p2b_html_tr
		</tbody>
	</table>";
	} else {
		$iflm_p2b_html = "<p>There are no scheduled broadcasts in this series: $iflm_p2b_series.</p>";
	}
	
	
	$iflm_broadcast_schedule = $iflm_p2b_html;

	return $iflm_broadcast_schedule;
}

// Add Shortcodes to WP
add_shortcode('iflm_p2b_schedule', 'iflm_p2b_schedule_shortcode');
?>