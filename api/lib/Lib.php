<?php
/**
 * @author    Saman Esmaeil
 * @link      http://github.com/teal33t/jyotish-api for the canonical source repository
 * @license   GNU General Public License version 3 or later
 */


namespace Jyotish;

use Jyotish\Base\Data;
use Jyotish\Base\Locality;
use Jyotish\Base\Analysis;
use Jyotish\Ganita\Method\Swetest;
use Jyotish\Dasha\Dasha;
use Jyotish\Panchanga\AngaDefiner;
use Jyotish\Graha\Lagna;
use Jyotish\Yoga\Yoga;
use Jyotish\Bala\AshtakaVarga;
use Jyotish\Bala\GrahaBala;
use Jyotish\Bala\RashiBala;
use Jyotish\Graha\Graha;
use Carbon\Carbon;

use \Datetime;
use \DateTimeZone;
use \DateInterval;

use Jyotish\Ganita\Astro;
use Jyotish\Ganita\Ayanamsha;
use Jyotish\Muhurta\Hora;

include __DIR__ . "/config.php";


class Lib
{

    public ?array $grahas = null;
    public ?array $lagnas = null;

    public function __construct(){}

    
    /**
     * Calculate astrological chart based on provided parameters
     * 
     * @param array $params Chart calculation parameters
     * @return array Calculated chart data
     */
    public function calculator(array $params = []): array
    {
        $latitude = $params['latitude'] ?? null;
        $longitude = $params['longitude'] ?? null;
        $year = $params['year'] ?? null;
        $month = $params['month'] ?? null;
        $day = $params['day'] ?? null;
        $hour = $params['hour'] ?? null;
        $min = $params['min'] ?? null;
        $sec = $params['sec'] ?? null;
        $time_zone = $params['time_zone'] ?? 'Asia/Tehran';
        $dst_hour = $params['dst_hour'] ?? 0;
        $dst_min = $params['dst_min'] ?? 0;
        $nesting = $params['nesting'] ?? 0;
        $varga = $params['varga'] ?? ["D1"];
        $infolevel = $params['infolevel'] ?? [];
        
        return $this->calculateChart(
            $latitude,
            $longitude,
            $year,
            $month,
            $day,
            $hour,
            $min,
            $sec,
            $time_zone,
            $dst_hour,
            $dst_min,
            $varga,
            $nesting,
            $infolevel
        );
    }

    public function calculateNow($latitude = '35.708309', $longitude = '51.380730', $time_zone="+03:30")
    {
        // $tz = $this->getNearestTimezone($latitude, $longitude);
        $now = new DateTime('now', new DateTimeZone($time_zone));
        $year = $now->format('Y');
        $month = $now->format('m');
        $day = $now->format('d');
        $hour = $now->format('H');
        $min = $now->format('i');
        $sec = $now->format('s');
        $dst_hour = 0;
        $dst_min = 0;
        $nesting = 2;
        $varga = ["D1", "D9"];
        $infolevel = [];

        return $this->calculateChart(
            $latitude,
            $longitude,
            $year,
            $month,
            $day,
            $hour,
            $min,
            $sec,
            $time_zone,
            $dst_hour,
            $dst_min,
            $varga,
            $nesting,
            $infolevel
        );
    }

    public function getNearestTimezone($cur_lat, $cur_long, $country_code = null)
    {
        static $locationCache = [];
    
        // Handle specific case for Iran
        if (strtolower($country_code) === 'ir') {
            return ['Asia/Tehran', '+03:30'];
        }
    
        // Fetch timezone identifiers based on country code
        $timezone_ids = $country_code
            ? DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, strtoupper($country_code))
            : DateTimeZone::listIdentifiers();
    
        if (empty($timezone_ids)) {
            return null;
        }
    
        // Single timezone case
        if (count($timezone_ids) === 1) {
            $time_zone = $timezone_ids[0];
        } else {
            // Precompute trigonometric values for current latitude
            $rad_cur_lat = deg2rad($cur_lat);
            $rad_cur_long = deg2rad($cur_long);
            $sin_cur_lat = sin($rad_cur_lat);
            $cos_cur_lat = cos($rad_cur_lat);
    
            $max_distance = -INF;
            $time_zone = null;
    
            foreach ($timezone_ids as $timezone_id) {
                // Cache timezone locations to avoid repeated lookups
                if (!isset($locationCache[$timezone_id])) {
                    $timezone = new DateTimeZone($timezone_id);
                    $location = $timezone->getLocation();
                    if (!$location) {
                        continue;
                    }
                    $locationCache[$timezone_id] = [
                        'lat' => $location['latitude'],
                        'long' => $location['longitude'],
                    ];
                }
    
                $tz_lat = $locationCache[$timezone_id]['lat'];
                $tz_long = $locationCache[$timezone_id]['long'];
    
                // Convert timezone coordinates to radians
                $tz_lat_rad = deg2rad($tz_lat);
                $tz_long_rad = deg2rad($tz_long);
    
                // Calculate angle components
                $theta = $rad_cur_long - $tz_long_rad;
                $sin_tz_lat = sin($tz_lat_rad);
                $cos_tz_lat = cos($tz_lat_rad);
                $cos_theta = cos($theta);
    
                // Compute dot product (cosine of the angle)
                $distance = $sin_cur_lat * $sin_tz_lat + $cos_cur_lat * $cos_tz_lat * $cos_theta;
                $distance = max(-1, min(1, $distance)); // Clamp to avoid NaN
    
                // Track maximum dot product (closest distance)
                if ($distance > $max_distance) {
                    $max_distance = $distance;
                    $time_zone = $timezone_id;
                }
            }
        }
    
        if (!$time_zone) {
            return null;
        }
    
        // Calculate formatted offset
        $tz = new DateTimeZone($time_zone);
        $date = new DateTime('now', $tz);
        $offset_seconds = $tz->getOffset($date);
    
        $sign = $offset_seconds >= 0 ? '+' : '-';
        $offset_seconds = abs($offset_seconds);
        $hours = (int) ($offset_seconds / 3600);
        $minutes = (int) (($offset_seconds % 3600) / 60);
        $formatted = sprintf('%s%02d:%02d', $sign, $hours, $minutes);
    
        return [$time_zone, $formatted];
    }

    /*
     * $infolevel = ["basic", "ashtakavarga", "grahabala", "rashibala", "yogas", "panchanga", "transit"]
     *
     */
    public function calculateChart(
        $latitude,
        $longitude,
        $year,
        $month,
        $day,
        $hour,
        $min,
        $sec,
        $time_zone = '+03:30',
        $dst_hour = 0,
        $dst_min = 0,
        $vargas = [
            "D1",
            "D2",
            "D3",
            "D4",
            "D7",
            "D9",
            "D10",
            "D12",
            "D16",
            "D20",
            "D24",
            "D27",
            "D30",
            "D40",
            "D45",
            "D60",
        ],
        $nesting = 4,
        array $infolevel = ["basic", "ashtakavarga", "grahabala", "rashibala", "yogas", "panchanga", "transit"]
    ) {

        $locality = new Locality([
            'longitude' => $longitude,
            'latitude' => $latitude,
            'altitude' => 0
        ]);

        # TODO: getNearestTimezone disabled, should rewrite with an accurate method in future
        # From now user should add $timezone from -12:00 to +12:00
        // $tz = $this->getNearestTimezone($latitude, $longitude);

        # format datetime for DateTime Object
        $datetime = sprintf("%s-%s-%s %s:%s:%s%s", $year, $month, $day, $hour, $min, $sec, $time_zone);
        $date = new DateTime($datetime);

        # perform DST offest
        $date->modify(sprintf("-%s hours", $dst_hour));
        $date->modify(sprintf("-%s minutes", $dst_min));

        # setup ephemeris and calculations
        $ganita = new Swetest(["swetest" => SWETEST_PATH]);
        $data = new Data($date, $locality, $ganita);
        $data->calcVargaData($vargas);
        $data->calcParams();
        if (in_array('panchanga', $infolevel)) {
            $data->calcPanchanga();
            $data->calcRising();
            $data->calcHora();
            $data->calcHora(Hora::TYPE_YAMA);
        }
        
        $analysis = new Analysis($data);
        $vargaData = $analysis->getVargaData('D1');


        if (in_array('ashtakavarga', $infolevel)) {
            // AshtakaVarga calculation
            $ashtakaVarga = new AshtakaVarga($data);
            // $vargaData['ashtakavarga'] = $ashtakaVarga->getSarvAshtakavarga(true);
            $vargaData['ashtakavarga'] = $ashtakaVarga->getBhinnAshtakavarga();
        }

        if (in_array('ayanamsa', $infolevel)) {
            $vargaData['ayanamsa'] = Ayanamsha::getAyanamsha();
        }

        if (in_array('grahabala', $infolevel)) {
            // GrahaBala calculation
            $grahaBala = new GrahaBala($data);
            $vargaData['grahabala'] = $grahaBala->getBala();

        }
        if (in_array('rashibala', $infolevel)) {
            // RashiBala calculation
            $rashiBala = new RashiBala($data);
            $vargaData['rashibala'] = $rashiBala->getBala();
        }


        $angaDefiner = new AngaDefiner($data);

        foreach ($vargaData['graha'] as $grahaKey => $value) {
            $nakshatra = $angaDefiner->getNakshatra(false, false, $grahaKey);
            $vargaData['graha'][$grahaKey]['nakshatra'] = $nakshatra;


            if (in_array('basic', $infolevel)) {
                $Graha = Graha::getInstance($grahaKey)->setEnvironment($data);
                $vargaData['graha'][$grahaKey]['astangata'] = $Graha->isAstangata(); // combustion
                $vargaData['graha'][$grahaKey]['rashiAvastha'] = $Graha->getRashiAvastha(); // dignity
                $vargaData['graha'][$grahaKey]['vargottama'] = $Graha->isVargottama(); // Vargottama
                $vargaData['graha'][$grahaKey]['yuddha'] = $Graha->isYuddha(); // graha is in planetary war
            }


            if (in_array('panchanga', $infolevel)) {
                $Graha = Graha::getInstance($grahaKey)->setEnvironment($data);
                $vargaData['graha'][$grahaKey]['astangata'] = $Graha->isAstangata(); // combustion
                $vargaData['graha'][$grahaKey]['rashiAvastha'] = $Graha->getRashiAvastha(); // dignity
                $vargaData['graha'][$grahaKey]['vargottama'] = $Graha->isVargottama(); // Vargottama
                $vargaData['graha'][$grahaKey]['yuddha'] = $Graha->isYuddha(); // graha is in planetary war
                $vargaData['graha'][$grahaKey]['gocharastha'] = $Graha->isGocharastha(); // gocharastha
                $vargaData['graha'][$grahaKey]['bhavaCharacter'] = $Graha->getBhavaCharacter(); // Bhava Character
                $vargaData['graha'][$grahaKey]['tempRelation'] = $Graha->getTempRelation(); // Get tatkalika (temporary) relations
                $vargaData['graha'][$grahaKey]['relation'] = $Graha->getRelation(); // Get summary relations
                $vargaData['graha'][$grahaKey]['yogakaraka'] = $Graha->isYogakaraka(); // yogakaraka
                $vargaData['graha'][$grahaKey]['mrityu'] = $Graha->isMrityu(); // graha is in mrityu bhaga
                $vargaData['graha'][$grahaKey]['pushkaraNavamsha'] = $Graha->isPushkara(Graha::PUSHKARA_NAVAMSHA); // graha is in pushkara navamsha
                $vargaData['graha'][$grahaKey]['pushkaraBhaga'] = $Graha->isPushkara(Graha::PUSHKARA_BHAGA); // graha is in pushkara bhaga
                $vargaData['graha'][$grahaKey]['avastha'] = $Graha->getAvastha(); // Get avastha of graha
                $vargaData['graha'][$grahaKey]['dispositor'] = $Graha->getDispositor(); // Get ruler of the bhava, where graha is positioned
            }
        }

        $nakshatra = $angaDefiner->getNakshatra(false, false, Lagna::KEY_LG);
        $vargaData['lagna'][Lagna::KEY_LG]['nakshatra'] = $nakshatra;

        $data->calcDasha(Dasha::TYPE_VIMSHOTTARI, null, ['nesting' => $nesting]);
        $dasha = $data->getData();


        if (in_array('panchanga', $infolevel)) {
            $vargaData['panchanga'] = $dasha['panchanga'];
        }

        $vargaData['dasha'] = $dasha['dasha']['vimshottari'];

        if (in_array('yogas', $infolevel)) {
            // Yoga calculations
            $data->calcYoga([
                Yoga::TYPE_DHANA,
                Yoga::TYPE_MAHAPURUSHA,
                Yoga::TYPE_NABHASHA,
                Yoga::TYPE_PARIVARTHANA,
                Yoga::TYPE_RAJA,
                Yoga::TYPE_SANNYASA,
                // Yoga::INTERPLAY_PARIVARTHANA,
                // Yoga::INTERPLAY_CONJUNCT,
                // Yoga::INTERPLAY_ASPECT
            ]);
            $yogas = $data->getData(['yoga']);
            $vargaData['yogas'] = $yogas;
        }
        $graha = $vargaData['graha'];
        $bhava = $vargaData['bhava'];

        # merge garaha and bhava
        foreach ($bhava as $bhava_key => $bhava_value) {
            $rashi = $bhava_value['rashi'];
            $bhava_grahas = [];

            foreach ($graha as $graha_key => $graha_value) {
                if ($graha_value['rashi'] == $rashi) {
                    $bhava_grahas[$graha_key] = $graha_value;
                }
            }

            $vargaData['houses'][$bhava_key]['graha'] = $bhava_grahas;
        }
        return $vargaData;
    }
}
