<?php
/**
 * International phone input: country code picker (default +1 US) + number.
 *
 * Forms render phone_input('phone', $value). The picker posts "phone_cc" (ISO code)
 * and bootstrap merges it into $_POST['phone'] as "+<dial> <number>", so every
 * handler keeps reading $_POST['phone'] as before.
 */

function phone_countries(): array
{
    static $list = null;
    if ($list !== null) return $list;
    // ISO => [name, dial]. Most-used first, then alphabetical.
    $top = [
        'US' => ['United States', '1'], 'CA' => ['Canada', '1'], 'IN' => ['India', '91'], 'GB' => ['United Kingdom', '44'],
        'AE' => ['United Arab Emirates', '971'], 'MX' => ['Mexico', '52'], 'AU' => ['Australia', '61'],
    ];
    $rest = [
        'AF' => ['Afghanistan', '93'], 'AL' => ['Albania', '355'], 'DZ' => ['Algeria', '213'], 'AR' => ['Argentina', '54'],
        'AM' => ['Armenia', '374'], 'AT' => ['Austria', '43'], 'AZ' => ['Azerbaijan', '994'], 'BS' => ['Bahamas', '1'],
        'BH' => ['Bahrain', '973'], 'BD' => ['Bangladesh', '880'], 'BY' => ['Belarus', '375'], 'BE' => ['Belgium', '32'],
        'BZ' => ['Belize', '501'], 'BO' => ['Bolivia', '591'], 'BA' => ['Bosnia & Herzegovina', '387'], 'BR' => ['Brazil', '55'],
        'BG' => ['Bulgaria', '359'], 'KH' => ['Cambodia', '855'], 'CM' => ['Cameroon', '237'], 'CL' => ['Chile', '56'],
        'CN' => ['China', '86'], 'CO' => ['Colombia', '57'], 'CR' => ['Costa Rica', '506'], 'HR' => ['Croatia', '385'],
        'CY' => ['Cyprus', '357'], 'CZ' => ['Czech Republic', '420'], 'DK' => ['Denmark', '45'], 'DO' => ['Dominican Republic', '1'],
        'EC' => ['Ecuador', '593'], 'EG' => ['Egypt', '20'], 'SV' => ['El Salvador', '503'], 'EE' => ['Estonia', '372'],
        'ET' => ['Ethiopia', '251'], 'FJ' => ['Fiji', '679'], 'FI' => ['Finland', '358'], 'FR' => ['France', '33'],
        'GE' => ['Georgia', '995'], 'DE' => ['Germany', '49'], 'GH' => ['Ghana', '233'], 'GR' => ['Greece', '30'],
        'GT' => ['Guatemala', '502'], 'HN' => ['Honduras', '504'], 'HK' => ['Hong Kong', '852'], 'HU' => ['Hungary', '36'],
        'IS' => ['Iceland', '354'], 'ID' => ['Indonesia', '62'], 'IR' => ['Iran', '98'], 'IQ' => ['Iraq', '964'],
        'IE' => ['Ireland', '353'], 'IL' => ['Israel', '972'], 'IT' => ['Italy', '39'], 'JM' => ['Jamaica', '1'],
        'JP' => ['Japan', '81'], 'JO' => ['Jordan', '962'], 'KZ' => ['Kazakhstan', '7'], 'KE' => ['Kenya', '254'],
        'KW' => ['Kuwait', '965'], 'LA' => ['Laos', '856'], 'LV' => ['Latvia', '371'], 'LB' => ['Lebanon', '961'],
        'LT' => ['Lithuania', '370'], 'LU' => ['Luxembourg', '352'], 'MO' => ['Macau', '853'], 'MY' => ['Malaysia', '60'],
        'MV' => ['Maldives', '960'], 'MT' => ['Malta', '356'], 'MU' => ['Mauritius', '230'], 'MD' => ['Moldova', '373'],
        'MC' => ['Monaco', '377'], 'MN' => ['Mongolia', '976'], 'ME' => ['Montenegro', '382'], 'MA' => ['Morocco', '212'],
        'MM' => ['Myanmar', '95'], 'NP' => ['Nepal', '977'], 'NL' => ['Netherlands', '31'], 'NZ' => ['New Zealand', '64'],
        'NI' => ['Nicaragua', '505'], 'NG' => ['Nigeria', '234'], 'MK' => ['North Macedonia', '389'], 'NO' => ['Norway', '47'],
        'OM' => ['Oman', '968'], 'PK' => ['Pakistan', '92'], 'PA' => ['Panama', '507'], 'PY' => ['Paraguay', '595'],
        'PE' => ['Peru', '51'], 'PH' => ['Philippines', '63'], 'PL' => ['Poland', '48'], 'PT' => ['Portugal', '351'],
        'PR' => ['Puerto Rico', '1'], 'QA' => ['Qatar', '974'], 'RO' => ['Romania', '40'], 'RU' => ['Russia', '7'],
        'SA' => ['Saudi Arabia', '966'], 'RS' => ['Serbia', '381'], 'SG' => ['Singapore', '65'], 'SK' => ['Slovakia', '421'],
        'SI' => ['Slovenia', '386'], 'ZA' => ['South Africa', '27'], 'KR' => ['South Korea', '82'], 'ES' => ['Spain', '34'],
        'LK' => ['Sri Lanka', '94'], 'SE' => ['Sweden', '46'], 'CH' => ['Switzerland', '41'], 'TW' => ['Taiwan', '886'],
        'TZ' => ['Tanzania', '255'], 'TH' => ['Thailand', '66'], 'TT' => ['Trinidad & Tobago', '1'], 'TN' => ['Tunisia', '216'],
        'TR' => ['Turkey', '90'], 'UG' => ['Uganda', '256'], 'UA' => ['Ukraine', '380'], 'UY' => ['Uruguay', '598'],
        'UZ' => ['Uzbekistan', '998'], 'VE' => ['Venezuela', '58'], 'VN' => ['Vietnam', '84'], 'ZM' => ['Zambia', '260'],
        'ZW' => ['Zimbabwe', '263'],
    ];
    return $list = $top + $rest;
}

function phone_flag(string $iso): string
{
    $iso = strtoupper($iso);
    if (!preg_match('/^[A-Z]{2}$/', $iso)) return '';
    return mb_chr(0x1F1E6 + ord($iso[0]) - 65, 'UTF-8') . mb_chr(0x1F1E6 + ord($iso[1]) - 65, 'UTF-8');
}

function phone_default_iso(): string
{
    $dial = preg_replace('/\D/', '', (string)setting('default_country_code', '1')) ?: '1';
    foreach (phone_countries() as $iso => [$n, $d]) if ($d === $dial) return $iso;
    return 'US';
}

/** Split a stored phone ("+44 7911 123456" or legacy "2135550101") into [iso, national number]. */
function phone_split(?string $phone): array
{
    $phone = trim((string)$phone);
    if ($phone === '') return [phone_default_iso(), ''];
    if (strpos($phone, '+') === 0 || strpos($phone, '00') === 0) {
        $digits = preg_replace('/\D/', '', $phone);
        if (strpos($phone, '00') === 0) $digits = substr($digits, 2);
        $best = null;
        foreach (phone_countries() as $iso => [$n, $dial]) {
            if (strpos($digits, $dial) === 0 && ($best === null || strlen($dial) > strlen(phone_countries()[$best][1]))) {
                $best = $iso;
            }
        }
        // Prefer the default country (e.g. US over Canada) when several share the dial code
        $def = phone_default_iso();
        if ($best && phone_countries()[$best][1] === phone_countries()[$def][1]) $best = $def;
        if ($best) {
            $rest = trim(substr(preg_replace('/^\s*(\+|00)\s*/', '', $phone), 0));
            // remove the dial code from the start of the remaining text (keeps user spacing)
            $rest = preg_replace('/^' . preg_quote(phone_countries()[$best][1], '/') . '[\s\-.]*/', '', $rest);
            return [$best, $rest];
        }
        return [phone_default_iso(), $phone];
    }
    return [phone_default_iso(), $phone];
}

/** Combine picker + number into "+<dial> <number>". */
function phone_combine(string $iso, string $number): string
{
    $number = trim($number);
    if ($number === '') return '';
    // Number typed with its own international prefix wins
    if (strpos($number, '+') === 0) return '+' . preg_replace('/[^\d\s\-()]/', '', substr($number, 1));
    if (strpos($number, '00') === 0) return '+' . ltrim(preg_replace('/[^\d\s\-()]/', '', substr($number, 2)));
    $countries = phone_countries();
    $iso = strtoupper($iso);
    $dial = isset($countries[$iso]) ? $countries[$iso][1] : $countries[phone_default_iso()][1];
    $clean = preg_replace('/[^\d\s\-()]/', '', $number);
    $clean = preg_replace('/^0+/', '', trim($clean)); // drop national trunk prefix (e.g. UK 07… → 7…)
    return '+' . $dial . ' ' . $clean;
}

/** One-time upgrade: recompute client phone keys as full international numbers. */
function phone_upgrade_keys(): void
{
    if (setting('phone_key_version', '1') === '2') return;
    try {
        foreach (rows('SELECT id, phone FROM clients') as $c) {
            try { q('UPDATE clients SET phone_key = ? WHERE id = ?', [phone_key($c['phone']), $c['id']]); } catch (Throwable $e) {}
        }
        save_setting('phone_key_version', '2');
    } catch (Throwable $e) { /* not installed yet */ }
}

/** Called from bootstrap: merge every *_cc picker into its phone field. */
function phone_merge_post(): void
{
    foreach ($_POST as $k => $v) {
        if (substr($k, -3) !== '_cc' || !is_string($v)) continue;
        $base = substr($k, 0, -3);
        if (isset($_POST[$base]) && is_string($_POST[$base])) {
            $_POST[$base] = phone_combine($v, $_POST[$base]);
        }
    }
}

/**
 * Render the phone field.
 * $opts: id, required (bool), placeholder, autocomplete, class (extra input classes)
 */
function phone_input(string $name, ?string $value, array $opts = []): string
{
    [$iso, $num] = phone_split($value);
    $id = $opts['id'] ?? $name;
    $countries = phone_countries();
    $sel = '<select class="cc-select" name="' . e($name) . '_cc" aria-label="Country code" data-cc-select>';
    $i = 0;
    foreach ($countries as $code => [$cname, $dial]) {
        if ($i === 7) $sel .= '<option disabled>──────────</option>';
        $sel .= '<option value="' . $code . '" data-short="' . e(phone_flag($code) . ' +' . $dial) . '"' . ($code === $iso ? ' selected' : '') . '>'
            . e(phone_flag($code) . ' ' . $cname . ' (+' . $dial . ')') . '</option>';
        $i++;
    }
    $sel .= '</select>';
    return '<div class="input-group phone-group">'
        . '<span class="input-group-text cc-wrap"><span class="cc-label">' . e(phone_flag($iso) . ' +' . $countries[$iso][1]) . '</span><i class="bi bi-chevron-down ms-1 small"></i>' . $sel . '</span>'
        . '<input class="form-control ' . e($opts['class'] ?? '') . '" type="tel" inputmode="tel" id="' . e($id) . '" name="' . e($name) . '" value="' . e($num) . '"'
        . ' placeholder="' . e($opts['placeholder'] ?? '213 555 0123') . '" autocomplete="' . e($opts['autocomplete'] ?? 'tel-national') . '"'
        . (!empty($opts['required']) ? ' required' : '') . '>'
        . '</div>';
}
