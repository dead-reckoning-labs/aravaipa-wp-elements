<?php
/**
 * Season Calendar.
 *
 * /races/ used one table for two different jobs: "what can I enter right
 * now" and "what is coming up." Those need different treatment. Aravaipa
 * Upcoming Races (with "Only show confirmed races" left on) is the first
 * job. This element is the second.
 *
 * The problem it replaces was concrete, not stylistic: 46 of 72 races on the
 * live page were already-run events still showing a live "Register" button
 * months after they finished, because UltraSignup's listing for a recurring
 * race persists until someone rolls it to the next running. This element
 * never claims a race is open. It only ever offers Race Details.
 *
 * Everything here looks forward, never back. A race that has run flips to
 * its next expected running after a short grace period, rather than sitting
 * in the past or vanishing: an annual race is still a real thing people plan
 * around a year out. Where the next date is genuinely known it is shown;
 * where it is not, the month is shown with the date marked TBD rather than
 * inventing a day. Anything with no expected date at all belongs in the
 * hiatus list at the bottom, which is hand-maintained because nothing can
 * detect "we are not putting this on next year" from the outside.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

cs_register_element(
	'aravaipa-season-calendar',
	array(
		'title'   => __( 'Aravaipa Season Calendar', 'aravaipa-elements' ),
		'values'  => cs_compose_values(
			array(
				'eyebrow' => cs_value( 'The full season', 'markup' ),
				'heading' => cs_value( 'Every Aravaipa race', 'markup' ),
				'intro'   => cs_value( 'Grouped by month. Registration for most races opens a few months out; check Race Details for the latest.', 'markup' ),
				'theme'   => cs_value( 'light', 'style' ),
				'region'  => cs_value( '', 'markup' ),
				'grace'   => cs_value( '2', 'markup' ),
				'filters' => cs_value( 'true', 'style' ),
				'hiatus_heading' => cs_value( 'On hiatus', 'markup' ),
				'hiatus_intro'   => cs_value( 'Not on the calendar right now. Watch this space.', 'markup' ),
				'hiatus_rows'    => cs_value( '', 'markup' ),
				// Reuses the exact row format Upcoming Races uses, so one
				// paste works for both and scripts/fetch-races.mjs never
				// needs to know two shapes exist.
				//
				// The whole season ships as the default, same as Upcoming
				// Races, so dropping this element on a page produces a
				// correct calendar with no editing at all. Regenerate with
				// scripts/fetch-races.mjs; race-rows-2026.txt in the repo
				// root is the same content.
				'rows'    => cs_value(
					"Rock Hawk | 2026-08-29 | August 29 | 50K | 25K | 10K | 5K | Phillip S. Miller Park | Castle Rock, CO | https://ultrasignup.com/register.aspx?dtid=63630 | https://www.aravaiparunning.com/bear-chase-series/rock-hawk/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2024/10/Jallucinations-8.png?fit=1080%2C1080&ssl=1 |  | https://live.aravaiparunning.com/#/rock_hawk-2026 | 2026-08-24 | 1 | 0 | 39.3698155 | -104.8785796\n" .
					"Black Bear Trail Race | 2026-08-29 | August 29 | 50KM | 23K | 4 Mile | 1 Mile | Waterville Valley Town Square | Waterville Valley, NH | https://ultrasignup.com/register.aspx?dtid=63468 | https://www.aravaiparunning.com/white-mountain-endurance/black-bear-trail-races/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/Black-Bear-Trail-Races_Logo-01.png?fit=1875%2C1920&ssl=1 |  | https://live.aravaiparunning.com/#/black_bear-2026 | 2026-08-24 | 1 | 0 | 43.9500695 | -71.4995204\n" .
					"Snow Mountain Ranch Trail Running Festival | 2026-09-12 | September 12 | 50KM | 33KM | Half-Marathon | 10 KM | 5KM | Snow Mountain Ranch | Granby, CO | https://ultrasignup.com/register.aspx?dtid=63673 | https://www.aravaiparunning.com/snow-mountain-ranch/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2025-SnowMountainRanch-Logo-v1.png?fit=1708%2C1920&ssl=1 |  |  | 2026-09-07 | 1 | 0 | 39.9923627 | -105.9280078\n" .
					"Mogollon Monster Trail Runs | 2026-09-13 | September 12-13 | 100 Mile, 42K | Mogollon Rim | Pine, AZ | https://ultrasignup.com/register.aspx?dtid=63380 | https://www.aravaiparunning.com/mogollon-monster/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2019/06/Mogollon-Monster-Run-logo.png?w=810&ssl=1 | 2026-09-13 | https://live.aravaiparunning.com/#/mogollon_monster-2026 | 2026-09-07 | 1 | 0 | 34.3737970367255 | -111.44377515415\n" .
					"Race The Cog | 2026-09-13 | September 13 | 2.75 Miles w/ 3500ft Gain | Mount Washington Cog Railway | Bretton Woods, NH | https://ultrasignup.com/register.aspx?dtid=63426 | https://www.aravaiparunning.com/white-mountain-endurance/cog/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2023/05/Race-the-Cog-Logo-Full-Color.png?fit=1438%2C1376&ssl=1 |  |  | 2026-09-07 | 1 | 0 | 44.2695697799723 | -71.3517559799011\n" .
					"Jangover Night Runs | 2026-09-19 | September 19 | 75K | 50K | 25K | 15K | 7K | McDowell Mountain Regional Park | Fountain Hills, AZ | https://ultrasignup.com/register.aspx?dtid=63613 | https://www.aravaiparunning.com/insomniac/jangover/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2020/04/2020_Jangover_Logo_Glow.png?fit=288%2C300&ssl=1 |  | https://live.aravaiparunning.com/#/jangover_runs-2026 | 2026-09-14 | 1 | 0 | 33.6671396497179 | -111.700315036743\n" .
					"Kilkenny Ridge Race | 2026-09-19 | September 19 | 50 Mile, 25 Mile, 25K | Kilkenny Ridge Trail | Stark, NH | https://ultrasignup.com/register.aspx?dtid=63469 | https://www.aravaiparunning.com/white-mountain-endurance/kilkenny-ridge-race/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2023/05/kilkenny-1536x1013-2.png?fit=1536%2C1013&ssl=1 |  | https://live.aravaiparunning.com/#/killeny_ridge-2026 | 2026-09-14 | 1 | 0 | 44.5971305286759 | -71.3675621496216\n" .
					"Bryce Canyon Ultras | 2026-09-19 | September 19 | 100M | 50M | 60K | 50K | 30K | Half Marathon | Lucky 7 Ranch | Hatch, UT | https://ultrasignup.com/register.aspx?dtid=63189 | https://www.aravaiparunning.com/bryce/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/Bryce-Canyon-Logo.png?fit=1484%2C1920&ssl=1 |  |  | 2026-09-13 | 1 | 0 | 37.6720948 | -112.4068428\n" .
					"Flagstaff Sky Peaks | 2026-09-27 | September 25-27 | 50M, 50K, 26K, 11K, 5K, 6HR, 12HR, Mountain 20K, Mountain 6K | Arizona Snowbowl | Flagstaff, AZ | https://ultrasignup.com/register.aspx?dtid=64873 | https://www.aravaiparunning.com/skypeaksweekend/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2018/04/2018_FlagstaffSkyPeaks_Logo_Badge-01-e1524955847501.png?fit=300%2C270&ssl=1 | 2026-09-27 |  | 2026-09-21 | 1 | 0 | 35.3304308072892 | -111.70948460426\n" .
					"Javelina Jallucinations | 2026-10-01 | October 1-31 | Month Long Virtual Race | Virtual | Worldwide | https://runsignup.com/Race/AZ/Phoenix/JavelinaJallucinations |  | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2025-Logo-1.png?fit=1519%2C955&ssl=1 | 2026-10-31 |  |  | 1 | 0 |  | \n" .
					"The Bear Chase | 2026-10-04 | October 3-4 | 100K, 50 Mile, 50K, Half Marathon, Baby Bear 10K, 5K | Bear Creek Lake Park | Lakewood, CO | https://ultrasignup.com/register.aspx?dtid=63926 | https://www.aravaiparunning.com/the-bear-chase-race/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2024/01/bear-chase-logo_no-border.png?fit=1287%2C552&ssl=1 | 2026-10-04 |  | 2026-09-28 | 1 | 0 | 39.6508843 | -105.1419767\n" .
					"Catalina State Park 50-Year | 2026-10-04 | October 4 | 9.3 Mile | 5K | Catalina State Park | Tucson, AZ | https://runsignup.com/Race/AZ/Tucson/CSP50YearTrailRaceand5kRoadRun?utm_source=ActiveCampaign&utm_medium=email&utm_content=Four%20Tucson%20Events%20Join%20Aravaipa%20Running%21&utm_campaign=Everyone%20Runs%20Everyone%20Walks%20-%20New%20Tucson%20Events | https://www.aravaiparunning.com/bryce | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/new-2-1.png?fit=975%2C648&ssl=1 |  |  |  | 1 | 0 |  | \n" .
					"That's No Moon | 2026-10-10 | October 10 | 50 Mile, 50K, 30K | Black Star Canyon | Silverado, CA | https://ultrasignup.com/register.aspx?dtid=64524 | https://www.aravaiparunning.com/thats-no-moon/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2024/12/Thats-No-Moon-Logo-Light-Background.png?fit=1206%2C1356&ssl=1 |  |  | 2026-10-05 | 1 | 0 | 33.7649326 | -117.6784122\n" .
					"Thrasher Night Trail | 2026-10-16 | October 16 | 33K, 22K, 11K, 5K | Cave Creek Regional Park | Cave Creek, AZ | https://ultrasignup.com/register.aspx?dtid=64770 | https://aravaiparunning.com/insomniac/thrasher | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2024/09/pun-1-2.png?fit=750%2C750&ssl=1 |  |  | 2026-10-12 | 1 | 0 | 33.8294316625771 | -112.003710212598\n" .
					"Cave Creek Thriller | 2026-10-17 | October 17 | 50K, 24K, 11K, 5K | Cave Creek Regional Park | Cave Creek, AZ | https://ultrasignup.com/register.aspx?dtid=64769 | https://www.aravaiparunning.com/Cave-Creek-Thriller/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2020/09/DRT_cavecreek_redesign_v9.png?resize=300%2C275&ssl=1 |  |  | 2026-10-12 | 1 | 0 | 33.8285966343967 | -112.005978118816\n" .
					"Bobcat Trail Races | 2026-10-17 | October 17 | 50K, 25K, 10K, 5K | Palmer Park | Colorado Springs, CO | https://ultrasignup.com/register.aspx?dtid=64334 | https://www.aravaiparunning.com/bobcat/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2024/10/Jallucinations-4.png?fit=1080%2C1080&ssl=1 |  |  | 2026-10-12 | 1 | 0 | 38.8683027056014 | -104.760150212708\n" .
					"Sonoma Fall Classic | 2026-10-17 | October 17-18 | 100 Mile | 50 Mile | Marathon | Relay | Lake Sonoma | Lake Sonoma, CA | https://ultrasignup.com/register.aspx?dtid=64677 | https://www.aravaiparunning.com/california-races/sonoma/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/SFC-black-MtC-logo.png?fit=676%2C545&ssl=1 | 2026-10-18 |  | 2026-10-12 | 1 | 0 | 38.7095383464242 | -123.009702890564\n" .
					"Javelina Jundred Presented by: HOKA | 2026-10-31 | October 31 | 100 Mile, 100K | McDowell Mountain Regional Park | Fountain Hills, AZ | https://ultrasignup.com/register.aspx?dtid=64465 | https://www.aravaiparunning.com/javelina | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2022/10/JJ-Logo-Vibrant-1.png?fit=300%2C293&ssl=1 |  |  | 2026-10-05 | 1 | 0 | 33.6904474958337 | -111.718367867554\n" .
					"Jackass Night Trail Presented by: HOKA | 2026-10-31 | October 31 | 31K | McDowell Mountain Regional Park | Fountain Hills, AZ | https://aravaiparunning.com/network/javelinajundred/registration/ | https://www.aravaiparunning.com/javelina | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2018_Jackass_v3-01-1.png?fit=1920%2C1920&ssl=1 |  |  |  | 1 | 0 |  | \n" .
					"Veterans Day Catalina State Park Trail Races | 2026-11-08 | November 8 | 10.6M | 5.3M | 5K Road | Catalina State Park | Tuscon, AZ | https://runsignup.com/Race/AZ/Tucson/CSPVetsdaytrailracesand5kroadrun | https://www.aravaiparunning.com/catalina-veterans-day/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/new-2-2.png?fit=975%2C648&ssl=1 |  |  |  | 1 | 0 |  | \n" .
					"Pass Mountain | 2026-11-14 | November 14 | 50 Mile, 50K, 25K, 10K, 5K | Usery Mountain Regional Park | Mesa, AZ | https://ultrasignup.com/register.aspx?dtid=64771 | https://www.aravaiparunning.com/pass-mountain/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2019/12/DRT_passmountain_redesign_v1.png?resize=300%2C276&ssl=1 |  |  | 2026-11-09 | 1 | 0 | 33.479178 | -111.619356\n" .
					"Punisher Night Trail | 2026-11-14 | November 14 | 30K, 20K, 10K, 5K | Usery Mountain Regional Park | Mesa, AZ | https://ultrasignup.com/register.aspx?dtid=64772 | https://www.aravaiparunning.com/insomniac/punisher-night-trail/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2024/09/pun-2-2.png?fit=750%2C750&ssl=1 |  |  | 2026-11-09 | 1 | 0 | 33.479178 | -111.619356\n" .
					"Louisville Trail Race | 2026-11-14 | November 14 | Half-Marathon | 10K | 5K | Louisville, CO | Louisville, CO | https://ultrasignup.com/register.aspx?dtid=64520 | https://www.aravaiparunning.com/louisville/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2025-LouisvilleTrailRace-Logo-v1.png?fit=1920%2C1341&ssl=1 |  |  | 2026-11-09 | 1 | 0 | 39.9711963340448 | -105.131099853967\n" .
					"Live Oak Odyssey | 2026-11-14 | November 14 | 6 Hour | 3 Hour | O'Neill Regional Park | Trabuco Canyon, CA | https://ultrasignup.com/register.aspx?dtid=64702 | https://www.aravaiparunning.com/live-oak-odyssey/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/Live-Oak-Odyssey-Logo-V1.png?fit=1000%2C1000&ssl=1 |  |  | 2026-11-09 | 1 | 0 | 33.6515804125719 | -117.600703031445\n" .
					"Fat Ox | 2026-11-22 | November 20-21 | 48/24/12/6 Hour, 100 Mile, 100K, 50 Mile, 50K | Estrella Mountain Regional Park | Goodyear, AZ | https://ultrasignup.com/register.aspx?dtid=66277 | https://www.aravaiparunning.com/fat-ox/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2023/05/2023-FatOx-Logo_v1.png?fit=1920%2C1425&ssl=1 | 2026-11-21 |  | 2026-11-15 | 1 | 0 | 33.3827205014124 | -112.369900715523\n" .
					"Merry Vertmas | 2026-12-01 | December 1-25 | Virtual Climbing | Aravaipa Virtual | Worldwide | https://runsignup.com/Race/AZ/Phoenix/MerryVertmas | https://www.aravaiparunning.com/merry-vertmas/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2024/10/Merry-Vertmas-Logo-No-Border.png?fit=236%2C300&ssl=1 | 2026-12-25 |  |  | 1 | 0 |  | \n" .
					"McDowell Mountain Frenzy | 2026-12-05 | December 5 | 50 Mile, 50K, 25K, 10 Mile, 5 Mile | McDowell Mountain Regional Park | Fountain Hills, AZ | https://ultrasignup.com/register.aspx?dtid=64773 | https://aravaiparunning.com/mcdowell-mountain-frenzy/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2019/12/DRT_mtfrenzy_redesign_v3-crazythin.png?resize=300%2C276&ssl=1 |  |  | 2026-11-30 | 1 | 0 | 33.6674487465873 | -111.70030050763\n" .
					"Mayhem Night Trail | 2026-12-05 | December 5 | 25K, 10 Mile, 5 Mile | McDowell Mountain Regional Park | Fountain Hills, AZ | https://ultrasignup.com/register.aspx?dtid=64774 | https://www.aravaiparunning.com/insomniac/mayhem/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2024/09/pun-4-2.png?fit=750%2C750&ssl=1 |  |  | 2026-11-30 | 1 | 0 | 33.6673908 | -111.6978411\n" .
					"Tucson Marathon | 2026-12-13 | December 13 | 50K, Marathon, 26.2 Relay, Half Marathon | Highway 77 | Oracle, AZ | https://raceroster.com/events/2025/101730/tucson-marathon-events | https://www.aravaiparunning.com/tucson/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2024/08/Black-Canyon-LIVE-Sponsor-Roll-15.png?fit=1040%2C496&ssl=1 |  |  |  | 1 | 0 | 32.58874044117 | -110.746907758813\n" .
					"Desert Solstice Track Invitational | 2026-12-19 | December 20 | 24 Hour, 100 Mile | Central High School | Phoenix, AZ | https://ultrasignup.com/register.aspx?dtid=65143 | https://www.aravaiparunning.com/desert-solstice/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2020/01/2019_DesertSolstice_LogoOnly.png?resize=300%2C152&ssl=1 |  |  | 2026-12-14 | 1 | 0 | 33.5027266 | -112.073323\n" .
					"Across The Globe | 2026-12-28 | December 28-January 3 | Virtual 6 Day | Aravaipa Virtual | Worldwide | https://runsignup.com/Race/AZ/Phoenix/AcrossTheGlobe | https://www.aravaiparunning.com/merry-vertmas/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2024/10/Across-the-Globe-Steampump.png?fit=1918%2C1920&ssl=1 | 2027-01-03 |  |  | 1 | 0 |  | \n" .
					"Across The Years | 2027-01-03 | December 28-January 3 | 6 Day, 72 Hr, 48 Hr, 24 Hr, 12 Hr, 6 Hr, 200 Mile, 100 Mile, 100 Km, Last Person Standing, Marathon | Peoria Sports Complex | Phoenix, AZ | https://ultrasignup.com/register.aspx?dtid=65492 | https://www.aravaiparunning.com/across-the-years/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2021/09/ATY-Logo-Color-Alt-Compact.png?fit=338%2C300&ssl=1 | 2027-01-03 |  |  | 1 | 0 | 33.6320953 | -112.2332781\n" .
					"San Tan Scramble | 2027-01-09 | January 9 | 50K, 26K, 17K, 9K, 5K, Kid's Run | San Tan Mountain Regional Park | Queen Creek, AZ | https://ultrasignup.com/register.aspx?dtid=66405 | https://www.aravaiparunning.com/drt-series/san-tan-scramble/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2024/01/DRT_SanTanScramble_redesign_v2-2.png?fit=1501%2C1800&ssl=1 |  |  | 2027-01-04 | 1 | 0 | 33.1676942633059 | -111.636080286786\n" .
					"Coldwater Hundred | 2027-01-16 | January 16 | 100M, 100K, 50K, 20M, 10M, 5M | Estrella Mountain Regional Park | Goodyear, AZ | https://ultrasignup.com/register.aspx?dtid=66404 | https://www.aravaiparunning.com/coldwater/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2026-ColdwaterRumble-Logo-v1.png?fit=901%2C913&ssl=1 |  |  | 2027-01-11 | 1 | 0 | 33.3660060578417 | -112.318490975725\n" .
					"Run Around Tucson (RAT) | 2027-01-23 | January 23 | 50+ Mile Relay | Rillito Regional Park | Tucson, AZ | https://runsignup.com/Race/AZ/Tucson/RATucson |  | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/Runaround-Tucson-Rat-Race-Outline.png?fit=1920%2C1080&ssl=1 |  |  |  | 0 | 1 |  | \n" .
					"Prickly Pedal Runs | 2027-01-31 | January 31 | 10 Mile | Lake Pleasant Regional Park | Morris Town, AZ | https://ultrasignup.com/register.aspx?dtid=66215 |  | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/9235_PricklyPedal_Logo_Black_Distressed_72dpi_24bit.png?fit=403%2C416&ssl=1 |  |  | 2027-01-25 | 1 | 0 | 33.8529181430017 | -112.289803368051\n" .
					"Vegas Golden Night & Day | 2027-02-06 | February 6 | Half Marathon, 10K, 5K | Sunset Regional Park | Las Vegas, NV | https://ultrasignup.com/register.aspx?dtid=68102 | https://www.aravaiparunning.com/elephant-mountain/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/New-Website-Images-2.0-15.png?fit=975%2C648&ssl=1 |  |  | 2027-02-01 | 1 | 0 | 36.065649 | -115.111595\n" .
					"Elephant Mountain | 2027-02-06 | February 6 | 50 Mile, 50K, 35K, 22K, 12K, 6K | Cave Creek Regional Park | Cave Creek, AZ | https://ultrasignup.com/register.aspx?dtid=66400 | https://www.aravaiparunning.com/elephant-mountain/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2020/01/DRT_ElephantMountain_redesign_v2.png?resize=274%2C300&ssl=1 |  |  | 2027-02-01 | 1 | 0 | 33.8287728748309 | -112.003833396167\n" .
					"Black Canyon Ultras | 2027-02-14 | February 13-14 | 100K, 50K | Black Canyon Trail | Mayer, AZ | https://ultrasignup.com/register.aspx?dtid=66041 | https://www.aravaiparunning.com/blackcanyon/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2018/04/BlackCanyon_Logo_v4-01-e1524957085653.png?w=810&ssl=1 | 2027-02-14 |  | 2027-02-01 | 1 | 0 | 34.3487696 | -112.1595283\n" .
					"Jackpot Ultras | 2027-02-21 | February 19-20 | 48H, 24H, 12H, 6H, 100M, 100Km, 50M | Cornerstone Park | Henderson, NV | https://ultrasignup.com/register.aspx?dtid=66314 | https://www.aravaiparunning.com/jackpot/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2023/02/2022-JackpotUltras-Logo-v3.png?fit=300%2C211&ssl=1 | 2027-02-20 |  | 2027-02-14 | 1 | 0 | 36.0362186 | -115.054013\n" .
					"Copper Corridor | 2027-02-27 | February 27 | 50K, 31K, 17K, 12K | Arizona Trail & Legends of Superior Trails | Superior, AZ | https://ultrasignup.com/register.aspx?dtid=66369 | https://www.aravaiparunning.com/copper/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2020/03/2020_CopperCorridor_Logo_v5_SuperiorAZ.png?fit=1920%2C1639&ssl=1 |  |  | 2027-02-23 | 1 | 0 | 33.2934876378086 | -111.097115628975\n" .
					"Labor of Love | 2027-03-06 | March 6 | 50K | Marathon | Half-Marathon | 10K | 5K | Lovell Canyon Road | Las Vegas, NV | https://ultrasignup.com/register.aspx?dtid=68140 | https://www.aravaiparunning.com/antelope/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/New-Website-Images-2.0-19.png?fit=975%2C648&ssl=1 |  |  | 2027-03-01 | 1 | 0 | 36.0185261 | -115.5613419\n" .
					"Antelope Canyon Ultras | 2027-03-06 | March 6 | 50 Mile | 55K | 30K | Half Marathon | Page Sports Complex | Page, AZ | https://ultrasignup.com/register.aspx?dtid=66537 | https://www.aravaiparunning.com/antelope/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/Antelope-Canyon-Logo.png?fit=1000%2C1000&ssl=1 |  |  | 2027-03-01 | 1 | 0 | 36.9005756 | -111.4588201\n" .
					"Mesquite Canyon | 2027-03-13 | March 13 | 50M, 50K, 30K, 1/2 Marathon, 8K, Kid's Fun Run | White Tank Mountain Regional Park | Waddell, AZ | https://ultrasignup.com/register.aspx?dtid=66399 | https://www.aravaiparunning.com/mesquite-canyon/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2020/01/DRT_MesquiteCanyon_redesign_v2.png?fit=1920%2C1642&ssl=1 |  |  | 2027-03-08 | 1 | 0 | 33.6045680983019 | -112.503796024444\n" .
					"Purple Run | 2027-03-20 | March 20 | Half-Marathon | 10K | 5K | Sunset Regional Park | Las Vegas, NV | https://ultrasignup.com/register.aspx?dtid=68109 | https://www.aravaiparunning.com/crown-king/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/New-Website-Images-2.0-18.png?fit=975%2C648&ssl=1 |  |  | 2027-03-15 | 1 | 0 | 36.065649 | -115.111595\n" .
					"Crown King Scramble | 2027-03-20 | March 20 | 50 Kilometer | Crown King | Crown King, AZ | https://ultrasignup.com/register.aspx?dtid=66367 | https://www.aravaiparunning.com/crown-king/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2019/04/2018_CrownKingScramble_Logo_v3-01.png?w=810&ssl=1 |  |  | 2027-03-16 | 1 | 0 | 33.9054678706758 | -112.30925630177\n" .
					"Mountain to Fountain | 2027-03-27 | March 27 | 15K & 5K | Fountain Hills | Fountain Hills, AZ | https://raceroster.com/events/2027/119328/mountain-to-fountain | https://www.aravaiparunning.com/mountain-to-fountain/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2024/01/2024-MountainToFountain-LogoDesign-v3-1-1.png?fit=1920%2C930&ssl=1 |  |  |  | 0 | 1 |  | \n" .
					"Dam Good Run | 2027-04-04 | April 4 | 40K, 26K, 13K, 4 Miler, 2 Miler | Lake Pleasant Regional Park | Morristown, AZ | https://ultrasignup.com/register.aspx?dtid=66418 | https://www.aravaiparunning.com/races/dam-good-run/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2025/01/2024-DamGoodRun-Logo-v1.png?fit=1112%2C1817&ssl=1 |  |  | 2027-03-29 | 1 | 0 | 33.9222809 | -112.315979\n" .
					"Zion Ultras | 2027-04-10 | April 10 | 100 Mile, 100K, 60K, 30K, Half Marathon | Ruby Rider Ranch | Apple Valley, UT | https://ultrasignup.com/register.aspx?dtid=66545 | https://www.aravaiparunning.com/zion/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/ZionUltrasLogo.png?fit=536%2C800&ssl=1 |  |  | 2027-04-05 | 1 | 0 | 37.1142363 | -113.1072639\n" .
					"Mountain Ridge Trail Race | 2027-04-11 | April 11 | 21 Mile, Half-Marathon, 10 KM, 5 KM | Highlands Ranch | Colorado | https://ultrasignup.com/register.aspx?did=131168 | https://www.aravaiparunning.com/mountain-ridge/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2025-MountainRidge-Logo-v1.png?fit=1226%2C1920&ssl=1 |  |  |  | 0 | 1 | 39.5208649379203 | -104.963577388769\n" .
					"Whiskey Basin Trail Runs | 2027-04-17 | April 17 | 92K, 58K, 33K, Half-Marathon, 10K | Prescott Circle Trail | Prescott, AZ | https://ultrasignup.com/register.aspx?dtid=66622 | https://www.aravaiparunning.com/whiskey-basin/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2025_WhiskeyBasin_Logo-NoDistances_v1-vertical.png?fit=1501%2C1501&ssl=1 |  |  | 2027-04-11 | 1 | 0 | 34.620826 | -112.567165\n" .
					"Sinister Night Runs | 2027-04-25 | April 25 | 54K | 27K | 18K | 9K | 6K | San Tan Regional Park | Queen Creek, AZ | https://ultrasignup.com/register.aspx?did=130980 | https://www.aravaiparunning.com/insomniac/sinister/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2020/04/2020_InsomniacSeries_Sinister_Sticker.png?fit=984%2C1059&ssl=1 |  |  |  | 0 | 1 | 33.1680527 | -111.6353965\n" .
					"Royal Gorge Groove Trail Runs | 2027-04-25 | April 25 | 50K, 30K, 20K, 10K, 5K, Kids Run | Royal Gorge Park | Canon City, CO | https://ultrasignup.com/register.aspx?did=129387 | https://www.aravaiparunning.com/royal-gorge-groove/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2022/02/2021-RoyalGorgeGroove-Logo-v1.png?fit=500%2C552&ssl=1 |  |  |  | 0 | 1 | 38.4667916 | -105.2941667\n" .
					"White Lake Ultras | 2027-05-02 | May 2 | 24 Hours, 12 Hours, 6 Hours, Relays | White Lake State Park | Tamworth, NH | https://ultrasignup.com/register.aspx?did=129138 | https://www.aravaiparunning.com/white-mountain-endurance/white-lake-ultras/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2025-WhiteLakeUltra-Logo-v2_times.png?fit=1920%2C1912&ssl=1 |  |  |  | 0 | 1 | 43.8358804 | -71.2087454\n" .
					"Cocodona 250 | 2027-05-02 | May 4 | 125 Mile, 100 Mile, 80 Mile, 40 Mile | Black Canyon City to Flagstaff | Central & Northern Arizona | https://ultrasignup.com/register.aspx?dtid=66412 | https://www.aravaiparunning.com/cocodona/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/Copy-of-2025-Cocodona250-Print-Black-AltraLockups_v1.png?fit=1920%2C1590&ssl=1 |  |  |  | 1 | 0 | 34.0813872524389 | -112.157437961885\n" .
					"Ram Party | 2027-05-16 | May 16 | 55 Mile | 60K | 50K | 24K | 16K | 15K | Rampart Range | Colorado Springs, CO Woodland Park, CO | https://ultrasignup.com/register.aspx?did=129482 | https://www.aravaiparunning.com/ram-party/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2022/02/2021-RamParty-Logo-v3-extraColor2-e1645722365331.png?fit=400%2C400&ssl=1 |  |  |  | 0 | 1 | 38.8592289 | -104.8848947\n" .
					"Adrenaline Night Runs | 2027-05-23 | May 23 | 50K | 25K | 15K | 10K | 6K | McDowell Mountain Regional Park | Fountain Hills, AZ | https://ultrasignup.com/register.aspx?did=120430 | https://www.aravaiparunning.com/insomniac-night-trail-series/adrenaline/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2020/03/2020_Adrenaline_Logo.png?resize=269%2C300&ssl=1 |  |  |  | 0 | 1 | 33.6673908 | -111.6978411\n" .
					"Hotfoot Hamster | 2027-05-30 | May 30 | 24 Hour, 12 Hour, 6 Hour | Nardini Manor | Buckeye, AZ | https://ultrasignup.com/register.aspx?did=129798 | https://www.aravaiparunning.com/hotfoot-hamster/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2018/04/2018_HotfootHamster_Logo_web-01-e1524852881953.png?fit=300%2C300&ssl=1 |  |  |  | 0 | 1 | 33.395589 | -112.477373\n" .
					"North Fork 50 | 2027-05-30 | May 30 | 50 Mile | 50K | Buffalo Creek | Buffalo Creek, CO | https://ultrasignup.com/register.aspx?did=129384 | https://www.aravaiparunning.com/north-fork-ultras/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2024/10/Jallucinations-5.png?fit=1080%2C1080&ssl=1 |  |  |  | 0 | 1 | 39.3878647 | -105.2745953\n" .
					"Rock River Canyon | 2027-06-06 | June 6 | 50K | 27K | Rock River Canyon Wilderness | Munsing, MI | https://ultrasignup.com/register.aspx?did=129354 | https://www.aravaiparunning.com/great-lakes-endurance/rock-river-canyon/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/Rock-River-Logo.png?fit=1000%2C1000&ssl=1 |  |  |  | 0 | 1 | 46.3633061820399 | -86.7128627458045\n" .
					"Chocorua Mountain Race | 2027-06-06 | June 6 | 25K | Chocorua Mountain | Tamworth, NH | https://ultrasignup.com/register.aspx?did=130502 | https://www.aravaiparunning.com/white-mountain-endurance/chocorua/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2023/05/Chocorua-Mountain-Race-Logo-FULL-COLOR-TRANSPARENT-2022.png?fit=1920%2C831&ssl=1 |  |  |  | 0 | 1 | 43.8943118878513 | -71.2533692414001\n" .
					"Hypnosis Night Runs | 2027-06-13 | June 13 | 52K | 36K | 22K | 15K | 6K | Estrella Mountain Regional Park | Avondale, AZ | https://ultrasignup.com/register.aspx?did=130997 | https://www.aravaiparunning.com/insomniac/hypnosis | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2020/03/2020_Hypnosis_Logo.png?resize=287%2C300&ssl=1 |  |  |  | 0 | 1 | 33.3661565535724 | -112.315385085925\n" .
					"Barr Lake Trail Race | 2027-06-13 | June 13 | 50K | 30K | Half Marathon | 15K | 5K | Barr Lake State Park | Brighton, CO | https://ultrasignup.com/register.aspx?did=131062 | https://www.aravaiparunning.com/barr-lake/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2025-BarrLake-Logo-v1.png?fit=1920%2C1258&ssl=1 |  |  |  | 0 | 1 | 39.9377226969802 | -104.751715378169\n" .
					"Blackout Night Runs | 2027-06-19 | June 19 | 27K, 13K, 6K | Fort Tuthill County Park | Flagstaff, AZ | https://ultrasignup.com/register.aspx?did=129572 | https://www.aravaiparunning.com/insomniac/blackout/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2022/04/2022-BlackoutNightRuns-Logo-v5.png?fit=1920%2C1920&ssl=1 |  |  |  | 0 | 1 | 35.1447847 | -111.6914115\n" .
					"Flagstaff Extreme Big Pine Trail Runs | 2027-06-20 | June 20 | 52K, 27K, 13K, 6K | Fort Tuthill County Park | Flagstaff, AZ | https://ultrasignup.com/register.aspx?did=129568 | https://www.aravaiparunning.com/big-pine/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/Hardrock-Virtual-Sample-Badge-1.png?fit=1000%2C1000&ssl=1 |  |  |  | 0 | 1 | 35.1447847 | -111.6914115\n" .
					"Two Hearted Trail Runs | 2027-06-20 | June 20 | 50K | Marathon | Half Marathon | Little Two Hearted River | Paradise, MI | https://ultrasignup.com/register.aspx?did=129717 | https://www.aravaiparunning.com/great-lakes-endurance/two-hearted/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/twohearted.png?fit=1200%2C1200&ssl=1 |  |  |  | 0 | 1 | 46.5802497095571 | -85.2565229452755\n" .
					"Ring the Springs | 2027-06-20 | June 20 | 100K | 50K | 35K | Rock Ledge Ranch at Garden of the Gods | Colorado Springs, CO | https://ultrasignup.com/register.aspx?did=129487 | https://www.aravaiparunning.com/ring-the-springs/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2024/05/Ring-the-Springs-RTS-Logo.png?fit=500%2C500&ssl=1 |  |  |  | 0 | 1 | 38.8307019 | -104.8309755\n" .
					"Run With The Roosters | 2027-06-20 | June 20 | 4 Mile Road Race | Sabino Canyon | Tucson, AZ | https://runsignup.com/Race/AZ/Tucson/RWRSabinoCanyon4Miler | https://www.aravaiparunning.com/run-with-the-roosters/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/new-2.png?fit=975%2C648&ssl=1 |  |  |  | 0 | 1 |  | \n" .
					"Running With The Devil | 2027-06-26 | June 26 | 50K | Marathon | Half Marathon | 10K | 5K | Lovell Canyon Road | Las Vegas, NV | https://ultrasignup.com/register.aspx?dtid=68122 | https://www.aravaiparunning.com/bear-chase-series/chase-the-moon/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/New-Website-Images-2.0-16.png?fit=975%2C648&ssl=1 |  |  | 2027-06-21 | 1 | 0 | 36.0185261 | -115.5613419\n" .
					"Chase The Moon | 2027-06-27 | June 27 | 12-Hour team relay (3 or 5 person), solo ultramarathon (overnight) | Mountain Vista High School | Mountain Vista Ridge, CO | https://ultrasignup.com/register.aspx?did=131067 | https://www.aravaiparunning.com/bear-chase-series/chase-the-moon/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2024/01/Untitled-design-2.png?fit=300%2C265&ssl=1 |  |  |  | 0 | 1 | 39.5224974 | -104.965483\n" .
					"Waugoshance Trail Run | 2027-07-11 | July 11 | 50K | Marathon | Half Marathon | North Country Trail | Emmett County, MI | https://ultrasignup.com/register.aspx?did=129767 | https://www.aravaiparunning.com/great-lakes-endurance/waugoshance/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/waugoshance-logo.png?fit=547%2C546&ssl=1 |  |  |  | 0 | 1 | 45.6565373 | -84.9595641\n" .
					"Stunner Night Runs | 2027-07-11 | July 11 | 50K | 25K | 12K | 6K | Usery Mountain Regional Park | Mesa, AZ | https://ultrasignup.com/register.aspx?did=131002 | https://www.aravaiparunning.com/insomniac/stunner/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2021/01/2020_Stunner_Logo.png?fit=1686%2C1920&ssl=1 |  |  |  | 0 | 1 | 33.4664504424039 | -111.607732112202\n" .
					"Silverton Alpine Marathon | 2027-07-18 | July 18 | 8 Mile, Marathon, 50K | Silverton Alpine Loop | Silverton, CO | https://ultrasignup.com/register.aspx?did=129589 | https://www.aravaiparunning.com/silverton-alpine/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2020/08/2019_SilvertonAlpineMarathon_Logo_8MileLast.png?resize=300%2C300&ssl=1 |  |  |  | 0 | 1 | 37.848391 | -107.680381\n" .
					"Kendall Mountain Run | 2027-07-19 | July 19 | 12 Mile, 11K | Kendall Mountain | Silverton, CO | https://ultrasignup.com/register.aspx?did=129592 | https://www.aravaiparunning.com/kendall/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2022/05/2018_KendallMtnRun_Logo-01-01.png?fit=1364%2C1318&ssl=1 |  |  |  | 0 | 1 | 37.811941 | -107.6645057\n" .
					"Harding Hustle | 2027-07-24 | July 18 | 50K, 30K, 15K | Tucker Wildlife Sanctuary | Modjeska Canyon, CA | https://ultrasignup.com/register.aspx?dtid=68267 | https://www.aravaiparunning.com/harding-hustle/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2024/12/Harding-Hustle-Logo.webp?fit=1286%2C1077&ssl=1 |  |  |  | 1 | 0 | 33.71083 | -117.64139\n" .
					"Grand Island | 2027-07-25 | July 25 | 50K, Marathon, Half-Marathon | Grand Island | Munising, MI | https://ultrasignup.com/register.aspx?did=129770 | https://www.aravaiparunning.com/great-lakes-endurance/grand-island/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/Grand-Island-Logo.png?fit=1201%2C1201&ssl=1 |  |  |  | 0 | 1 | 46.444929 | -86.6647146\n" .
					"Baldface Scramble | 2027-07-25 | July 25 | 29KM, 14KM | White Mountain National Forest | Chatham, NH | https://ultrasignup.com/register.aspx?did=130611 | https://www.aravaiparunning.com/white-mountain-endurance/baldface-scramble/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2024/01/BALDFACE-SCRAMBLE-600x600-1.png?fit=600%2C600&ssl=1 |  |  |  | 0 | 1 | 44.2361858231926 | -71.0181529306965\n" .
					"Aspen Backcountry | 2027-08-01 | August 1 | 50K | Marathon | Half-Marathon | Rio Grande Park | Aspen. CO | https://ultrasignup.com/register.aspx?did=130516 | https://www.aravaiparunning.com/colorado/aspen-backcountry/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/AspenBackCountry-MArathon-2023-Horiz-Black-No-YEAR%402x.png?fit=1225%2C466&ssl=1 |  |  |  | 0 | 1 | 39.192604 | -106.818576\n" .
					"Tahqua Trail Runs | 2027-08-08 | August 8 | 25K | 10K | Tahquemenon Falls State Park | Paradise, MI | https://ultrasignup.com/register.aspx?did=129775 | https://www.aravaiparunning.com/great-lakes-endurance/tahqua/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/tahquatr.png?fit=547%2C546&ssl=1 |  |  |  | 0 | 1 | 46.6112109 | -85.2098186\n" .
					"Vertigo Night Runs | 2027-08-08 | August 8 | 52K | 31K | 20K | 10K | 6K | White Tank Mountain Regional Park | Waddell, AZ | https://ultrasignup.com/register.aspx?did=131006 | https://aravaiparunning.com/insomniac/vertigo | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2020/03/2020_Vertigo_Logo.png?resize=300%2C277&ssl=1 |  |  |  | 0 | 1 | 33.5670329 | -112.4978078\n" .
					"Jigger Johnson Ultras | 2027-08-14 | August 14-16 | 100 Mile, 50 Mile, 20 Mile | White Mountains | Waterville Valley, NH | https://ultrasignup.com/register.aspx?did=130607 | https://www.aravaiparunning.com/white-mountain-endurance/jigger/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2023/05/JJ-Ultras-color-gold-outline.png?fit=864%2C864&ssl=1 | 2027-08-16 |  |  | 0 | 1 | 43.9508418 | -71.505077\n" .
					"Westminster | 2027-08-15 | August 15 | 50KM | 35KM | Half Marathon | 10 KM | 5KM | Westminster Lake | Westminster, CO | https://ultrasignup.com/register.aspx?did=121142 | https://www.aravaiparunning.com/westminster/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2025-WestminsterTrailRace-Logo-v3.png?fit=1216%2C1920&ssl=1 |  |  |  | 0 | 1 | 39.8768488752265 | -105.110807822229\n" .
					"Great Lakes Endurance | 2027-08-20 | June - August 2026 | 10K to 50K | Upper Peninsula of Michigan | Michigan | https://www.aravaiparunning.com/great-lakes-endurance/ | https://www.aravaiparunning.com/great-lakes-endurance/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/Great-Lakes-Endurance-Logo.png?fit=1289%2C1261&ssl=1 |  |  |  | 0 | 1 |  | \n" .
					"Jackrabbit Jubilee | 2027-08-22 | August 22 | 6 Hour, 12 Hour | Nardini Manor | Buckeye, AZ | https://ultrasignup.com/register.aspx?did=129946 | https://www.aravaiparunning.com/jackrabbit-jubilee/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2018/08/JackrabbitJubilee_Logo_v2-01.png?w=810&ssl=1 |  |  |  | 0 | 1 | 33.3951129 | -112.4778962"
					,
					'markup'
				),
			),
			'omega'
		),
		'builder' => 'arv_season_calendar_builder',
		'render'  => 'arv_season_calendar_render',
	)
);

/**
 * Builder controls.
 *
 * @return array
 */
function arv_season_calendar_builder() {
	return cs_compose_controls(
		array(
			'controls'    => array(
				array(
					'key'   => 'eyebrow',
					'type'  => 'text',
					'label' => __( 'Eyebrow', 'aravaipa-elements' ),
				),
				array(
					'key'   => 'heading',
					'type'  => 'text',
					'label' => __( 'Heading', 'aravaipa-elements' ),
				),
				array(
					'key'   => 'intro',
					'type'  => 'text',
					'label' => __( 'Intro line', 'aravaipa-elements' ),
				),
				array(
					'key'     => 'theme',
					'type'    => 'select',
					'label'   => __( 'Theme', 'aravaipa-elements' ),
					'options' => array(
						'choices' => array(
							array(
								'value' => 'light',
								'label' => __( 'Light', 'aravaipa-elements' ),
							),
							array(
								'value' => 'dark',
								'label' => __( 'Dark panel', 'aravaipa-elements' ),
							),
						),
					),
				),
				array(
					'key'         => 'region',
					'type'        => 'text',
					'label'       => __( 'Region slug (optional)', 'aravaipa-elements' ),
					'description' => __( 'Limits the calendar to one region, for a division page. Leave blank for every race. Only applies once races are in the store.', 'aravaipa-elements' ),
				),
				array(
					'key'         => 'filters',
					'type'        => 'toggle',
					'label'       => __( 'Search and filter bar', 'aravaipa-elements' ),
					'description' => __( 'Adds a search box and region/month/open-only filters above the list. Filtering happens in the browser on rows that are already on the page, so every race stays in the HTML for search engines whether or not it is currently visible.', 'aravaipa-elements' ),
				),
				array(
					'key'         => 'grace',
					'type'        => 'text',
					'label'       => __( 'Days a finished race stays before flipping forward', 'aravaipa-elements' ),
					'description' => __( 'A race that has just run keeps its place in the list for this many days, then rolls to its next expected running at the far end. 0 flips it the morning after.', 'aravaipa-elements' ),
				),
				array(
					'key'         => 'hiatus_heading',
					'type'        => 'text',
					'label'       => __( 'Hiatus section heading', 'aravaipa-elements' ),
				),
				array(
					'key'         => 'hiatus_intro',
					'type'        => 'text',
					'label'       => __( 'Hiatus section intro', 'aravaipa-elements' ),
				),
				array(
					'key'         => 'hiatus_rows',
					'type'        => 'textarea',
					'label'       => __( 'On hiatus', 'aravaipa-elements' ),
					'description' => __( 'Races with no planned date at all, one per line: Name | race page URL (optional) | note (optional). Hand-maintained on purpose: nothing outside can tell the difference between "next year is not scheduled yet" and "we are not running this again", so that call has to be made here.', 'aravaipa-elements' ),
				),
				array(
					'key'         => 'rows',
					'type'        => 'textarea',
					'label'       => __( 'Races', 'aravaipa-elements' ),
					'description' => __( 'Same format as Aravaipa Upcoming Races: paste the same rows here. Every race is shown regardless of date or whether registration is confirmed open; this element never links to registration directly, only to the race\'s own page.', 'aravaipa-elements' ),
				),
			),
		),
		cs_partial_controls( 'omega' )
	);
}

/**
 * How many days from today until this race next comes round.
 *
 * The sort key for the whole element, and the reason it reads as "what is
 * coming up" rather than "January to December". Everything is measured
 * forward from today: a race in three weeks sorts near the top, a race that
 * ran last month sorts near the bottom because its next running is eleven
 * months away, and the list rolls over on its own as the year turns without
 * anyone editing anything.
 *
 * Month and day come off the row; the year on the row is deliberately
 * ignored. Roughly 84% of this calendar carries a generator-guessed year
 * (see the confirmed flag), and a guess must never be allowed to decide
 * ordering. The month and day are real either way, taken from the site's own
 * listing rather than from UltraSignup.
 *
 * @param string $iso   Y-m-d, whose year is not trusted.
 * @param string $today Y-m-d in site time.
 * @param int    $grace Days a just-finished race keeps its place before
 *                      flipping to next year.
 * @return int Days until the next occurrence, 0 or more.
 */
function arv_season_calendar_days_until( $iso, $today, $grace = 2 ) {
	$month = (int) substr( $iso, 5, 2 );
	$day   = (int) substr( $iso, 8, 2 );
	$now   = strtotime( $today . ' 00:00:00 UTC' );
	$year  = (int) gmdate( 'Y', $now );

	// Feb 29 in a non-leap year is not a date. Nudging to the 28th keeps the
	// race in roughly the right place instead of letting mktime silently roll
	// it into March.
	if ( 2 === $month && $day > 28 && ! checkdate( 2, $day, $year ) ) {
		$day = 28;
	}

	$this_year = gmmktime( 0, 0, 0, $month, $day, $year );
	$diff      = (int) floor( ( $this_year - $now ) / DAY_IN_SECONDS );

	// Still ahead, or recent enough to still be worth showing where it was.
	if ( $diff >= -$grace ) {
		return max( 0, $diff );
	}

	$next = gmmktime( 0, 0, 0, $month, $day, $year + 1 );

	return (int) floor( ( $next - $now ) / DAY_IN_SECONDS );
}

/**
 * The month heading a race belongs under, and the year that goes with it.
 *
 * Derived from the sort position rather than the row's own year, so a race
 * that has already run this year lands under next year's heading without the
 * row needing to know that. This is what makes "flips forward" true in the
 * output and not just in the ordering.
 *
 * @param string $iso
 * @param string $today
 * @param int    $grace
 * @return string e.g. "September 2026"
 */
function arv_season_calendar_bucket( $iso, $today, $grace = 2 ) {
	$days = arv_season_calendar_days_until( $iso, $today, $grace );
	$when = strtotime( $today . ' 00:00:00 UTC' ) + ( $days * DAY_IN_SECONDS );

	return gmdate( 'F Y', $when );
}

/**
 * One race, as a single line.
 *
 * The date shown depends on whether it is actually known. A race whose date
 * came straight off Aravaipa's own listing gets its real day, whether or not
 * registration happens to be open yet. A race whose year was rolled forward
 * by the generator, because its listed date has already passed, gets "TBD"
 * instead: the month is still real, but the day belongs to a running nobody
 * has scheduled, and printing it would state a date no one committed to.
 *
 * Keyed on `guessed`, not `confirmed`. Conflating them hid a real published
 * date (The Bear Chase, October 3-4) behind a TBD purely because its
 * UltraSignup listing had not rolled over yet.
 *
 * @param array $race Parsed row from arv_upcoming_races_parse_row().
 * @return string
 */
function arv_season_calendar_row( $race, $today, $grace = 2 ) {
	$href = '' !== $race['page'] ? $race['page'] : $race['register'];

	// A date is only real for the running it was published for. Once a race
	// has been rolled forward past its grace window, the row still carries
	// last running's day and printing it would assert a date for a running
	// nobody has scheduled: exactly the mistake the `guessed` column exists
	// to prevent, arriving from the other direction as time passes rather
	// than at generation time. Recomputed here so it self-corrects.
	$rolled  = gmdate( 'Y', strtotime( $today . ' 00:00:00 UTC' ) + ( arv_season_calendar_days_until( $race['iso'], $today, $grace ) * DAY_IN_SECONDS ) )
		!== substr( $race['iso'], 0, 4 );
	$show_day = ! $race['guessed'] && ! $rolled;

	// Split into a main link plus an optional action link, rather than one
	// anchor wrapping everything. A race that is running today needs a way
	// through to its live results, and nesting that inside the row's own
	// anchor would be invalid markup. The main link keeps the large tap
	// target; the action is a separate, smaller one.
	// Filter targets, rendered as data attributes rather than looked up in
	// JavaScript from the visible text. The text is for people: it is
	// abbreviated, wrapped and translated, and matching against it would tie
	// filtering to presentation. These are the raw values.
	$state = '';
	if ( preg_match( '/,\s*([A-Za-z]{2})\s*$/', $race['location'], $m ) ) {
		$state = strtoupper( $m[1] );
	}

	// The full state name rides along in the searchable text, not just the
	// two-letter code the location carries. Someone typing "california" got
	// "No races match" because the row only ever contained "CA".
	$where = $race['venue'] . ' ' . $race['location'];
	if ( '' !== $state ) {
		$where .= ' ' . arv_state_name( $state );
	}

	// Searching "insomniac" should find all ten Insomniac races, none of
	// which carry the word in their own name.
	$series = arv_race_series_for( $race );
	if ( $series ) {
		$where .= ' ' . $series['label'];
	}

	$out = '<div class="arv-calendar__row"'
		. ' data-series="' . esc_attr( $series ? $series['slug'] : '' ) . '"'
		. ' data-name="' . esc_attr( strtolower( $race['name'] ) ) . '"'
		. ' data-where="' . esc_attr( strtolower( $where ) ) . '"'
		. ' data-distances="' . esc_attr( strtolower( $race['distances'] ) ) . '"'
		. ' data-state="' . esc_attr( $state ) . '"'
		. ' data-month="' . esc_attr( substr( $race['iso'], 5, 2 ) ) . '"'
		. ' data-open="' . ( $race['confirmed'] ? '1' : '0' ) . '"'
		. '>';
	$out .= '<a class="arv-calendar__main" href="' . esc_url( $href ) . '">';

	if ( '' !== $race['image'] ) {
		$out .= '<span class="arv-calendar__logo"><img src="' . esc_url( $race['image'] ) . '" alt="" loading="lazy" decoding="async" /></span>';
	}

	if ( $show_day ) {
		$day = gmdate( 'j', strtotime( $race['iso'] . ' 00:00:00 UTC' ) );
		// A multi-day race states its own span ("September 12-13"); the day
		// part of that is what belongs next to a month heading.
		if ( '' !== $race['display'] && preg_match( '/\d+\s*[-\x{2013}]\s*\d+/u', $race['display'] ) ) {
			$day = preg_replace( '/^[A-Za-z]+\s+/', '', $race['display'] );
		}

		// The month repeats the heading above, deliberately. Once someone has
		// scrolled a few rows the heading is off screen, and "29" alone is
		// then a number with no month attached to it.
		$month = gmdate( 'M', strtotime( $race['iso'] . ' 00:00:00 UTC' ) );

		$out .= '<span class="arv-calendar__day">'
			. '<span class="arv-calendar__day-month">' . esc_html( $month ) . '</span>'
			. '<span class="arv-calendar__day-num">' . esc_html( $day ) . '</span>'
			. '</span>';
	} else {
		$out .= '<span class="arv-calendar__day arv-calendar__day--tbd">' . esc_html( __( 'TBD', 'aravaipa-elements' ) ) . '</span>';
	}

	$out .= '<span class="arv-calendar__body">';
	$out .= '<span class="arv-calendar__name">' . esc_html( $race['name'] ) . '</span>';
	// Pills, matching the map popup, rather than the raw delimiter-joined
	// string. "50KM | 23K | 4 Mile | 1 Mile" read as a machine-separated
	// list; the same distances as chips scan as four things a runner can
	// pick between. Shares arv_split_distances() with the map so the two
	// can never disagree about what counts as a separator.
	$distances = arv_split_distances( $race['distances'] );
	if ( ! empty( $distances ) ) {
		$out .= '<span class="arv-calendar__distances">';
		foreach ( $distances as $distance ) {
			$out .= '<span class="arv-calendar__pill">' . esc_html( $distance ) . '</span>';
		}
		$out .= '</span>';
	}
	// A label, not a link. This whole block sits inside
	// <a class="arv-calendar__main">, and an anchor inside an anchor is
	// invalid HTML that browsers silently tear apart. The Series filter
	// above the list is the interactive route to a whole series, and it is
	// the better one anyway: it shows the season in place rather than
	// navigating away from the calendar someone is already reading.
	if ( $series ) {
		$out .= '<span class="arv-calendar__series">' . esc_html( $series['label'] ) . '</span>';
	}
	$where = array_filter( array( $race['venue'], $race['location'] ) );
	if ( ! empty( $where ) ) {
		$out .= '<span class="arv-calendar__where">' . esc_html( implode( ', ', $where ) ) . '</span>';
	}
	$out .= '</span>';
	$out .= '</a>';

	$out .= '<span class="arv-calendar__actions">';
	$out .= arv_season_calendar_action( $race, $today );

	// Race Info is always offered and always the same shape, so the right
	// hand edge of the list stays a straight line whatever state each race is
	// in, rather than buttons appearing and disappearing down the column.
	if ( '' !== $href ) {
		$out .= '<a class="arv-calendar__info" href="' . esc_url( $href ) . '">'
			. esc_html( __( 'Race Info', 'aravaipa-elements' ) ) . '</a>';
	}

	$out .= '</span>';
	$out .= '</div>';

	return $out;
}

/**
 * The live/results link on a calendar row, when there is a safe one to give.
 *
 * Uses arv_upcoming_races_action(), the same function the homepage runs on,
 * so a race changes state on both pages at the same moment by construction
 * rather than by two implementations agreeing.
 *
 * Only rendered for a race whose registration is confirmed. For the other 58
 * every URL available is derived from an UltraSignup listing that still
 * describes a previous running: "results" would be last year's results, and
 * "Register" would be the stale link this whole element exists to stop
 * showing. Those rows stay quiet, which is the honest answer for them.
 *
 * Register is offered here now, but only for the confirmed races, which is
 * the same gate as everything else: for the other 58 the registration link
 * still points at a previous running, and that stale link is the single
 * problem this whole element was built to stop showing.
 *
 * @param array  $race
 * @param string $today Y-m-d in site time.
 * @return string
 */
function arv_season_calendar_action( $race, $today ) {
	if ( ! $race['confirmed'] || $race['guessed'] ) {
		return '';
	}

	$action = arv_upcoming_races_action( $race, $today );

	// Entries closed with nowhere to send anyone is not worth a button here:
	// Race Info beside it is the only thing left to do, and a dead chip in a
	// long list is just noise.
	if ( 'closed' === $action['phase'] || '' === $action['url'] ) {
		return '';
	}

	return '<a class="arv-calendar__action arv-calendar__action--' . esc_attr( $action['phase'] ) . '" href="'
		. esc_url( $action['url'] ) . '" target="_blank" rel="noopener">'
		. esc_html( $action['label'] ) . '</a>';
}

/**
 * The hand-maintained hiatus list.
 *
 * Deliberately not derived from anything. "No date on UltraSignup yet" and
 * "we are not running this again" look identical from outside, and only one
 * of them should be told to a runner as a hiatus, so the call is made here
 * by a person rather than guessed at by the generator.
 *
 * @param string $raw One per line: Name | URL (optional) | note (optional).
 * @return string
 */
function arv_season_calendar_hiatus( $raw ) {
	$rows = arv_parse_rows( $raw, 1 );

	if ( empty( $rows ) ) {
		return '';
	}

	$out = '';
	foreach ( $rows as $row ) {
		$name = trim( arv_cell( $row, 0 ) );
		if ( '' === $name ) {
			continue;
		}

		$url  = trim( arv_cell( $row, 1 ) );
		$note = trim( arv_cell( $row, 2 ) );

		$inner  = '<span class="arv-calendar__name">' . esc_html( $name ) . '</span>';
		$inner .= '' !== $note ? '<span class="arv-calendar__where">' . esc_html( $note ) . '</span>' : '';

		// A hiatus race with no page left to point at is still worth listing,
		// just not as a link to nowhere.
		$out .= '<div class="arv-calendar__row arv-calendar__row--hiatus">';
		$out .= '' !== $url
			? '<a class="arv-calendar__main" href="' . esc_url( $url ) . '"><span class="arv-calendar__body">' . $inner . '</span><span class="arv-calendar__arrow" aria-hidden="true">&rarr;</span></a>'
			: '<span class="arv-calendar__main"><span class="arv-calendar__body">' . $inner . '</span></span>';
		$out .= '</div>';
	}

	return $out;
}

/**
 * The search and filter bar.
 *
 * Built from the races on this page rather than a fixed list, so the region
 * and month options only ever offer something that will actually match. An
 * empty result is a dead end, and a dropdown that can produce one is a bug in
 * the dropdown, not in the filtering.
 *
 * Everything here is progressive enhancement: the list is fully rendered
 * server side, every race is in the HTML, and this only hides rows. With
 * JavaScript off, or before it runs, the page is a complete list rather than
 * an empty shell waiting on a fetch. That matters for a page whose whole job
 * is being the complete list, and it means search engines index all 84 races
 * regardless of what the filter is set to.
 *
 * @param array $races Parsed races, already filtered and sorted.
 * @return string
 */
function arv_season_calendar_filters( $races ) {
	$states = array();
	$months = array();
	$series = array();

	foreach ( $races as $race ) {
		if ( preg_match( '/,\s*([A-Za-z]{2})\s*$/', $race['location'], $m ) ) {
			$states[ strtoupper( $m[1] ) ] = true;
		}
		$months[ substr( $race['iso'], 5, 2 ) ] = true;

		$s = arv_race_series_for( $race );
		if ( $s ) {
			$series[ $s['slug'] ] = $s['label'];
		}
	}

	// Sorted by the name the reader actually sees, not by the code behind it.
	// ksort on the code puts NH before NV, which renders as "New Hampshire"
	// above "Nevada": alphabetical by a string the dropdown never shows.
	uksort(
		$states,
		function ( $a, $b ) {
			return strcasecmp( arv_state_name( $a ), arv_state_name( $b ) );
		}
	);
	ksort( $months );
	asort( $series );

	$out  = '<div class="arv-calendar__filters" data-arv-filters>';

	$out .= '<label class="arv-calendar__field arv-calendar__field--search">';
	$out .= '<span class="arv-calendar__field-label">' . esc_html( __( 'Search races', 'aravaipa-elements' ) ) . '</span>';
	$out .= '<input type="search" class="arv-calendar__search" data-arv-search placeholder="' . esc_attr( __( 'Race, place or distance', 'aravaipa-elements' ) ) . '" autocomplete="off" />';
	$out .= '</label>';

	if ( count( $states ) > 1 ) {
		$out .= '<label class="arv-calendar__field">';
		$out .= '<span class="arv-calendar__field-label">' . esc_html( __( 'State', 'aravaipa-elements' ) ) . '</span>';
		$out .= '<select class="arv-calendar__select" data-arv-state>';
		$out .= '<option value="">' . esc_html( __( 'Anywhere', 'aravaipa-elements' ) ) . '</option>';
		foreach ( array_keys( $states ) as $code ) {
			// Value stays the code, since that is what data-state carries and
			// what the JS compares; only the label spells it out.
			$out .= '<option value="' . esc_attr( $code ) . '">' . esc_html( arv_state_name( $code ) ) . '</option>';
		}
		$out .= '</select></label>';
	}

	// Only worth a control when there is a choice to make: a page showing one
	// series would otherwise offer a dropdown whose every option is the same
	// answer, and a single-series division page is a real case.
	if ( count( $series ) > 1 ) {
		$out .= '<label class="arv-calendar__field">';
		$out .= '<span class="arv-calendar__field-label">' . esc_html( __( 'Series', 'aravaipa-elements' ) ) . '</span>';
		$out .= '<select class="arv-calendar__select" data-arv-series>';
		$out .= '<option value="">' . esc_html( __( 'All series', 'aravaipa-elements' ) ) . '</option>';
		foreach ( $series as $slug => $label ) {
			$out .= '<option value="' . esc_attr( $slug ) . '">' . esc_html( $label ) . '</option>';
		}
		$out .= '</select></label>';
	}

	if ( count( $months ) > 1 ) {
		$out .= '<label class="arv-calendar__field">';
		$out .= '<span class="arv-calendar__field-label">' . esc_html( __( 'Month', 'aravaipa-elements' ) ) . '</span>';
		$out .= '<select class="arv-calendar__select" data-arv-month>';
		$out .= '<option value="">' . esc_html( __( 'Any month', 'aravaipa-elements' ) ) . '</option>';
		foreach ( array_keys( $months ) as $num ) {
			$out .= '<option value="' . esc_attr( $num ) . '">' . esc_html( gmdate( 'F', mktime( 0, 0, 0, (int) $num, 1 ) ) ) . '</option>';
		}
		$out .= '</select></label>';
	}

	$out .= '<label class="arv-calendar__toggle">';
	$out .= '<input type="checkbox" data-arv-open />';
	$out .= '<span>' . esc_html( __( 'Open for registration', 'aravaipa-elements' ) ) . '</span>';
	$out .= '</label>';

	// Announced politely rather than assertively: a count that changes on
	// every keystroke should not interrupt whatever a screen reader is
	// already saying.
	$out .= '<p class="arv-calendar__count" data-arv-count role="status" aria-live="polite"></p>';

	$out .= '</div>';

	return $out;
}

/**
 * Render callback.
 *
 * @param array $data Element values.
 * @return string
 */
function arv_season_calendar_render( $data ) {
	// Same source rule as Upcoming Races: the store when it has races, this
	// element's own rows otherwise.
	$races = arv_races_source( $data );

	// No dated races is not necessarily nothing to show: a page could be
	// down to its hiatus list alone, and silently rendering empty would hide
	// it.
	if ( empty( $races ) && '' === arv_season_calendar_hiatus( isset( $data['hiatus_rows'] ) ? $data['hiatus_rows'] : '' ) ) {
		return '';
	}

	$today = arv_upcoming_races_today();
	$grace = isset( $data['grace'] ) ? (int) $data['grace'] : 2;
	$grace = max( 0, min( 60, $grace ) );

	// Everything measured forward from today. See
	// arv_season_calendar_days_until() for why the row's own year is ignored.
	usort(
		$races,
		function ( $a, $b ) use ( $today, $grace ) {
			$da = arv_season_calendar_days_until( $a['iso'], $today, $grace );
			$db = arv_season_calendar_days_until( $b['iso'], $today, $grace );
			if ( $da === $db ) {
				// Same day: settle it by name so the order does not wobble
				// between page loads for no visible reason.
				return strcmp( $a['name'], $b['name'] );
			}
			return $da - $db;
		}
	);

	$buckets = array();
	foreach ( $races as $race ) {
		$label = arv_season_calendar_bucket( $race['iso'], $today, $grace );
		if ( ! isset( $buckets[ $label ] ) ) {
			$buckets[ $label ] = array();
		}
		$buckets[ $label ][] = $race;
	}

	$rows_html = '';
	foreach ( $buckets as $label => $month_races ) {
		$rows_html .= '<div class="arv-calendar__month">';
		$rows_html .= '<h3 class="arv-calendar__month-name">' . esc_html( $label ) . '</h3>';
		$rows_html .= '<div class="arv-calendar__rows">';

		foreach ( $month_races as $race ) {
			$rows_html .= arv_season_calendar_row( $race, $today, $grace );
		}

		$rows_html .= '</div></div>';
	}

	$theme = ( isset( $data['theme'] ) && 'dark' === $data['theme'] ) ? 'dark' : 'light';
	$base  = 'arv-calendar arv-calendar--' . $theme;

	$out  = '<div class="' . arv_wrapper_class( $data, $base ) . '">';
	$out .= '<div class="arv-calendar__inner">';

	$eyebrow = isset( $data['eyebrow'] ) ? $data['eyebrow'] : '';
	$heading = isset( $data['heading'] ) ? $data['heading'] : '';
	$intro   = isset( $data['intro'] ) ? $data['intro'] : '';

	if ( '' !== trim( $eyebrow ) ) {
		$out .= '<p class="arv-calendar__eyebrow">' . esc_html( $eyebrow ) . '</p>';
	}
	if ( '' !== trim( $heading ) ) {
		$out .= '<h2 class="arv-calendar__heading">' . esc_html( $heading ) . '</h2>';
	}
	if ( '' !== trim( $intro ) ) {
		$out .= '<p class="arv-calendar__intro">' . esc_html( $intro ) . '</p>';
	}

	$show_filters = isset( $data['filters'] ) ? $data['filters'] : true;
	$show_filters = ! ( 'false' === $show_filters || false === $show_filters || '0' === $show_filters );

	if ( $show_filters && count( $races ) > 5 ) {
		$out .= arv_season_calendar_filters( $races );
	}

	$out .= '<div class="arv-calendar__list" data-arv-list>' . $rows_html . '</div>';

	// The static tail: races with no expected date at all.
	$hiatus = arv_season_calendar_hiatus( isset( $data['hiatus_rows'] ) ? $data['hiatus_rows'] : '' );
	if ( '' !== $hiatus ) {
		// Defaulted rather than left blank when absent, matching how
		// cta_label behaves in Upcoming Races: a hiatus list with no heading
		// above it reads as a broken continuation of the month list.
		$h_heading = ( isset( $data['hiatus_heading'] ) && '' !== trim( $data['hiatus_heading'] ) )
			? $data['hiatus_heading']
			: __( 'On hiatus', 'aravaipa-elements' );
		$h_intro   = isset( $data['hiatus_intro'] ) ? $data['hiatus_intro'] : '';

		$out .= '<div class="arv-calendar__hiatus">';
		if ( '' !== trim( $h_heading ) ) {
			$out .= '<h3 class="arv-calendar__month-name">' . esc_html( $h_heading ) . '</h3>';
		}
		if ( '' !== trim( $h_intro ) ) {
			$out .= '<p class="arv-calendar__hiatus-intro">' . esc_html( $h_intro ) . '</p>';
		}
		$out .= '<div class="arv-calendar__rows">' . $hiatus . '</div>';
		$out .= '</div>';
	}

	$out .= '</div></div>';

	return $out;
}
