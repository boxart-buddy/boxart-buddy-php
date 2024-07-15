<?php

namespace App\ConsoleCommand;

use App\Command\Factory\BuildCommandCollectionFactory;
use App\Command\Handler\CentralHandler;
use App\Config\Reader\ConfigReader;
use App\Config\Validator\ConfigValidator;
use App\ConsoleCommand\Interactive\PostProcessChoice;
use App\ConsoleCommand\Interactive\PromptChoices;
use App\ConsoleCommand\Interactive\PromptOptionsGenerator;
use App\Lock\LockIO;
use App\PostProcess\Option\CounterPostProcessOptions;
use App\PostProcess\Option\TranslationPostProcessOptions;
use App\PostProcess\Option\VerticalDotScrollbarPostProcessOptions;
use App\PostProcess\Option\VerticalScrollbarPostProcessOptions;
use App\Provider\PathProvider;
use App\Reader\SkippedRomReader;
use App\Util\CommandUtility;
use App\Util\Console\BlockSectionHelper;
use App\Util\Path;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Stopwatch\Stopwatch;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\search;
use function Laravel\Prompts\select;

#[AsCommand(
    name: 'build-interactive',
    description: 'Build artwork by answering questions on the command prompt',
)]
class BuildInteractiveCommand extends Command
{
    use PlatformOverviewTrait;
    use PreflightCheckTrait;

    public function __construct(
        readonly private PromptOptionsGenerator $promptOptionsGenerator,
        readonly private BuildCommandCollectionFactory $buildCommandCollectionFactory,
        readonly private CentralHandler $centralHandler,
        readonly private LoggerInterface $logger,
        readonly private ConfigValidator $configValidator,
        readonly private PathProvider $pathProvider,
        readonly private SkippedRomReader $skippedRomReader,
        readonly private LockIO $lockIO,
        readonly private ConfigReader $configReader
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new BlockSectionHelper($input, $output, $this->logger);
        $io->heading();
        $this->printPlatformOverview($io, $this->configValidator);
        $this->runPreflightChecks($io, $this->configReader, $this->lockIO);

        $stopwatch = (new Stopwatch())->start('all');

        $options = $this->promptOptionsGenerator->generate();

        // yml to option parser
        $package = (string) select(
            'Select Template',
            $options->getPackages(),
            null,
            8
        );

        $variant = (string) select(
            'Select Variant',
            $options->getVariants($package),
            null,
            8
        );

        $optionChoices = multiselect(
            'Select Options (spacebar to select, enter to confirm)',
            $options->getOptions($package, $variant),
            $options->getOptionDefaults($package, $variant),
            8
        );

        $choiceOptionNames = ['artwork', 'folder', 'portmaster', 'inner', 'counter', 'scrollbar', 'translation', 'zip', 'transfer'];
        foreach ($choiceOptionNames as $optionName) {
            $$optionName = false;
            if (in_array($optionName, $optionChoices, true)) {
                $$optionName = true;
            }
        }

        $postProcessChoices = [];
        if ($inner) { // @phpstan-ignore variable.undefined
            $postProcessChoices[] = new PostProcessChoice('inner_mask', []);
        }
        if ($counter) { // @phpstan-ignore variable.undefined
            $postProcessChoices[] = $this->getCounterPostProcessChoice();
        }
        if ($scrollbar) { // @phpstan-ignore variable.undefined
            $postProcessChoices[] = $this->getScrollBarPostProcessChoice();
        }
        if ($translation) { // @phpstan-ignore variable.undefined
            $postProcessChoices[] = $this->getTranslationPostProcessChoice();
        }

        $theme = 'default';
        if ($this->promptOptionsGenerator->isThemeable($package, $variant, $postProcessChoices)) {
            $themeOptions = Collection::make($options->getThemes());

            $theme = search(
                'Select Theme',
                fn (string $value) => strlen($value) > 0 ? $themeOptions->filter(function ($q) use ($value) { return Str::contains($q, strtolower($value)); })->all() : $themeOptions->all(),
                'default',
                10
            );

            if (!is_string($theme)) {
                throw new \LogicException();
            }
        }

        if ('default' === $theme) {
            $theme = null;
        }

        $choices = new PromptChoices(
            $package,
            $variant,
            $artwork, // @phpstan-ignore variable.undefined
            $folder, // @phpstan-ignore variable.undefined
            $portmaster, // @phpstan-ignore variable.undefined
            $zip, // @phpstan-ignore variable.undefined
            $transfer, // @phpstan-ignore variable.undefined
            $postProcessChoices,
            $theme
        );
        $this->lockIO->write(LockIO::KEY_LAST_RUN_BUILD, $choices);

        $buildCommandCollection = $this->buildCommandCollectionFactory->create($choices);
        $this->centralHandler->handleBuildCommandCollection($buildCommandCollection);

        $packageName = $this->buildCommandCollectionFactory->getPackageName($choices);
        $packageRoot = $this->pathProvider->getPackageRootPath($packageName);

        $event = $stopwatch->stop();
        $size = Path::getDirectorySize($packageRoot);
        $io->complete(sprintf("Build complete in %s\n\n(Package Size %s): %s", CommandUtility::formatStopwatchEvent($event), $size, $packageRoot));

        $skipped = $this->skippedRomReader->getSkippedRomCount();
        if ($skipped > 0) {
            $io->help(sprintf("%s roms were skipped as they were missing from the cache \n see: https://boxart-buddy.github.io/boxart-buddy/skipped/ for help", $skipped));
        }

        return Command::SUCCESS;
    }

    private function getCounterPostProcessChoice(): PostProcessChoice
    {
        $options = [];
        // ask counter questions
        $bg = select(
            'Give the counter a background?',
            ['No', 'Yes'],
            'No'
        );

        if ('Yes' === $bg) {
            $options[CounterPostProcessOptions::BACKGROUND] = true;
        }

        $variant = select(
            'Counter Style',
            ['simple' => 'Simple', 'circular' => 'Circular (kinda ugly!)'],
            'simple'
        );
        $options[CounterPostProcessOptions::VARIANT] = $variant;

        $position = select(
            'Counter Position: ',
            CounterPostProcessOptions::POSITION_VALUES,
            'simple'
        );
        $options[CounterPostProcessOptions::POSITION] = $position;

        return new PostProcessChoice('counter', $options);
    }

    private function getScrollBarPostProcessChoice(): PostProcessChoice
    {
        $options = [];
        $type = select(
            'Scrollbar Type?',
            ['bar', 'dots'],
            'bar'
        );

        $position = select(
            'Scrollbar Position',
            ['left', 'right'],
            'right'
        );
        if ('bar' === $type) {
            $options[VerticalScrollbarPostProcessOptions::POSITION] = $position;
            $options[VerticalScrollbarPostProcessOptions::OPACITY] = 85;

            return new PostProcessChoice('vertical_scrollbar', $options);
        }
        if ('dots' === $type) {
            $options[VerticalDotScrollbarPostProcessOptions::POSITION] = $position;
            $options[VerticalDotScrollbarPostProcessOptions::OPACITY] = 85;

            return new PostProcessChoice('vertical_dot_scrollbar', $options);
        }
        throw new \LogicException();
    }

    private function getTranslationPostProcessChoice(): PostProcessChoice
    {
        $position = select(
            'Translation Position',
            TranslationPostProcessOptions::POSITIONS,
            'center-bottom'
        );

        return new PostProcessChoice('translation', [TranslationPostProcessOptions::POSITION => $position]);
    }
}
