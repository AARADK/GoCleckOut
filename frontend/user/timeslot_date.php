<?php

const TIMERANGES = [
    0 => '10:00 - 13:00',
    1 => '13:00 - 16:00',
    2 => '16:00 - 19:00'
];

function getTimeslots($days = 10)
{
    $timezone = new DateTimeZone('+0545');
    $now = new DateTime('now', $timezone);
    $startDate = clone $now;
    $startDate->modify('+24 hours');

    $endDate = clone $startDate;
    $endDate->modify("+$days days");

    $tempslots = [];
    $current = clone $startDate;
    $firstValidDaySet = false;

    while ($current <= $endDate) {
        $day = $current->format('l');
        if (in_array($day, ['Wednesday', 'Thursday', 'Friday'])) {
            $slotIndexes = [];

            if (!$firstValidDaySet) {
                if ($current->format('Y-m-d') === $startDate->format('Y-m-d')) {
                    $hour = (int)$startDate->format('H');

                    if ($hour < 10) {
                        $slotIndexes = [0, 1, 2];
                    } elseif ($hour < 13) {
                        $slotIndexes = [1, 2];
                    } elseif ($hour < 16) {
                        $slotIndexes = [2];
                    }

                    if (!empty($slotIndexes)) {
                        $tempslots[] = [
                            'date' => clone $current,
                            'indexes' => $slotIndexes
                        ];
                    }
                } else {
                    $tempslots[] = [
                        'date' => clone $current,
                        'indexes' => [0, 1, 2]
                    ];
                }
                $firstValidDaySet = true;
            } else {
                $tempslots[] = [
                    'date' => clone $current,
                    'indexes' => [0, 1, 2]
                ];
            }
        }
        $current->modify('+1 day');
    }

    $timeslots = [];

    foreach ($tempslots as $entry) {
        $date = $entry['date'];
        foreach ($entry['indexes'] as $index) {
            $startTime = explode(' - ', TIMERANGES[$index])[0];
            $slotDateTime = DateTime::createFromFormat(
                'Y-m-d H:i',
                $date->format('Y-m-d') . ' ' . $startTime,
                $timezone
            );

            $timeslots[] = [
                'label' => $date->format('l') . " - " . TIMERANGES[$index] . " - " . $date->format('Y/m/d'),
                'timestamp' => date('Y-m-d H:i:s', $slotDateTime->getTimestamp())
            ];
        }
    }

    return $timeslots;
}
