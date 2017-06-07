<?php

namespace ease\dates;

use Yii;
use ej\helpers\DateTimeHelper;


class DateTime extends \DateTime
{
    /**
     * Creates a new [[DateTime]] object (rather than \DateTime)
     *
     * @param string $format
     * @param string $time
     * @param mixed $timezone The timezone the string is set in (defaults to UTC).
     *
     * @return DateTime|false
     */
    public static function createFromFormat($format, $time, $timezone = null)
    {
        if ($timezone !== null) {
            $dateTime = parent::createFromFormat($format, $time, $timezone);
        } else {
            $dateTime = parent::createFromFormat($format, $time);
        }

        if ($dateTime) {
            $timeStamp = $dateTime->getTimestamp();

            if (DateTimeHelper::isValidTimeStamp($timeStamp)) {
                return new DateTime('@' . $dateTime->getTimestamp());
            }
        }

        return false;
    }

    /**
     * @param \DateTime $datetime2
     * @param boolean $absolute
     *
     * @return DateInterval
     */
    public function diff($datetime2, $absolute = false)
    {
        $interval = parent::diff($datetime2, $absolute);

        // Convert it to a DateInterval in this namespace
        if ($interval instanceof \DateInterval) {
            $spec = 'P';

            if ($interval->y) {
                $spec .= $interval->y . 'Y';
            }
            if ($interval->m) {
                $spec .= $interval->m . 'M';
            }
            if ($interval->d) {
                $spec .= $interval->d . 'D';
            }

            if ($interval->h || $interval->i || $interval->s) {
                $spec .= 'T';

                if ($interval->h) {
                    $spec .= $interval->h . 'H';
                }
                if ($interval->i) {
                    $spec .= $interval->i . 'M';
                }
                if ($interval->s) {
                    $spec .= $interval->s . 'S';
                }
            }

            // If $spec is P at this point, the interval was less than a second. Accuracy be damned.
            if ($spec === 'P') {
                $spec = 'PT0S';
            }

            $newInterval = new DateInterval($spec);
            $newInterval->invert = $interval->invert;

            // Apparently 'days' is a read-only property. Oh well.
            //$newInterval->days = $interval->days;

            return $newInterval;
        }

        return $interval;
    }
}
