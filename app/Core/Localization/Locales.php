<?php

namespace App\Core\Localization;

use App\Core\Settings\SettingsRepository;

/**
 * Storefront languages: the store's default language (the language product
 * and category fields are written in) plus any number of additional
 * languages with translations. Codes are lowercase ISO 639-1.
 */
final class Locales
{
    /** ISO 639-1 languages the store can enable. */
    public const AVAILABLE = [
        'aa' => 'Afar',
        'ab' => 'Abkhazian',
        'ae' => 'Avestan',
        'af' => 'Afrikaans',
        'ak' => 'Akan',
        'am' => 'አማርኛ',
        'an' => 'Aragonese',
        'ar' => 'العربية',
        'as' => 'অসমীয়া',
        'av' => 'Avaric',
        'ay' => 'Aymara',
        'az' => 'azərbaycan',
        'ba' => 'Bashkir',
        'be' => 'беларуская',
        'bg' => 'български',
        'bh' => 'Bhojpuri',
        'bi' => 'Bislama',
        'bm' => 'bamanakan',
        'bn' => 'বাংলা',
        'bo' => 'བོད་སྐད་',
        'br' => 'brezhoneg',
        'bs' => 'bosanski',
        'ca' => 'català',
        'ce' => 'нохчийн',
        'ch' => 'Chamorro',
        'co' => 'Corsican',
        'cr' => 'Cree',
        'cs' => 'Čeština',
        'cu' => 'Church Slavic',
        'cv' => 'чӑваш',
        'cy' => 'Cymraeg',
        'da' => 'Dansk',
        'de' => 'Deutsch',
        'dv' => 'Divehi',
        'dz' => 'རྫོང་ཁ',
        'ee' => 'eʋegbe',
        'el' => 'Ελληνικά',
        'en' => 'English',
        'eo' => 'Esperanto',
        'es' => 'Español',
        'et' => 'eesti',
        'eu' => 'euskara',
        'fa' => 'فارسی',
        'ff' => 'Pulaar',
        'fi' => 'Suomi',
        'fj' => 'Fijian',
        'fo' => 'føroyskt',
        'fr' => 'Français',
        'fy' => 'Frysk',
        'ga' => 'Gaeilge',
        'gd' => 'Gàidhlig',
        'gl' => 'galego',
        'gn' => 'Guarani',
        'gu' => 'ગુજરાતી',
        'gv' => 'Gaelg',
        'ha' => 'Hausa',
        'he' => 'עברית',
        'hi' => 'हिन्दी',
        'ho' => 'Hiri Motu',
        'hr' => 'hrvatski',
        'ht' => 'créole haïtien',
        'hu' => 'magyar',
        'hy' => 'հայերեն',
        'hz' => 'Herero',
        'ia' => 'interlingua',
        'id' => 'Indonesia',
        'ie' => 'Interlingue',
        'ig' => 'Igbo',
        'ii' => 'ꆈꌠꉙ',
        'ik' => 'Inupiaq',
        'io' => 'Ido',
        'is' => 'íslenska',
        'it' => 'Italiano',
        'iu' => 'Inuktitut',
        'ja' => '日本語',
        'jv' => 'Jawa',
        'ka' => 'ქართული',
        'kg' => 'Kongo',
        'ki' => 'Gikuyu',
        'kj' => 'Kuanyama',
        'kk' => 'қазақ тілі',
        'kl' => 'kalaallisut',
        'km' => 'ខ្មែរ',
        'kn' => 'ಕನ್ನಡ',
        'ko' => '한국어',
        'kr' => 'Kanuri',
        'ks' => 'کٲشُر',
        'ku' => 'kurdî (kurmancî)',
        'kv' => 'Komi',
        'kw' => 'kernewek',
        'ky' => 'кыргызча',
        'la' => 'Latin',
        'lb' => 'Lëtzebuergesch',
        'lg' => 'Luganda',
        'li' => 'Limburgish',
        'ln' => 'lingála',
        'lo' => 'ລາວ',
        'lt' => 'lietuvių',
        'lu' => 'Tshiluba',
        'lv' => 'latviešu',
        'mg' => 'Malagasy',
        'mh' => 'Marshallese',
        'mi' => 'Māori',
        'mk' => 'македонски',
        'ml' => 'മലയാളം',
        'mn' => 'монгол',
        'mr' => 'मराठी',
        'ms' => 'Melayu',
        'mt' => 'Malti',
        'my' => 'မြန်မာ',
        'na' => 'Nauru',
        'nb' => 'Norsk bokmål',
        'nd' => 'isiNdebele',
        'ne' => 'नेपाली',
        'ng' => 'Ndonga',
        'nl' => 'Nederlands',
        'nn' => 'norsk nynorsk',
        'no' => 'norsk',
        'nr' => 'South Ndebele',
        'nv' => 'Navajo',
        'ny' => 'Nyanja',
        'oc' => 'occitan',
        'oj' => 'Ojibwa',
        'om' => 'Oromoo',
        'or' => 'ଓଡ଼ିଆ',
        'os' => 'ирон',
        'pa' => 'ਪੰਜਾਬੀ',
        'pi' => 'Pali',
        'pl' => 'Polski',
        'ps' => 'پښتو',
        'pt' => 'Português',
        'qu' => 'Runasimi',
        'rm' => 'rumantsch',
        'rn' => 'Ikirundi',
        'ro' => 'Română',
        'ru' => 'русский',
        'rw' => 'Ikinyarwanda',
        'sa' => 'संस्कृत भाषा',
        'sc' => 'sardu',
        'sd' => 'سنڌي',
        'se' => 'davvisámegiella',
        'sg' => 'Sängö',
        'si' => 'සිංහල',
        'sk' => 'slovenčina',
        'sl' => 'slovenščina',
        'sm' => 'Samoan',
        'sn' => 'chiShona',
        'so' => 'Soomaali',
        'sq' => 'shqip',
        'sr' => 'српски',
        'ss' => 'Swati',
        'st' => 'Sesotho',
        'su' => 'Basa Sunda',
        'sv' => 'Svenska',
        'sw' => 'Kiswahili',
        'ta' => 'தமிழ்',
        'te' => 'తెలుగు',
        'tg' => 'тоҷикӣ',
        'th' => 'ไทย',
        'ti' => 'ትግርኛ',
        'tk' => 'türkmen dili',
        'tl' => 'Filipino / Tagalog',
        'tn' => 'Setswana',
        'to' => 'lea fakatonga',
        'tr' => 'Türkçe',
        'ts' => 'Tsonga',
        'tt' => 'татар',
        'tw' => 'Twi',
        'ty' => 'Tahitian',
        'ug' => 'ئۇيغۇرچە',
        'uk' => 'українська',
        'ur' => 'اردو',
        'uz' => 'o‘zbek',
        've' => 'Venda',
        'vi' => 'Tiếng Việt',
        'vo' => 'Volapük',
        'wa' => 'Walloon',
        'wo' => 'Wolof',
        'xh' => 'IsiXhosa',
        'yi' => 'ייִדיש',
        'yo' => 'Èdè Yorùbá',
        'za' => 'Vahcuengh',
        'zh' => '中文',
        'zu' => 'isiZulu',
    ];

    public static function direction(string $code): string
    {
        return in_array($code, ['ar', 'he', 'fa', 'ur', 'ps', 'sd', 'ug', 'yi', 'dv'], true) ? 'rtl' : 'ltr';
    }

    public const DEFAULT_KEY = 'store.default_locale';

    public const EXTRA_KEY = 'store.locales';

    public function __construct(private readonly SettingsRepository $settings) {}

    public function default(): string
    {
        $locale = (string) $this->settings->get(self::DEFAULT_KEY, config('app.locale', 'en'));

        return array_key_exists($locale, self::AVAILABLE) ? $locale : 'en';
    }

    /**
     * Additional languages (translations), never including the default.
     *
     * @return list<string>
     */
    public function additional(): array
    {
        $extra = array_values(array_filter(
            (array) ($this->settings->get(self::EXTRA_KEY) ?? []),
            fn ($code) => is_string($code) && array_key_exists($code, self::AVAILABLE) && $code !== $this->default(),
        ));

        return array_values(array_unique($extra));
    }

    /**
     * @return list<string> default first
     */
    public function enabled(): array
    {
        return [$this->default(), ...$this->additional()];
    }

    public function isEnabled(string $locale): bool
    {
        return in_array($locale, $this->enabled(), true);
    }

    /**
     * @param  list<string>  $additional
     */
    public function update(string $default, array $additional): void
    {
        $this->settings->set(self::DEFAULT_KEY, $default);
        $this->settings->set(self::EXTRA_KEY, array_values(array_unique(array_diff($additional, [$default]))));
    }

    /**
     * True when the current request shows a language other than the default.
     */
    public function translating(): bool
    {
        return app()->getLocale() !== $this->default();
    }

    public static function name(string $code): string
    {
        return self::AVAILABLE[$code] ?? $code;
    }

    /**
     * English and native names make the admin catalog searchable internationally.
     *
     * @param  list<string>  $codes
     * @return list<array{code: string, name: string}>
     */
    public static function adminOptions(array $codes): array
    {
        $options = array_map(function (string $code): array {
            $native = self::name($code);
            $display = $code === 'tl' ? 'Filipino / Tagalog' : \Locale::getDisplayLanguage($code, 'en');
            $english = is_string($display) ? $display : $native;

            return ['code' => $code, 'name' => strcasecmp($english, $native) === 0 ? $english : $english.' — '.$native];
        }, $codes);
        usort($options, fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));

        return $options;
    }

    /**
     * @param  list<string>  $codes
     * @return list<array{code: string, name: string}>
     */
    public static function options(array $codes): array
    {
        return array_map(fn (string $code) => ['code' => $code, 'name' => self::name($code)], $codes);
    }
}
