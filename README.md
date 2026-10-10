# BlockGenerator

BlockGenerator is an Axolotl-PM plugin that creates a randomly weighted block beside a fence when water meets the configured conditions.

![A fence and water block generator](https://user-images.githubusercontent.com/60387689/215071694-7d4d28d6-9961-4c8a-bb01-5a6642bd1c40.jpg)

## Requirements and compatibility

- Axolotl-PM with plugin API 5.0.0 or later in the 5.x API series.
- PHP 8.1 or later with the extensions supplied by Axolotl-PM.

The manifest declares API 5.0.0. See [the build workflow](.github/workflows/build.yml) for the pinned Axolotl-PM source used by CI. Compatibility with every intermediate 5.x release has not been tested.

## Installation

Download a PHAR from [GitHub Releases](https://github.com/NhanAZ-Plugins/BlockGenerator/releases), place it in the server's `plugins` directory, and restart the server. The PHAR has no external plugin dependency.

## Configuration and behavior

Edit `plugins/BlockGenerator/config.yml` after the first start. The [`resources/config.yml`](resources/config.yml) file shows the defaults.

- `blocks` maps block names to percentages. Each value must be positive, finite, and the percentages must add up to 100. Block names accepted by the server's item parsers can be used, including supported legacy item IDs. Tiny weights do not expand into a large in-memory list.
- `generatorMode` is `interact` or `nonInteract`. In `interact` mode, water directly beside a fence is replaced. In `nonInteract` mode, an air gap beside the fence is filled when water is two blocks away.
- `checkSource` skips generation when the relevant water is a source block.
- `delayTime` is a nonnegative number of seconds. When delayed, the plugin checks the fence, water, and target again before placing a block, so a changed target is left alone.
- `produceSound` enables the fizz sound when a block is generated. `percentageWarning` logs a warning for weights below 0.1%.

There are no commands or permissions. A generator is triggered by a block update on the fence. The generated block is selected according to the configured percentages.

## Build and development

The [build workflow](.github/workflows/build.yml) tests the water modes and delayed placement, runs PHPStan at maximum level, and uses [DevTools](https://github.com/NhanAZ/DevTools) to build and validate the PHAR. The workflow pins its tools and server source. It leaves GitHub's default run title in place so push runs remain identifiable by commit message.

## License and support

BlockGenerator is distributed under [GPL-3.0](LICENSE). Report bugs through [GitHub issues](https://github.com/NhanAZ-Plugins/BlockGenerator/issues) or join [NhanAZ Discord](https://discord.gg/j2X83ujT6c).
