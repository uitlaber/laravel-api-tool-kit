<?php

namespace Essa\APIToolKit\Commands;

use Essa\APIToolKit\Enum\GeneratorFilesType;
use Essa\APIToolKit\Generator\ApiGenerationCommandInputs;
use Essa\APIToolKit\Generator\Configs\CommandConfigHandler;
use Essa\APIToolKit\Generator\Configs\PathConfigHandler;
use Essa\APIToolKit\Generator\ConsoleTable\GeneratedFilesConsoleTable;
use Essa\APIToolKit\Generator\ConsoleTable\SchemaConsoleTable;
use Essa\APIToolKit\Generator\Contracts\ConsoleTableInterface;
use Essa\APIToolKit\Generator\SchemaDefinition;
use Illuminate\Console\Command;
use Illuminate\Container\Container;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class ApiGenerateCommand extends Command
{
    protected $name = 'api:generate';

    protected $description = 'This command generate api crud.';

    private array $reservedNames = [
        '__halt_compiler',
        'abstract',
        'and',
        'array',
        'as',
        'break',
        'callable',
        'case',
        'catch',
        'class',
        'clone',
        'const',
        'continue',
        'declare',
        'default',
        'die',
        'do',
        'echo',
        'else',
        'elseif',
        'empty',
        'enddeclare',
        'endfor',
        'endforeach',
        'endif',
        'endswitch',
        'endwhile',
        'eval',
        'exit',
        'extends',
        'final',
        'finally',
        'fn',
        'for',
        'foreach',
        'function',
        'global',
        'goto',
        'if',
        'implements',
        'include',
        'include_once',
        'instanceof',
        'insteadof',
        'interface',
        'isset',
        'list',
        'namespace',
        'new',
        'or',
        'print',
        'private',
        'protected',
        'public',
        'require',
        'require_once',
        'return',
        'static',
        'switch',
        'throw',
        'trait',
        'try',
        'unset',
        'use',
        'var',
        'while',
        'xor',
        'yield',
    ];

    public function __construct(private Container $container)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $model = ucfirst($this->argument('model'));

        if ($this->isReservedName($this->argument('model'))) {
            $this->error('The name "' . $this->argument('model') . '" is reserved by PHP.');

            return self::FAILURE;
        }

        if (! PathConfigHandler::isValidPathGroup($this->option('group'))) {
            $this->error('The path group you entered is not valid');

            return self::FAILURE;
        }

        $pluginPath = $this->option('plugin')
            ?? PathConfigHandler::getDefaultPluginPath($this->option('group'));

        $baseNamespace = $this->option('namespace')
            ?? PathConfigHandler::getDefaultNamespace($this->option('group'));


        $apiGenerationCommandInputs = new ApiGenerationCommandInputs(
            model: ucfirst($this->argument('model')),
            userChoices: $this->getUserChoices(),
            schema: SchemaDefinition::createFromSchemaString($this->argument('schema')),
            pathGroup: $this->option('group'),

            // Pass the new options:
            pluginPath: $pluginPath,
            baseNamespace: $baseNamespace
        );

        $this->executeCommands($apiGenerationCommandInputs);

        $this->info('Here is your schema : ');

        $this->displayTable(new SchemaConsoleTable(), $apiGenerationCommandInputs);

        $this->info('Generated Files : ');

        $this->displayTable(new GeneratedFilesConsoleTable(), $apiGenerationCommandInputs);

        return self::SUCCESS;
    }

    protected function getArguments(): array
    {
        return [
            ['model', InputArgument::REQUIRED, 'The model.'],
            ['schema', InputArgument::OPTIONAL, 'The schema.'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            ['all', null, InputOption::VALUE_NONE, 'Generate a migration, seeder, factory, policy, resource controller, and form request classes for the model'],
            ['routes', null, InputOption::VALUE_NONE, 'Generate routes for the crud operations'],
            ['soft-delete', null, InputOption::VALUE_NONE, 'Generate soft delete functionality for the model'],
            ['controller', 'c', InputOption::VALUE_NONE, 'Create a new controller for the model'],
            ['factory', 'f', InputOption::VALUE_NONE, 'Create a new factory for the model'],
            ['filter', 'F', InputOption::VALUE_NONE, 'Create a new filter for the model'],
            ['test', 't', InputOption::VALUE_NONE, 'Create new test cases for the model'],
            ['migration', 'm', InputOption::VALUE_NONE, 'Create a new migration file for the model'],
            ['seeder', 's', InputOption::VALUE_NONE, 'Create a new seeder for the model'],
            ['resource', 'r', InputOption::VALUE_NONE, 'Generate a resource controller for the model'],
            ['request', 'R', InputOption::VALUE_NONE, 'Create new form request classes for the model and use them in the resource controller'],
            ['group', 'g', InputOption::VALUE_OPTIONAL, 'Specify the group for the generated files', PathConfigHandler::getDefaultPathGroup()],
            ['plugin', null, InputOption::VALUE_REQUIRED, 'The full path to your October plugin folder'],
            ['namespace',  null, InputOption::VALUE_REQUIRED, 'The base namespace for your plugin (e.g. Acme\\Blog)'],
        ];
    }

    private function getUserChoices(): array
    {
        if ($this->option('all')) {
            $this->setDefaultOptions();
        }

        $extraOptions = [
            GeneratorFilesType::UPDATE_REQUEST => $this->option('request'),
            GeneratorFilesType::CREATE_REQUEST => $this->option('request'),
            'model' => true,
        ];

        return $this->options() + $extraOptions;
    }

    private function setDefaultOptions(): void
    {
        $defaultOptions = config('api-tool-kit.default_generates');

        foreach ($defaultOptions as $option) {
            $this->input->setOption($option, true);
        }
    }

    private function isReservedName(string $name): bool
    {
        return in_array(mb_strtolower($name), $this->reservedNames);
    }

    private function executeCommands(ApiGenerationCommandInputs $apiGenerationCommandInputs): void
    {
        $apiGeneratorCommands = CommandConfigHandler::getAllCommands();

        foreach ($apiGeneratorCommands as $type => $commandClass) {
            if ($apiGenerationCommandInputs->isOptionSelected($type)) {
                $this->container
                    ->get($commandClass)
                    ->run($apiGenerationCommandInputs);
            }
        }
    }

    private function displayTable(
        ConsoleTableInterface $consoleTable,
        ApiGenerationCommandInputs $apiGenerationCommandInputs
    ): void {
        $output = $consoleTable->generate($apiGenerationCommandInputs);

        $this->table($output->getHeaders(), $output->getTableData());
    }
}
