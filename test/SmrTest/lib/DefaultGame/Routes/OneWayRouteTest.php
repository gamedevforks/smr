<?php declare(strict_types=1);

namespace SmrTest\lib\DefaultGame\Routes;

use PHPUnit\Framework\TestCase;
use Smr\Path;
use Smr\Routes\OneWayRoute;

/**
 * @covers Smr\Routes\OneWayRoute
 */
class OneWayRouteTest extends TestCase {

	private Path $path;

	protected function setUp(): void {
		$path = new Path(1);
		$path->addLink(2);
		$path->addLink(3);
		$this->path = $path;
	}

	public function test_trivial_getters(): void {
		$route = new OneWayRoute(3, 1, RACE_NEUTRAL, RACE_HUMAN, 2, 1, $this->path, GOODS_ORE);
		self::assertSame(1, $route->getBuySectorId());
		self::assertSame(3, $route->getSellSectorId());
		self::assertSame(RACE_HUMAN, $route->getBuyPortRace());
		self::assertSame(RACE_NEUTRAL, $route->getSellPortRace());
		self::assertSame(1, $route->getBuyDi());
		self::assertSame(2, $route->getSellDi());
		self::assertSame(GOODS_ORE, $route->getGoodID());
	}

	public function test_getExpMultiplierSum(): void {
		$route = new OneWayRoute(3, 1, RACE_NEUTRAL, RACE_HUMAN, 2, 1, $this->path, GOODS_ORE);
		self::assertSame(3, $route->getExpMultiplierSum());
	}

	/**
	 * @dataProvider dataProvider_getMoneyMultiplierSum
	 */
	public function test_getMoneyMultiplierSum(int $goodID, int $expected): void {
		$route = new OneWayRoute(3, 1, RACE_NEUTRAL, RACE_HUMAN, 2, 1, $this->path, $goodID);
		self::assertSame($expected, $route->getMoneyMultiplierSum());
	}

	public function dataProvider_getMoneyMultiplierSum(): array {
		return [
			[GOODS_NOTHING, 0],
			[GOODS_ORE, 54],
		];
	}

	/**
	 * @dataProvider dataProvider_getTurnsForRoute
	 */
	public function test_getTurnsForRoute(int $goodID, int $expected): void {
		$route = new OneWayRoute(3, 1, RACE_NEUTRAL, RACE_HUMAN, 0, 0, $this->path, $goodID);
		self::assertSame($this->path->getTurns() + $expected, $route->getTurnsForRoute());
	}

	public function dataProvider_getTurnsForRoute(): array {
		return [
			[GOODS_NOTHING, 0],
			[GOODS_ORE, 2],
		];
	}

	public function test_containsPort(): void {
		$route = new OneWayRoute(3, 1, RACE_NEUTRAL, RACE_HUMAN, 2, 1, $this->path, GOODS_ORE);
		// Only the endpoints of the route should return True
		self::assertTrue($route->containsPort(1));
		self::assertFalse($route->containsPort(2));
		self::assertTrue($route->containsPort(3));
		self::assertFalse($route->containsPort(4));
	}

	public function test_getRoutes(): void {
		$route = new OneWayRoute(3, 1, RACE_NEUTRAL, RACE_HUMAN, 2, 1, $this->path, GOODS_ORE);
		self::assertNull($route->getForwardRoute());
		self::assertNull($route->getReturnRoute());
	}

	public function test_getRouteString(): void {
		$route = new OneWayRoute(3, 1, RACE_NEUTRAL, RACE_HUMAN, 2, 1, $this->path, GOODS_ORE);
		$expected = '1 (Human) buy Ore for 1x to sell at 3 (Neutral) for 2x (Distance: 2)';
		self::assertSame($expected, $route->getRouteString());
	}

}
