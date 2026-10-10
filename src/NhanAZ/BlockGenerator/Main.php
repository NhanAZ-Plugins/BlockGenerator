<?php

declare(strict_types=1);

namespace NhanAZ\BlockGenerator;

use InvalidArgumentException;
use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\Fence;
use pocketmine\block\Water;
use pocketmine\event\block\BlockUpdateEvent;
use pocketmine\event\Listener;
use pocketmine\item\ItemBlock;
use pocketmine\item\LegacyStringToItemParser;
use pocketmine\item\LegacyStringToItemParserException;
use pocketmine\item\StringToItemParser;
use pocketmine\math\Facing;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\Position;
use pocketmine\world\sound\FizzSound;

final class Main extends PluginBase implements Listener {

	/** @var list<array{limit: float, block: Block}> */
	private array $weightedBlocks = [];
	private float $totalWeight = 0.0;
	private string $mode;
	private bool $checkSource;
	private bool $produceSound;
	private int $delayTicks;

	protected function onEnable(): void {
		$this->saveDefaultConfig();
		$this->loadConfiguration();
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	private function parseBlock(string $name): Block {
		try {
			$item = StringToItemParser::getInstance()->parse($name)
				?? LegacyStringToItemParser::getInstance()->parse($name);
		} catch (LegacyStringToItemParserException $e) {
			throw new InvalidArgumentException("Unknown block in configuration: {$name}", 0, $e);
		}
		if (!$item instanceof ItemBlock) {
			throw new InvalidArgumentException("Configured item is not a block: {$name}");
		}
		return $item->getBlock();
	}

	private function loadConfiguration(): void {
		$config = $this->getConfig();
		$blocks = $config->get('blocks');
		if (!is_array($blocks) || $blocks === []) {
			throw new InvalidArgumentException('Configure at least one block weight');
		}
		foreach ($blocks as $name => $weight) {
			if (!is_string($name) || !is_numeric($weight)) {
				throw new InvalidArgumentException('Each block needs a numeric percentage');
			}
			$value = (float) $weight;
			if (!is_finite($value) || $value <= 0.0) {
				throw new InvalidArgumentException("The percentage for {$name} must be finite and positive");
			}
			$this->totalWeight += $value;
			$this->weightedBlocks[] = ['limit' => $this->totalWeight, 'block' => $this->parseBlock($name)];
			if ($value < 0.1 && $config->get('percentageWarning') === true) {
				$this->getLogger()->warning("The percentage for {$name} is below 0.1%");
			}
		}
		if (abs($this->totalWeight - 100.0) > 0.000001) {
			throw new InvalidArgumentException('Block percentages must total 100%');
		}
		$mode = $config->get('generatorMode');
		if ($mode !== 'interact' && $mode !== 'nonInteract') {
			throw new InvalidArgumentException('generatorMode must be interact or nonInteract');
		}
		$this->mode = $mode;
		$checkSource = $config->get('checkSource');
		$produceSound = $config->get('produceSound');
		if (!is_bool($checkSource) || !is_bool($produceSound)) {
			throw new InvalidArgumentException('checkSource and produceSound must be booleans');
		}
		$this->checkSource = $checkSource;
		$this->produceSound = $produceSound;
		$delay = $config->get('delayTime');
		if (!is_numeric($delay) || !is_finite((float) $delay) || (float) $delay < 0.0) {
			throw new InvalidArgumentException('delayTime must be a finite nonnegative number of seconds');
		}
		$this->delayTicks = $delay == 0 ? 0 : max(1, (int) round((float) $delay * 20));
	}

	private function pickBlock(): Block {
		$roll = random_int(0, PHP_INT_MAX - 1) / PHP_INT_MAX * $this->totalWeight;
		foreach ($this->weightedBlocks as $entry) {
			if ($roll < $entry['limit']) {
				return $entry['block'];
			}
		}
		throw new \LogicException('No configured block matched the random weight');
	}

	private function eligibleTarget(Fence $fence, int $facing): ?Block {
		$target = $fence->getSide($facing);
		$water = $this->mode === 'interact' ? $target : $fence->getSide($facing, 2);
		if (!$water instanceof Water || ($this->checkSource && $water->isSource())) {
			return null;
		}
		if ($this->mode === 'interact' && !($target instanceof Water)) {
			return null;
		}
		if ($this->mode === 'nonInteract' && $target->getTypeId() !== BlockTypeIds::AIR) {
			return null;
		}
		return $target;
	}

	private function generate(Position $fencePosition, int $facing): void {
		if (!$fencePosition->isValid()) {
			return;
		}
		$fence = $fencePosition->getWorld()->getBlock($fencePosition);
		if (!$fence instanceof Fence || ($target = $this->eligibleTarget($fence, $facing)) === null) {
			return;
		}
		$position = $target->getPosition();
		$position->getWorld()->setBlock($position, $this->pickBlock(), false);
		if ($this->produceSound) {
			$position->getWorld()->addSound($position->add(0.5, 0.5, 0.5), new FizzSound());
		}
	}

	public function onBlockUpdate(BlockUpdateEvent $event): void {
		$fence = $event->getBlock();
		if (!$fence instanceof Fence) {
			return;
		}
		foreach (Facing::ALL as $facing) {
			if ($this->eligibleTarget($fence, $facing) === null) {
				continue;
			}
			$position = $fence->getPosition()->asPosition();
			if ($this->delayTicks > 0) {
				$this->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($position, $facing): void {
					$this->generate($position, $facing);
				}), $this->delayTicks);
			} else {
				$this->generate($position, $facing);
			}
		}
	}
}
