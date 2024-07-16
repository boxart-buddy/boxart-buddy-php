<?php

namespace App\ConsoleCommand;

use App\Command\Factory\BuildCommandCollectionFactory;
use App\Command\Handler\CentralHandler;
use App\Config\Reader\ConfigReader;
use App\Config\Validator\ConfigValidator;
use App\ConsoleCommand\Interactive\PostProcessChoice;
use App\ConsoleCommand\Interactive\PromptChoices;
use App\ConsoleCommand\Interactive\PromptOptionsGenerator;
use App\Util\Console\BlockSectionHelper;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'build-all',
    description: 'Builds every template/variant with default options',
)]
class BuildAllCommand extends Command
{
    use PlatformOverviewTrait;

    public function __construct(
        readonly private PromptOptionsGenerator $promptOptionsGenerator,
        readonly private BuildCommandCollectionFactory $buildCommandCollectionFactory,
        readonly private CentralHandler $centralHandler,
        readonly private LoggerInterface $logger,
        readonly private ConfigValidator $configValidator,
        readonly private ConfigReader $configReader
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('template', 't', InputOption::VALUE_REQUIRED, 'a template, if provided then build will be restricted to that template only')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new BlockSectionHelper($input, $output, $this->logger);
        $io->heading();
        $this->printPlatformOverview($io, $this->configValidator);

        $options = $this->promptOptionsGenerator->generate();

        $template = $input->getOption('template');

        if ($template && !in_array($template, $options->getPackages(), true)) {
            throw new \InvalidArgumentException(sprintf('Invalid template: %s', $template));
        }

        foreach ($options->getPackages() as $package) {
            if ($template && $template !== $package) {
                continue;
            }
            foreach ($options->getVariants($package) as $variant => $variantDescription) {
                $defaultOptions = $options->getOptionDefaults($package, $variant);
                $artwork = $folder = $portmaster = false;

                foreach ($defaultOptions as $o) {
                    if ('artwork' === $o) {
                        $artwork = true;
                    }
                    if ('folder' === $o) {
                        $folder = true;
                    }
                    if ('portmaster' === $o) {
                        $portmaster = true;
                    }
                }

                $postProcessChoices = $this->getPostProcessChoicesByPackageAndVariant($package, $variant);

                $themes = [null];
                if ($this->promptOptionsGenerator->isThemeable($package, $variant, $postProcessChoices)) {
                    $themes = $this->configReader->getConfig()->previewThemes;
                    $themes[] = null;
                }

                foreach ($themes as $theme) {
                    $choices = new PromptChoices($package, $variant, $artwork, $folder, $portmaster, false, false, $postProcessChoices, $theme);

                    $io->section(sprintf('build-choices-%s:%s', $choices->package, $choices->variant));
                    $io->help("Building with choices:\n\n".$choices->prettyPrint());

                    $buildCommandCollection = $this->buildCommandCollectionFactory->create($choices);
                    $this->centralHandler->handleBuildCommandCollection($buildCommandCollection);
                }
            }
        }

        $io->complete('Complete');

        return Command::SUCCESS;
    }

    // sensible defaults to provide counter choices etc. to the build all process
    private function getPostProcessChoicesByPackageAndVariant(string $package, string $variant): array
    {
        return match (sprintf('%s:%s', $package, $variant)) {
            'artbook-next:artbook_next_vertical' => [new PostProcessChoice('counter', ['position' => 'bottom-right', 'background' => true])],
            'cartwheel:cartwheel_right_game_logo' => [new PostProcessChoice('counter', ['position' => 'bottom'])],
            'game-logo-list:game_logo_scrolling' => [new PostProcessChoice('vertical_dot_scrollbar', ['position' => 'right'])],
            'game-logo-list:splitscreen' => [new PostProcessChoice('vertical_scrollbar', ['position' => 'left'])],
            'minimal:text_screenshot' => [new PostProcessChoice('counter', ['position' => 'absolute-bottom-right'])],
            'minimal:text_only' => [new PostProcessChoice('vertical_dot_scrollbar', ['position' => 'right'])],
            default => []
        };
    }
}
