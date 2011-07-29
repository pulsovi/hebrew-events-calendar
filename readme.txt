=== Plugin Name ===
Contributors: Yitzi
Donate link: 
Tags: events, calendar, hebrew, jewish
Requires at least: 3.1
Tested up to: 3.1
Stable tag: trunk

An events calendar that allows easy entry of reoccuring events with either Gregorian or Jewish dates.

== Description ==

An events calendar that allows easy entry of reoccuring events with either Gregorian or Jewish dates. Attaches events to posts, pages, or other custom post types.

* Metaboxes for event data on edit post/page
* Calendar short code: [calendar] or [calendar mon="XX" year="XXXX"]
* Upcoming events are attached to relevent pages
* "Upcoming events" widget
* Dashboard widget for editing "Upcoming Events"

TODO:

* Documentation, see FAQ until then
* Auto create holidays and parashah

== Installation ==

This section describes how to install the plugin and get it working.

1. Upload `hebrew-events-calendar.zip` contentx to the `/wp-content/plugins/hebrew-events-calendar` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Goto the options page in the settings admin section and set the latitude/longitude so Hebrew dates will be calculated correctly.

Events are created by attaching event to info to Posts/Pages. Goto the edit screen and edit the event data in the "Hebrew Event" metabox. Less info usually results
in reoccuring events. For instance, if you set the weekday to "Friday" and the time to "6:00 PM" then the event will be every Friday at 6:00PM. For a general explanation
of the fields in this metabox see the FAQ.

== Frequently Asked Questions ==

= Where is the documentation =

Still working on it. For now you are on your own. Until then here is a short description of the fields in the edit post meta boxes:

Hebrew Event Metabox

* Time: This is either an absolute time (12:24 pm) or a relative time offset (-0:18 = minus eighteen minutes, +2:00 = plus two hours)
* Weekday: Dropdown for either Gregorian (Sunday, Monday, ...) or Hebrew (Rishon, Sheni, ...).   Note that Hebrew days start at sunset.
* Week: Which week of the month the event occurs (1, 2, 3, ...)
* Day: Day of month event occurs in. If the month selected is a Hebrew month than the day of that month is used
* Month: Dropdown for either Gregorian (January, February, ...) or Hebrew (Teshri, Cheshvan, ...)
* Year: Year of the event. Years larger than 5000 are assumed to be a Hebrew year.
* Sunrise: Whether to begin the event at sunrise
* Duration: Duration of event in days, hours & minutes. For instance, 2:25 = 2 hours & 25 minutes or 2 3:00 = 2 days and 3 hours
* Start Date: The start of a the window in which the event is allowed to occur. Right now the format is M/d/Y
* Stop Date: The stop of a the window in which the event is allowed to occur. Right now the format is M/d/Y
* Hebrew Encoded Dates: The Hebrew day & month for encodeded days. Needed for parashah and holiday calculation. See http://www.jewfaq.org/calendar.htm for a basic explanation

Hebrew Event Occurences Metabox

Here notes can be attached to upcoming occurences and occurences can hidden. If a reoccuring event instance is cancelled you
can just uncheck that specific occurence without deleting the event.

= How do I get Holidays & Parashot? =

Import http://templeisraelvaldosta.org/files/2011/07/holidays-diaspora.xml using WordPress import WXR for Diapora pages. More to come.

== Screenshots ==

None yet

== Changelog ==

= 0.4
* Event calculation now integrated in WP_Query. For instance, WP_Query('hec_date' => array('2011-01-01', '2011-01-07')) gets events in the first 7 days of January.
* Multiple event pattern's can be attached to a post/page
* Lat/Long/Zenith can now be overriden in events
* Added style support for event lists
* Began cleaning up code

= 0.3
* Fixed pagination query problem in hec_get_occurences
* Fixed missing upcoming events in pages/posts

= 0.2
* Initial checkin

== Upgrade Notice ==

= 0.4
Added multiple events in post/pages

= 0.3
Upcoming Events widget & Upcoming occurences on pages now work.

= 0.2
Initial checkin
