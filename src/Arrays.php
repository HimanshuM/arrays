<?php

namespace Arrays;

use ArrayAccess;
use Exception;
use Countable;
use Iterator;
use JsonSerializable;
use Serializable;

use Accessors\Accessor;
use Arrays\Helpers\Nil;

if (!defined("nil")) {
	define ("nil", Nil::nil());
}

class Arrays implements ArrayAccess, Countable, Iterator, JsonSerializable, Serializable
{
	use Helpers\Accessor;
	use Helpers\Counter;
	use Helpers\IndexFetcher;
	use Helpers\TIterator;
	use Helpers\JsonSerializer;
	use Helpers\Serializer;

	use Accessor;

	protected $_internal = [];
	protected $_keys = [];
	protected $_list = [];
	protected $_position = 0;

	const Ones = [
		"first",
		"second",
		"third",
		"fourth",
		"fifth",
		"sixth",
		"seventh",
		"eigth",
		"nineth"
	];

	const Tens = [
		"tenth" => 10,
		"eleventh" => 11,
		"twelfth" => 12,
		"thirteenth" => 13,
		"fourteenth" => 14,
		"fifteenth" => 15,
		"sixteenth" => 16,
		"seventeenth" => 17,
		"eighteenth" => 18,
		"nineteenth" => 19,
		"twentieth" => "twenty", /* 10th index */
		"thirtieth" => "thirty",
		"fourtieth" => "fourty",
		"fiftieth" => "fifty",
		"sixtieth" => "sixty",
		"seventieth" => "seventy",
		"eightieth" => "eighty",
		"ninetieth" => "ninety"
	];

	function __construct(array|Arrays $arr = [])
	{
		if (!is_array($arr)) {
			if (is_a($arr, Arrays::class)) {
				$arr = $arr->_internal;
			}
			else {
				$arr = [$arr];
			}
		}

		$this->_internal = $arr;
		$this->_reevaluate();

		$this->methodsAsProperties("all", "array", "count", "empty", "first", "join", "keys", "last", "length", "pop", "shift", "skip", "take", "unique", "values");
		$this->readonly(["toArray", "array"]);
		$this->notFoundResponse(ACCESSOR_NOT_FOUND_CALLBACK, "fetchByIndex");
	}

	function __debugInfo()
	{
		return $this->_internal;
	}

	/* Self defined functions */
	function all(int $length = null): array
	{
		if (empty($offset)) {
			return $this->array();
		}

		return array_slice($this->_internal, 0, $length);
	}

	function append(mixed $value): Arrays
	{
		$this->_internal[] = $value;
		$this->_reevaluate();
		return $this;
	}

	function array(): array
	{
		return $this->_internal;
	}

	function clear(): Arrays
	{
		$this->_internal = [];
		$this->_keys = [];
		$this->_list = [];

		return $this;
	}

	protected function copyFrom(Arrays $array)
	{
		if (!is_a($array, Arrays::class)) {
			return false;
		}

		$this->_keys = $array->_keys;
		$this->_list = $array->_list;
		$this->_internal = $array->_internal;
		$this->_position = $array->_position;
	}

	function delete($key): mixed
	{
		$value = null;
		if (isset($this->_keys[$key])) {

			$value = $this->_internal[$key];
			unset($this->_internal[$key]);
			$this->_reevaluate();

		}

		return $value;
	}

	function diff(array|Arrays $arr): Arrays
	{
		if (is_a($arr, Arrays::class)) {
			$arr = $arr->_internal;
		}
		else if (!is_array($arr)) {
			$arr = [$arr];
		}

		return new static(array_diff($this->_internal, $arr));
	}

	function empty(): bool
	{
		return empty($this->_internal);
	}

	function exists($key): bool
	{
		return isset($this->_keys[$key]);
	}

	static function explode(string $delimiter, string $string): Arrays
	{
		return new static(explode($delimiter, $string));
	}

	function fetch($key): Arrays
	{
		if (is_a($key, Arrays::class)) {
			$key = $key->_internal;
		}

		if (is_array($key)) {
			if (!empty($values = array_intersect_key($this->_internal, array_flip($key)))) {
				return new static($values);
			}
		}
		else if (isset($this->_keys[$key])) {
			if (!is_array(($value = $this->_internal[$key]))) {
				return $value;
			}

			return new static($value);
		}

		if (func_num_args() == 1) {
			throw new Exception("Invalid index '$key'", 1);
		}
		else {
			$arg = func_get_arg(1);
			if (is_callable($arg)) {
				call_user_func_array($arg, [$key]);
			}
			else if (is_a($arg, "Exception")) {
				throw $arg;
			}
			else {
				return $arg;
			}
		}
	}

	function filter($callback, $flag = 0): Arrays
	{
		if (is_string($callback) && $callback[0] == ":") {
			return $this->invoke(substr($callback, 1), 1);
		}

		return new static(array_filter($this->_internal, $callback, $flag));
	}

	function firstFew(int $length): Arrays
	{
		if (empty($this->_internal)) {
			return false;
		}

		return new static(array_slice($this->_internal, 0, $length));
	}

	function has(mixed $value): bool
	{
		return in_array($value, $this->_internal);
	}

	function hasKey($key): bool
	{
		return $this->exists($key);
	}

	function implode(string $delimiter): string
	{
		return implode($delimiter, $this->_internal);
	}

	function indexOf(mixed $value)
	{
		return array_search($value, $this->_internal);
	}

	function ignore(array|Arrays $keys = []): Arrays
	{
		if (is_a($keys, Arrays::class)) {
			$keys = $keys->_internal;
		}
		else if (!is_array($keys)) {
			$keys = [$keys];
		}

		return new static(array_diff_key($this->_internal, array_flip($keys)));
	}

	function intersect(array|Arrays $arr): Arrays
	{
		if (is_a($arr, Arrays::class)) {
			$arr = $arr->_internal;
		}
		else if (!is_array($arr)) {
			$arr = [$arr];
		}

		return new static(array_intersect($this->_internal, $arr));
	}

	function invoke($arg, $map = 0): Arrays
	{
		$walkers = ["array_map", "array_filter", "array_walk"];

		if ($map >= count($walkers)) {
			$map = 0;
		}

		$param = function($e) use ($arg) {
			if (method_exists($e, $arg)) {
				return $e->$arg();
			}

			return $e->$arg;
		};

		$map = $walkers[$map];
		if ($map == "array_map") {
			return new static($map($param, $this->_internal));
		}

		return new static($map($this->_internal, $param));
	}

	function join(string $delimiter = "_"): string
	{
		return implode($delimiter, $this->_internal);
	}

	function keys(): Arrays
	{
		return new static($this->_list);
	}

	function last(mixed $value = nil): mixed
	{
		if (empty($this->_internal)) {
			return false;
		}

		if ($value === nil) {
			return $this->_internal[$this->_list[count($this->_list) - 1]];
		}

		$this->_internal[$this->_list[count($this->_list) - 1]] = $value;
	}

	function lastFew(int $offset): Arrays
	{
		if (empty($this->_internal)) {
			return false;
		}

		return new static(array_slice($this->_internal, -$offset));
	}

	function length(): int
	{
		return count($this->_internal);
	}

	function map($closure): Arrays
	{
		if (is_string($closure) && $closure[0] == ":") {
			return $this->invoke(substr($closure, 1));
		}

		$args = [$closure, $this->_internal];

		if (func_num_args() > 1) {
			$otherArgs = array_slice(func_get_args(), 1);
			foreach ($otherArgs as $arg) {
				if (is_array($arg)) {
					$args[] = $arg;
				}
				else if (is_a($arg, Arrays::class)) {
					$args[] = $arg->_internal;
				}
				else {
					$args[] = [$arg];
				}
			}
		}

		return new static(call_user_func_array("array_map", $args));
	}

	function merge(array|Arrays $arr = []): Arrays
	{
		if (is_a($arr, Arrays::class)) {
			$arr = $arr->_internal;
		}

		if (!is_array($arr)) {
			$this->_internal[] = $arr;
		}
		else {
			$this->_internal = array_merge($this->_internal, $arr);
		}

		$this->_reevaluate();

		return $this;
	}

	function pick(array|Arrays $keys = []): Arrays
	{
		if (is_a($keys, Arrays::class)) {
			$keys = $keys->_internal;
		}
		elseif (!is_array($keys)) {
			$keys = [$keys];
		}

		return new static(array_intersect_key($this->_internal, array_flip($keys)));
	}

	function pluck(): Arrays
	{
		$keys = func_get_args();

		$return = new static;
		foreach ($this->_internal as $each) {
			$return[] = new static(array_values(array_intersect_key($each, array_flip($keys))));
		}

		return $return;
	}

	function pop(): mixed
	{
		if (empty($this->_internal)) {
			return null;
		}

		unset($this->_keys[array_pop($this->_list)]);
		return array_pop($this->_internal);
	}

	function positionOf(mixed $value): int
	{
		$position = array_search($value, $this->_internal);
		return $position === false ? -1 : $position + 1;
	}

	function prepend(mixed $value): Arrays
	{
		array_unshift($this->_internal, $value);
		$this->_reevaluate();

		return $this;
	}

	static function range(int $start, int $end, int $step = 1): Arrays
	{
		return new static(range($start, $end, $step));
	}

	function recursiveMerge(array|Arrays $arr = []): Arrays
	{
		if (is_a($arr, Arrays::class)) {
			$arr = $arr->_internal;
		}

		if (!is_array($arr)) {
			$this->_internal[] = $arr;
		}
		else {
			$this->_internal = array_merge_recursive($this->_internal, $arr);
		}

		$this->_reevaluate();

		return $this;
	}

	function _reevaluate()
	{
		$this->_list = array_keys($this->_internal);
		$this->_keys = array_flip($this->_list);
	}

	function shift(): mixed
	{
		if (empty($this->_internal)) {
			return null;
		}

		$r = array_shift($this->_internal);
		$this->_reevaluate();

		return $r;
	}

	function skip(int $offset = 0): Arrays
	{
		if (empty($this->_internal)) {
			return null;
		}

		return new static(array_slice($this->_internal, $offset));
	}

	function slice(int $offset = 0, $length = null, bool $preserveKey = false): Arrays
	{
		return new static(array_slice($this->_internal, $offset, $length, $preserveKey));
	}

	function splice(int $offset = 0, int $length = null, mixed $replacement = []): Arrays
	{
		if (is_null($length)) {
			$length = count($this->_internal);
		}

		array_splice($this->_internal, $offset, $length, $replacement);
		$this->_reevaluate();

		return $this;
	}

	static function split(string $delimiter, string $string): Arrays
	{
		return new static(explode($delimiter, $string));
	}

	function take(int $length): Arrays
	{
		return $this->firstFew($length);
	}

	function unique(int $flag = SORT_STRING): Arrays
	{
		return new static(array_unique($this->_internal, $flag));
	}

	function values(): Arrays
	{
		return new static(array_values($this->_internal));
	}

	function walk($callback, $userData = null): bool
	{
		if (is_string($callback) && $callback[0] == ":") {
			return $this->invoke(substr($callback, 1), 2);
		}

		return array_walk($this->_internal, $callback, $userData);
	}

}

?>
