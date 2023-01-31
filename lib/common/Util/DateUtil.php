<?php

namespace CsrDelft\common\Util;

use DateTimeInterface;

final class DateUtil
{
	public static function reldate($datum)
	{
		if ($datum instanceof DateTimeInterface) {
			$moment = $datum->getTimestamp();
		} else {
			$moment = strtotime($datum);
		}

		if (date('Y-m-d') == date('Y-m-d', $moment)) {
			$return = 'vandaag om ' . strftime('%H:%M', $moment);
		} elseif (date('Y-m-d', $moment) == date('Y-m-d', strtotime('1 day ago'))) {
			$return = 'gisteren om ' . strftime('%H:%M', $moment);
		} else {
			$return = strftime('%A %e %B %Y om %H:%M', $moment); // php-bug: %e does not work on Windows
		}
		return '<time class="timeago" title="' .
			$return .
			'" datetime="' .
			date('Y-m-d\TG:i:sO', $moment) .
			'">' .
			$return .
			'</time>'; // ISO8601
	}

	/**
	 * @param string $date
	 * @param string $format
	 * @return true als huidige datum & tijd voorbij gegeven datum en tijd zijn
	 */
	public static function isDatumVoorbij(string $date, $format = 'Y-m-d H:i:s')
	{
		$date = date_create_immutable_from_format($format, $date);
		$now = date_create_immutable();
		return $now >= $date;
	}

	/**
	 * @param int $timestamp optional
	 *
	 * @return string current DateTime formatted Y-m-d H:i:s
	 */
	public static function getDateTime($timestamp = null)
	{
		if ($timestamp === null) {
			$timestamp = time();
		}
		return date('Y-m-d H:i:s', $timestamp);
	}
}
