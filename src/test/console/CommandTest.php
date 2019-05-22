<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\test\console;

use Codeception\Stub;
use craft\helpers\StringHelper;
use yii\base\InvalidArgumentException;
use yii\console\Controller;
use Craft;
use yii\base\InvalidConfigException;
use Closure;

/**
 * Class ConsoleTest
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @author Global Network Group | Giel Tettelaar <giel@yellowflash.net>
 * @since 3.1
 */
class CommandTest
{
    // Constants
    // =========================================================================
    const STD_OUT = 'stdOut';
    const STD_ERR = 'stderr';
    const PROMPT = 'prompt';
    const CONFIRM = 'confirm';
    const SELECT = 'select';

    // Public properties
    // =========================================================================

    /**
     * @var ConsoleTest
     */
    protected $test;

    /**
     * @var string
     */
    protected $command;

    /**
     * @var array
     */
    protected $parameters;

    /**
     * @var int
     */
    protected $expectedExitCode;

    /**
     * @var bool
     */
    protected $hasExecuted = false;

    /**
     * @var array|CommandTestItem
     */
    protected $eventChain = [];

    /**
     * @var integer
     */
    protected $currentIndex;

    /**
     * @var Controller
     */
    protected $controller;

    /**
     * @var string
     */
    protected $actionId;

    /**
     * @var int
     */
    protected $desiredExitCode;

    /**
     * @var int
     */
    protected $eventChainItemsHandled = 0;

    // Public Methods
    // =========================================================================

    /**
     * CommandTest constructor.
     *
     * @param ConsoleTest $consoleTest
     * @param string $command
     * @param array $parameters
     * @throws InvalidConfigException
     */
    public function __construct(ConsoleTest $consoleTest, string $command, array $parameters = [])
    {
        $this->command = $command;
        $this->parameters = $parameters;
        $this->test = $consoleTest;
        $this->setupController();
    }


    /**
     * @throws InvalidArgumentException
     */
    public function run()
    {
        if (!isset($this->desiredExitCode)) {
            throw new InvalidArgumentException('Please enter a desired exit code');
        }

        $exitCode = $this->controller->run($this->actionId, $this->parameters);

        $this->test->assertSame($this->desiredExitCode, $exitCode);

        $this->test->assertSame($this->eventChainItemsHandled, count($this->eventChain));
    }

    /**
     * @param $value
     * @return CommandTest
     */
    public function exitCode($value) : CommandTest
    {
        $this->desiredExitCode = $value;
        return $this;
    }

    /**
     * @param string $desiredOutput
     * @return CommandTest
     */
    public function stdOut(string $desiredOutput) : CommandTest
    {
        return $this->addEventChainItem([
            'type' => self::STD_OUT,
            'desiredOutput' => $desiredOutput
        ]);
    }

    /**
     * @param string $desiredOutput
     * @return CommandTest
     */
    public function stderr(string $desiredOutput) : CommandTest
    {
        return $this->addEventChainItem([
            'type' => self::STD_ERR,
            'desiredOutput' => $desiredOutput
        ]);
    }

    /**
     * @param string $prompt
     * @param $returnValue
     * @param array $options
     * @return CommandTest
     */
    public function prompt(string $prompt, $returnValue, array $options = []) : CommandTest
    {
        return $this->addEventChainItem([
            'type' => self::PROMPT,
            'prompt' => $prompt,
            'options' => $options,
            'returnValue' => $returnValue
        ]);
    }

    /**
     * @param string $message
     * @param $returnValue
     * @param bool $default
     * @return CommandTest
     */
    public function confirm(string $message, $returnValue, bool $default = false) : CommandTest
    {
        return $this->addEventChainItem([
            'type' => self::CONFIRM,
            'message' => $message,
            'default' => $default,
            'returnValue' => $returnValue
        ]);
    }

    /**
     * @param $prompt
     * @param $returnValue
     * @param array $options
     * @return CommandTest
     */
    public function select(string $prompt, $returnValue, $options = []) : CommandTest
    {
        return $this->addEventChainItem([
            'type' => self::SELECT,
            'prompt' => $prompt,
            'options' => $options,
            'returnValue' => $returnValue
        ]);
    }

    // Protected Methods
    // =========================================================================

    /**
     * @throws InvalidConfigException
     */
    protected function setupController()
    {
        $parts = StringHelper::split($this->command, '/');
        $controllerId = $parts[0];
        $actionId = $parts[1];

        $controller = Craft::$app->createControllerByID($controllerId);
        if (!$controller instanceof Controller) {
            throw new InvalidArgumentException('Invalid controller');
        }

        $stubController = Stub::construct(get_class($controller), [$controllerId, \Craft::$app], [
            'stdOut' => $this->stdOutHandler(),
            'stderr' => $this->stderrHandler(),
            'prompt' => $this->promptHandler(),
            'confirm' => $this->confirmHandler(),
            'select' => $this->selectHandler()
            ]);

        $this->controller = $stubController;
        $this->actionId = $actionId;
    }

    /**
     * @return Closure
     */
    protected function stdOutHandler() : Closure
    {
        return function ($out) {
            $nextItem = $this->runHandlerCheck($out, self::STD_OUT);


            $this->test::assertSame(
                $nextItem->desiredOutput,
                $out
            );
        };
    }

    /**
     * @return Closure
     */
    protected function stderrHandler() : Closure
    {
        return function ($out) {
            $nextItem = $this->runHandlerCheck($out, self::STD_ERR);

            $this->test::assertSame(
                $nextItem->desiredOutput,
                $out
            );
        };
    }

    /**
     * @return Closure
     */
    protected function promptHandler() : Closure
    {
        return function ($text, $options = []) {
            $nextItem = $this->runHandlerCheck('A prompt with value: '. $text, self::PROMPT);

            $this->test::assertSame(
                $nextItem->prompt,
                $text
            );

            $this->test::assertSame(
                $nextItem->options,
                $options
            );

            return $nextItem->returnValue;
        };
    }

    /**
     * @return Closure
     */
    protected function confirmHandler() : Closure
    {
        return function ($message, $default = false) {
            $nextItem = $this->runHandlerCheck('A confirm with value: '. $message, self::CONFIRM);

            $this->test::assertSame(
                $nextItem->message,
                $message
            );

            $this->test::assertSame(
                $nextItem->default,
                $default
            );

            return $nextItem->returnValue;
        };
    }

    /**
     * @return Closure
     */
    protected function selectHandler() : Closure
    {
        return function ($prompt, $options = []) {
            $nextItem = $this->runHandlerCheck('A select with value: '. $prompt, self::SELECT);

            $this->test::assertSame(
                $nextItem->prompt,
                $prompt
            );

            $this->test::assertSame(
                $nextItem->options,
                $options
            );

            return $nextItem->returnValue;
        };
    }

    /**
     * @param $out
     * @param $type
     *
     * @return CommandTestItem
     */
    protected function runHandlerCheck($out, $type): CommandTestItem
    {
        $nextItem = $this->getNextItem();

        if (!$nextItem) {
            $this->test::fail("There are no more items however: $out was printed");
        }

        if ($nextItem->type !== $type) {
            throw new InvalidArgumentException("A $type message was expected but $nextItem->type was given");
        }

        $this->eventChainItemsHandled ++;

        return $nextItem;
    }

    /**
     * @return CommandTestItem|null
     */
    protected function getNextItem()
    {
        if ($this->currentIndex === null) {
            $this->currentIndex = 0;
        }

        if (count($this->eventChain) === $this->currentIndex) {
            return null;
        }

        $eventChainItem = $this->eventChain[$this->currentIndex];

        $this->currentIndex ++;

        return $eventChainItem;
    }

    /**
     * @param array $config
     *
     * @return CommandTest
     */
    protected function addEventChainItem(array $config) : CommandTest
    {
        $chainItem = new CommandTestItem($config);

        $this->eventChain[] = $chainItem;

        return $this;
    }
}