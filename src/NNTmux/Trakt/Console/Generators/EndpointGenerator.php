<?php

namespace NNTmux\Trakt\Console\Generators;

use Illuminate\Support\Collection;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use ReflectionClass;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use NNTmux\Trakt\Api\Endpoint;
use NNTmux\Trakt\Exception\ClassCanNotBeImplementedAsEndpointException;
use NNTmux\Trakt\Request\AbstractRequest;

/**
 * Class ClassGenerator
 * @package NNTmux\Trakt\Console\Generators
 */
class EndpointGenerator
{

    use TemplateWriter;
    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var string
     */
    private $apiNamespace = "NNTmux\\Trakt\\Api";

    /**
     * @var string
     */
    private $requestsNamespace = "NNTmux\\Trakt\\Request\\";

    /**
     * @var Collection
     */
    private $endpoint;

    /**
     * @var Collection|ReflectionClass[]
     */
    private $uses;
    /**
     * @var OutputInterface
     */
    private $out;

    private $className;

    private $file;
    /**
     * @var QuestionHelper
     */
    private $questionHelper;
    /**
     * @var InputInterface
     */
    private $inputInterface;

    /**
     * @var bool
     */
    private $delete;

    /**
     * @var bool
     */
    private $force;

    /**
     * @param InputInterface $inputInterface
     * @param OutputInterface $outputInterface
     * @param QuestionHelper $questionHelper
     * @param bool $force
     * @param bool $delete
     * @throws \LogicException
     */
    public function __construct(
        InputInterface $inputInterface,
        OutputInterface $outputInterface,
        QuestionHelper $questionHelper,
        $force = false,
        $delete = false
    )
    {
        $this->out = $outputInterface;
        $this->questionHelper = $questionHelper;
        $this->inputInterface = $inputInterface;
        $this->force = $force;
        $this->delete = $delete;

        $localAdapter = new Local(__DIR__ .'/../..');
        $this->filesystem = new Filesystem($localAdapter);
    }

    /**
     * @param $endpoint
     * @return $this|bool
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function generateForEndpoint($endpoint)
    {
        $this->template = $this->filesystem->read('/Console/stubs/api.stub');
        $this->endpoint = $this->createEndpoint($endpoint);

        $this->file = '/Api/'. $this->endpoint->implode('/') . '.php';

        $this->className = $this->apiNamespace . '\\' . $this->endpoint->implode('\\');

        $this->uses = new Collection();

        if ($this->filesystem->has($this->file) && $this->userWantsToOverwrite()) {
            $this->filesystem->delete($this->file);
            return $this->createContent()->writeToFile();
        }

        $this->out->writeln('Not overwriting '. $this->file);
        return $this;
    }

    /**
     * @return string
     */
    public function getGeneratedTemplate()
    {
        return $this->template;
    }

    /**
     * @return $this
     */
    private function createContent()
    {
        $this->out->writeln('Generating class for API endpoint: '. $this->endpoint->implode("\\"));
        $this->setNamespace()
            ->setClassName()
            ->generateMethods()
            ->addUseStatements()
            ->deleteUnusedPlaceholders();

        $this->out->writeln('Deleted unused placeholders in template');

        return $this;
    }

    /**
     * @return $this
     * @throws \League\Flysystem\FileExistsException
     */
    private function writeToFile(): self
    {
        $this->filesystem->write($this->file, $this->template);
        $this->out->writeln(
            'Written endpoint wrapper to :'. $this->filesystem->get($this->file)->getPath
            ()
        );
        $this->out->writeln('Class '. $this->className .' is generated');
        return $this;
    }

    /**
     * @return $this
     */
    private function generateMethods()
    {
        $methods = new Collection();
        $properties = new Collection();
        foreach ($this->getRequestFolderContents() as $content) {

            try {
                if ($content['type'] === 'file') {
                    $this->handleFile($content, $methods);
                }
                if ($content['type'] === 'dir') {
                    $this->handleDirectory($properties, $content);
                }
            } catch (ClassCanNotBeImplementedAsEndpointException $exception) {
                continue;
            }
        };
        $this->out->writeln('Adding generated methods to template');
        return $this->writeInTemplate('methods', $methods->implode("\n\n\t"))->addProperties($properties);
    }

    /**
     * @param \Illuminate\Support\Collection $className
     * @param $file
     * @param null $methodName
     * @return \NNTmux\Trakt\Console\Generators\Method
     * @throws \NNTmux\Trakt\Exception\ClassCanNotBeImplementedAsEndpointException
     * @throws \ReflectionException
     */
    private function createMethod(Collection $className, $file, $methodName = null)
    {

        $reflection = new ReflectionClass(
            $this->requestsNamespace . $className->implode("\\") . "\\" .
            $file['filename']
        );
        if ($reflection->isTrait() || $reflection->isAbstract())
            throw new ClassCanNotBeImplementedAsEndpointException;

        return new Method($reflection, $this->filesystem, $methodName);
    }

    /**
     * @return $this
     * @throws \ReflectionException
     */
    private function addUseStatements(): self
    {
        if ($this->endpoint->count() > 1) {
            $this->uses->push(new ReflectionClass(Endpoint::class));
        }
        $aliases = $this->uses->unique()->map(
            function (ReflectionClass $useStatement) {
                $parent = $useStatement->getParentClass();
                if ($parent !== false && $parent->getName() === AbstractRequest::class) {
                    return $useStatement->getName() .' as '. $useStatement->getShortName() .'Request';
                }

                return $useStatement->getName();
            }
        );
        if ($aliases->count() > 0) {
            $uses = $aliases->implode(";\nuse ");
            $this->out->writeln('Adding use statements to template'
            );
            return $this->writeInTemplate('use_statements', 'use '. $uses .';');
        }

        return $this;
    }

    /**
     * @param $method
     */
    private function updateUsages(Method $method)
    {
        $this->uses = $this->uses->merge($method->getUses())->push($method->getRequestClass());
    }

    /**
     * @return $this
     */
    private function setClassName()
    {
        return $this->writeInTemplate('class_name', $this->endpoint->last());
    }

    /**
     * @throws \League\Flysystem\FileNotFoundException
     */
    public function generateAllEndpoints()
    {
        $this->handleOptions();
        foreach ($this->filesystem->listContents('/Request') as $content) {
            if ($content['type'] === 'dir'
                && $content['basename'] !== 'Exception'
                && $content['basename'] !== 'Parameters'
            ) {
                $this->generateForEndpoint($content['basename']);
            }
        }
    }

    /**
     * @return mixed
     * @throws \Symfony\Component\Console\Exception\RuntimeException
     */
    private function userWantsToOverwrite()
    {
        $question = new Question('Class '. $this->className .' already exist, do you want to overwrite it?', false);
        return $this->questionHelper->ask(
            $this->inputInterface,
            $this->out,
            $question
        );
    }

    /**
     * @return Collection
     */
    private function getRequestFolderContents()
    {

        return new Collection($this->filesystem->listContents('/Request/'. $this->endpoint->implode('/')));
    }

    /**
     * @param \Illuminate\Support\Collection $properties
     * @return $this
     */
    private function addProperties(Collection $properties)
    {
        $formatted = new Collection();
        $properties->each(
            function ($property) use ($formatted) {
                $generator = new Property(
                    $this->apiNamespace . "\\" . $this->endpoint->implode("\\") . "\\" . $property,
                    $this->filesystem
                );
                $formatted->push($generator->generate());
            }
        );

        return $this->writeInTemplate('public_properties', "\n" . $formatted->implode("\n\n"));
    }

    /**
     * @param $content
     * @param \Illuminate\Support\Collection $methods
     * @return \Illuminate\Support\Collection
     * @throws \NNTmux\Trakt\Exception\ClassCanNotBeImplementedAsEndpointException
     * @throws \ReflectionException
     */
    private function handleFile($content, Collection $methods)
    {
        $method = $this->createMethod($this->endpoint, $content);
        $methods->push($method->generate());

        $this->out->writeln("Generated method for: '" . $method->getName() . "'");

        $this->updateUsages($method);

        if ($method->getName() === 'summary') {
            $methods->push($this->createMethod($this->endpoint, $content, 'get')->generate());
            $this->out->writeln('Generated alias method get for summary');
        }

        return $methods;
    }

    /**
     * @param \Illuminate\Support\Collection $properties
     * @param $content
     * @throws \LogicException
     * @throws \League\Flysystem\FileNotFoundException
     */
    private function handleDirectory(Collection $properties, $content)
    {
        $properties->push($content['filename']);

        $this->filesystem->createDir('Api/' . $this->endpoint->first());
        $generator = new EndpointGenerator($this->inputInterface, $this->out, $this->questionHelper);
        $endpoint = str_replace('Request/', '', $content['path']);
        $generator->generateForEndpoint($endpoint);
    }

    /**
     * @param $endpoint
     * @return string
     */
    private function createEndpoint($endpoint)
    {
        return collect(explode('/', $endpoint))->map(
            function ($endpoint) {
                return ucfirst($endpoint);
            }
        );
    }

    /**
     * @return $this
     */
    private function setNamespace()
    {
        $parts = clone $this->endpoint;
        $parts->pop();
        $namespace = ($this->endpoint->count() === 1) ? $this->apiNamespace : $this->apiNamespace . '\\' .
            $parts->implode("\\");

        return $this->writeInTemplate('namespace', $namespace);
    }

    private function handleOptions()
    {
        if ($this->delete) {
            $this->getItemsToDelete()->each(
                function ($item) {
                    if ($item['type'] === 'dir') {
                        dump($item);
                        $this->filesystem->deleteDir($item['path']);
                        return true;
                    }
                    $this->filesystem->delete($item['path']);
                    return true;
                }
            );
        }
    }

    /**
     * @return Collection
     */
    private function getItemsToDelete()
    {
        return collect($this->filesystem->listContents('/Api'))->filter(
            function ($content) {
                return ! ($content['filename'] === 'Endpoint');
            }
        );
    }
}
