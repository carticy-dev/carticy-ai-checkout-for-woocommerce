/**
 * Dates and times in the site's timezone and formats (Settings > General).
 *
 * WordPress's date package (`wp.date`) carries the site's timezone, date and
 * time formats and translated month names, so a screen shows dates the way
 * the rest of wp-admin does, never in the browser's own zone or locale.
 *
 * Imported from '@carticy/admin-ui/date' rather than the package index: it
 * needs the '@wordpress/date' external and the 'wp-date' script dependency,
 * which the rest of the package does not.
 */
import { dateI18n, getSettings, gmdateI18n, humanTimeDiff } from '@wordpress/date';

/** An ISO 8601 date and time that names its zone: 2026-11-21T09:18:00Z, 2026-11-21T10:18:00+01:00. */
const ZONED = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?(?:Z|[+-]\d{2}:?\d{2})$/i;

/** A calendar day, 2026-11-21: already a date in the site's terms, with no time to convert. */
const DAY = /^\d{4}-\d{2}-\d{2}$/;

const STYLES = [ 'date', 'datetime', 'time', 'relative' ];

/**
 * The moment a value names. A string without a zone is refused rather than
 * read in the browser's zone, which would be a guess.
 *
 * @param {Date|number|string} value A Date, milliseconds since the epoch, or a zoned ISO 8601 string.
 * @return {Date|null} The moment, or null when the value names none.
 */
function toMoment( value ) {
	const known = value instanceof Date || ( 'number' === typeof value && Number.isFinite( value ) ) || ( 'string' === typeof value && ZONED.test( value ) );
	const moment = known ? new Date( value ) : null;
	return moment && ! Number.isNaN( moment.getTime() ) ? moment : null;
}

/**
 * Format a moment or a calendar day for the screen.
 *
 * @param {Date|number|string|null} value A Date, milliseconds since the epoch, a zoned ISO 8601
 *                                        string, or a Y-m-d calendar day (with the 'date' style).
 *                                        null, undefined and '' mean there is no date.
 * @param {string} style 'datetime' (default), 'date' or 'time' in the site's formats and
 *                       timezone, or 'relative' ("2 minutes ago", "3 hours from now").
 * @return {string} The formatted text, or '' when there is no date.
 * @throws {RangeError} For an unknown style, a calendar day with a style other than 'date', or a
 *                      value that names no moment (such as a string without a zone).
 */
export function formatDate( value, style = 'datetime' ) {
	if ( ! STYLES.includes( style ) ) {
		throw new RangeError( `formatDate: unknown style "${ style }"; use ${ STYLES.join( ', ' ) }.` );
	}
	if ( null === value || undefined === value || '' === value ) {
		return '';
	}
	const day = 'string' === typeof value && DAY.test( value );
	if ( day && 'date' !== style ) {
		throw new RangeError( `formatDate: "${ value }" is a calendar day; format it with the 'date' style.` );
	}
	// A calendar day is read at midnight UTC and shown in UTC, so no zone moves it to another day.
	const moment = toMoment( day ? `${ value }T00:00:00Z` : value );
	if ( ! moment ) {
		throw new RangeError( `formatDate: ${ JSON.stringify( value ) } is not a Date, a timestamp in milliseconds, a Y-m-d day or an ISO 8601 date and time with its zone.` );
	}
	if ( day ) {
		return gmdateI18n( getSettings().formats.date, moment );
	}
	return 'relative' === style ? humanTimeDiff( moment ) : dateI18n( getSettings().formats[ style ], moment );
}
