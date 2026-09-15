<?php
namespace Lider\Search;

class BrandNormalizer
{
    private static array $map = [
        'hi-q'=>'SANGSIN','hi q'=>'SANGSIN','hiq'=>'SANGSIN','sangsin'=>'SANGSIN','sang sin'=>'SANGSIN',
        'sangsin brake'=>'SANGSIN','hi-q brake'=>'SANGSIN',
        'mann'=>'MANN-FILTER','mann-filter'=>'MANN-FILTER','mann filter'=>'MANN-FILTER','mannfilter'=>'MANN-FILTER',
        'lynx'=>'LYNXauto','lynxauto'=>'LYNXauto','lynx auto'=>'LYNXauto',
        'japanparts'=>'JAPANPARTS','japan parts'=>'JAPANPARTS',
        'nipparts'=>'NIPPARTS','nip parts'=>'NIPPARTS',
        'blue print'=>'BLUE PRINT','blueprint'=>'BLUE PRINT',
        'febi'=>'FEBI','febi bilstein'=>'FEBI',
        'magneti marelli'=>'MAGNETI MARELLI','magneti'=>'MAGNETI MARELLI','magneti-marelli'=>'MAGNETI MARELLI',
        'victor reinz'=>'VICTOR REINZ','victor'=>'VICTOR REINZ',
        'jp group'=>'JP GROUP','j.p. group'=>'JP GROUP','jpgroup'=>'JP GROUP',
        'borg & beck'=>'BORG & BECK','borg beck'=>'BORG & BECK','borg&beck'=>'BORG & BECK',
        'herth+buss'=>'HERTH+BUSS','herth buss'=>'HERTH+BUSS','herth und buss'=>'HERTH+BUSS',
        'quinton hazell'=>'QH','qh'=>'QH',
        'phc vale'=>'PHC VALE','phc'=>'PHC VALE',
        'hamburg technic'=>'HAMBURG TECHNIC','hans pries'=>'HANS PRIES',
        'first line'=>'FIRST LINE','van wezel'=>'VAN WEZEL',
        's ashika'=>'ASHIKA','ashika'=>'ASHIKA',
        'ruhr'=>'RUHR AUTO','ruhr auto'=>'RUHR AUTO',
        'triple q'=>'TRIPLE Q',
        'ga'=>'GATES','gates'=>'GATES',
        'miles'=>'MILES','ctr'=>'CTR','bosch'=>'BOSCH','febest'=>'FEBEST','filtron'=>'FILTRON',
        'trialli'=>'TRIALLI','luzar'=>'LUZAR','startvolt'=>'STARTVOLT','jikiu'=>'JIKIU','era'=>'ERA',
        'ngk'=>'NGK','sakura'=>'SAKURA','stellox'=>'STELLOX','nibk'=>'NiBK','tesla'=>'TESLA',
        'sidem'=>'SIDEM','motul'=>'MOTUL','denso'=>'DENSO','elring'=>'ELRING','trw'=>'TRW',
        'azumi'=>'Azumi','ajusa'=>'AJUSA','skf'=>'SKF','airline'=>'Airline','lpr'=>'LPR',
        'corteco'=>'CORTECO','dolz'=>'DOLZ','castrol'=>'CASTROL','norma'=>'NORMA','sasic'=>'SASIC',
        'ravenol'=>'RAVENOL','valeo'=>'VALEO','liqui moly'=>'LIQUI MOLY','gmb'=>'GMB','krafttech'=>'KRAFTTECH',
        'js asakashi'=>'JS ASAKASHI','brembo'=>'BREMBO','dayco'=>'DAYCO','kyb'=>'KYB','nk'=>'NK',
        'fenox'=>'FENOX','bosal'=>'BOSAL','frenkit'=>'FRENKIT','snr'=>'SNR','finwhale'=>'FINWHALE',
        'mobil'=>'MOBIL','nsk'=>'NSK','eneos'=>'ENEOS','tatsumi'=>'TATSUMI','swag'=>'SWAG',
        'mando'=>'MANDO','addinol'=>'ADDINOL','sachs'=>'SACHS','carville racing'=>'CARVILLE RACING','nty'=>'NTY',
        'mapco'=>'MAPCO','asva'=>'ASVA','vika'=>'VIKA','tyc'=>'TYC','meat&doria'=>'MEAT & DORIA',
        'meat & doria'=>'MEAT & DORIA','aslyx'=>'Aslyx','zzvf'=>'ZZVF','optimal'=>'OPTIMAL','fleetguard'=>'FLEETGUARD',
        'monroe'=>'MONROE','ert'=>'ERT','polcar'=>'POLCAR','caffaro'=>'CAFFARO','kroner'=>'KRONER',
        'gsp'=>'GSP','gkn'=>'GKN','borsehung'=>'Borsehung','bsg'=>'BSG','allied nippon'=>'ALLIED NIPPON',
        'remsa'=>'REMSA','avista'=>'AVISTA','metalcaucho'=>'Metalcaucho','zekkert'=>'ZEKKERT','zentparts'=>'ZentParts',
        'ufi'=>'UFI','abs'=>'A.B.S.','alkar'=>'ALKAR','champion'=>'CHAMPION','dpa'=>'DPA',
        'meyle'=>'MEYLE','ate'=>'ATE','iberis'=>'IBERIS','delphi'=>'DELPHI','ae'=>'AE',
        'ruville'=>'RUVILLE','elwis royal'=>'ELWIS ROYAL','fa1'=>'FA1','ossca'=>'OSSCA','sollo'=>'Sollo',
        'fae'=>'FAE','autofrenseinsa'=>'AUTOFREN SEINSA','speedmate'=>'SpeedMate','varta'=>'VARTA','bremi'=>'BREMI',
        'metelli'=>'METELLI','brisk'=>'BRISK','g.u.d'=>'GUD','cworks'=>'CWORKS','king'=>'KING',
        'mobiletron'=>'MOBILETRON','nissens'=>'NISSENS','textar'=>'TEXTAR','philips'=>'PHILIPS','kolbenschmidt'=>'KOLBENSCHMIDT',
        'teknorot'=>'TEKNOROT','pentosin'=>'Pentosin','egt'=>'EGT','formpart'=>'FORMPART','advics'=>'ADVICS',
        'repsol'=>'REPSOL','aisin'=>'AISIN','exide'=>'EXIDE','standard springs'=>'STANDARD SPRINGS','gebe'=>'GEBE',
        'hepu'=>'HEPU','wolf'=>'WOLF','cs germany'=>'CS Germany','alpine'=>'ALPINE','klokkerholm'=>'KLOKKERHOLM',
        'glyco'=>'GLYCO','freccia'=>'FRECCIA','icer'=>'ICER','narva'=>'NARVA','petronas'=>'PETRONAS',
        'ferodo'=>'FERODO','motrio'=>'MOTRIO','glaser'=>'GLASER','bando'=>'BANDO','birth'=>'BIRTH',
        'kamoka'=>'KAMOKA','delco remy'=>'Delco Remy',
        'hyundai/kia'=>'HYUNDAI/KIA','hyundai / kia'=>'HYUNDAI/KIA','hyundai'=>'HYUNDAI/KIA','kia'=>'HYUNDAI/KIA','mobis'=>'HYUNDAI/KIA',
        'general motors'=>'GM','generalmotors'=>'GM',
        'sb'=>'SB NAGAMOCHI',
    ];

    private static array $partials = [
        'SANGSIN'=>['hi-q','hi q','hiq','sangsin','sang sin'],
        'MANN-FILTER'=>['mann-filter','mann filter','mannfilter','mann'],
        'LYNXauto'=>['lynxauto','lynx auto','lynx'],
        'JAPANPARTS'=>['japanparts','japan parts'],
        'NIPPARTS'=>['nipparts','nip parts'],
        'BLUE PRINT'=>['blue print','blueprint'],
        'FEBI'=>['febi bilstein','febi'],
        'MAGNETI MARELLI'=>['magneti marelli','magneti-marelli','magneti'],
        'VICTOR REINZ'=>['victor reinz','victor'],
        'JP GROUP'=>['j.p. group','jp group','jpgroup'],
        'BORG & BECK'=>['borg & beck','borg beck','borg&beck'],
        'HERTH+BUSS'=>['herth+buss','herth buss','herth und buss'],
        'QH'=>['quinton hazell','qh'],
        'PHC VALE'=>['phc vale','phc'],
        'HAMBURG TECHNIC'=>['hamburg technic'],
        'HANS PRIES'=>['hans pries'],
        'FIRST LINE'=>['first line'],
        'VAN WEZEL'=>['van wezel'],
        'ASHIKA'=>['s ashika','ashika'],
        'RUHR AUTO'=>['ruhr auto','ruhr'],
        'TRIPLE Q'=>['triple q'],
        'GATES'=>['ga','gates'],
    ];

    private static function str($v): string
    {
        if ($v === null) {
            return '';
        }
        if (is_string($v)) {
            return $v;
        }
        if (is_int($v) || is_float($v)) {
            return (string)$v;
        }
        return trim((string)$v);
    }

    public static function map(string $brand): string
    {
        $original = trim(self::str($brand));
        if ($original === '') {
            return '';
        }
        $lower = mb_strtolower($original);
        if (isset(self::$map[$lower])) {
            return self::$map[$lower];
        }
        foreach (self::$partials as $canonical => $variants) {
            foreach ($variants as $variant) {
                if (mb_stripos($lower, $variant) !== false) {
                    return $canonical;
                }
            }
        }
        return $original;
    }

    public static function normalize($brand): string
    {
        return self::stripAll(self::map(self::str($brand)));
    }

    public static function normalizeArticle($article): string
    {
        $article = self::str($article);
        if ($article === '') {
            return '';
        }
        return mb_strtolower(preg_replace('/[\s\-\.\/\\\\_+]/u', '', $article) ?? '');
    }

    public static function stripAll($s): string
    {
        $s = self::str($s);
        if ($s === '') {
            return '';
        }
        return mb_strtolower(preg_replace('/[^a-zа-яё0-9]/iu', '', $s) ?? '');
    }

    public static function groupKey($brand, $article): string
    {
        return self::normalize($brand) . '|' . self::normalizeArticle($article);
    }

    public static function pickDisplayArticle(array $articles, $fallback = ''): string
    {
        $candidates = [];
        foreach ($articles as $a) {
            $a = trim(self::str($a));
            if ($a !== '') {
                $candidates[] = $a;
            }
        }
        $fallback = trim(self::str($fallback));
        if ($fallback !== '') {
            $candidates[] = $fallback;
        }
        if (!$candidates) {
            return $fallback;
        }

        usort($candidates, function ($a, $b) {
            $sa = substr_count($a, ' ');
            $sb = substr_count($b, ' ');
            if ($sa !== $sb) {
                return $sa <=> $sb;
            }
            if (mb_strlen($a) !== mb_strlen($b)) {
                return mb_strlen($a) <=> mb_strlen($b);
            }
            return strcmp($a, $b);
        });

        return $candidates[0];
    }

    public static function displayBrand($brand): string
    {
        $brand = self::str($brand);
        $mapped = self::map($brand);
        return $mapped !== '' ? $mapped : $brand;
    }
}
