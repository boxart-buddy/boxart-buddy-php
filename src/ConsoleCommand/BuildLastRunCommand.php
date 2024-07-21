<?php

namespace App\ConsoleCommand;

use App\ApplicationConstant;
use App\Command\Factory\BuildCommandCollectionFactory;
use App\Command\Handler\CentralHandler;
use App\Config\Reader\ConfigReader;
use App\Config\Validator\ConfigValidator;
use App\ConsoleCommand\Interactive\PostProcessChoice;
use App\ConsoleCommand\Interactive\PromptChoices;
use App\Lock\LockIO;
use App\PostProcess\CounterPostProcess;
use App\PostProcess\Option\CounterPostProcessOptions;
use App\PostProcess\Option\TranslationPostProcessOptions;
use App\PostProcess\Option\VerticalDotScrollbarPostProcessOptions;
use App\PostProcess\Option\VerticalScrollbarPostProcessOptions;
use App\PostProcess\TranslationPostProcess;
use App\PostProcess\VerticalDotScrollbarPostProcess;
use App\PostProcess\VerticalScrollbarPostProcess;
use App\Provider\PathProvider;
use App\Reader\SkippedRomReader;
use App\Util\CommandUtility;
use App\Util\Console\BlockSectionHelper;
use App\Util\Path;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Stopwatch\Stopwatch;

#[AsCommand(
    name: 'build-last-run',
    description: 'Build using the last set of inputs used in the interactive command `build`',
)]
class BuildLastRunCommand extends Command
{
    use PlatformOverviewTrait;
    use PreflightCheckTrait;

    public function __construct(
        readonly private BuildCommandCollectionFactory $buildCommandCollectionFactory,
        readonly private CentralHandler $centralHandler,
        readonly private LoggerInterface $logger,
        readonly private ConfigValidator $configValidator,
        readonly private PathProvider $pathProvider,
        readonly private SerializerInterface $serializer,
        readonly private SkippedRomReader $skippedRomReader,
        readonly private LockIO $lockIO,
        readonly private ConfigReader $configReader
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('scrollbar-example', null, InputOption::VALUE_NONE, 'Adds a scrollbar in every position, for demonstration purposes')
            ->addOption('translation-example', null, InputOption::VALUE_REQUIRED, 'Adds a scrollbar in every position, for demonstration purposes')
            ->addOption('counter-example', null, InputOption::VALUE_NONE, 'Adds a counter in every position, for demonstration purposes')
            ->addOption('inner-example', null, InputOption::VALUE_NONE, 'Adds a the inner option, for demonstration purposes')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new BlockSectionHelper($input, $output, $this->logger);
        $io->heading();
        $this->printPlatformOverview($io, $this->configValidator);
        $stopwatch = (new Stopwatch())->start('all');

        $lastRunBuild = $this->lockIO->read(LockIO::KEY_LAST_RUN_BUILD);

        if (!$lastRunBuild) {
            $io->failure('Last run build not found, you need to run `make build` at least once');
        }

        if (!$this->serializer instanceof DenormalizerInterface) {
            throw new \RuntimeException();
        }
        $choices = $this->serializer->denormalize($lastRunBuild, PromptChoices::class);

        if ($input->getOption('scrollbar-example')) {
            $choices = $this->addAllScrollbarChoices($choices);
        }

        if ($input->getOption('counter-example')) {
            $choices = $this->addAllCounterChoices($choices);
        }

        if ($input->getOption('inner-example')) {
            $choices = $this->addInnerChoice($choices);
        }

        if ($input->getOption('translation-example')) {
            $choices = $this->addTranslationChoices($choices, $input->getOption('translation-example'));
        }

        $io->section('build-choices');
        $io->help("Building with last run choices:\n\n".$choices->prettyPrint());

        $this->runPreflightChecks($io, $this->configReader, $this->lockIO);

        $buildCommandCollection = $this->buildCommandCollectionFactory->create($choices);
        $this->centralHandler->handleBuildCommandCollection($buildCommandCollection);

        $packageName = $this->buildCommandCollectionFactory->getPackageName($choices);
        $packageRoot = $this->pathProvider->getPackageRootPath($packageName);

        $event = $stopwatch->stop();
        $size = Path::getDirectorySize($packageRoot);
        $io->complete(sprintf("Build complete in %s\n\n(Package Size %s): %s", CommandUtility::formatStopwatchEvent($event), $size, $packageRoot));

        $skipped = $this->skippedRomReader->getSkippedRomCount();
        if ($skipped > 0) {
            $io->help(sprintf("%s roms were skipped as they were missing from the cache \n see: %s/skipped/ for help", $skipped, ApplicationConstant::DOCS_URL));
        }

        return Command::SUCCESS;
    }

    private function addAllCounterChoices(PromptChoices $promptChoices): PromptChoices
    {
        $choices = [];
        foreach (CounterPostProcessOptions::POSITION_VALUES as $position) {
            $choices[] = new PostProcessChoice(CounterPostProcess::NAME, [CounterPostProcessOptions::POSITION => $position, CounterPostProcessOptions::BACKGROUND => true]);
        }

        return $promptChoices->cloneWithAdditionalPostProcessChoices($choices);
    }

    private function addAllScrollbarChoices(PromptChoices $promptChoices): PromptChoices
    {
        $choices = [];
        $choices[] = new PostProcessChoice(VerticalScrollbarPostProcess::NAME, [VerticalScrollbarPostProcessOptions::POSITION => 'left']);
        $choices[] = new PostProcessChoice(VerticalDotScrollbarPostProcess::NAME, [VerticalDotScrollbarPostProcessOptions::POSITION => 'right']);

        return $promptChoices->cloneWithAdditionalPostProcessChoices($choices);
    }

    private function addInnerChoice(PromptChoices $promptChoices): PromptChoices
    {
        return $promptChoices->cloneWithAdditionalPostProcessChoices([new PostProcessChoice('inner_mask', [])]);
    }

    private function addTranslationChoices(PromptChoices $promptChoices, string $option): PromptChoices
    {
        $positions = match ($option) {
            '1' => ['left', 'right', 'bottom-left', 'bottom-right', 'top-left', 'top-right'],
            '2' => ['top', 'center-top', 'center', 'center-bottom', 'bottom'],
            default => throw new \InvalidArgumentException('Translation option must be 1 or 2')
        };

        $choices = [];
        foreach ($positions as $position) {
            $choices[] = new PostProcessChoice(TranslationPostProcess::NAME, [TranslationPostProcessOptions::POSITION => $position]);
        }

        return $promptChoices->cloneWithAdditionalPostProcessChoices($choices);
    }
}
