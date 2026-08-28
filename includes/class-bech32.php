<?php
namespace MvxNftGater\Utils;

use Exception;

class Bech32 {
    private const GENERATOR = [0x3b6a57b2, 0x26508e6d, 0x1ea119fa, 0x3d4233dd, 0x2a1462b3];
    private const CHARSET = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';
    private const CHARKEY_KEY = [
        -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,
        -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,
        -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1, -1,
        15, -1, 10, 17, 21, 20, 26, 30,  7,  5, -1, -1, -1, -1, -1, -1,
        -1, 29, -1, 24, 13, 25,  9,  8, 23, -1, 18, 22, 31, 27, 19, -1,
        1,  0,  3, 16, 11, 28, 12, 14,  6,  4,  2, -1, -1, -1, -1, -1,
        -1, 29, -1, 24, 13, 25,  9,  8, 23, -1, 18, 22, 31, 27, 19, -1,
        1,  0,  3, 16, 11, 28, 12, 14,  6,  4,  2, -1, -1, -1, -1, -1
    ];

    public static function polyMod(array $values, $numValues): int {
        $chk = 1;
        for ($i = 0; $i < $numValues; $i++) {
            $top = $chk >> 25;
            $chk = ($chk & 0x1ffffff) << 5 ^ $values[$i];
            for ($j = 0; $j < 5; $j++) {
                $value = (($top >> $j) & 1) ? self::GENERATOR[$j] : 0;
                $chk ^= $value;
            }
        }
        return $chk;
    }

    public static function hrpExpand($hrp, $hrpLen): array {
        $expand1 = [];
        $expand2 = [];
        for ($i = 0; $i < $hrpLen; $i++) {
            $o = \ord($hrp[$i]);
            $expand1[] = $o >> 5;
            $expand2[] = $o & 31;
        }
        return \array_merge($expand1, [0], $expand2);
    }

    public static function convertBits(array $data, $inLen, $fromBits, $toBits, $pad = true): array {
        $acc = 0;
        $bits = 0;
        $ret = [];
        $maxv = (1 << $toBits) - 1;
        $maxacc = (1 << ($fromBits + $toBits - 1)) - 1;

        for ($i = 0; $i < $inLen; $i++) {
            $value = $data[$i];
            if ($value < 0 || $value >> $fromBits) {
                throw new Exception('Invalid value for convert bits');
            }
            $acc = (($acc << $fromBits) | $value) & $maxacc;
            $bits += $fromBits;
            while ($bits >= $toBits) {
                $bits -= $toBits;
                $ret[] = (($acc >> $bits) & $maxv);
            }
        }

        if ($pad) {
            if ($bits) {
                $ret[] = ($acc << $toBits - $bits) & $maxv;
            }
        } else if ($bits >= $fromBits || ((($acc << ($toBits - $bits))) & $maxv)) {
            throw new Exception('Invalid data');
        }
        return $ret;
    }

    public static function decode(string $sBech): array {
        $length = \strlen($sBech);
        if ($length < 8) {
            throw new Exception("Bech32 string is too short");
        }
        if ($length > 90) {
            throw new Exception('Bech32 string cannot exceed 90 characters in length');
        }

        $chars = array_values(unpack('C*', $sBech));
        $haveUpper = false;
        $haveLower = false;
        $positionOne = -1;

        for ($i = 0; $i < $length; $i++) {
            $x = $chars[$i];
            if ($x < 33 || $x > 126) {
                throw new Exception('Out of range character in bech32 string');
            }
            if ($x >= 0x61 && $x <= 0x7a) {
                $haveLower = true;
            }
            if ($x >= 0x41 && $x <= 0x5a) {
                $haveUpper = true;
                $x = $chars[$i] = $x + 0x20;
            }
            if ($x === 0x31) {
                $positionOne = $i;
            }
        }

        if ($haveUpper && $haveLower) {
            throw new Exception('Data contains mixture of higher/lower case characters');
        }
        if ($positionOne === -1) {
            throw new Exception("Missing separator character");
        }
        if ($positionOne < 1) {
            throw new Exception("Empty HRP");
        }
        if (($positionOne + 7) > $length) {
            throw new Exception('Too short checksum');
        }

        $hrp = \pack("C*", ...\array_slice($chars, 0, $positionOne));

        $data = [];
        for ($i = $positionOne + 1; $i < $length; $i++) {
            $data[] = ($chars[$i] & 0x80) ? -1 : self::CHARKEY_KEY[$chars[$i]];
        }

        // Verify checksum
        $expandHrp = self::hrpExpand($hrp, \strlen($hrp));
        $r = \array_merge($expandHrp, $data);
        $poly = self::polyMod($r, \count($r));
        if ($poly !== 1) {
            throw new Exception('Invalid bech32 checksum');
        }

        return [$hrp, array_slice($data, 0, -6)];
    }

    public static function decodeToHex(string $address): string {
        list($hrp, $dataChars) = self::decode($address);
        if ($hrp !== 'erd') {
            throw new Exception('Only MultiversX erd addresses are supported');
        }
        $bytes = self::convertBits($dataChars, count($dataChars), 5, 8, false);
        $hex = '';
        foreach ($bytes as $byte) {
            $hex .= str_pad(dechex($byte), 2, '0', STR_PAD_LEFT);
        }
        return $hex;
    }
}
