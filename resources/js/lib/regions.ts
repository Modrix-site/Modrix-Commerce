/**
 * Sub-national regions (states / provinces / emirates) for the countries where
 * shoppers expect a picker rather than free text. Keyed by ISO 3166-1 alpha-2
 * country code; the value is the list of region names in the order to show.
 *
 * A country without an entry uses a free-text "State / province" field, so any
 * country still works. Matches the server's ShippingRegions, which today only
 * normalises the UAE emirates.
 */
export const REGIONS: Record<string, string[]> = {
    AE: [
        'Dubai',
        'Abu Dhabi',
        'Sharjah',
        'Ajman',
        'Umm Al Quwain',
        'Ras Al Khaimah',
        'Fujairah',
    ],
    US: [
        'Alabama',
        'Alaska',
        'Arizona',
        'Arkansas',
        'California',
        'Colorado',
        'Connecticut',
        'Delaware',
        'District of Columbia',
        'Florida',
        'Georgia',
        'Hawaii',
        'Idaho',
        'Illinois',
        'Indiana',
        'Iowa',
        'Kansas',
        'Kentucky',
        'Louisiana',
        'Maine',
        'Maryland',
        'Massachusetts',
        'Michigan',
        'Minnesota',
        'Mississippi',
        'Missouri',
        'Montana',
        'Nebraska',
        'Nevada',
        'New Hampshire',
        'New Jersey',
        'New Mexico',
        'New York',
        'North Carolina',
        'North Dakota',
        'Ohio',
        'Oklahoma',
        'Oregon',
        'Pennsylvania',
        'Rhode Island',
        'South Carolina',
        'South Dakota',
        'Tennessee',
        'Texas',
        'Utah',
        'Vermont',
        'Virginia',
        'Washington',
        'West Virginia',
        'Wisconsin',
        'Wyoming',
    ],
    CA: [
        'Alberta',
        'British Columbia',
        'Manitoba',
        'New Brunswick',
        'Newfoundland and Labrador',
        'Northwest Territories',
        'Nova Scotia',
        'Nunavut',
        'Ontario',
        'Prince Edward Island',
        'Quebec',
        'Saskatchewan',
        'Yukon',
    ],
    AU: [
        'Australian Capital Territory',
        'New South Wales',
        'Northern Territory',
        'Queensland',
        'South Australia',
        'Tasmania',
        'Victoria',
        'Western Australia',
    ],
};

/** The regions for a country, or an empty list when it uses free text. */
export function regionsFor(countryCode: string): string[] {
    return REGIONS[countryCode.toUpperCase()] ?? [];
}

/** Whether the region is required for a country (it has a known region list). */
export function regionRequired(countryCode: string): boolean {
    return regionsFor(countryCode).length > 0;
}
