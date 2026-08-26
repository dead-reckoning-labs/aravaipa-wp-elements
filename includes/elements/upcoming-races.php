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
// close date. Named because the row parser counts backwards from it, so the
// two have to agree.
if ( ! defined( 'ARV_RACES_COLUMNS' ) ) {
	define( 'ARV_RACES_COLUMNS', 12 );
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
				'cta_label' => cs_value( 'Register', 'markup' ),
				'live_lead' => cs_value( '5', 'markup' ),
				'all_label' => cs_value( 'See all races', 'markup' ),
				'all_url'   => cs_value( 'https://www.aravaiparunning.com/races/', 'markup' ),
				'schema'    => cs_value( 'true', 'style' ),
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
					"Rock Hawk | 2026-08-29 | August 29 | 50K | 25K | 10K | 5K | Phillip S. Miller Park | Castle Rock, CO | https://ultrasignup.com/register.aspx?did=131056 | https://www.aravaiparunning.com/bear-chase-series/rock-hawk/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2024/10/Jallucinations-8.png?fit=1080%2C1080&ssl=1 |  | https://live.aravaiparunning.com/#/rock_hawk-2026 | 2026-08-24\n" .
					"Black Bear Trail Race | 2026-08-29 | August 29 | 50KM | 23K | 4 Mile | 1 Mile | Waterville Valley Town Square | Waterville Valley, NH | https://ultrasignup.com/register.aspx?did=130629 | https://www.aravaiparunning.com/white-mountain-endurance/black-bear-trail-races/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/Black-Bear-Trail-Races_Logo-01.png?fit=1875%2C1920&ssl=1 |  | https://live.aravaiparunning.com/#/black_bear-2026 | 2026-08-24\n" .
					"Mogollon Monster Trail Runs | 2026-09-12 | September 12-13 | 100 Mile, 42K | Mogollon Rim | Pine, AZ | https://ultrasignup.com/register.aspx?did=130408 | https://www.aravaiparunning.com/mogollon-monster/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2019/06/Mogollon-Monster-Run-logo.png?w=810&ssl=1 | 2026-09-13 | https://live.aravaiparunning.com/#/mogollon_monster-2026 | 2026-09-07\n" .
					"Snow Mountain Ranch Trail Running Festival | 2026-09-12 | September 12 | 50KM | 33KM | Half-Marathon | 10 KM | 5KM | Snow Mountain Ranch | Granby, CO | https://ultrasignup.com/register.aspx?did=131162 | https://www.aravaiparunning.com/snow-mountain-ranch/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2025-SnowMountainRanch-Logo-v1.png?fit=1708%2C1920&ssl=1 |  |  | 2026-09-07\n" .
					"Race The Cog | 2026-09-13 | September 13 | 2.75 Miles w/ 3500ft Gain | Mount Washington Cog Railway | Bretton Woods, NH | https://ultrasignup.com/register.aspx?did=130509 | https://www.aravaiparunning.com/white-mountain-endurance/cog/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2023/05/Race-the-Cog-Logo-Full-Color.png?fit=1438%2C1376&ssl=1 |  |  | 2026-09-07\n" .
					"Jangover Night Runs | 2026-09-19 | September 19 | 75K | 50K | 25K | 15K | 7K | McDowell Mountain Regional Park | Fountain Hills, AZ | https://ultrasignup.com/register.aspx?did=131011 | https://www.aravaiparunning.com/insomniac/jangover/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2020/04/2020_Jangover_Logo_Glow.png?fit=288%2C300&ssl=1 |  | https://live.aravaiparunning.com/#/jangover_runs-2026 | 2026-09-14\n" .
					"Kilkenny Ridge Race | 2026-09-19 | September 19 | 50 Mile, 25 Mile, 25K | Kilkenny Ridge Trail | Stark, NH | https://ultrasignup.com/register.aspx?did=130633 | https://www.aravaiparunning.com/white-mountain-endurance/kilkenny-ridge-race/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2023/05/kilkenny-1536x1013-2.png?fit=1536%2C1013&ssl=1 |  | https://live.aravaiparunning.com/#/killeny_ridge-2026 | 2026-09-14\n" .
					"Bryce Canyon Ultras | 2026-09-19 | September 19 | 100M | 50M | 60K | 50K | 30K | Half Marathon | Lucky 7 Ranch | Hatch, UT | https://ultrasignup.com/register.aspx?did=129957 | https://www.aravaiparunning.com/bryce/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/Bryce-Canyon-Logo.png?fit=1484%2C1920&ssl=1 |  |  | 2026-09-13\n" .
					"Flagstaff Sky Peaks | 2026-09-25 | September 25-27 | 50M, 50K, 26K, 11K, 5K, 6HR, 12HR, Mountain 20K, Mountain 6K | Arizona Snowbowl | Flagstaff, AZ | https://ultrasignup.com/register.aspx?did=134360 | https://www.aravaiparunning.com/skypeaksweekend/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2018/04/2018_FlagstaffSkyPeaks_Logo_Badge-01-e1524955847501.png?fit=300%2C270&ssl=1 | 2026-09-27 | https://live.aravaiparunning.com/#/flagstaff_sky_peaks-2026 | 2026-09-21\n" .
					"The Bear Chase | 2026-10-03 | October 3-4 | 100K, 50 Mile, 50K, Half Marathon, Baby Bear 10K, 5K | Bear Creek Lake Park | Lakewood, CO | https://ultrasignup.com/register.aspx?did=120730 | https://www.aravaiparunning.com/the-bear-chase-race/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2024/01/bear-chase-logo_no-border.png?fit=1287%2C552&ssl=1 | 2026-10-04 |  | \n" .
					"That's No Moon | 2026-10-10 | October 10 | 50 Mile, 50K, 30K | Black Star Canyon | Silverado, CA | https://ultrasignup.com/register.aspx?did=122136 | https://www.aravaiparunning.com/thats-no-moon/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2024/12/Thats-No-Moon-Logo-Light-Background.png?fit=1206%2C1356&ssl=1 |  |  | \n" .
					"Thrasher Night Trail | 2026-10-16 | October 16 | 33K, 22K, 11K, 5K | Cave Creek Regional Park | Cave Creek, AZ | https://ultrasignup.com/register.aspx?did=116579 | https://aravaiparunning.com/insomniac/thrasher | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2024/09/pun-1-2.png?fit=750%2C750&ssl=1 |  |  | \n" .
					"Cave Creek Thriller | 2026-10-17 | October 17 | 50K, 24K, 11K, 5K | Cave Creek Regional Park | Cave Creek, AZ | https://ultrasignup.com/register.aspx?did=124487 | https://www.aravaiparunning.com/Cave-Creek-Thriller/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2020/09/DRT_cavecreek_redesign_v9.png?resize=300%2C275&ssl=1 |  |  | \n" .
					"Bobcat Trail Races | 2026-10-17 | October 17 | 50K, 25K, 10K, 5K | Palmer Park | Colorado Springs, CO | https://ultrasignup.com/register.aspx?did=110971 | https://www.aravaiparunning.com/bobcat/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2024/10/Jallucinations-4.png?fit=1080%2C1080&ssl=1 |  |  | \n" .
					"Pass Mountain | 2026-11-14 | November 14 | 50 Mile, 50K, 25K, 10K, 5K | Usery Mountain Regional Park | Mesa, AZ | https://ultrasignup.com/register.aspx?did=124491 | https://www.aravaiparunning.com/pass-mountain/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2019/12/DRT_passmountain_redesign_v1.png?resize=300%2C276&ssl=1 |  |  | \n" .
					"Punisher Night Trail | 2026-11-14 | November 14 | 30K, 20K, 10K, 5K | Usery Mountain Regional Park | Mesa, AZ | https://ultrasignup.com/register.aspx?did=125372 | https://www.aravaiparunning.com/insomniac/punisher-night-trail/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2024/09/pun-2-2.png?fit=750%2C750&ssl=1 |  |  | \n" .
					"Louisville Trail Race | 2026-11-14 | November 14 | Half-Marathon | 10K | 5K | Louisville, CO | Louisville, CO | https://ultrasignup.com/register.aspx?did=121156 | https://www.aravaiparunning.com/louisville/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2025-LouisvilleTrailRace-Logo-v1.png?fit=1920%2C1341&ssl=1 |  |  | \n" .
					"Live Oak Odyssey | 2026-11-14 | November 14 | 6 Hour | 3 Hour | O'Neill Regional Park | Trabuco Canyon, CA | https://ultrasignup.com/register.aspx?did=127499 | https://www.aravaiparunning.com/live-oak-odyssey/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/Live-Oak-Odyssey-Logo-V1.png?fit=1000%2C1000&ssl=1 |  |  | \n" .
					"Fat Ox | 2026-11-20 | November 20-21 | 48/24/12/6 Hour, 100 Mile, 100K, 50 Mile, 50K | Estrella Mountain Regional Park | Goodyear, AZ | https://ultrasignup.com/register.aspx?did=121720 | https://www.aravaiparunning.com/fat-ox/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2023/05/2023-FatOx-Logo_v1.png?fit=1920%2C1425&ssl=1 | 2026-11-21 |  | \n" .
					"McDowell Mountain Frenzy | 2026-12-05 | December 5 | 50 Mile, 50K, 25K, 10 Mile, 5 Mile | McDowell Mountain Regional Park | Fountain Hills, AZ | https://ultrasignup.com/register.aspx?did=124496 | https://aravaiparunning.com/mcdowell-mountain-frenzy/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2019/12/DRT_mtfrenzy_redesign_v3-crazythin.png?resize=300%2C276&ssl=1 |  |  | \n" .
					"Mayhem Night Trail | 2026-12-05 | December 5 | 25K, 10 Mile, 5 Mile | McDowell Mountain Regional Park | Fountain Hills, AZ | https://ultrasignup.com/register.aspx?did=125376 | https://www.aravaiparunning.com/insomniac/mayhem/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2024/09/pun-4-2.png?fit=750%2C750&ssl=1 |  |  | \n" .
					"Desert Solstice Track Invitational | 2026-12-20 | December 20 | 24 Hour, 100 Mile | Central High School | Phoenix, AZ | https://ultrasignup.com/register.aspx?did=123347 | https://www.aravaiparunning.com/desert-solstice/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2020/01/2019_DesertSolstice_LogoOnly.png?resize=300%2C152&ssl=1 |  |  | \n" .
					"Across The Years | 2026-12-28 | December 28-January 3 | 6 Day, 72 Hr, 48 Hr, 24 Hr, 12 Hr, 6 Hr, 200 Mile, 100 Mile, 100 Km, Last Person Standing, Marathon | Peoria Sports Complex | Phoenix, AZ | https://ultrasignup.com/register.aspx?did=124888 | https://www.aravaiparunning.com/across-the-years/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2021/09/ATY-Logo-Color-Alt-Compact.png?fit=338%2C300&ssl=1 | 2027-01-03 |  | \n" .
					"San Tan Scramble | 2027-01-09 | January 9 | 50K, 26K, 17K, 9K, 5K, Kid's Run | San Tan Mountain Regional Park | Queen Creek, AZ | https://ultrasignup.com/register.aspx?did=125327 | https://www.aravaiparunning.com/drt-series/san-tan-scramble/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2024/01/DRT_SanTanScramble_redesign_v2-2.png?fit=1501%2C1800&ssl=1 |  |  | \n" .
					"Coldwater Hundred | 2027-01-16 | January 16 | 100M, 100K, 50K, 20M, 10M, 5M | Estrella Mountain Regional Park | Goodyear, AZ | https://ultrasignup.com/register.aspx?did=125337 | https://www.aravaiparunning.com/coldwater/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2026-ColdwaterRumble-Logo-v1.png?fit=901%2C913&ssl=1 |  |  | \n" .
					"Prickly Pedal Runs | 2027-01-31 | January 31 | 10 Mile | Lake Pleasant Regional Park | Morris Town, AZ | https://ultrasignup.com/register.aspx?did=129382 |  | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/9235_PricklyPedal_Logo_Black_Distressed_72dpi_24bit.png?fit=403%2C416&ssl=1 |  |  | \n" .
					"Vegas Golden Night & Day | 2027-02-06 | February 6 | Half Marathon, 10K, 5K | Sunset Regional Park | Las Vegas, NV | https://ultrasignup.com/register.aspx?did=125347 | https://www.aravaiparunning.com/elephant-mountain/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/New-Website-Images-2.0-15.png?fit=975%2C648&ssl=1 |  |  | \n" .
					"Elephant Mountain | 2027-02-06 | February 6 | 50 Mile, 50K, 35K, 22K, 12K, 6K | Cave Creek Regional Park | Cave Creek, AZ | https://ultrasignup.com/register.aspx?did=125347 | https://www.aravaiparunning.com/elephant-mountain/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2020/01/DRT_ElephantMountain_redesign_v2.png?resize=274%2C300&ssl=1 |  |  | \n" .
					"Black Canyon Ultras | 2027-02-13 | February 13-14 | 100K, 50K | Black Canyon Trail | Mayer, AZ | https://ultrasignup.com/register.aspx?did=125776 | https://www.aravaiparunning.com/blackcanyon/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2018/04/BlackCanyon_Logo_v4-01-e1524957085653.png?w=810&ssl=1 | 2027-02-14 |  | \n" .
					"Jackpot Ultras | 2027-02-19 | February 19-20 | 48H, 24H, 12H, 6H, 100M, 100Km, 50M | Cornerstone Park | Henderson, NV | https://ultrasignup.com/register.aspx?did=124861 | https://www.aravaiparunning.com/jackpot/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2023/02/2022-JackpotUltras-Logo-v3.png?fit=300%2C211&ssl=1 | 2027-02-20 |  | \n" .
					"Copper Corridor | 2027-02-27 | February 27 | 50K, 31K, 17K, 12K | Arizona Trail & Legends of Superior Trails | Superior, AZ | https://ultrasignup.com/register.aspx?did=126437 | https://www.aravaiparunning.com/copper/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2020/03/2020_CopperCorridor_Logo_v5_SuperiorAZ.png?fit=1920%2C1639&ssl=1 |  |  | \n" .
					"Antelope Canyon Ultras | 2027-03-06 | March 6 | 50 Mile | 55K | 30K | Half Marathon | Page Sports Complex | Page, AZ | https://ultrasignup.com/register.aspx?did=138695 | https://www.aravaiparunning.com/antelope/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/Antelope-Canyon-Logo.png?fit=1000%2C1000&ssl=1 |  |  | 2027-03-01\n" .
					"Mesquite Canyon | 2027-03-13 | March 13 | 50M, 50K, 30K, 1/2 Marathon, 8K, Kid's Fun Run | White Tank Mountain Regional Park | Waddell, AZ | https://ultrasignup.com/register.aspx?did=125359 | https://www.aravaiparunning.com/mesquite-canyon/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2020/01/DRT_MesquiteCanyon_redesign_v2.png?fit=1920%2C1642&ssl=1 |  |  | \n" .
					"Purple Run | 2027-03-20 | March 20 | Half-Marathon | 10K | 5K | Sunset Regional Park | Las Vegas, NV | https://ultrasignup.com/register.aspx?did=142296 | https://www.aravaiparunning.com/crown-king/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/New-Website-Images-2.0-18.png?fit=975%2C648&ssl=1 |  |  | 2027-03-15\n" .
					"Crown King Scramble | 2027-03-20 | March 20 | 50 Kilometer | Crown King | Crown King, AZ | https://ultrasignup.com/register.aspx?did=125704 | https://www.aravaiparunning.com/crown-king/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2019/04/2018_CrownKingScramble_Logo_v3-01.png?w=810&ssl=1 |  |  | \n" .
					"Dam Good Run | 2027-04-04 | April 4 | 40K, 26K, 13K, 4 Miler, 2 Miler | Lake Pleasant Regional Park | Morristown, AZ | https://ultrasignup.com/register.aspx?did=126402 | https://www.aravaiparunning.com/races/dam-good-run/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2025/01/2024-DamGoodRun-Logo-v1.png?fit=1112%2C1817&ssl=1 |  |  | \n" .
					"Zion Ultras | 2027-04-10 | April 10 | 100 Mile, 100K, 60K, 30K, Half Marathon | Ruby Rider Ranch | Apple Valley, UT | https://ultrasignup.com/register.aspx?did=126402 | https://www.aravaiparunning.com/zion/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/ZionUltrasLogo.png?fit=536%2C800&ssl=1 |  |  | \n" .
					"Mountain Ridge Trail Race | 2027-04-11 | April 11 | 21 Mile, Half-Marathon, 10 KM, 5 KM | Highlands Ranch | Colorado | https://ultrasignup.com/register.aspx?did=131168 | https://www.aravaiparunning.com/mountain-ridge/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2025-MountainRidge-Logo-v1.png?fit=1226%2C1920&ssl=1 |  |  | \n" .
					"Whiskey Basin Trail Runs | 2027-04-17 | April 17 | 92K, 58K, 33K, Half-Marathon, 10K | Prescott Circle Trail | Prescott, AZ | https://ultrasignup.com/register.aspx?did=126299 | https://www.aravaiparunning.com/whiskey-basin/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2025_WhiskeyBasin_Logo-NoDistances_v1-vertical.png?fit=1501%2C1501&ssl=1 |  |  | \n" .
					"Sinister Night Runs | 2027-04-25 | April 25 | 54K | 27K | 18K | 9K | 6K | San Tan Regional Park | Queen Creek, AZ | https://ultrasignup.com/register.aspx?did=130980 | https://www.aravaiparunning.com/insomniac/sinister/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2020/04/2020_InsomniacSeries_Sinister_Sticker.png?fit=984%2C1059&ssl=1 |  |  | \n" .
					"Royal Gorge Groove Trail Runs | 2027-04-25 | April 25 | 50K, 30K, 20K, 10K, 5K, Kids Run | Royal Gorge Park | Canon City, CO | https://ultrasignup.com/register.aspx?did=129387 | https://www.aravaiparunning.com/royal-gorge-groove/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2022/02/2021-RoyalGorgeGroove-Logo-v1.png?fit=500%2C552&ssl=1 |  |  | \n" .
					"White Lake Ultras | 2027-05-02 | May 2 | 24 Hours, 12 Hours, 6 Hours, Relays | White Lake State Park | Tamworth, NH | https://ultrasignup.com/register.aspx?did=129138 | https://www.aravaiparunning.com/white-mountain-endurance/white-lake-ultras/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2025-WhiteLakeUltra-Logo-v2_times.png?fit=1920%2C1912&ssl=1 |  |  | \n" .
					"Cocodona 250 | 2027-05-04 | May 4 | 125 Mile, 100 Mile, 80 Mile, 40 Mile | Black Canyon City to Flagstaff | Central & Northern Arizona | https://ultrasignup.com/register.aspx?did=126941 | https://www.aravaiparunning.com/cocodona/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/Copy-of-2025-Cocodona250-Print-Black-AltraLockups_v1.png?fit=1920%2C1590&ssl=1 |  |  | \n" .
					"Ram Party | 2027-05-16 | May 16 | 55 Mile | 60K | 50K | 24K | 16K | 15K | Rampart Range | Colorado Springs, CO Woodland Park, CO | https://ultrasignup.com/register.aspx?did=129482 | https://www.aravaiparunning.com/ram-party/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2022/02/2021-RamParty-Logo-v3-extraColor2-e1645722365331.png?fit=400%2C400&ssl=1 |  |  | \n" .
					"Adrenaline Night Runs | 2027-05-23 | May 23 | 50K | 25K | 15K | 10K | 6K | McDowell Mountain Regional Park | Fountain Hills, AZ | https://ultrasignup.com/register.aspx?did=120430 | https://www.aravaiparunning.com/insomniac-night-trail-series/adrenaline/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2020/03/2020_Adrenaline_Logo.png?resize=269%2C300&ssl=1 |  |  | \n" .
					"Hotfoot Hamster | 2027-05-30 | May 30 | 24 Hour, 12 Hour, 6 Hour | Nardini Manor | Buckeye, AZ | https://ultrasignup.com/register.aspx?did=129798 | https://www.aravaiparunning.com/hotfoot-hamster/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2018/04/2018_HotfootHamster_Logo_web-01-e1524852881953.png?fit=300%2C300&ssl=1 |  |  | \n" .
					"North Fork 50 | 2027-05-30 | May 30 | 50 Mile | 50K | Buffalo Creek | Buffalo Creek, CO | https://ultrasignup.com/register.aspx?did=129384 | https://www.aravaiparunning.com/north-fork-ultras/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2024/10/Jallucinations-5.png?fit=1080%2C1080&ssl=1 |  |  | \n" .
					"Rock River Canyon | 2027-06-06 | June 6 | 50K | 27K | Rock River Canyon Wilderness | Munsing, MI | https://ultrasignup.com/register.aspx?did=129354 | https://www.aravaiparunning.com/great-lakes-endurance/rock-river-canyon/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/Rock-River-Logo.png?fit=1000%2C1000&ssl=1 |  |  | \n" .
					"Chocorua Mountain Race | 2027-06-06 | June 6 | 25K | Chocorua Mountain | Tamworth, NH | https://ultrasignup.com/register.aspx?did=130502 | https://www.aravaiparunning.com/white-mountain-endurance/chocorua/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2023/05/Chocorua-Mountain-Race-Logo-FULL-COLOR-TRANSPARENT-2022.png?fit=1920%2C831&ssl=1 |  |  | \n" .
					"Hypnosis Night Runs | 2027-06-13 | June 13 | 52K | 36K | 22K | 15K | 6K | Estrella Mountain Regional Park | Avondale, AZ | https://ultrasignup.com/register.aspx?did=130997 | https://www.aravaiparunning.com/insomniac/hypnosis | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2020/03/2020_Hypnosis_Logo.png?resize=287%2C300&ssl=1 |  |  | \n" .
					"Barr Lake Trail Race | 2027-06-13 | June 13 | 50K | 30K | Half Marathon | 15K | 5K | Barr Lake State Park | Brighton, CO | https://ultrasignup.com/register.aspx?did=131062 | https://www.aravaiparunning.com/barr-lake/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2025-BarrLake-Logo-v1.png?fit=1920%2C1258&ssl=1 |  |  | \n" .
					"Blackout Night Runs | 2027-06-19 | June 19 | 27K, 13K, 6K | Fort Tuthill County Park | Flagstaff, AZ | https://ultrasignup.com/register.aspx?did=129572 | https://www.aravaiparunning.com/insomniac/blackout/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2022/04/2022-BlackoutNightRuns-Logo-v5.png?fit=1920%2C1920&ssl=1 |  |  | \n" .
					"Flagstaff Extreme Big Pine Trail Runs | 2027-06-20 | June 20 | 52K, 27K, 13K, 6K | Fort Tuthill County Park | Flagstaff, AZ | https://ultrasignup.com/register.aspx?did=129568 | https://www.aravaiparunning.com/big-pine/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/Hardrock-Virtual-Sample-Badge-1.png?fit=1000%2C1000&ssl=1 |  |  | \n" .
					"Two Hearted Trail Runs | 2027-06-20 | June 20 | 50K | Marathon | Half Marathon | Little Two Hearted River | Paradise, MI | https://ultrasignup.com/register.aspx?did=129717 | https://www.aravaiparunning.com/great-lakes-endurance/two-hearted/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/twohearted.png?fit=1200%2C1200&ssl=1 |  |  | \n" .
					"Ring the Springs | 2027-06-20 | June 20 | 100K | 50K | 35K | Rock Ledge Ranch at Garden of the Gods | Colorado Springs, CO | https://ultrasignup.com/register.aspx?did=129487 | https://www.aravaiparunning.com/ring-the-springs/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2024/05/Ring-the-Springs-RTS-Logo.png?fit=500%2C500&ssl=1 |  |  | \n" .
					"Chase The Moon | 2027-06-27 | June 27 | 12-Hour team relay (3 or 5 person), solo ultramarathon (overnight) | Mountain Vista High School | Mountain Vista Ridge, CO | https://ultrasignup.com/register.aspx?did=131067 | https://www.aravaiparunning.com/bear-chase-series/chase-the-moon/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2024/01/Untitled-design-2.png?fit=300%2C265&ssl=1 |  |  | \n" .
					"Waugoshance Trail Run | 2027-07-11 | July 11 | 50K | Marathon | Half Marathon | North Country Trail | Emmett County, MI | https://ultrasignup.com/register.aspx?did=129767 | https://www.aravaiparunning.com/great-lakes-endurance/waugoshance/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/waugoshance-logo.png?fit=547%2C546&ssl=1 |  |  | \n" .
					"Stunner Night Runs | 2027-07-11 | July 11 | 50K | 25K | 12K | 6K | Usery Mountain Regional Park | Mesa, AZ | https://ultrasignup.com/register.aspx?did=131002 | https://www.aravaiparunning.com/insomniac/stunner/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2021/01/2020_Stunner_Logo.png?fit=1686%2C1920&ssl=1 |  |  | \n" .
					"Silverton Alpine Marathon | 2027-07-18 | July 18 | 8 Mile, Marathon, 50K | Silverton Alpine Loop | Silverton, CO | https://ultrasignup.com/register.aspx?did=129589 | https://www.aravaiparunning.com/silverton-alpine/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2020/08/2019_SilvertonAlpineMarathon_Logo_8MileLast.png?resize=300%2C300&ssl=1 |  |  | \n" .
					"Harding Hustle | 2027-07-18 | July 18 | 50K, 30K, 15K | Tucker Wildlife Sanctuary | Modjeska Canyon, CA | https://ultrasignup.com/register.aspx?did=129985 | https://www.aravaiparunning.com/harding-hustle/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2024/12/Harding-Hustle-Logo.webp?fit=1286%2C1077&ssl=1 |  |  | \n" .
					"Kendall Mountain Run | 2027-07-19 | July 19 | 12 Mile, 11K | Kendall Mountain | Silverton, CO | https://ultrasignup.com/register.aspx?did=129592 | https://www.aravaiparunning.com/kendall/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2022/05/2018_KendallMtnRun_Logo-01-01.png?fit=1364%2C1318&ssl=1 |  |  | \n" .
					"Grand Island | 2027-07-25 | July 25 | 50K, Marathon, Half-Marathon | Grand Island | Munising, MI | https://ultrasignup.com/register.aspx?did=129770 | https://www.aravaiparunning.com/great-lakes-endurance/grand-island/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/Grand-Island-Logo.png?fit=1201%2C1201&ssl=1 |  |  | \n" .
					"Baldface Scramble | 2027-07-25 | July 25 | 29KM, 14KM | White Mountain National Forest | Chatham, NH | https://ultrasignup.com/register.aspx?did=130611 | https://www.aravaiparunning.com/white-mountain-endurance/baldface-scramble/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2024/01/BALDFACE-SCRAMBLE-600x600-1.png?fit=600%2C600&ssl=1 |  |  | \n" .
					"Aspen Backcountry | 2027-08-01 | August 1 | 50K | Marathon | Half-Marathon | Rio Grande Park | Aspen. CO | https://ultrasignup.com/register.aspx?did=130516 | https://www.aravaiparunning.com/colorado/aspen-backcountry/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/AspenBackCountry-MArathon-2023-Horiz-Black-No-YEAR%402x.png?fit=1225%2C466&ssl=1 |  |  | \n" .
					"Tahqua Trail Runs | 2027-08-08 | August 8 | 25K | 10K | Tahquemenon Falls State Park | Paradise, MI | https://ultrasignup.com/register.aspx?did=129775 | https://www.aravaiparunning.com/great-lakes-endurance/tahqua/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/tahquatr.png?fit=547%2C546&ssl=1 |  |  | \n" .
					"Vertigo Night Runs | 2027-08-08 | August 8 | 52K | 31K | 20K | 10K | 6K | White Tank Mountain Regional Park | Waddell, AZ | https://ultrasignup.com/register.aspx?did=131006 | https://aravaiparunning.com/insomniac/vertigo | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2020/03/2020_Vertigo_Logo.png?resize=300%2C277&ssl=1 |  |  | \n" .
					"Jigger Johnson Ultras | 2027-08-14 | August 14-16 | 100 Mile, 50 Mile, 20 Mile | White Mountains | Waterville Valley, NH | https://ultrasignup.com/register.aspx?did=130607 | https://www.aravaiparunning.com/white-mountain-endurance/jigger/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2023/05/JJ-Ultras-color-gold-outline.png?fit=864%2C864&ssl=1 | 2027-08-16 |  | \n" .
					"Westminster | 2027-08-15 | August 15 | 50KM | 35KM | Half Marathon | 10 KM | 5KM | Westminster Lake | Westminster, CO | https://ultrasignup.com/register.aspx?did=121142 | https://www.aravaiparunning.com/westminster/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2025-WestminsterTrailRace-Logo-v3.png?fit=1216%2C1920&ssl=1 |  |  | \n" .
					"Jackrabbit Jubilee | 2027-08-22 | August 22 | 6 Hour, 12 Hour | Nardini Manor | Buckeye, AZ | https://ultrasignup.com/register.aspx?did=129946 | https://www.aravaiparunning.com/jackrabbit-jubilee/ | https://i0.wp.com/www.aravaiparunning.com/avr/wp-content/uploads/2018/08/JackrabbitJubilee_Logo_v2-01.png?w=810&ssl=1 |  |  |"
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
			'control_nav' => array(
				'races' => __( 'Races', 'aravaipa-elements' ),
			),
			'controls'    => array(
				array(
					'key'   => 'eyebrow',
					'type'  => 'text',
					'label' => __( 'Eyebrow', 'aravaipa-elements' ),
					'group' => 'races',
				),
				array(
					'key'   => 'heading',
					'type'  => 'text',
					'label' => __( 'Heading', 'aravaipa-elements' ),
					'group' => 'races',
				),
				array(
					'key'   => 'intro',
					'type'  => 'text',
					'label' => __( 'Intro line', 'aravaipa-elements' ),
					'group' => 'races',
				),
				array(
					'key'     => 'theme',
					'type'    => 'select',
					'label'   => __( 'Theme', 'aravaipa-elements' ),
					'group'   => 'races',
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
					'group'   => 'races',
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
					'group'       => 'races',
				),
				array(
					'key'   => 'cta_label',
					'type'  => 'text',
					'label' => __( 'Button label', 'aravaipa-elements' ),
					'group' => 'races',
				),
				array(
					'key'         => 'live_lead',
					'type'        => 'text',
					'label'       => __( 'Days before race day to show live results', 'aravaipa-elements' ),
					'description' => __( 'The live board carries the start list, with bib numbers, days before anyone runs, so it is worth reaching before race morning. A race also switches over the moment entries close, whichever happens first. 0 waits for race day.', 'aravaipa-elements' ),
					'group'       => 'races',
				),
				array(
					'key'   => 'all_label',
					'type'  => 'text',
					'label' => __( 'Footer link label', 'aravaipa-elements' ),
					'group' => 'races',
				),
				array(
					'key'   => 'all_url',
					'type'  => 'text',
					'label' => __( 'Footer link URL', 'aravaipa-elements' ),
					'group' => 'races',
				),
				array(
					'key'         => 'schema',
					'type'        => 'toggle',
					'label'       => __( 'Event structured data', 'aravaipa-elements' ),
					'description' => __( 'Emits schema.org Event JSON-LD for each race, which is what makes them eligible for Google event results and readable by AI answer engines. Turn off only if another plugin is already emitting Event schema for the same races, so they are not described twice.', 'aravaipa-elements' ),
					'group'       => 'races',
				),
				array(
					'key'         => 'rows',
					'type'        => 'textarea',
					'label'       => __( 'Races', 'aravaipa-elements' ),
					'description' => __( 'One per line: Name | ISO date (2026-08-29) | display date | distances | venue | city, ST | register URL | race page URL | image URL. The ISO date is required and drives both the sort order and the structured data. Display date is optional and is for ranges a single date cannot express, like "September 12-13".', 'aravaipa-elements' ),
					'group'       => 'races',
				),
			),
		),
		cs_partial_controls( 'omega' )
	);
}

/**
 * Normalize an ISO-ish date cell into Y-m-d, or '' if it is not a real date.
 *
 * Deliberately strict. A row whose date does not parse is dropped rather than
 * shown with a wrong date or emitted into schema as a malformed startDate,
 * which Google reports as an error against the whole page rather than
 * ignoring the one bad entry.
 *
 * @param string $cell
 * @return string
 */
function arv_upcoming_races_date( $cell ) {
	$cell = trim( $cell );

	if ( '' === $cell || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $cell ) ) {
		return '';
	}

	list( $y, $m, $d ) = array_map( 'intval', explode( '-', $cell ) );

	// checkdate rejects 2026-02-30 and friends, which the regex above happily
	// accepts. An impossible date reaching schema is the same class of error
	// as an unparseable one.
	return checkdate( $m, $d, $y ) ? $cell : '';
}

/**
 * Today's date in the site's own timezone.
 *
 * current_time() rather than gmdate(): a race in Arizona should drop off the
 * morning after it runs in Arizona, not at 5pm the day before because the
 * server thinks in UTC.
 *
 * Filterable so the "has this passed" logic can be tested against a fixed
 * date without waiting for the calendar.
 *
 * @return string Y-m-d
 */
function arv_upcoming_races_today() {
	$today = function_exists( 'current_time' ) ? current_time( 'Y-m-d' ) : gmdate( 'Y-m-d' );

	/**
	 * Filters the date used to decide which races have already happened.
	 *
	 * @param string $today Y-m-d in site time.
	 */
	return apply_filters( 'arv_upcoming_races_today', $today );
}

/**
 * UltraSignup's results page for a race, worked out from its register URL.
 *
 * Both carry the same "did". Deriving the results link means a row does not
 * have to carry a second URL that would only ever be the first one with a
 * different filename, and it cannot fall out of sync with it.
 *
 * Before a race has results, UltraSignup redirects this to the entrants list,
 * which during the race is the live field. That is what a runner's family
 * wants on race day, so one URL serves both the live and results phases.
 *
 * @param string $register_url
 * @return string '' when the URL is not an UltraSignup registration link.
 */
function arv_upcoming_races_results_url( $register_url ) {
	if ( ! preg_match( '#^https?://(?:www\.)?ultrasignup\.com/register\.aspx\?did=(\d+)#i', $register_url, $m ) ) {
		return '';
	}

	return 'https://ultrasignup.com/results_event.aspx?did=' . $m[1];
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
 * Which part of its life a race is in, and what to offer.
 *
 * Driven by dates rather than by asking UltraSignup whether entries are still
 * open, because UltraSignup does not say so in any way worth trusting: every
 * race page carries "Register Now" in its title whether or not it is
 * accepting entries, and Rock Hawk, which is open, contains the word
 * "Closed". Checked before relying on it. Dates are the signal we actually
 * have, and for most races registration closes at or near the start anyway.
 *
 * A row can carry its own live URL (a broadcast, a tracker); without one,
 * both live and results fall back to UltraSignup. It can also carry the date
 * entries close, which UltraSignup publishes for some races.
 *
 * @param array  $race
 * @param string $today Y-m-d in site time.
 * @param int    $lead  Days before race day that live results become the
 *                      offer, even though nobody has run yet.
 * @return array {phase, label, url}
 */
function arv_upcoming_races_action( $race, $today, $lead = 5 ) {
	$last    = '' !== $race['end'] ? $race['end'] : $race['iso'];
	$results = '' !== $race['live'] ? $race['live'] : arv_upcoming_races_results_url( $race['register'] );

	if ( $today < $race['iso'] ) {
		// The live board is populated well before the gun: it carries the
		// start list, with bib numbers, for anyone wanting to see who is
		// running. So the switch happens either once entries close, which is
		// the natural moment because there is nothing left to sell, or a few
		// days out for a race that never published a close date, whichever
		// comes first.
		$entries_closed = ( '' !== $race['closes'] && $today > $race['closes'] );
		$within_lead    = ( $lead > 0 && $today >= gmdate( 'Y-m-d', strtotime( $race['iso'] . ' 00:00:00 UTC' ) - ( $lead * DAY_IN_SECONDS ) ) );

		if ( '' !== $results && ( $entries_closed || $within_lead ) ) {
			return array(
				'phase' => 'live',
				'label' => __( 'Live Results', 'aravaipa-elements' ),
				'url'   => $results,
			);
		}
	}

	if ( $today < $race['iso'] ) {
		// UltraSignup publishes a registration close date on some races and
		// not others: 9 of the 69 in the current calendar, checked against
		// the live pages. When it is there, entries really do stop that day
		// and offering Register afterwards sends people to a dead end. When
		// it is not, the race keeps offering entries until race day, which is
		// what the other 60 do anyway.
		if ( '' !== $race['closes'] && $today > $race['closes'] ) {
			return array(
				'phase' => 'closed',
				'label' => __( 'Entries Closed', 'aravaipa-elements' ),
				'url'   => '',
			);
		}

		return array(
			'phase' => 'upcoming',
			'label' => __( 'Register', 'aravaipa-elements' ),
			'url'   => $race['register'],
		);
	}

	if ( $today <= $last ) {
		return array(
			'phase' => 'live',
			'label' => __( 'Live Results', 'aravaipa-elements' ),
			'url'   => $results,
		);
	}

	return array(
		'phase' => 'results',
		'label' => __( 'Results', 'aravaipa-elements' ),
		'url'   => $results,
	);
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
		$tail      = array_slice( $row, $count - 8 );
		$distances = implode( ' | ', array_slice( $row, 3, $count - 11 ) );
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
	);
}

/**
 * Render callback.
 *
 * @param array $data Element values.
 * @return string
 */
function arv_upcoming_races_render( $data ) {
	$rows = arv_parse_rows( isset( $data['rows'] ) ? $data['rows'] : '', 2 );

	if ( empty( $rows ) ) {
		return '';
	}

	$races = array();

	foreach ( $rows as $row ) {
		$race = arv_upcoming_races_parse_row( $row );

		// A race with no name or no usable date cannot be sorted, displayed
		// or described, so there is nothing useful to render for it.
		if ( null === $race ) {
			continue;
		}

		$races[] = $race;
	}

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
	$today = arv_upcoming_races_today();
	$races = array_values(
		array_filter(
			$races,
			function ( $race ) use ( $today ) {
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

		$cards .= '<div class="arv-races__card">';

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
		$cards .= '<time class="arv-races__date" datetime="' . esc_attr( $race['iso'] ) . '">' . esc_html( $display ) . '</time>';

		$title = esc_html( $race['name'] );
		if ( '' !== $card_url ) {
			$title = '<a class="arv-races__link" href="' . esc_url( $card_url ) . '">' . $title . '</a>';
		}
		$cards .= '<h3 class="arv-races__name">' . $title . '</h3>';

		if ( '' !== $race['distances'] ) {
			$cards .= '<p class="arv-races__distances">' . esc_html( $race['distances'] ) . '</p>';
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

		$cards .= '<div class="arv-races__actions">';
		if ( '' === $action['url'] && '' !== $action['label'] ) {
			// Nowhere to send anyone, but the slot still has something worth
			// saying. A span, not a disabled link: there is no destination,
			// so it should not be focusable or look clickable. Race Details
			// beside it becomes the only thing to press, which is correct.
			$cards .= '<span class="arv-races__cta arv-races__cta--' . esc_attr( $action['phase'] ) . '">' . esc_html( $action['label'] ) . '</span>';
		} elseif ( '' !== $action['url'] ) {
			// Both destinations are off-site (ultrasignup.com, or a tracker),
			// so this leaves the site. noopener because target=_blank without
			// it hands the opened page a live reference back to this window.
			$cards .= '<a class="arv-races__cta arv-races__cta--' . esc_attr( $action['phase'] ) . '" href="' . esc_url( $action['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $primary_label ) . '</a>';
		}
		if ( '' !== $card_url ) {
			// Always present, and always the secondary of the pair, so the
			// buttons keep the same shape whatever phase a race is in and the
			// eye is not re-learning the layout down the list.
			$cards .= '<a class="arv-races__details" href="' . esc_url( $card_url ) . '">' . esc_html( __( 'Race Details', 'aravaipa-elements' ) ) . '</a>';
		}
		$cards .= '</div>';

		$cards .= '</div></div>';

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
 * Build the schema.org Event array for one race.
 *
 * Only fields we actually have are included. An Event carrying a placeholder
 * location or an invented end date is worse than one carrying fewer fields:
 * Google validates what is there, so a wrong value is an error where a
 * missing optional value is just a missing optional value.
 *
 * @param array  $race
 * @param string $phase Where the race is in its life, so the offer can say
 *                      whether entries are actually available.
 * @return array
 */
function arv_upcoming_races_event_schema( $race, $phase = 'upcoming' ) {
	$event = array(
		'@type'               => 'SportsEvent',
		'name'                => $race['name'],
		'startDate'           => $race['iso'],
		// Every race here is a real race in a real place. Saying so
		// explicitly stops Google assuming the pandemic-era default of
		// "online", which it does when the attendance mode is unstated.
		'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
		'eventStatus'         => 'https://schema.org/EventScheduled',
		'organizer'           => array(
			'@type' => 'Organization',
			'name'  => 'Aravaipa Running',
			'url'   => 'https://www.aravaiparunning.com/',
		),
	);

	// Only when it differs: an endDate equal to startDate says nothing, and
	// schema is better off carrying fewer, truer fields.
	if ( '' !== $race['end'] && $race['end'] !== $race['iso'] ) {
		$event['endDate'] = $race['end'];
	}

	if ( '' !== $race['page'] ) {
		$event['url'] = $race['page'];
	}

	if ( '' !== $race['image'] ) {
		$event['image'] = $race['image'];
	}

	if ( '' !== $race['distances'] ) {
		$event['description'] = $race['name'] . ': ' . $race['distances']
			. ( '' !== $race['location'] ? ' in ' . $race['location'] : '' ) . '.';
	}

	$place = array( '@type' => 'Place' );
	// Venue if we have one, otherwise the town. schema.org's Place requires a
	// name, so a Place with only an address is not valid and is better left
	// off entirely.
	$place['name'] = '' !== $race['venue'] ? $race['venue'] : $race['location'];

	if ( '' !== $race['location'] ) {
		$parts = array_map( 'trim', explode( ',', $race['location'] ) );
		$addr  = array( '@type' => 'PostalAddress' );
		if ( count( $parts ) >= 2 ) {
			$addr['addressLocality'] = $parts[0];
			$addr['addressRegion']   = $parts[1];
		} else {
			// "Arizona" and the like: a region with no town, which is what
			// the series rows carry.
			$addr['addressRegion'] = $race['location'];
		}
		$addr['addressCountry'] = 'US';
		$place['address']       = $addr;
	}

	if ( '' !== $place['name'] ) {
		$event['location'] = $place;
	}

	// An offer only belongs on a race you can still enter. Carrying one for a
	// race that has already run would advertise a closed registration in
	// search results, which is worse than saying nothing about entries.
	if ( '' !== $race['register'] && in_array( $phase, array( 'upcoming', 'closed' ), true ) ) {
		$event['offers'] = array(
			'@type'        => 'Offer',
			'url'          => $race['register'],
			// No price: entry fees vary by distance and by how early you
			// enter, and a single wrong number in schema is worse than none.
			// availability carries the thing that actually matters, and has
			// to follow the phase: claiming InStock for a race whose entries
			// closed last week is a factual error in the markup, not a
			// cosmetic one.
			'availability' => ( 'closed' === $phase )
				? 'https://schema.org/SoldOut'
				: 'https://schema.org/InStock',
			'category'     => 'primary',
		);
	}

	return $event;
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
