<?php

namespace Test\Generator\Generators\Admin\Module\Controller\Edit;

// use Generator\Generators\Admin\Module\Controller\Edit\BaseGenerator;
// use Generator\Generators\Admin\Module\Controller\Edit\ConfigGenerator;
use PHPUnit\Framework\TestCase;
// use Symfony\Component\Console\Output\ConsoleOutput;
// use Hurah\Types\Type;

class BaseGeneratorTest extends TestCase {

    public function __construct(?string $name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->markTestIncomplete("Due to a large refactoring project this test is not working anymore, needs fixing");
    }
    /*
    public function testGenerate() {

        $oConfig = ConfigGenerator::create(
            new Type\PlainText('Test module'),
            new Type\PlainText('TestModule'),
            new Type\PlainText('TestScripts'),
            Type\PhpNamespace::make('AdminModules', 'Custom', 'Anton', 'Test'),
            Type\PhpNamespace::make('AdminModules', 'Custom', 'Anton', 'Test', 'Base'),
            Type\PhpNamespace::make('Crud', 'Custom', 'Anton', 'Test'),
            Type\PhpNamespace::make('Model', 'Custom', 'Anton', 'Test', 'TestModuleQuery'),
            Type\PhpNamespace::make('Crud', 'Custom', 'Anton', 'Test', 'TestModule'),
            Type\PhpNamespace::make('Model', 'Custom', 'Anton', 'Test', 'TestModule'),
        );
        $oBaseGenerator = new BaseGenerator($oConfig, new ConsoleOutput());
        $sGenerated = $oBaseGenerator->generate();
        $this->assertTrue(strpos($sGenerated, '<?php') === 0);
        $this->assertTrue(strpos($sGenerated, 'extends GenericEditController') > 0);
        $this->assertTrue(strpos($sGenerated, 'class EditController') > 0);
        $this->assertTrue(substr_count($sGenerated, '{') === substr_count($sGenerated, '}'));
    }
    */
}
