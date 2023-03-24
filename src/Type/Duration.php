<?php

declare(strict_types=1);

namespace Cassandra\Type;

use DateInterval;

class Duration extends Base
{
    use CommonResetValue;
    use CommonBinaryOfValue;

    protected const INT32_MIN = -2147483648;
    protected const INT32_MAX = 2147483647;

    /**
     * @var ?array{ months: int, days: int, nanoseconds: int } $_value
     */
    protected ?array $_value = null;

    /**
     * @param ?array{ months: int, days: int, nanoseconds: int } $value
     *
     * @throws \Cassandra\Type\Exception
     */
    public function __construct(?array $value = null)
    {
        if (PHP_INT_SIZE < 8) {
            throw new Exception('The Duration data type requires a 64-bit system');
        }

        if ($value !== null) {
            self::validateValue($value);
        }

        $this->_value = $value;
    }

    /**
     * @param mixed $value
     * @param null|int|array<int|array<mixed>> $definition
     *
     * @throws \Cassandra\Type\Exception
     */
    protected static function create(mixed $value, null|int|array $definition): self
    {
        if ($value !== null && !is_array($value)) {
            throw new Exception('Invalid duration value');
        }

        if (!isset($value['months']) || !is_int($value['months'])) {
            throw new Exception('Invalid duration value - value "months" is not set or has an invalid data type (must be int)');
        }

        if (!isset($value['days']) || !is_int($value['days'])) {
            throw new Exception('Invalid duration value - value "days" is not set or has an invalid data type (must be int)');
        }

        if (!isset($value['nanoseconds']) || !is_int($value['nanoseconds'])) {
            throw new Exception('Invalid duration value - value "nanoseconds" is not set or has an invalid data type (must be int)');
        }

        return new self([
            'months' => $value['months'],
            'days' => $value['days'],
            'nanoseconds' => $value['nanoseconds'],
        ]);
    }

    /**
     * @param array<mixed> $value
     *
     * @throws \Cassandra\Type\Exception
     */
    protected static function validateValue(array $value): void
    {
        foreach (['months', 'days', 'nanoseconds'] as $key) {
            if (!isset($value[$key]) || !is_int($value[$key])) {
                throw new Exception('Invalid duration value - value "' . $key . '"  is not set or has an invalid data type (must be int)');
            }
        }

        if ($value['months'] < self::INT32_MIN || $value['months'] > self::INT32_MAX) {
            throw new Exception('Invalid duration value - value "months" must be within the allowed range of ' . self::INT32_MIN . ' and ' . self::INT32_MAX);
        }

        if ($value['days'] < self::INT32_MIN || $value['days'] > self::INT32_MAX) {
            throw new Exception('Invalid duration value - value "days" must be within the allowed range of ' . self::INT32_MIN . ' and ' . self::INT32_MAX);
        }

        if (!($value['months'] <= 0 && $value['days'] <= 0 && $value['nanoseconds'] <= 0)
            &&
            !($value['months'] >= 0 && $value['days'] >= 0 && $value['nanoseconds'] >= 0)
        ) {
            throw new Exception('Invalid duration value - days, months and nanoseconds must be either all positive or all negative');
        }
    }

    /**
     * @return ?array{ months: int, days: int, nanoseconds: int }
     *
     * @throws \Cassandra\Type\Exception
     */
    protected function parseValue(): ?array
    {
        if ($this->_value === null && $this->_binary !== null) {
            $this->_value = static::parse($this->_binary);
        }

        return $this->_value;
    }

    /**
     * @throws \Cassandra\Type\Exception
     */
    public static function fromDateInterval(DateInterval $value): self
    {
        $months = ((int)$value->format('%r%y') * 12) + (int)$value->format('%r%m');
        if (!is_int($months)) {
            throw new Exception('Cannot create Duration from DateInterval - months value exceeds range of PHP_INT_MIN and PHP_INT_MAX');
        }

        $days = (int)$value->format('%r%d');

        $hoursInNanoseconds = (int)$value->format('%r%h') * 3600000000000;
        $minutesInNanoseconds = (int)$value->format('%r%i') * 60000000000;
        $secondsInNanoseconds = (int)$value->format('%r%s') * 1000000000;
        $microsecondsInNanoseconds = (int)$value->format('%r%f') * 1000;

        $totalNanoseconds = $hoursInNanoseconds + $minutesInNanoseconds + $secondsInNanoseconds + $microsecondsInNanoseconds;
        if (!is_int($totalNanoseconds)) {
            throw new Exception('Cannot create Duration from DateInterval - nanoseconds value exceeds range of PHP_INT_MIN and PHP_INT_MAX');
        }

        return new self([
            'months' => $months,
            'days' => $days,
            'nanoseconds' => $totalNanoseconds
        ]);
    }

    /**
     * @throws \Cassandra\Type\Exception
     */
    public static function fromString(string $value): self
    {
        $pattern = '/(?<sign>[+-])?';
        foreach ([
            'years' => 'y',
            'months' => 'mo',
            'weeks' => 'w',
            'days' => 'd',
            'hours' => 'h',
            'minutes' => 'm',
            'seconds' => 's',
            'milliseconds' => 'ms',
            'microseconds' => '(?:us|µs)',
            'nanoseconds' => 'ns',
        ] as $name => $unit) {
            $pattern .= '(?:(?<'. $name . '>\d+)' . $unit . ')?';
        }
        $pattern .= '/';

        $matches = [];
        preg_match($pattern, $value, $matches);

        $isNegative = isset($matches['sign']) && $matches['sign'] === '-';

        $months = 0;
        foreach ([
            'years' => 12,
            'months' => 1,
        ] as $key => $factor) {
            if (isset($matches[$key])) {
                if ($isNegative) {
                    $months += (int)('-' . $matches[$key]) * $factor;
                } else {
                    $months += (int)$matches[$key] * $factor;
                }
            }
        }
        if (!is_int($months)) {
            throw new Exception('Cannot create Duration from String - months value exceeds range of PHP_INT_MIN and PHP_INT_MAX');
        }

        $days = 0;
        foreach ([
            'weeks' => 7,
            'days' => 1,
        ] as $key => $factor) {
            if (isset($matches[$key])) {
                if ($isNegative) {
                    $days += (int)('-' . $matches[$key]) * $factor;
                } else {
                    $days += (int)$matches[$key] * $factor;
                }
            }
        }
        if (!is_int($days)) {
            throw new Exception('Cannot create Duration from String - days value exceeds range of PHP_INT_MIN and PHP_INT_MAX');
        }

        $nanoseconds = 0;
        foreach ([
            'hours' => 3600000000000,
            'minutes' => 60000000000,
            'seconds' => 1000000000,
            'milliseconds' => 1000000,
            'microseconds' => 1000,
            'nanoseconds' => 1,

        ] as $key => $factor) {
            if (isset($matches[$key])) {
                if ($isNegative) {
                    $nanoseconds += (int)('-' . $matches[$key]) * $factor;
                } else {
                    $nanoseconds += (int)$matches[$key] * $factor;
                }
            }
        }
        if (!is_int($nanoseconds)) {
            throw new Exception('Cannot create Duration from String - nanoseconds value exceeds range of PHP_INT_MIN and PHP_INT_MAX');
        }

        return new self([
            'months' => $months,
            'days' => $days,
            'nanoseconds' => $nanoseconds
        ]);
    }

    /**
     * @param array{ months: int, days: int, nanoseconds: int } $value
     *
     * @throws \Exception
     * @throws \Cassandra\Type\Exception
     */
    public static function toDateInterval(array $value): DateInterval
    {
        $isNegative = $value['months'] < 0 || $value['days'] < 0 || $value['nanoseconds'] < 0;
        $sign = $isNegative ? '' : '+';

        $years = intdiv($value['months'], 12);
        $value['months'] %= 12;

        $weeks = intdiv($value['days'], 7);
        $value['days'] %= 7;

        $duration = '';

        if ($years) {
            $duration .= $sign . $years . ' years ';
        }

        if ($value['months']) {
            $duration .= $sign . $value['months'] . ' months ';
        }

        if ($weeks) {
            $duration .= $sign . $weeks . ' weeks ';
        }

        if ($value['days']) {
            $duration .= $sign . $value['days'] . ' days ';
        }

        if ($value['nanoseconds']) {
    
            $hours = intdiv($value['nanoseconds'], 3600000000000);
            $value['nanoseconds'] %= 3600000000000;

            $minutes = intdiv($value['nanoseconds'], 60000000000);
            $value['nanoseconds'] %= 60000000000;

            $seconds = intdiv($value['nanoseconds'], 1000000000);
            $value['nanoseconds'] %= 1000000000;

            $microseconds = intdiv($value['nanoseconds'], 1000);

            if ($hours) {
                $duration .= $sign . $hours . ' hours ';
            }

            if ($minutes) {
                $duration .= $sign . $minutes . ' minutes ';
            }

            if ($seconds) {
                $duration .= $sign . $seconds . ' seconds ';
            }

            if ($microseconds) {
                $duration .= $sign . $microseconds . ' microseconds ';
            }
        }

        $interval = DateInterval::createFromDateString($duration);
        if ($interval === false) {
            throw new Exception('Cannot convert Time to DateInterval');
        }

        return $interval;
    }

    /**
     * @param array{ months: int, days: int, nanoseconds: int } $value
     */
    public static function toString(array $value): string
    {
        $isNegative = $value['months'] < 0 || $value['days'] < 0 || $value['nanoseconds'] < 0;
        if ($isNegative) {
            $duration = '-';
        } else {
            $duration = '';
        }

        $years = intdiv($value['months'], 12);
        $value['months'] %= 12;

        $weeks = intdiv($value['days'], 7);
        $value['days'] %= 7;

        if ($isNegative) {
            $years = abs($years);
            $value['months'] = abs($value['months']);
            $weeks = abs($weeks);
            $value['days'] = abs($value['days']);
        }

        if ($years) {
            $duration .= $years . 'y';
        }

        if ($value['months']) {
            $duration .= $value['months'] . 'mo';
        }

        if ($weeks) {
            $duration .= $weeks . 'w';
        }

        if ($value['days']) {
            $duration .= $value['days'] . 'd';
        }

        $hours = intdiv($value['nanoseconds'], 3600000000000);
        $value['nanoseconds'] %= 3600000000000;

        $minutes = intdiv($value['nanoseconds'], 60000000000);
        $value['nanoseconds'] %= 60000000000;

        $seconds = intdiv($value['nanoseconds'], 1000000000);
        $value['nanoseconds'] %= 1000000000;

        $milliseconds = intdiv($value['nanoseconds'], 1000000);
        $value['nanoseconds'] %= 1000000 ;

        $microseconds = intdiv($value['nanoseconds'], 1000);
        $value['nanoseconds'] %= 1000;


        if ($isNegative) {
            $hours = abs($hours);
            $minutes = abs($minutes);
            $seconds = abs($seconds);
            $milliseconds = abs($milliseconds);
            $microseconds = abs($microseconds);
            $value['nanoseconds'] = abs($value['nanoseconds']);
        }

        if ($hours) {
            $duration .= $hours . 'h';
        }

        if ($minutes) {
            $duration .= $minutes . 'm';
        }

        if ($seconds) {
            $duration .= $seconds . 's';
        }

        if ($milliseconds) {
            $duration .= $milliseconds . 'ms';
        }

        if ($microseconds) {
            $duration .= $microseconds . 'us';
        }

        if ($value['nanoseconds']) {
            $duration .= $value['nanoseconds'] . 'ns';
        }

        return $duration;
    }

    /**
     * @throws \Cassandra\Type\Exception
     */
    public function __toString(): string
    {
        $value = $this->parseValue();

        if ($value === null) {
            return 'null';
        }

        return self::toString($value);
    }

    public static function encodeVint(int $number): string
    {
        $extraBytes = [];
        $extraBytesCount = 0;
        $mask = 0x80;

        while (true) {
            $cur = $number & 0xFF;
            $next = $number >> 8;

            if ($next === 0 && ($cur & $mask) === 0) {
                $number = $cur;
                break;
            }

            if ($extraBytesCount === 8) {
                break;
            }

            $extraBytes[] = $cur;
            $extraBytesCount++;

            $mask |= $mask >> 1;
            $number = $next;
        }

        if ($extraBytesCount < 8) {
            $mask <<= 1;
        }

        $firstByte = $mask | $number;

        return pack('C*', $firstByte, ...array_reverse($extraBytes));
    }

    /**
     * @param array<int> $vint
     * @throws \Cassandra\Type\Exception
     */
    public static function decodeVint(array $vint, int &$pos = 0): int
    {
        $byte = $vint[$pos];
        $extraBytes = 0;

        while (($byte & 0x80) !== 0) {
            $extraBytes++;
            $byte <<= 1;
        }

        $totalBytes = $extraBytes + 1;

        if ($pos + $totalBytes > count($vint)) {
            throw new Exception('Invalid data value');
        }

        $decodedValue = ($byte & 0x7F) >> $extraBytes;

        for ($i = $pos + 1; $i < $pos + $totalBytes; $i++) {
            $decodedValue <<= 8;
            $decodedValue |= $vint[$i];
        }

        $pos += $totalBytes;

        return $decodedValue;
    }

    /**
     * @param array{ months: int, days: int, nanoseconds: int } $value
     *
     * @throws \Cassandra\Type\Exception
     */
    public static function binary(array $value): string
    {
        if (PHP_INT_SIZE < 8) {
            throw new Exception('The Duration data type requires a 64-bit system');
        }

        $monthsEncoded = ($value['months'] >> 31) ^ ($value['months'] << 1);
        $daysEncoded = ($value['days'] >> 31) ^ ($value['days'] << 1);
        $nanosecondsEncoded = ($value['nanoseconds'] >> 63) ^ ($value['nanoseconds'] << 1);

        return self::encodeVint($monthsEncoded)
                . self::encodeVint($daysEncoded)
                . self::encodeVint($nanosecondsEncoded);
    }

    /**
     * @param null|int|array<int|array<mixed>> $definition
     * @return array{ months: int, days: int, nanoseconds: int }
     *
     * @throws \Cassandra\Type\Exception
     */
    public static function parse(string $binary, null|int|array $definition = null): array
    {
        if (PHP_INT_SIZE < 8) {
            throw new Exception('The Duration data type requires a 64-bit system');
        }

        /**
         * @var false|array<int> $values
         */
        $values = unpack('C*', $binary);
        if ($values === false) {
            throw new Exception('Cannot unpack duration.');
        }

        $values = array_values($values);

        $pos = 0;

        $monthsEncoded = self::decodeVint($values, $pos);
        $daysEncoded = self::decodeVint($values, $pos);
        $nanosecondsEncoded = self::decodeVint($values, $pos);

        $value = [
            'months' => ($monthsEncoded >> 1) ^ -($monthsEncoded & 1),
            'days' => ($daysEncoded >> 1) ^ -($daysEncoded & 1),
            'nanoseconds' => (($nanosecondsEncoded >> 1) & 0x7FFFFFFFFFFFFFFF) ^ -($nanosecondsEncoded & 1)
        ];

        self::validateValue($value);

        return $value;
    }
}
