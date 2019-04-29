<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */
namespace craftunit\helpers;

use Codeception\Test\Unit;
use craft\helpers\DateTimeHelper;
use yii\base\ErrorException;

/**
 * Unit tests for the DateTime Helper class.
 *
 * TODO: This suite must be more effecient with some global variables.
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @author Global Network Group | Giel Tettelaar <giel@yellowflash.net>
 * @since 3.0
 */
class DateTimeHelperTest extends Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    protected $systemTimezone;
    protected $utcTimezone;
    protected $asiaTokyoTimezone;

    protected function _before()
    {
        \Craft::$app->setTimeZone('America/Los_Angeles');
        $this->systemTimezone = new \DateTimeZone(\Craft::$app->getTimeZone());
        $this->utcTimezone = new \DateTimeZone('UTC');
        $this->asiaTokyoTimezone = new \DateTimeZone('Asia/Tokyo');
    }

    /**
     * @dataProvider constantsData
     */
    public function testContsants($result, $input)
    {
        $this->assertSame($result, $input);
        $this->assertInternalType('integer', $input);
    }

    public function constantsData()
    {
        return [
            [86400, DateTimeHelper::SECONDS_DAY],
            [3600, DateTimeHelper::SECONDS_HOUR],
            [60, DateTimeHelper::SECONDS_MINUTE],
            [2629740, DateTimeHelper::SECONDS_MONTH],
            [31556874, DateTimeHelper::SECONDS_YEAR],
        ];
    }

    public function testCurrentUtcDateTime()
    {
        $this->assertSame(
            (new \DateTime(null, $this->utcTimezone))->format('Y-m-d H:i:s'),
            DateTimeHelper::currentUTCDateTime()->format('Y-m-d H:i:s')
        );
    }

    public function testCurrentUtcDateTimeStamp()
    {
        $this->assertSame(
            DateTimeHelper::currentTimeStamp(),
            (new \DateTime(null, $this->utcTimezone))->getTimestamp()
        );
    }

    /**
     * @dataProvider secondsToHumanTimeData
     */
    public function testSecondsToHumanTimeDuration($result, $input, $showSeconds = true)
    {
        $toHuman = DateTimeHelper::secondsToHumanTimeDuration($input, $showSeconds);
        $this->assertSame($result, $toHuman);
        $this->assertInternalType('string', $toHuman);
    }

    public function secondsToHumanTimeData()
    {
        return [
            ['22 seconds', 22],
            ['1 second', 1],
            ['2 minutes', 120],
            ['2 minutes, 5 seconds', 125],
            ['2 minutes, 1 second', 121],
            ['2 minutes', 121, false],
            ['3 minutes', 179, false],
            ['1 hour', 3600],
            ['1 day', 86400],
            ['1 week', 604800],

        ];
    }

    /**
     * What we are testing here is that if we tell the DtHelper to not assume a timezone and set it to system.
     * That all formats are converted to the system timezone from the inputted system timezone. ie an array like this:
     *
     * ['date' => '2018-08-08', 'timezone' => 'Asia/Tokyo']
     *
     * toDateTime must start the DateTime from Asia/Tokyo instead of UTC(The default starting point) and then convert it to the system timezone.
     *
     * @dataProvider formatsWithTimezone
     *
     * @param           $format
     * @param \DateTime $expectedResult
     */
    public function testUtcIgnorance($format, \Closure $expectedResult)
    {
        $expectedResult = $expectedResult();

        $toDateTime = DateTimeHelper::toDateTime($format);
        $systemTz = $this->systemTimezone->getName();

        $this->assertInstanceOf(\DateTime::class, $toDateTime);
        $this->assertSame($systemTz, $toDateTime->getTimezone()->getName());

        // Ensure the expected result is in the same timezone as the system.
        $this->assertSame($systemTz, $expectedResult->getTimezone()->getName());

        // Are they the same?
        $this->assertSame($expectedResult->format('Y-m-d H:i:s'), $toDateTime->format('Y-m-d H:i:s'));
    }

    public function formatsWithTimezone()
    {
        $dt = function() {
            $dt = new \DateTime('2018-08-09 20:00:00', new \DateTimeZone('Asia/Tokyo'));
            $dt->setTimezone(new \DateTimeZone(\Craft::$app->getTimeZone()));

            return $dt;
        };

        return [
            'array-format' => [
                ['date' => '08-09-2018', 'time' => '08:00 PM', 'timezone' => 'Asia/Tokyo'],
                $dt,
            ],
            'w3c-format' => [
                '2018-08-09T20:00:00+09:00',
                $dt,
            ],
        ];
    }

    /**
     * @dataProvider invalidToDateTimeFormatsData
     * @param $format
     */
    public function testToDateTimeInvalidFormats($format)
    {
        $this->assertFalse(DateTimeHelper::toDateTime($format));
    }

    public function invalidToDateTimeFormatsData()
    {
        return [
            'no-params' => [['date' => '', 'time' => '']],
            'invalid-seperator' => ['2018/08/09 20:00:00'],
            'invalid-seperator-2' => ['2018.08.09 20:00:00'],
            'null-type' => [null],
            'empty-string' => [''],
            'empty-array' => [[]]
        ];
    }

    /**
     * Test that if we set the $setToSystemTimezone value to false that toDateTime creates a tz in UTC.
     *
     * @dataProvider simpleDateTimeFormats
     * @param $format
     */
    public function testUtcDefault($format)
    {
        $utc = new \DateTimeZone('UTC');
        $toDateTime = DateTimeHelper::toDateTime($format, false, false);
        $this->assertSame($this->utcTimezone->getName(), $toDateTime->getTimezone()->getName());
    }

    public function simpleDateTimeFormats()
    {
        return [
            'mysql' => ['2018-08-08 20:00:00'],
            'array' => [['date' => '08-09-2018', 'time' => '08:00 PM']],
            'w3c-format' => ['2018-08-09T20:00:00'],
            'dtobject' => [new \DateTime('2018-08-09', new \DateTimeZone('UTC'))]
        ];
    }


    /**
     * Test that dateTime is created with the passed in timezone IF $setSystemTimezone is set to false.
     *
     *@dataProvider toDateTimeWithTzFormats
     * @param               $format
     * @param \DateTime      $expectedResult
     * @param \DateTimeZone $expectedTimezone
     */
    public function testToDateTimeRespectsTz($format, \DateTime $expectedResult, \DateTimeZone $expectedTimezone)
    {
        $toDateTime = DateTimeHelper::toDateTime($format, false, false);

        $this->assertInstanceOf(\DateTime::class, $toDateTime);
        $this->assertSame($expectedTimezone->getName(), $toDateTime->getTimezone()->getName());
        $this->assertSame($expectedTimezone->getName(), $expectedResult->getTimezone()->getName());
        $this->assertSame($expectedResult->format('Y-m-d H:i:s'), $toDateTime->format('Y-m-d H:i:s'));
    }

    public function toDateTimeWithTzFormats()
    {
        $basicDateTimeCreator = function ($timezone){
            $tz = new \DateTimezone($timezone);
            $dt = new \DateTime('2018-08-09 20:00:00', $tz);
            return $dt;
        };

        return [
            'mysql-format' => [
               '2018-08-09 20:00:00',
                $basicDateTimeCreator('UTC'),
                new \DateTimeZone('UTC')
            ],
            'array-format' => [
                ['date' => '08-09-2018', 'time' => '08:00 PM', 'timezone' => 'Asia/Tokyo'],
                $basicDateTimeCreator('Asia/Tokyo'),
                new \DateTimeZone('Asia/Tokyo')
            ],
            'w3c-format' => [
                '2018-08-09T20:00:00+09:00',
                $basicDateTimeCreator('+09:00'),
                new \DateTimeZone('+09:00')
            ],
        ];
    }

    /**
     * @dataProvider toDateTimeFormats
     * @param          $format
     * @param \Closure $expectedResult
     */
    public function testToDateTimeCreation($format, \Closure $expectedResult, $closureParam = null)
    {
        $expectedResult = $closureParam ? $expectedResult($closureParam) : $expectedResult();

        $toDateTime = DateTimeHelper::toDateTime($format);

        $this->assertSame($expectedResult->format('Y-m-d H:i:s'), DateTimeHelper::toDateTime($format)->format('Y-m-d H:i:s'));
        $this->assertInstanceOf(\DateTime::class, $toDateTime);
    }

    public function toDateTimeFormats()
    {
        // Because we dont have access to Craft::$app here we smuggle this in via callback and call it in the test function. Which does have access to Craft::$app.
        $dt = function ($dateParam = '2018-08-09 20:00:00') {
            $systemTimezone = new \DateTimezone(\Craft::$app->getTimeZone());
            $utcTz = new \DateTimeZone('UTC');

            // Crafts toDateTime sets the input time as utc. Then converts to system tz unless overridden by variables $assumeSystemTimeZone and $setToSystemTimeZone.
            $dt = new \DateTime($dateParam, $utcTz);
            $dt->setTimezone($systemTimezone);
            return $dt;
        };

        return [
            'was-invalid-date-valid-time' => [['date' => '2018-08-09', 'time' => '08:00 PM'], $dt],
            'was-invalid-date-format' => [['date' => '2018-08-09'], $dt, '2018-08-09 00:00:00'],

            'basic-mysql-format' => [
                '2018-08-09 20:00:00',
                $dt,
            ],
            'array-diff-seperator' => [
                ['date' => '08/09/2018', 'time' => '08:00 PM'],
                $dt,
            ],
            'array-diff-seperator-2' => [
                ['date' => '08.09.2018', 'time' => '08:00 PM'],
                $dt,
            ],
            'array-format' => [
                ['date' => '08-09-2018', 'time' => '08:00 PM'],
                $dt,
            ],
            'array-format' => [
                ['date' => '08-09-2018', 'time' => '08:00 PM'],
                $dt,
            ],
            'w3c-format' => [
                '2018-08-09T20:00:00',
                $dt,
            ],
            'unix-timestamp' => [
                '1533844800',
                $dt,
            ],
        ];
    }

    /**
     * DateTimeHelper::toDateTime:145-148
     */
    public function testEmptyArrayDateDefault()
    {
        $dt = DateTimeHelper::toDateTime(['date' => '', 'time' => '08:00PM']);

        $created = new \DateTime('now', $this->utcTimezone);
        $comparable = new \DateTime($created->format('Y-m-d').' 20:00:00', $this->utcTimezone);
        $comparable->setTimezone($this->systemTimezone);

        $this->assertSame($comparable->format('Y-m-d H:i:s'), $dt->format('Y-m-d H:i:s'));
    }

    /**
     * @dataProvider timezoneNormalizeData
     * @param $result
     * @param $input
     */
    public function testNormalizeTimezone($result, $input)
    {
        $normalized = DateTimeHelper::normalizeTimeZone($input);
        $this->assertSame($result, $normalized);
    }

    public function timezoneNormalizeData()
    {
        return [
            ['America/New_York', 'EST'],
            ['Europe/Berlin', 'CEST'],
            ['+09:00', '+0900'],
            ['-02:00', '-02:00'],
            ['UTC', 'UTC'],
            ['UTC', 'GMT'],
            ['Europe/Amsterdam', 'Europe/Amsterdam'],
            [false, 'NotATz'],
        ];
    }

    /**
     * @dataProvider isIso8601Data
     * @param $result
     * @param $input
     */
    public function testIso86($result, $input, $convert = false)
    {
        if ($convert) {
            $input = DateTimeHelper::toIso8601($input);
        }

        $isIso = DateTimeHelper::isIso8601($input);


        $this->assertSame($result, $isIso);
    }

    public function isIso8601Data()
    {
        $dateTimeObject = new \DateTime('2018-09-21');

        return [
            [true, $dateTimeObject, true],
            [true, '2018', true],
            [true, '2018-09-09', true],
            [true, '2018-09-30T13:41:06+00:00'],

            [false, 'YYYY-MM-DDTHH:MM:SS+HH:MM'],
            [false, '2008-09-15'],
            [false, 'Iam not a string'],
            [false, $dateTimeObject],
            [false, false],
            [false, null],

        ];
    }


    /**
     * @dataProvider humanIntervalDurationData
     * @param $result
     * @param $inputString
     *
     * @throws \Exception
     */
    public function testHumanIntervalDuration($result, $inputString, $showSeconds = true)
    {
        $fromInterval = DateTimeHelper::humanDurationFromInterval(new \DateInterval($inputString), $showSeconds);

        $this->assertSame($result, $fromInterval);
    }
    public function humanIntervalDurationData()
    {
        return [
            ['1 day', 'P1D'],
            ['1 year', 'P1Y'],
            ['1 month', 'P1M'],
            ['1 hour', 'PT1H'],
            ['1 second', 'PT1S'],
            ['2 months, 1 day, and 1 hour', 'P2M1DT1H'],
            ['1 hour and 1 minute', 'PT1H1M25S', false],
            ['1 hour and 2 minutes', 'PT1H1M55S', false],
            ['less than a minute', 'PT1S', false],
        ];
    }

    public function testIsToday()
    {
        $dateTime = new \DateTime('now');
        $this->assertTrue(DateTimeHelper::isToday($dateTime));

        $dateTime->modify('-1 days');
        $this->assertFalse(DateTimeHelper::isToday($dateTime));

        $dateTime->modify('-1 days');
        $this->assertFalse(DateTimeHelper::isToday($dateTime));

        $dateTime->modify('+2 days');
        $this->assertTrue(DateTimeHelper::isToday($dateTime));
    }

    public function testYesterday()
    {
        $dateTime = new \DateTime('now');

        $dateTime->modify('-1 days');
        $this->assertTrue(DateTimeHelper::isYesterday($dateTime));

        $dateTime->modify('-1 days');
        $this->assertFalse(DateTimeHelper::isYesterday($dateTime));

        $dateTime->modify('+2 days');
        $this->assertFalse(DateTimeHelper::isYesterday($dateTime));

        $dateTime = new \DateTime('yesterday');
        $this->assertTrue(DateTimeHelper::isYesterday($dateTime));
    }

    public function testThisYearCheck()
    {
        $dateTime = new \DateTime('now');
        $this->assertTrue(DateTimeHelper::isThisYear($dateTime));

        $dateTime->modify('-1 years');
        $this->assertFalse(DateTimeHelper::isThisYear($dateTime));

        $dateTime->modify('+2 years');
        $this->assertFalse(DateTimeHelper::isThisYear($dateTime));
    }

    public function testThisWeek()
    {
        $dateTime = new \DateTime('now');
        $this->assertTrue(DateTimeHelper::isThisWeek($dateTime));

        $dateTime->modify('-1 weeks');
        $this->assertFalse(DateTimeHelper::isThisWeek($dateTime));


        $dateTime->modify('+1 weeks');
        $this->assertTrue(DateTimeHelper::isThisWeek($dateTime));

        $dateTime->modify('+2 weeks');
        $this->assertFalse(DateTimeHelper::isYesterday($dateTime));
    }

    public function testIsInThePast()
    {
        $systemTz = new \DateTimeZone(\Craft::$app->getTimeZone());
        $dateTime = new \DateTime('now', $systemTz);
        $dateTime->modify('-5 seconds');
        $this->assertTrue(DateTimeHelper::isInThePast($dateTime));

        $dateTime->modify('-1 minutes');
        $this->assertTrue(DateTimeHelper::isInThePast($dateTime));

        $dateTime->modify('+2 minutes');
        $this->assertFalse(DateTimeHelper::isInThePast($dateTime));
    }

    public function testIsThisMonth()
    {
        $dateTime = new \DateTime('now');
        $this->assertTrue(DateTimeHelper::isThisMonth($dateTime));

        $dateTime->modify('-35 days');
        $this->assertFalse(DateTimeHelper::isThisMonth($dateTime));
    }

    /**
     * @dataProvider withinLastData
     */
    public function testIsWithinLast($result, $dateTime, $interval)
    {
        $isWthinLast = DateTimeHelper::isWithinLast($dateTime, $interval);
        $this->assertSame($result, $isWthinLast);
    }

    public function withinLastData()
    {
        $tomorow = new \DateTime('tomorrow');
        $yesterday = new \DateTime('yesterday');
        $aYearAgo = new \DateTime('2010-08-8 20:00:00');

        $modable = new \DateTime('now');
        $modable->modify('-2 days');

        $hourAgo = new \DateTime('now');
        $hourAgo->modify('-1 hour');

        return [
            [true, $yesterday, 2],
            [true, $yesterday, 'somestring'],
            [true, $yesterday, ''],
            [true, $modable->format('Y-m-d H:i:s'), 3],
            [true, $hourAgo, '4 hours'],

            [false, $aYearAgo, 25],
            [false, $tomorow, 0],
        ];
    }


    /**
     * @dataProvider secondsToIntervalData
     * @param $result
     * @param $input
     * @param $shortFormat
     * @param $longFormat
     */
    public function testSecondsToInterval($shortResult, $longResult, $input)
    {
        $interval = DateTimeHelper::secondsToInterval($input);
        $this->assertSame($shortResult, $interval->s);
        $this->assertSame($longResult, (int)$interval->format('%s%d%h%m'));
    }

    public function secondsToIntervalData()
    {
        return [
            [10, 10000, 10],
            [0, 0000, 0],
            [928172, 928172000, 928172],

        ];
    }

    /**
     * @dataProvider intervalToSecondsData
     * @param $result
     * @param $period
     *
     * @throws \Exception
     */
    public function testIntervalToSeconds($result, $period)
    {
        $seconds = DateTimeHelper::intervalToSeconds(new \DateInterval($period));
        $this->assertSame($result, $seconds);
    }
    public function intervalToSecondsData()
    {
        return [
            [86400, 'P1D'],
            [90000, 'P1DT1H']
        ];
    }

    /**
     * @dataProvider toIso8601Data
     * @param $result
     * @param $input
     */
    public function testToIso8601($result, $input)
    {
        $toIso8601 = DateTimeHelper::toIso8601($input);
        $this->assertSame($result, $toIso8601);
    }

    /**
     * @return array
     */
    public function toIso8601Data()
    {
        $amsterdamTime = new \DateTime('2018-08-08 20:00:00', new \DateTimeZone('Europe/Amsterdam'));
        $tokyoTime = new \DateTime('2018-08-08 20:00:00', new \DateTimeZone('Asia/Tokyo'));

        return [
            ['2018-08-08T20:00:00+09:00', $tokyoTime],
            ['2018-08-08T20:00:00+02:00', $amsterdamTime],
            'invalid-format-returns-false' => [false, ['date' => '']]
        ];
    }

    /**
     * @dataProvider timezoneAbbreviationData
     * @param $result
     * @param $input
     */
    public function testTimezoneAbbreviation($result, $input)
    {
        $abbreviated = DateTimeHelper::timeZoneAbbreviation($input);
        $this->assertSame($result, $abbreviated);
        $this->assertInternalType('string', $abbreviated);
    }
    public function timezoneAbbreviationData()
    {
        return [
            // TODO: Summer and winter time.... >.< This test fails in summer time: ['CET', 'Europe/Amsterdam'],
            ['JST', 'Asia/Tokyo'],
            ['GMT+0900', '+0900'],
        ];
    }

    /**
     * @dataProvider isValidTimestampData
     * @param $result
     * @param $input
     */
    public function testIsValidTimeStamp($result, $input)
    {
        $isValidTimestamp = DateTimeHelper::isValidTimeStamp($input);
        $this->assertSame($result, $isValidTimestamp);
        $this->assertInternalType('bool', $isValidTimestamp);
    }
    public function isValidTimestampData()
    {
        $amsterdamTime = new \DateTime('2018-12-30 20:00:00', new \DateTimeZone('Europe/Amsterdam'));
        $tokyoTime = new \DateTime('2018-12-30 20:00:00', new \DateTimeZone('Asia/Tokyo'));

        return [
            [true, $amsterdamTime->getTimestamp()],
            [true, $tokyoTime->getTimestamp()],
            [true, '1539520249'],
            [true, 0000000000],
            [false, '2018-10-14T21:30:49+09:00'],
            [false, true],
            [false, 'string'],
            [false, null],
            [false, false],
        ];
    }

    /**
     * @dataProvider isInvalidIntervalString
     * @param $result
     * @param $input
     */
    public function testIsValidIntervalString($result, $input)
    {
        $isValid = DateTimeHelper::isValidIntervalString($input);
        $this->assertSame($result, $isValid);
    }
    public function isInvalidIntervalString()
    {
        return [
            [true, '1 day'],
            [true, '1 hour'],
            [true, '1 hour + 1 day'],
            [true, '1 second'],
            [true, '1 year'],
            [true, '1 month'],
            [true, '1 minutes'],

            [false, ''],
            [false, 'random string'],

        ];
    }

    /**
     * @dataProvider timezoneOffsetData
     * @param $result
     * @param $input
     */
    public function testTimezoneOffset($result, $input)
    {
        $offset = DateTimeHelper::timezoneOffset($input);
        $this->assertSame($result, $offset);
    }
    public function timezoneOffsetData()
    {
        return [
            ['+00:00', 'UTC'],
            ['+00:00', 'GMT'],
            ['-04:00', 'America/New_York'],
            ['+09:00', 'Asia/Tokyo'],
            ['+09:00', '+09:00'],
        ];
    }
    public function testTimezoneOffsetException()
    {
        $this->tester->expect(\Exception::class, function () {
            DateTimeHelper::timeZoneOffset('invalid');
        });
    }
}