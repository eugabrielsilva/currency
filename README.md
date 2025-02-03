## Currency for PHP

This package is a translation of [currency.js](https://github.com/scurker/currency.js) for PHP.

### Installation

```
composer require eugabrielsilva/currency
```

### Usage

**With real numbers:**

```php
$result = currency(3.2)->multiply(0.5)->add(1)->value;
```

**With currency instances:**

```php
$value1 = currency(3.2);
$value2 = currency(0.5);
$value3 = currency(1);

$result = $value1->multiply($value2)->add($value3)->value;
```

**Formatting:**

```php
$value = currency(3.2)->multiply(0.5)->add(1);

echo $value->format();
```
