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
        return mb_strtolower(preg_replace('/[\s\-\.\/\\\_]/u', '', $article) ?? '');
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
