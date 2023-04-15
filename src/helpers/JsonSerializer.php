<?php

namespace Arrays\Helpers;

trait JsonSerializer
{
	function jsonSerialize()
	{
		return $this->_internal;
	}
}
