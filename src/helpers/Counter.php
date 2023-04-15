<?php

namespace Arrays\Helpers;

/* Countable functions */
trait Counter
{
	function count(): int
	{
		return count($this->_internal);
	}
}
