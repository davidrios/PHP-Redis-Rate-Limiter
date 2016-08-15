<?php
class RedisRateLimiter {
	protected $connection;
	protected $limits;

	public function __construct($connection, $limits) {
		$this->connection = $connection;
		$this->limits = $limits;
	}

	public function update() {
		$name = $_SERVER['REMOTE_ADDR'];
		$now = time();

		$pipe = $this->connection->multi(Redis::PIPELINE);
		foreach ($this->limits as $prec => $limit) {
			$pnow = intval($now / $prec);
			$hash = sprintf('%s:%s', $prec, $name);
			$pipe->zAdd('known:', 0, $hash);
			$pipe->hIncrBy('count:' . $hash, $pnow, 1);
		}
		$pipe->exec();
	}

	public function is_allowed() {
		$name = $_SERVER['REMOTE_ADDR'];

		foreach ($this->limits as $prec => $limit) {
			$hash = sprintf('%s:%s', $prec, $name);
			$data = $this->connection->hGetAll('count:' . $hash);
			$counters = array();

			foreach ($data as $key => $value) {
				$counters[] = array(intval($key), intval($value));
			}

			ksort($counters);

			$latest_counter = $counters[count($counters) - 1];
			if ($latest_counter[1] >= $limit) {
				return FALSE;
			}
		}

		return TRUE;
	}
}