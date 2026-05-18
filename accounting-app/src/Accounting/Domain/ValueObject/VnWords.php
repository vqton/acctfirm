<?php
namespace Accounting\Domain\ValueObject;

class VnWords
{
    private static array $digits = ['không', 'một', 'hai', 'ba', 'bốn', 'năm', 'sáu', 'bảy', 'tám', 'chín'];
    private static array $units = ['', 'nghìn', 'triệu', 'tỷ'];

    public static function toWords(float $amount): string
    {
        if ($amount == 0) return 'Không đồng';

        $negative = $amount < 0;
        $amount = abs($amount);
        $integer = (int)floor($amount);
        $fraction = (int)round(($amount - $integer) * 100);

        $result = self::readInteger($integer);
        if ($fraction > 0) {
            $result .= ' phẩy ' . self::readInteger($fraction);
        }
        $result .= ' đồng';

        $result = preg_replace('/\s+/', ' ', trim($result));

        if ($negative) {
            $result = 'Âm ' . lcfirst($result);
        }

        return ucfirst($result);
    }

    private static function readInteger(int $num): string
    {
        if ($num === 0) return 'không';

        $groups = [];
        while ($num > 0) {
            $groups[] = $num % 1000;
            $num = intdiv($num, 1000);
        }

        $parts = [];
        $seenNonZero = false;
        foreach (array_reverse($groups) as $i => $g) {
            $idx = count($groups) - 1 - $i;
            $isUnits = $idx === 0;

            if ($g === 0) {
                if ($seenNonZero && !$isUnits) {
                    foreach (array_slice($groups, 0, $idx) as $lower) {
                        if ($lower > 0) {
                            $parts[] = 'không trăm';
                            break;
                        }
                    }
                }
                continue;
            }

            $str = '';
            if ($seenNonZero && $g < 100) {
                $str .= 'không trăm ';
            }

            $h = intdiv($g, 100);
            $t = intdiv($g % 100, 10);
            $o = $g % 10;

            if ($h > 0) {
                $str .= self::$digits[$h] . ' trăm';
            }
            if ($h > 0 && ($t > 0 || $o > 0)) $str .= ' ';

            if ($t === 0) {
                if ($o > 0 && ($h > 0 || $seenNonZero)) {
                    $str .= 'linh ';
                }
            } elseif ($t === 1) {
                $str .= 'mười';
                if ($o > 0) $str .= ' ';
            } else {
                $str .= self::$digits[$t] . ' mươi';
                if ($o > 0) $str .= ' ';
            }

            if ($o > 0) {
                if ($t === 0) {
                    $str .= self::$digits[$o];
                } elseif ($t === 1) {
                    $str .= ($o === 5) ? 'lăm' : self::$digits[$o];
                } else {
                    if ($o === 1) { $str .= 'mốt'; }
                    elseif ($o === 4) { $str .= 'tư'; }
                    elseif ($o === 5) { $str .= 'lăm'; }
                    else { $str .= self::$digits[$o]; }
                }
            }

            $unit = self::$units[$idx] ?? '';
            $str .= $unit ? ' ' . $unit : '';
            $parts[] = trim($str);
            $seenNonZero = true;
        }

        return implode(' ', $parts);
    }
}
