<?php

namespace SMW\DataValues\Time;

use DateTime;
use DateTimeZone;
use DateInterval;
use RuntimeException;
use User;

/**
 * @license GNU GPL v2+
 * @since 3.0
 *
 * @author mwjames
 */
class LocalTime {

	/**
	 * @see Language::userAdjust
	 *
	 * Language::userAdjust cannot be used as entirely relies on the timestamp
	 * premises making < 1970 return invalid results hence we copy the relevant
	 * part on work with the DateInterval instead.
	 *
	 * @since 3.0
	 *
	 * @param DateTime $dateTime
	 * @param User|null $user
	 *
	 * @return DateTime
	 */
	public static function adjustTimeByPreference( DateTime $dateTime, User $user = null ) {

		$tz = false;

		if ( $user === null  ) {
			$tz = $GLOBALS['wgUser']->getOption( 'timecorrection' );
		}

		$data = explode( '|', $tz, 3 );

		if ( $data[0] == 'ZoneInfo' ) {
			try {
				$userTZ = new DateTimeZone( $data[2] );
				$dateTime->setTimezone( $userTZ );
				return $dateTime;
			} catch ( \Exception $e ) {
				// Unrecognized timezone, default to 'Offset' with the stored offset.
				$data[0] = 'Offset';
			}
		}

		if ( $data[0] == 'System' || $tz == '' ) {
			# Global offset in minutes.
			$minDiff = $GLOBALS['wgLocalTZoffset'];
		} elseif ( $data[0] == 'Offset' ) {
			$minDiff = intval( $data[1] );
		} else {
			$data = explode( ':', $tz );
			if ( count( $data ) == 2 ) {
				$data[0] = intval( $data[0] );
				$data[1] = intval( $data[1] );
				$minDiff = abs( $data[0] ) * 60 + $data[1];
				if ( $data[0] < 0 ) {
					$minDiff = -$minDiff;
				}
			} else {
				$minDiff = intval( $data[0] ) * 60;
			}
		}

		# No difference ?
		if ( 0 == $minDiff ) {
			return $dateTime;
		}

		$dateInterval = new DateInterval( "PT" . abs( $minDiff ) . "M" );

		if ( $minDiff > 0 ) {
			$dateTime->add( $dateInterval );
		} else {
			$dateTime->sub( $dateInterval );
		}
	}

}
