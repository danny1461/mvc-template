<?php

namespace Lib\Database;

interface IDatabaseAdapter {
	public const MIN_DATETIME_VALUE = '1000-01-01 00:00:00';
	public const MAX_DATETIME_VALUE = '9999-12-31 23:59:59';
	public const DATETIME_FORMAT = 'Y-m-d H:i:s';
	public const DATE_FORMAT = 'Y-m-d';

	function query($sql, ...$args);
	public function populateQuery($sql, ...$args);
	function getLastError();
	function getLastQuery();
	function inTransaction();
	function startTransaction();
	function abortTransaction();
	function commitTransaction();
	function withTransaction(callable $fn);
	function trackModel(TrackTypeEnum $type, callable $cb);
}