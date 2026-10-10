<?php

declare(strict_types=1);

namespace pocketmine\math {
	final class Facing {
		public const OFFSET = [[0, -1, 0], [0, 1, 0], [0, 0, -1], [0, 0, 1], [-1, 0, 0], [1, 0, 0]];
		public const ALL = [0, 1, 2, 3, 4, 5];
	}
}

namespace pocketmine\world {
	final class Position {
		public function __construct(public int $x, public int $y, public int $z, private World $world) {}
		public function getWorld(): World { return $this->world; }
		public function isValid(): bool { return true; }
		public function asPosition(): self { return clone $this; }
		public function add(float $x, float $y, float $z): self { return clone $this; }
	}
	final class World {
		/** @var array<string, \pocketmine\block\Block> */
		private array $blocks = [];
		private function key(int $x, int $y, int $z): string { return "$x:$y:$z"; }
		public function getBlockAt(int $x, int $y, int $z): \pocketmine\block\Block {
			return $this->blocks[$this->key($x, $y, $z)] ?? new \pocketmine\block\Block(0, new Position($x, $y, $z, $this));
		}
		public function getBlock(Position $position): \pocketmine\block\Block {
			return $this->getBlockAt($position->x, $position->y, $position->z);
		}
		public function setBlock(Position $position, \pocketmine\block\Block $block, bool $update = true): void {
			$copy = clone $block;
			$copy->position(new Position($position->x, $position->y, $position->z, $this));
			$this->blocks[$this->key($position->x, $position->y, $position->z)] = $copy;
		}
		public function addSound(Position $position, object $sound): void {}
	}
}

namespace pocketmine\block {
	class Block {
		public function __construct(private int $type, private \pocketmine\world\Position $position) {}
		public function getPosition(): \pocketmine\world\Position { return $this->position; }
		public function position(\pocketmine\world\Position $position): void { $this->position = $position; }
		public function getTypeId(): int { return $this->type; }
		public function getSide(int $facing, int $step = 1): self {
			[$x, $y, $z] = \pocketmine\math\Facing::OFFSET[$facing];
			$p = $this->position;
			return $p->getWorld()->getBlockAt($p->x + $x * $step, $p->y + $y * $step, $p->z + $z * $step);
		}
	}
	final class Fence extends Block {}
	final class Water extends Block {
		public function __construct(\pocketmine\world\Position $position, private bool $source = false) { parent::__construct(8, $position); }
		public function isSource(): bool { return $this->source; }
	}
	final class BlockTypeIds { public const AIR = 0; }
}

namespace pocketmine\item {
	final class ItemBlock {
		public function __construct(private \pocketmine\block\Block $block) {}
		public function getBlock(): \pocketmine\block\Block { return $this->block; }
	}
	final class StringToItemParser {
		public static function getInstance(): self { return new self(); }
		public function parse(string $name): ?ItemBlock {
			$type = match ($name) { 'stone' => 7, 'cobblestone' => 9, default => null };
			return $type === null ? null : new ItemBlock(new \pocketmine\block\Block($type, new \pocketmine\world\Position(0, 0, 0, new \pocketmine\world\World())));
		}
	}
	final class LegacyStringToItemParser {
		public static function getInstance(): self { return new self(); }
		public function parse(string $name): ?ItemBlock { return null; }
	}
	final class LegacyStringToItemParserException extends \RuntimeException {}
}

namespace pocketmine\event {
	interface Listener {}
}

namespace pocketmine\event\block {
	final class BlockUpdateEvent {
		public function __construct(private \pocketmine\block\Block $block) {}
		public function getBlock(): \pocketmine\block\Block { return $this->block; }
	}
}

namespace pocketmine\scheduler {
	final class ClosureTask {
		public function __construct(private \Closure $callback) {}
		public function run(): void { ($this->callback)(); }
	}
}

namespace pocketmine\world\sound {
	final class FizzSound {}
}

namespace pocketmine\plugin {
	class PluginBase {
		/** @var array<string, mixed> */
		public static array $config = [];
		/** @var list<\pocketmine\scheduler\ClosureTask> */
		public static array $tasks = [];
		public function saveDefaultConfig(): void {}
		public function getConfig(): object { return new class {
			public function get(string $key): mixed { return PluginBase::$config[$key] ?? null; }
		}; }
		public function getServer(): object { return new class {
			public function getPluginManager(): object { return new class {
				public function registerEvents(object $listener, object $plugin): void {}
			}; }
		}; }
		public function getScheduler(): object { return new class {
			public function scheduleDelayedTask(\pocketmine\scheduler\ClosureTask $task, int $ticks): void { PluginBase::$tasks[] = $task; }
		}; }
		public function getLogger(): object { return new class { public function warning(string $message): void {} }; }
	}
}

namespace {
	require __DIR__ . '/../src/NhanAZ/BlockGenerator/Main.php';

	use NhanAZ\BlockGenerator\Main;
	use pocketmine\block\Block;
	use pocketmine\block\Fence;
	use pocketmine\block\Water;
	use pocketmine\event\block\BlockUpdateEvent;
	use pocketmine\plugin\PluginBase;
	use pocketmine\world\Position;
	use pocketmine\world\World;

	function check(bool $condition, string $message): void {
		if (!$condition) { throw new \RuntimeException($message); }
	}

	function plugin(string $mode, int $delay = 0, bool $checkSource = false): Main {
		PluginBase::$config = [
			'blocks' => ['stone' => 100.0],
			'generatorMode' => $mode,
			'checkSource' => $checkSource,
			'produceSound' => false,
			'percentageWarning' => false,
			'delayTime' => $delay,
		];
		PluginBase::$tasks = [];
		$plugin = new Main();
		(new \ReflectionMethod(Main::class, 'onEnable'))->invoke($plugin);
		return $plugin;
	}

	$world = new World();
	$fencePosition = new Position(0, 0, 0, $world);
	$east = new Position(1, 0, 0, $world);
	$farEast = new Position(2, 0, 0, $world);
	$world->setBlock($fencePosition, new Fence(2, $fencePosition));
	$world->setBlock($east, new Water($east));
	$interact = plugin('interact');
	$interact->onBlockUpdate(new BlockUpdateEvent($world->getBlock($fencePosition)));
	check($world->getBlock($east)->getTypeId() === 7, 'Direct water interaction did not generate configured block');

	$world->setBlock($east, new Block(0, $east));
	$world->setBlock($farEast, new Water($farEast));
	$nonInteract = plugin('nonInteract');
	$nonInteract->onBlockUpdate(new BlockUpdateEvent($world->getBlock($fencePosition)));
	check($world->getBlock($east)->getTypeId() === 7, 'Water across an air gap did not generate block');

	$world->setBlock($east, new Water($east));
	$delayed = plugin('interact', 1);
	$delayed->onBlockUpdate(new BlockUpdateEvent($world->getBlock($fencePosition)));
	check(count(PluginBase::$tasks) === 1, 'Expected one delayed generation task');
	$world->setBlock($east, new Block(99, $east));
	PluginBase::$tasks[0]->run();
	check($world->getBlock($east)->getTypeId() === 99, 'Delayed task overwrote a changed block');

	$world->setBlock($east, new Water($east, true));
	$sourceSafe = plugin('interact', 0, true);
	$sourceSafe->onBlockUpdate(new BlockUpdateEvent($world->getBlock($fencePosition)));
	check($world->getBlock($east)->getTypeId() === 8, 'Source water was replaced despite checkSource');

	PluginBase::$config['blocks'] = ['stone' => 0.00000001, 'cobblestone' => 99.99999999];
	$tinyWeight = new Main();
	(new \ReflectionMethod(Main::class, 'onEnable'))->invoke($tinyWeight);
	check(count((new \ReflectionProperty(Main::class, 'weightedBlocks'))->getValue($tinyWeight)) === 2, 'Tiny weight expanded into a large block list');
	PluginBase::$config['blocks'] = ['stone' => 50.0, 'unknown' => 50.0];
	try {
		(new \ReflectionMethod(Main::class, 'onEnable'))->invoke(new Main());
		throw new \RuntimeException('Unknown block was accepted');
	} catch (\InvalidArgumentException) {}

	echo "Direct, gap, delayed, source-water, bounded-weight, and invalid-block checks passed\n";
}
