<?php

declare(strict_types=1);

namespace Cassandra\Type;

class Bigint extends Base
{
    protected float|int|null $_value = null;

    public function __construct(float|int|null $value = null)
    {
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
        if ($value !== null && !is_int($value) && !is_float($value)) {
            throw new Exception('Invalid value type');
        }

        return new self($value);
    }

    /**
     * @throws \Cassandra\Type\Exception
     */
    public function binaryOfValue(): string
    {
        if ($this->_value === null) {
            throw new Exception('value is null');
        }

        return static::binary($this->_value);
    }

    /**
     * @throws \Cassandra\Type\Exception
     */
    public function parseValue(): float|int|null
    {
        if ($this->_value === null && $this->_binary !== null) {
            $this->_value = static::parse($this->_binary);
        }

        return $this->_value;
    }

    public function __toString(): string
    {
        return (string) $this->_value;
    }

    public static function binary(float|int $value): string
    {
        $higher = ($value & 0xffffffff00000000) >>32;
        $lower = $value & 0x00000000ffffffff;
        return pack('NN', $higher, $lower);
    }

    /**
     * @param null|int|array<int|array<mixed>> $definition
     *
     * @throws \Cassandra\Type\Exception
     */
    public static function parse(string $binary, null|int|array $definition = null): float|int
    {
        /**
         * @var false|array<int> $unpacked
         */
        $unpacked = unpack('N2', $binary);
        if ($unpacked === false) {
            throw new Exception('Cannot unpack binary.');
        }

        return $unpacked[1] << 32 | $unpacked[2];
    }
}
