<?php

namespace App\Support;

final class E164PhoneNumber
{
    private const COUNTRY_CALLING_CODES = [
        'AE' => '971', 'AF' => '93', 'AL' => '355', 'AM' => '374', 'AO' => '244', 'AR' => '54', 'AT' => '43',
        'AU' => '61', 'AZ' => '994', 'BA' => '387', 'BD' => '880', 'BE' => '32', 'BG' => '359', 'BH' => '973',
        'BR' => '55', 'BY' => '375', 'BZ' => '501', 'CA' => '1', 'CH' => '41', 'CI' => '225', 'CL' => '56', 'CM' => '237',
        'CN' => '86', 'CO' => '57', 'CR' => '506', 'CY' => '357', 'CZ' => '420', 'DE' => '49', 'DK' => '45',
        'DO' => '1', 'DZ' => '213', 'EC' => '593', 'EE' => '372', 'EG' => '20', 'ES' => '34', 'ET' => '251',
        'FI' => '358', 'FR' => '33', 'GB' => '44', 'GE' => '995', 'GH' => '233', 'GR' => '30', 'HK' => '852',
        'HR' => '385', 'HU' => '36', 'ID' => '62', 'IE' => '353', 'IL' => '972', 'IN' => '91', 'IQ' => '964',
        'IR' => '98', 'IS' => '354', 'IT' => '39', 'JO' => '962', 'JP' => '81', 'KE' => '254', 'KG' => '996',
        'KH' => '855', 'KR' => '82', 'KW' => '965', 'KZ' => '7', 'LB' => '961', 'LK' => '94', 'LT' => '370',
        'LU' => '352', 'LV' => '371', 'LY' => '218', 'MA' => '212', 'MD' => '373', 'ME' => '382', 'MG' => '261',
        'MK' => '389', 'MM' => '95', 'MN' => '976', 'MT' => '356', 'MU' => '230', 'MX' => '52', 'MY' => '60',
        'NG' => '234', 'NL' => '31', 'NO' => '47', 'NP' => '977', 'NZ' => '64', 'OM' => '968', 'PA' => '507',
        'PE' => '51', 'PH' => '63', 'PK' => '92', 'PL' => '48', 'PS' => '970', 'PT' => '351', 'QA' => '974',
        'RO' => '40', 'RS' => '381', 'RU' => '7', 'RW' => '250', 'SA' => '966', 'SE' => '46', 'SG' => '65',
        'SI' => '386', 'SK' => '421', 'SN' => '221', 'SV' => '503', 'TH' => '66', 'TJ' => '992', 'TN' => '216',
        'TR' => '90', 'TW' => '886', 'TZ' => '255', 'UA' => '380', 'UG' => '256', 'US' => '1', 'UY' => '598',
        'UZ' => '998', 'VE' => '58', 'VN' => '84', 'ZA' => '27', 'ZM' => '260', 'ZW' => '263',
    ];

    private const NATIONAL_PREFIX_COUNTRIES = [
        'AE', 'AR', 'AT', 'AU', 'BD', 'BE', 'BG', 'BR', 'CH', 'CL', 'CN', 'CO', 'CZ', 'DE', 'DK', 'EG', 'ES',
        'FI', 'FR', 'GB', 'GR', 'HU', 'ID', 'IE', 'IL', 'IN', 'JP', 'KE', 'KR', 'LK', 'MA', 'MY', 'NG', 'NL',
        'NO', 'NZ', 'OM', 'PE', 'PH', 'PK', 'PL', 'PT', 'RO', 'SA', 'SE', 'SG', 'TH', 'TN', 'TR', 'TW', 'UA',
        'ZA',
    ];

    public static function format(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (preg_match('/^55\d{2}[6-9]\d{7}$/', $digits) === 1) {
            $digits = substr($digits, 0, 4).'9'.substr($digits, 4);
        }

        return preg_match('/^[1-9]\d{7,14}$/', $digits) === 1 ? '+'.$digits : null;
    }

    public static function formatForCountry(string $phone, ?string $countryCode): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with(trim($phone), '+')) {
            return self::format($digits);
        }

        $country = strtoupper((string) $countryCode);

        if (str_starts_with($digits, '00')) {
            return self::format(substr($digits, 2));
        }

        if (in_array($country, ['US', 'CA'], true) && str_starts_with($digits, '011')) {
            return self::format(substr($digits, 3));
        }

        $callingCode = self::COUNTRY_CALLING_CODES[$country] ?? null;

        if ($callingCode === null) {
            return self::format($digits);
        }

        if (str_starts_with($digits, $callingCode) && strlen($digits) >= strlen($callingCode) + 7) {
            return self::format($digits);
        }

        if ($country === 'BR' && preg_match('/^0\d{2}([1-9]\d{9,10})$/', $digits, $matches) === 1) {
            $digits = $matches[1];
        } elseif (in_array($country, self::NATIONAL_PREFIX_COUNTRIES, true) && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        } elseif (in_array($country, ['RU', 'KZ'], true) && preg_match('/^8\d{10}$/', $digits) === 1) {
            $digits = substr($digits, 1);
        }

        if ($country === 'BR' && preg_match('/\A\d{10,11}\z/', $digits) !== 1) {
            return null;
        }

        return self::format($callingCode.$digits);
    }

    public static function recipient(string $phone): string
    {
        return ltrim(self::format($phone) ?? $phone, '+');
    }
}
