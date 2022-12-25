<?php /** @noinspection PhpComposerExtensionStubsInspection */

declare(strict_types=1);

namespace Fp\PsalmToolkit\Toolkit;

use Fp\PsalmToolkit\Toolkit\Hook\GenericObjectReturnTypeProvider;
use Fp\PsalmToolkit\Toolkit\Hook\IntersectionReturnTypeProvider;
use Fp\PsalmToolkit\Toolkit\Hook\OptionalReturnTypeProvider;
use Fp\PsalmToolkit\Toolkit\Hook\ShapeReturnTypeProvider;
use Fp\PsalmToolkit\Toolkit\Hook\ShowTypeHook;
use Fp\PsalmToolkit\Toolkit\Hook\TestCaseAnalysis;
use Psalm\Internal\Analyzer\ProjectAnalyzer;
use Psalm\Plugin\PluginEntryPointInterface;
use Psalm\Plugin\RegistrationInterface;
use SimpleXMLElement;

final class Plugin implements PluginEntryPointInterface
{
    public function __invoke(RegistrationInterface $registration, ?SimpleXMLElement $config = null): void
    {
        PsalmApi::$types = new Types();
        PsalmApi::$args = new Args();
        PsalmApi::$classlikes = new Classlikes();
        PsalmApi::$codebase = ProjectAnalyzer::getInstance()->getCodebase();
        PsalmApi::$issue = new Issue();
        PsalmApi::$methods = new Methods();
        PsalmApi::$properties = new Properties();

        $register = function(string $hook) use ($registration): void {
            if (class_exists($hook)) {
                $registration->registerHooksFromClass($hook);
            }
        };

        $register(ShapeReturnTypeProvider::class);
        $register(IntersectionReturnTypeProvider::class);
        $register(GenericObjectReturnTypeProvider::class);
        $register(OptionalReturnTypeProvider::class);
        $register(TestCaseAnalysis::class);
        $register(ShowTypeHook::class);
    }
}
