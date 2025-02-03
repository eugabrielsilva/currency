<?php

namespace Currency;

class Currency
{
    /**
     * Default settings.
     *
     * @var array
     */
    private static $defaults = [
        'symbol'           => '$',
        'separator'        => ',',
        'decimal'          => '.',
        'errorOnInvalid'   => false,
        'precision'        => 2,
        'pattern'          => '!#',
        'negativePattern'  => '-!#',
        // If no custom format is provided, the defaultFormat() method is used.
        'format'           => null,
        'fromCents'        => false,
        // If true, will use the Vedic (Indian numbering) grouping.
        'useVedic'         => false,
    ];

    /**
     * Regular expression for standard grouping.
     *
     * @var string
     */
    private static $groupRegex = '/(\d)(?=(\d{3})+\b)/';

    /**
     * Regular expression for Vedic grouping.
     *
     * @var string
     */
    private static $vedicRegex = '/(\d)(?=(\d\d)+\d\b)/';

    /**
     * Internal integer value (scaled value).
     *
     * @var int|float
     */
    public $intValue;

    /**
     * Floating point value.
     *
     * @var float
     */
    public $value;

    /**
     * Settings used by the instance.
     *
     * @var array
     */
    private $_settings = [];

    /**
     * Power of 10 based on the precision (e.g., 10^2 = 100).
     *
     * @var int|float
     */
    private $_precision;

    /**
     * Constructor.
     *
     * @param mixed $value Number, string, or Currency instance.
     * @param array $opts  Optional settings.
     * @throws \Exception If the value is invalid and errorOnInvalid is true.
     */
    public function __construct($value, array $opts = [])
    {
        // Merge default settings with provided options.
        $this->_settings = array_merge(self::$defaults, $opts);

        $this->_precision = pow(10, $this->_settings['precision']);

        // Parse the value and scale it to the internal integer representation.
        $v = self::parse($value, $this->_settings);
        $this->intValue = $v;
        $this->value = $v / $this->_precision;

        // Set default incremental value if not defined.
        if (!isset($this->_settings['increment'])) {
            $this->_settings['increment'] = 1 / $this->_precision;
        }

        // Set grouping based on whether Vedic numbering is used.
        if (!empty($this->_settings['useVedic'])) {
            $this->_settings['groups'] = self::$vedicRegex;
        } else {
            $this->_settings['groups'] = self::$groupRegex;
        }
    }

    /**
     * Rounds a value according to a given increment.
     *
     * @param float $value
     * @param float $increment
     * @return float
     */
    private static function rounding(float $value, float $increment): float
    {
        return round($value / $increment) * $increment;
    }

    /**
     * Parses the input value and converts it to the internal scaled integer.
     *
     * For addition/subtraction the value is scaled.
     *
     * @param mixed $value Number, string, or Currency instance.
     * @param array $opts  Settings.
     * @param bool  $useRounding Whether to round the result.
     * @return float
     * @throws \Exception
     */
    private static function parse($value, array $opts, bool $useRounding = true)
    {
        $v = 0;
        $decimal        = $opts['decimal'] ?? '.';
        $errorOnInvalid = $opts['errorOnInvalid'] ?? false;
        $decimals       = $opts['precision'] ?? 2;
        $fromCents      = $opts['fromCents'] ?? false;
        $precision      = pow(10, $decimals);

        // If the value is an instance of Currency.
        if ($value instanceof self) {
            if ($fromCents) {
                return $value->intValue;
            } else {
                $v = $value->value;
            }
        } elseif (is_numeric($value)) {
            $v = $value;
        } elseif (is_string($value)) {
            // Allow negative notation with parentheses, e.g., (1.99) -> -1.99.
            $v = preg_replace('/\((.*)\)/', '-$1', $value);
            // Remove any characters that are not digits, minus sign, or the decimal separator.
            $regex = '/[^-\d' . preg_quote($decimal, '/') . ']/';
            $v = preg_replace($regex, '', $v);
            // Replace the decimal separator with a dot.
            $decimalString = '/' . preg_quote($decimal, '/') . '/';
            $v = preg_replace($decimalString, '.', $v);
            if ($v === '' || $v === null) {
                $v = 0;
            }
        } else {
            if ($errorOnInvalid) {
                throw new \Exception('Invalid Input');
            }
            $v = 0;
        }

        if (!$fromCents) {
            $v = $v * $precision;
            // Ensure 4 decimal places for proper rounding.
            $v = round($v, 4);
        }
        if ($useRounding) {
            $v = round($v);
        }
        return $v;
    }

    /**
     * Default formatting: applies grouping, decimal places, and currency symbol.
     *
     * @param Currency $currency
     * @param array    $settings
     * @return string
     */
    private function defaultFormat(Currency $currency, array $settings): string
    {
        // Get the numeric representation as a string without the negative sign.
        $str = ltrim($currency->__toString(), '-');
        $parts = explode('.', $str);
        $dollars = $parts[0];
        $cents = $parts[1] ?? '';

        // Apply digit grouping.
        if (isset($settings['groups']) && isset($settings['separator'])) {
            $dollars = preg_replace($settings['groups'], '$1' . $settings['separator'], $dollars);
        }
        $formatted = $dollars;
        if ($cents !== '') {
            $formatted .= $settings['decimal'] . $cents;
        }

        // Choose positive or negative pattern.
        $pattern = ($currency->value >= 0) ? $settings['pattern'] : $settings['negativePattern'];
        // Replace placeholders with the currency symbol and formatted number.
        $result = str_replace(['!', '#'], [$settings['symbol'], $formatted], $pattern);
        return $result;
    }

    /**
     * Adds a value to the current instance.
     *
     * @param mixed $number Number, string, or Currency instance.
     * @return Currency
     */
    public function add($number): Currency
    {
        $newIntValue = $this->intValue + self::parse($number, $this->_settings);
        $divisor = ($this->_settings['fromCents'] ? 1 : $this->_precision);
        return new self($newIntValue / $divisor, $this->_settings);
    }

    /**
     * Subtracts a value from the current instance.
     *
     * @param mixed $number Number, string, or Currency instance.
     * @return Currency
     */
    public function subtract($number): Currency
    {
        $newIntValue = $this->intValue - self::parse($number, $this->_settings);
        $divisor = ($this->_settings['fromCents'] ? 1 : $this->_precision);
        return new self($newIntValue / $divisor, $this->_settings);
    }

    /**
     * Multiplies the current value by a factor.
     *
     * Note: if a Currency instance is passed, its unscaled value is used.
     *
     * @param mixed $number Number, string, or Currency instance.
     * @return Currency
     */
    public function multiply($number): Currency
    {
        if ($number instanceof self) {
            $factor = $number->value;
        } else {
            // Parse without scaling: divide by _precision to get the actual numeric value.
            $factor = self::parse($number, $this->_settings, false) / $this->_precision;
        }
        $newValue = $this->value * $factor;
        return new self($newValue, $this->_settings);
    }

    /**
     * Divides the current value by a divisor.
     *
     * Note: if a Currency instance is passed, its unscaled value is used.
     *
     * @param mixed $number Number, string, or Currency instance.
     * @return Currency
     * @throws \Exception When division by zero occurs.
     */
    public function divide($number): Currency
    {
        if ($number instanceof self) {
            $divisor = $number->value;
        } else {
            $divisor = self::parse($number, $this->_settings, false) / $this->_precision;
        }
        if ($divisor == 0) {
            throw new \Exception('Division by zero');
        }
        $newValue = $this->value / $divisor;
        return new self($newValue, $this->_settings);
    }

    /**
     * Distributes the current amount evenly among a given count.
     * Any leftover pennies are added to the first parts.
     *
     * @param int $count
     * @return Currency[] Array of Currency instances.
     */
    public function distribute(int $count): array
    {
        $distribution = [];
        // Use floor for positive values; ceil for negatives.
        $split = ($this->intValue >= 0)
            ? (int) floor($this->intValue / $count)
            : (int) ceil($this->intValue / $count);
        $pennies = abs($this->intValue - ($split * $count));
        $divisor = ($this->_settings['fromCents'] ? 1 : $this->_precision);

        for ($i = 0; $i < $count; $i++) {
            $item = new self($split / $divisor, $this->_settings);
            if ($pennies > 0) {
                if ($this->intValue >= 0) {
                    $item = $item->add(1 / $divisor);
                } else {
                    $item = $item->subtract(1 / $divisor);
                }
                $pennies--;
            }
            $distribution[] = $item;
        }
        return $distribution;
    }

    /**
     * Returns the integer part (dollars) of the value.
     *
     * @return int
     */
    public function dollars(): int
    {
        return (int) $this->value;
    }

    /**
     * Returns the cents part of the value.
     *
     * @return int
     */
    public function cents(): int
    {
        return (int) ($this->intValue % $this->_precision);
    }

    /**
     * Formats the currency value according to the settings.
     *
     * If the $options parameter is a callback function, it will be called with (Currency, settings).
     * If it is an array, the options will be merged with the current settings.
     *
     * @param mixed $options Callback function or array of options.
     * @return string
     */
    public function format($options = null): string
    {
        if (is_callable($options)) {
            return call_user_func($options, $this, $this->_settings);
        }

        $settings = $this->_settings;
        if (is_array($options)) {
            $settings = array_merge($settings, $options);
        }
        if (isset($settings['format']) && is_callable($settings['format'])) {
            return call_user_func($settings['format'], $this, $settings);
        } else {
            return $this->defaultFormat($this, $settings);
        }
    }

    /**
     * Returns a string representation of the value, rounded according to the increment and fixed decimal places.
     *
     * @return string
     */
    public function toString(): string
    {
        $amount = self::rounding($this->intValue / $this->_precision, $this->_settings['increment']);
        // Format with a fixed number of decimals (without thousands separator)
        return number_format($amount, $this->_settings['precision'], '.', '');
    }

    /**
     * Returns the numeric value for JSON serialization.
     *
     * @return float
     */
    public function toJSON(): float
    {
        return $this->value;
    }

    /**
     * Magic method for implicit string conversion.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->toString();
    }
}
