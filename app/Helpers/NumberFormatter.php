<?php

namespace App\Helpers;

class NumberFormatter {
    public static function shortMoney($number) {
        $absNumber = abs($number);
        
        if ($absNumber >= 1000000000000) {
            return number_format($number / 1000000000000, 2) . 't';
        }
        
        if ($absNumber >= 1000000000) {
            return number_format($number / 1000000000, 2) . 'm';
        }
        
        if ($absNumber >= 1000000) {
            return number_format($number / 1000000, 2) . 'jt';
        }
        
        return number_format($number);
    }
}