<?php
/**
 * Upcoming Races.
 *
 * The homepage had no registration link on it at all. Not a weak one: zero,
 * measured against the live page. Meanwhile /races/ carries 72 races with
 * live UltraSignup links, so everything a visitor could actually buy was
 * three clicks away behind a carousel slide. This is the module that puts
 * the next few races, with dates and a real button, on the page people land
 * on.
 *
 * It also emits Event structured data for every race it shows, which is the
 * other half of the same problem: the site had no schema anywhere, so none of
 * those races were eligible for Google's event results or citable by an
 * answer engine. Doing it here rather than in a separate element means the
 * markup and the schema are generated from one row and cannot drift apart.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Name, ISO date, display date, distances, venue, location, register URL,
// race page URL, image URL, ISO end date, live/results URL, ISO registration
// close date, confirmed (1 or 0), date guessed (1 or 0), latitude,
// longitude. Named because the row parser counts backwards from it, so the
// two have to agree.
if ( ! defined( 'ARV_RACES_COLUMNS' ) ) {
	define( 'ARV_RACES_COLUMNS', 16 );
}

cs_register_element(
	'aravaipa-upcoming-races',
	array(
		'title'   => __( 'Aravaipa Upcoming Races', 'aravaipa-elements' ),
		'values'  => cs_compose_values(
			array(
				'eyebrow'   => cs_value( 'Next up', 'markup' ),
				'heading'   => cs_value( 'Races open now', 'markup' ),
				'intro'     => cs_value( '', 'markup' ),
				'theme'     => cs_value( 'light', 'style' ),
				'columns'   => cs_value( '3', 'markup' ),
				'limit'     => cs_value( '6', 'markup' ),
				'featured'  => cs_value( '', 'markup' ),
				'cta_label' => cs_value( 'Register', 'markup' ),
				'live_lead' => cs_value( '5', 'markup' ),
				'all_label' => cs_value( 'See all races', 'markup' ),
				'all_url'   => cs_value( 'https://www.aravaiparunning.com/races/', 'markup' ),
				'schema'         => cs_value( 'true', 'style' ),
				'only_confirmed' => cs_value( 'true', 'style' ),
				'region'         => cs_value( '', 'markup' ),
				'rows'      => cs_value(
					// Name | ISO date | display date | distances | venue | city, ST | register URL | race page URL | image URL
					//
					// Two date columns on purpose. The ISO one is what the Event
					// schema needs and has to be a real machine date; the display one
					// is what a runner reads, and carries what a date cannot
					// ("September 12-13", a series spanning months). Leave display
					// blank and it is formatted from the ISO date.
					//
					// The whole season ships as the default rather than one example
					// row: a freshly placed element is then immediately useful, and
					// the sort plus the "maximum races to show" limit mean it keeps
					// showing the right six as races pass, with no edit at all.
					// Regenerate with scripts/fetch-races.mjs when the calendar moves;
					// race-rows-2026.txt in the repo root is the same content.
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
		'builder' => 'arv_upcoming_races_builder',
		'render'  => 'arv_upcoming_races_render',
	)
);

/**
 * Builder controls.
 *
 * @return array
 */
function arv_upcoming_races_builder() {
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
					'key'     => 'columns',
					'type'    => 'select',
					'label'   => __( 'Columns', 'aravaipa-elements' ),
					'options' => array(
						'choices' => array(
							array(
								'value' => '2',
								'label' => '2',
							),
							array(
								'value' => '3',
								'label' => '3',
							),
							array(
								'value' => '4',
								'label' => '4',
							),
						),
					),
				),
				array(
					'key'         => 'limit',
					'type'        => 'text',
					'label'       => __( 'Maximum races to show', 'aravaipa-elements' ),
					'description' => __( 'Rows past this are skipped. Paste the whole season and let this pick the front of it. 0 shows every row.', 'aravaipa-elements' ),
				),
				array(
					'key'         => 'featured',
					'type'        => 'text',
					'label'       => __( 'Featured races (optional)', 'aravaipa-elements' ),
					'description' => __( 'Comma-separated race names to pin to the front regardless of date, ahead of races that are chronologically sooner. For something like a month-long virtual race whose date sorts it off the list even while it is open right now. Still subject to the row limit above, so pinning does not add a slot, it takes one.', 'aravaipa-elements' ),
				),
				array(
					'key'   => 'cta_label',
					'type'  => 'text',
					'label' => __( 'Button label', 'aravaipa-elements' ),
				),
				array(
					'key'         => 'live_lead',
					'type'        => 'text',
					'label'       => __( 'Days before race day to show live results', 'aravaipa-elements' ),
					'description' => __( 'The live board carries the start list, with bib numbers, days before anyone runs, so it is worth reaching before race morning. A race also switches over the moment entries close, whichever happens first. 0 waits for race day.', 'aravaipa-elements' ),
				),
				array(
					'key'   => 'all_label',
					'type'  => 'text',
					'label' => __( 'Footer link label', 'aravaipa-elements' ),
				),
				array(
					'key'   => 'all_url',
					'type'  => 'text',
					'label' => __( 'Footer link URL', 'aravaipa-elements' ),
				),
				array(
					'key'         => 'region',
					'type'        => 'text',
					'label'       => __( 'Region slug (optional)', 'aravaipa-elements' ),
					'description' => __( 'Limits the list to one region, for a division page: arizona, colorado, nevada, california, ultra-adventures, great-lakes-endurance, white-mountain-endurance, bad-beard. Leave blank for every race. Only applies once races are in the store.', 'aravaipa-elements' ),
				),
				array(
					'key'         => 'only_confirmed',
					'type'        => 'toggle',
					'label'       => __( 'Only show confirmed races', 'aravaipa-elements' ),
					'description' => __( 'Most recurring races do not have next year\'s UltraSignup listing yet, so the generator marks them unconfirmed rather than guess a date is really open for entries. On (the safe default), this element skips them entirely rather than offer a Register button that leads to a stale page. Turn off only for a list meant to show the whole season regardless of registration status.', 'aravaipa-elements' ),
				),
				array(
					'key'         => 'schema',
					'type'        => 'toggle',
					'label'       => __( 'Event structured data', 'aravaipa-elements' ),
					'description' => __( 'Emits schema.org Event JSON-LD for each race, which is what makes them eligible for Google event results and readable by AI answer engines. Turn off only if another plugin is already emitting Event schema for the same races, so they are not described twice.', 'aravaipa-elements' ),
				),
				array(
					'key'         => 'rows',
					'type'        => 'textarea',
					'label'       => __( 'Races', 'aravaipa-elements' ),
					'description' => __( 'One per line: Name | ISO date (2026-08-29) | display date | distances | venue | city, ST | register URL | race page URL | image URL. The ISO date is required and drives both the sort order and the structured data. Display date is optional and is for ranges a single date cannot express, like "September 12-13".', 'aravaipa-elements' ),
				),
			),
		),
		cs_partial_controls( 'omega' )
	);
}

/**
 * UltraSignup's results page for a race, worked out from its register URL.
 *
 * Both carry the same id. Deriving the results link means a row does not
 * have to carry a second URL that would only ever be the first one with a
 * different filename, and it cannot fall out of sync with it.
 *
 * The id comes in two shapes and this had only ever matched one of them.
 * "did" identifies one specific edition of a race and is what an older
 * registration link carries; "dtid" identifies the race's current edition
 * and is what every 2026 row in the calendar carries, because UltraSignup
 * changed which id it hands out for a new registration link some time after
 * this was written and never checked again. The regex only ever matched
 * "did", so this returned '' for every race on the calendar and the one
 * call site that reaches it with no other results link to fall back on
 * (arv_upcoming_races_action(), for a race with no live timing board) never
 * had anything to show. Confirmed live rather than assumed: UltraSignup
 * redirects results_event.aspx?dtid=N straight to the canonical
 * ?did=N for four different current races, so passing the id straight
 * through under whichever name it arrived as needs no second request to
 * resolve it.
 *
 * Before a race has results, UltraSignup redirects this to the entrants
 * list, which during the race is the live field. That is what a runner's
 * family wants on race day, so one URL serves both the live and results
 * phases.
 *
 * @param string $register_url
 * @return string '' when the URL is not an UltraSignup registration link.
 */
function arv_upcoming_races_results_url( $register_url ) {
	if ( ! preg_match( '#^https?://(?:www\.)?ultrasignup\.com/register\.aspx\?(did|dtid)=(\d+)#i', $register_url, $m ) ) {
		return '';
	}

	return 'https://ultrasignup.com/results_event.aspx?' . strtolower( $m[1] ) . '=' . $m[2];
}

/**
 * The day a finished race stops being shown.
 *
 * Races run at the weekend and interest in the result runs through it, so a
 * race stays up over the rest of its weekend and clears on the Monday
 * morning after. Strictly after, so a race that itself ends on a Monday is
 * not gone the moment it finishes.
 *
 * @param string $end_iso Y-m-d of the race's last day.
 * @return string Y-m-d of the first day it should be gone.
 */
function arv_upcoming_races_clears_on( $end_iso ) {
	$ts  = strtotime( $end_iso . ' 00:00:00 UTC' );
	$dow = (int) gmdate( 'N', $ts ); // 1 Mon ... 7 Sun

	return gmdate( 'Y-m-d', $ts + ( ( 8 - $dow ) * DAY_IN_SECONDS ) );
}

/**
 * A latitude or longitude cell, or '' when it is not a usable coordinate.
 *
 * Validated rather than trusted: these are pasted by hand as often as they
 * are generated, and a pin at a plausible-looking but wrong coordinate is
 * worse than no pin, because nothing about the map looks broken. Out of
 * range, non-numeric, or empty all resolve to the same "do not place a pin".
 *
 * @param string $value
 * @param int    $max   90 for latitude, 180 for longitude.
 * @return string
 */
function arv_upcoming_races_coord( $value, $max ) {
	$value = trim( $value );

	if ( '' === $value || ! is_numeric( $value ) ) {
		return '';
	}

	$number = (float) $value;

	if ( $number < -$max || $number > $max ) {
		return '';
	}

	// 0,0 is in the Atlantic. It is what an empty field becomes when
	// something upstream casts before checking, so it is treated as missing
	// rather than as a race off the coast of Africa.
	if ( 0.0 === $number ) {
		return '';
	}

	return $value;
}

/**
 * Turn one pipe-separated row into a race, or null if it cannot be one.
 *
 * The awkward part: distances are written the way the rest of the site writes
 * them, "50K | 25K | 10K | 5K", and that pipe is also the column separator.
 * Rather than make editors rewrite a format they already use everywhere else,
 * a full-length row is read from both ends. The first three columns and the
 * last five are fixed, so whatever is left in the middle is the distance
 * list, however many pipes it happens to contain.
 *
 * A short row (someone typing a quick one by hand and stopping early) has no
 * fixed tail to anchor against, so it falls back to plain positional reading
 * and simply cannot carry pipes in its distances.
 *
 * @param array $row Cells, already trimmed by arv_parse_rows().
 * @return array|null
 */
function arv_upcoming_races_parse_row( $row ) {
	$count = count( $row );
	$name  = trim( arv_cell( $row, 0 ) );
	$iso   = arv_upcoming_races_date( arv_cell( $row, 1 ) );

	if ( '' === $name || '' === $iso ) {
		return null;
	}

	if ( $count >= ARV_RACES_COLUMNS ) {
		$tail      = array_slice( $row, $count - 12 );
		$distances = implode( ' | ', array_slice( $row, 3, $count - 15 ) );
		return array(
			'name'      => $name,
			'iso'       => $iso,
			'display'   => trim( arv_cell( $row, 2 ) ),
			'distances' => trim( $distances ),
			'venue'     => trim( $tail[0] ),
			'location'  => trim( $tail[1] ),
			'register'  => trim( $tail[2] ),
			'page'      => trim( $tail[3] ),
			'image'     => trim( $tail[4] ),
			'end'       => arv_upcoming_races_date( $tail[5] ),
			'live'      => trim( $tail[6] ),
			'closes'    => arv_upcoming_races_date( $tail[7] ),
			// Anything other than the literal '0' counts as confirmed. Rows
			// written by hand, or by anything older than this column, have no
			// tenth tail value at all, and an event nobody has flagged as
			// unconfirmed should behave as it always did rather than
			// silently vanish from a page that filters on this.
			'confirmed' => ( '0' !== trim( $tail[8] ) ),
			// Whether the year on this row was rolled forward by the
			// generator rather than published by Aravaipa. Deliberately a
			// separate fact from `confirmed`: a race can have a real,
			// future, site-published date and still have no registration set
			// up (The Bear Chase, October 3-4), and the two must not be
			// conflated or a real date gets hidden behind a TBD.
			'guessed'   => ( '1' === trim( $tail[9] ) ),
			'lat'       => arv_upcoming_races_coord( $tail[10], 90 ),
			'lng'       => arv_upcoming_races_coord( $tail[11], 180 ),
		);
	}

	return array(
		'name'      => $name,
		'iso'       => $iso,
		'display'   => trim( arv_cell( $row, 2 ) ),
		'distances' => trim( arv_cell( $row, 3 ) ),
		'venue'     => trim( arv_cell( $row, 4 ) ),
		'location'  => trim( arv_cell( $row, 5 ) ),
		'register'  => trim( arv_cell( $row, 6 ) ),
		'page'      => trim( arv_cell( $row, 7 ) ),
		'image'     => trim( arv_cell( $row, 8 ) ),
		'end'       => arv_upcoming_races_date( arv_cell( $row, 9 ) ),
		'live'      => trim( arv_cell( $row, 10 ) ),
		'closes'    => arv_upcoming_races_date( arv_cell( $row, 11 ) ),
		'confirmed' => ( '0' !== trim( arv_cell( $row, 12 ) ) ),
		'guessed'   => ( '1' === trim( arv_cell( $row, 13 ) ) ),
		'lat'       => arv_upcoming_races_coord( arv_cell( $row, 14 ), 90 ),
		'lng'       => arv_upcoming_races_coord( arv_cell( $row, 15 ), 180 ),
	);
}

/**
 * Where an element's races come from.
 *
 * The store when it has anything in it, the element's own pasted or bundled
 * rows otherwise. That order matters for the migration: installing the store
 * changes nothing until races are actually imported, so a half-migrated site
 * keeps rendering exactly what it rendered yesterday rather than going blank
 * between two deploys.
 *
 * @param array $data Element values.
 * @return array<int, array>
 */
function arv_races_source( $data ) {
	if ( function_exists( 'arv_race_store_has_races' ) && arv_race_store_has_races() ) {
		return arv_race_store_get(
			array(
				'region' => isset( $data['region'] ) ? trim( $data['region'] ) : '',
			)
		);
	}

	$races = array();

	foreach ( arv_parse_rows( isset( $data['rows'] ) ? $data['rows'] : '', 2 ) as $row ) {
		$race = arv_upcoming_races_parse_row( $row );

		// A race with no name or no usable date cannot be sorted, displayed
		// or described, so there is nothing useful to render for it.
		if ( null !== $race ) {
			$races[] = $race;
		}
	}

	return $races;
}

/**
 * A race's distance list, each distance written the way the rest of the site
 * writes it.
 *
 * The store keeps whatever the source said, which is "50KM" for one race and
 * "50K" for the next, so the normalising belongs at the point of display
 * rather than in the data: arv_results_distance_label() is the same function
 * the race week block uses, and this is what makes both agree.
 *
 * Splits on the pipe the store already uses and puts it back, so a race with
 * no distances, or one distance, is left exactly as it was.
 *
 * @param string $distances
 * @return string
 */
function arv_races_distance_list( $distances ) {
	$distances = trim( (string) $distances );

	if ( '' === $distances || ! function_exists( 'arv_results_distance_label' ) ) {
		return $distances;
	}

	$parts = array_map(
		function ( $part ) {
			return arv_results_distance_label( trim( $part ) );
		},
		explode( '|', $distances )
	);

	return implode( ' | ', array_filter( $parts, 'strlen' ) );
}


/**
 * Render callback.
 *
 * @param array $data Element values.
 * @return string
 */
function arv_upcoming_races_render( $data ) {
	$races = arv_races_source( $data );

	if ( empty( $races ) ) {
		return '';
	}

	// Drop what has already happened. Without this the module sorts a static
	// list and shows the front of it, which means the morning after a race it
	// is still sitting there as "next up", pointing at a closed registration.
	// Sorting alone was never enough to make this self-maintaining.
	//
	// A race stays up through its own race day, and through the last day of a
	// multi-day race when an end date is given: Cocodona runs the better part
	// of a week, and dropping it on day two while people are still on the
	// course would be worse than leaving it a day too long.
	$today          = arv_upcoming_races_today();
	$only_confirmed = isset( $data['only_confirmed'] ) ? $data['only_confirmed'] : true;
	$only_confirmed = ! ( 'false' === $only_confirmed || false === $only_confirmed || '0' === $only_confirmed );

	$races = array_values(
		array_filter(
			$races,
			function ( $race ) use ( $today, $only_confirmed ) {
				if ( $only_confirmed && ! $race['confirmed'] ) {
					return false;
				}

				$last = '' !== $race['end'] ? $race['end'] : $race['iso'];

				// Not simply "has it finished". A result is at its most
				// wanted in the days right after the race, so a finished race
				// stays up over the rest of its weekend and clears on the
				// Monday morning after.
				return $today < arv_upcoming_races_clears_on( $last );
			}
		)
	);

	if ( empty( $races ) ) {
		// Every race in the list is in the past. Rendering the heading over an
		// empty grid would look broken; showing nothing is the honest state
		// and is the signal that the rows need regenerating.
		return '';
	}

	// Sorted here rather than trusting the paste order: the whole point of
	// this module is "what is next", and a row list maintained by hand drifts
	// out of order the first time someone inserts a race in the middle.
	usort(
		$races,
		function ( $a, $b ) {
			return strcmp( $a['iso'], $b['iso'] );
		}
	);

	// Pinning happens after the date sort and before the limit, so a named
	// race jumps the queue rather than widening it: it takes a slot instead
	// of adding one, which is what keeps "6 races" meaning 6 on a homepage
	// that was never asked to show more. A virtual race open for a month
	// sorts by its start date same as everything else, which buries it
	// behind every physical race happening sooner even though it is the one
	// somebody could act on today.
	$featured = isset( $data['featured'] ) ? arv_parse_list( $data['featured'] ) : array();
	if ( ! empty( $featured ) ) {
		$featured_lc = array_map( 'strtolower', $featured );
		$pinned      = array();
		$rest        = array();

		foreach ( $races as $race ) {
			$slot = array_search( strtolower( $race['name'] ), $featured_lc, true );
			if ( false !== $slot ) {
				// Keyed by its position in the featured list, not appended,
				// so pinning two races puts them in the order they were
				// named rather than the order they happened to appear in
				// the already-date-sorted array.
				$pinned[ $slot ] = $race;
			} else {
				$rest[] = $race;
			}
		}

		ksort( $pinned );
		$races = array_merge( array_values( $pinned ), $rest );
	}

	$limit = isset( $data['limit'] ) ? (int) $data['limit'] : 6;
	if ( $limit > 0 ) {
		$races = array_slice( $races, 0, $limit );
	}

	$cta_label = isset( $data['cta_label'] ) && '' !== trim( $data['cta_label'] ) ? $data['cta_label'] : __( 'Register', 'aravaipa-elements' );

	// Clamped rather than trusted: a lead longer than the gap between races
	// would put every race on the list into its live phase at once, which
	// would read as though the whole season were running today.
	$live_lead = isset( $data['live_lead'] ) ? (int) $data['live_lead'] : 5;
	$live_lead = max( 0, min( 30, $live_lead ) );

	$cards  = '';
	$events = array();

	foreach ( $races as $race ) {
		$display = '' !== $race['display'] ? $race['display'] : gmdate( 'F j', strtotime( $race['iso'] . ' 00:00:00 UTC' ) );

		// The card is a link to the race page when there is one, so the whole
		// tile is clickable, and a plain container when there is not. Falling
		// back to the register URL instead would send someone straight to
		// checkout for a race they have not read about yet.
		$card_url = '' !== $race['page'] ? $race['page'] : '';

		// data-arv-results-row is what lets the clock script find this card's
		// live marker from the clock inside it: the two are siblings rather
		// than nested, so the script walks up to the nearest marked row.
		$cards .= '<div class="arv-races__card" data-arv-results-row>';

		if ( '' !== $race['image'] ) {
			$cards .= '<div class="arv-races__media">';
			// Race name as alt rather than empty: unlike the region map's
			// brand marks, this image is the only thing identifying the race
			// visually, and it sits above the name rather than beside it.
			$img = '<img class="arv-races__img" src="' . esc_url( $race['image'] ) . '" alt="' . esc_attr( $race['name'] ) . '" loading="lazy" decoding="async" />';
			// Wrapped in a link to the race page when there is one. It is the
			// most obviously clickable thing on the card and was doing
			// nothing. aria-hidden with tabindex -1 because the race name
			// directly below is already a link to the same page: a screen
			// reader or keyboard user hitting the same destination twice in a
			// row is noise, not access.
			if ( '' !== $card_url ) {
				$cards .= '<a class="arv-races__media-link" href="' . esc_url( $card_url ) . '" aria-hidden="true" tabindex="-1">' . $img . '</a>';
			} else {
				$cards .= $img;
			}
			$cards .= '</div>';
		}

		$cards .= '<div class="arv-races__body">';
		// <time> rather than a bare span so the machine-readable date is in
		// the markup itself, not only in the JSON-LD below.
		$cards .= '<span class="arv-races__when">';
		$cards .= '<time class="arv-races__date" datetime="' . esc_attr( $race['iso'] ) . '">' . esc_html( $display ) . '</time>';

		// On the date line rather than a line of its own, so a card only
		// changes shape while a race is actually running and goes back to
		// exactly what it was afterwards. Renders nothing at all otherwise.
		if ( function_exists( 'arv_races_live_clock' ) ) {
			$cards .= arv_races_live_clock( $race );
		}

		$cards .= '</span>';

		$title = esc_html( $race['name'] );
		if ( '' !== $card_url ) {
			$title = '<a class="arv-races__link" href="' . esc_url( $card_url ) . '">' . $title . '</a>';
		}
		$cards .= '<h3 class="arv-races__name">' . $title . '</h3>';

		if ( '' !== $race['distances'] ) {
			// Normalised the same way the race week block already does it, so
			// the same race does not read "50KM | 33KM | 10 KM" here and
			// "50K 33K 10K" three sections down the results page. It also
			// buys back a line: Snow Mountain Ranch's five distances wrapped
			// at 390px and no longer do.
			$cards .= '<p class="arv-races__distances">'
				. esc_html( arv_races_distance_list( $race['distances'] ) ) . '</p>';
		}

		$where = array_filter( array( $race['venue'], $race['location'] ) );
		if ( ! empty( $where ) ) {
			$cards .= '<p class="arv-races__where">' . esc_html( implode( ', ', $where ) ) . '</p>';
		}

		// What the primary button offers depends on where the race is in its
		// life: entries before it, the live field during it, results after.
		$action = arv_upcoming_races_action( $race, $today, $live_lead );

		// The configured label is about selling entries. Once the race is
		// running, "Register" would be wrong whatever the setting says, so
		// the phase wins.
		$primary_label = ( 'upcoming' === $action['phase'] && '' !== trim( $cta_label ) )
			? $cta_label
			: $action['label'];

		// Closes the body before the actions open, so the buttons are a
		// sibling of the text block rather than sitting inside it. Stacked on
		// a phone the card becomes two columns, a small logo beside the text,
		// and buttons nested in the text column inherit that indent: they
		// start well right of the card's edge and lose about a third of the
		// width to a column that has nothing in it below the logo. As a
		// sibling the row can span the whole card at that width. In the
		// desktop column layout this changes nothing, since the card is a
		// column either way.
		$cards .= '</div>';

		$cards .= '<div class="arv-races__actions">';
		if ( '' === $action['url'] && '' !== $action['label'] ) {
			// Nowhere to send anyone, but the slot still has something worth
			// saying. A span, not a disabled link: there is no destination,
			// so it should not be focusable or look clickable. Race Details
			// beside it becomes the only thing to press, which is correct.
			$cards .= '<span class="arv-races__cta arv-races__cta--' . esc_attr( $action['phase'] ) . '">' . esc_html( $action['label'] ) . '</span>';
		} elseif ( '' !== $action['url'] ) {
			// Registration and the timing board are off-site, so those open
			// in a new tab, with noopener because target=_blank without it
			// hands the opened page a live reference back to this window.
			// A race with a live page of its own is not off-site any more,
			// and sending someone to another tab of the site they are
			// already on is the kind of thing that quietly accumulates
			// windows.
			$cards .= '<a class="arv-races__cta arv-races__cta--' . esc_attr( $action['phase'] ) . '" href="' . esc_url( $action['url'] ) . '"'
				. arv_races_link_target( $action['url'] ) . '>' . esc_html( $primary_label ) . '</a>';
		}
		if ( '' !== $card_url ) {
			// Always present, and always the secondary of the pair, so the
			// buttons keep the same shape whatever phase a race is in and the
			// eye is not re-learning the layout down the list.
			$cards .= '<a class="arv-races__details" href="' . esc_url( $card_url ) . '">' . esc_html( __( 'Race Details', 'aravaipa-elements' ) ) . '</a>';
		}
		$cards .= '</div>';

		$cards .= '</div>';

		$events[] = arv_upcoming_races_event_schema( $race, $action['phase'] );
	}

	if ( '' === $cards ) {
		return '';
	}

	$theme     = ( isset( $data['theme'] ) && 'dark' === $data['theme'] ) ? 'dark' : 'light';
	$columns   = isset( $data['columns'] ) ? (int) $data['columns'] : 3;
	$columns   = in_array( $columns, array( 2, 3, 4 ), true ) ? $columns : 3;

	// Cornerstone toggles arrive as the strings "true"/"false" as often as
	// booleans depending on how the value was saved, so compare loosely
	// rather than trusting a bare truthiness check ("false" is truthy).
	$want_schema = isset( $data['schema'] ) ? $data['schema'] : true;
	$want_schema = ! ( 'false' === $want_schema || false === $want_schema || '0' === $want_schema );

	$base = 'arv-races arv-races--' . $theme . ' arv-races--cols-' . $columns;

	$out  = '<div class="' . arv_wrapper_class( $data, $base ) . '">';
	$out .= '<div class="arv-races__inner">';

	$eyebrow = isset( $data['eyebrow'] ) ? $data['eyebrow'] : '';
	$heading = isset( $data['heading'] ) ? $data['heading'] : '';
	$intro   = isset( $data['intro'] ) ? $data['intro'] : '';

	if ( '' !== trim( $eyebrow ) ) {
		$out .= '<p class="arv-races__eyebrow">' . esc_html( $eyebrow ) . '</p>';
	}
	if ( '' !== trim( $heading ) ) {
		$out .= '<h2 class="arv-races__heading">' . esc_html( $heading ) . '</h2>';
	}
	if ( '' !== trim( $intro ) ) {
		$out .= '<p class="arv-races__intro">' . esc_html( $intro ) . '</p>';
	}

	$out .= '<div class="arv-races__grid">' . $cards . '</div>';

	$all_url   = isset( $data['all_url'] ) ? trim( $data['all_url'] ) : '';
	$all_label = isset( $data['all_label'] ) ? trim( $data['all_label'] ) : '';
	if ( '' !== $all_url && '' !== $all_label ) {
		$out .= '<p class="arv-races__all"><a href="' . esc_url( $all_url ) . '">' . esc_html( $all_label ) . '</a></p>';
	}

	$out .= '</div>';

	if ( $want_schema && ! empty( $events ) ) {
		$out .= arv_upcoming_races_schema_block( $events );
	}

	$out .= '</div>';

	return $out;
}

/**
 * Wrap the events in a single JSON-LD script tag.
 *
 * One script holding an array rather than one per race: fewer tags for the
 * same graph, and it keeps the events grouped as what they are, a list this
 * one module is responsible for.
 *
 * @param array $events
 * @return string
 */
function arv_upcoming_races_schema_block( $events ) {
	// Wrapped in @context/@graph rather than emitted as a bare array. Without
	// the context, none of the "@type": "SportsEvent" values resolve to
	// anything: a consumer has no way to know they mean schema.org's
	// SportsEvent, so the whole block is ignored rather than misread. @graph
	// is what carries several top-level nodes under one context.
	$payload = array(
		'@context' => 'https://schema.org',
		'@graph'   => $events,
	);

	// JSON_UNESCAPED_SLASHES keeps the URLs readable rather than \/ escaped;
	// JSON_UNESCAPED_UNICODE keeps accented race names as themselves.
	$json = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

	if ( false === $json ) {
		return '';
	}

	// The payload is built from editor input, so it gets the same treatment
	// as any other untrusted string heading into a <script>: "</script>"
	// inside a race name would otherwise close the tag early and drop the
	// rest of the JSON into the document as markup.
	$json = str_replace( '<', '\u003C', $json );

	return '<script type="application/ld+json">' . $json . '</script>';
}
