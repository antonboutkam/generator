<?php

namespace Generator\Generators\Crud\Field;

use Cli\Tools\CommandUtils;
use Core\DeferredAction;
use Core\Utils;
use Crud\Generic\Field\GenericBoolean;
use Crud\Generic\Field\GenericBsn;
use Crud\Generic\Field\GenericCheckbox;
use Crud\Generic\Field\GenericColor;
use Crud\Generic\Field\GenericDate;
use Crud\Generic\Field\GenericDateTime;
use Crud\Generic\Field\GenericDelete;
use Crud\Generic\Field\GenericEdit;
use Crud\Generic\Field\GenericEmail;
use Crud\Generic\Field\GenericFile;
use Crud\Generic\Field\GenericFloat;
use Crud\Generic\Field\GenericForeignKey;
use Crud\Generic\Field\GenericIcon;
use Crud\Generic\Field\GenericImage;
use Crud\Generic\Field\GenericInteger;
use Crud\Generic\Field\GenericLookup;
use Crud\Generic\Field\GenericMoney;
use Crud\Generic\Field\GenericOpenInApi;
use Crud\Generic\Field\GenericPassword;
use Crud\Generic\Field\GenericPostcode;
use Crud\Generic\Field\GenericString;
use Crud\Generic\Field\GenericTextarea;
use Crud\Generic\Field\GenericUrl;
use Crud\IEditableField;
use Crud\IEventField;
use Crud\IField;
use Crud\IFieldHasApi;
use Crud\IFilterableField;
use Crud\IFilterableLookupField;
use Crud\IRequiredField;
use Exception;
use Exception\LogicException;
use Generator\Generators\Crud\CollectionType\CrudFieldCollectionTypeGenerator;
use Helper\ApiXsd\Schema\Api;
use Helper\Schema\Column;
use Helper\Schema\Module;
use Helper\Schema\Table;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpNamespace;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Ui\Form\Field\Dynamic\Helper\LookupGeneratorFactory;

final class CrudFieldGenerator
{
    private OutputInterface $output;

    public function __construct(OutputInterface $oOutput = null)
    {
        if ($oOutput) {
            $this->output = $oOutput;
        } else {
            $this->output = new ConsoleOutput();
        }
    }
    private function output(string $sMessage)
    {
        $this->output->writeln($sMessage);
    }
    public function create(Table $oTable, Api $oApi = null)
    {
        foreach ($oTable->getColumns(['id']) as $x => $oColumn) {
            $sBaseClassFile = $this->makeFileName($oTable, $oColumn->getClassName(), true);
            $sBaseClassContents = $this->createFieldClass($oTable, $oColumn);
            $this->output("Created <info>{$sBaseClassFile}</info>");

            file_put_contents($sBaseClassFile, '<?php' . PHP_EOL . $sBaseClassContents);

            $sFieldClassFile = $this->makeFileName($oTable, $oColumn->getClassName());
            if (file_exists($sFieldClassFile)) {
                $this->output("Skipped creating {$sFieldClassFile}, it already existed");
            } else {
                $sFieldClassContents = $this->createFieldClassPlaceholder($oTable, $oColumn);
                $this->output("Created <info>{$sFieldClassFile}</info>");
                file_put_contents($sFieldClassFile, '<?php' . PHP_EOL . $sFieldClassContents);
            }
        }

        if ($oTable->getModule() instanceof Module) {
            $this->makeEditField($oTable);
            $this->makeDeleteField($oTable);
        }
        $this->makeGenericCheckboxField($oTable);
        if ($oApi instanceof Api && $oTable->getModule() instanceof Module) {
            $this->makeOpenInApiField($oTable);
        }
    }

    private function makeFileName(Table $oTable, string $sClassName, bool $bIsBaseVersion = false): string
    {
        $sDir = '';
        if ($oTable->getCrudDir()) {
            $sDir = $oTable->getCrudDir() . '/';
        }
        $sRoot = CommandUtils::getRoot() . '/classes/Crud/' . $sDir . $oTable->getPhpName();
        if ($bIsBaseVersion) {
            return $sRoot . '/Field/Base/' . $sClassName . '.php';
        }
        return $sRoot . '/Field/' . $sClassName . '.php';
    }

    private function createFieldClass(Table $oTable, Column $oColumn): string
    {
        $sGeneratedCrudNamespace = $this->getBaseClassNamespace($oTable) . '\\Base';
        $oNamespace = new PhpNamespace($sGeneratedCrudNamespace);

        $oClass = new ClassType($oColumn->getClassName());
        $oNamespace->add($oClass);
        $sSuperClass = $this->getSuperClass($oColumn);

        $oCollectionFieldInterfaceName = CrudFieldCollectionTypeGenerator::getPublicInterfaceName($oTable);
        $oClass->setExtends($sSuperClass);
        $oClass->setImplements([
            IFilterableField::class,
            IEditableField::class,
            (string)$oCollectionFieldInterfaceName,
        ]);

        $oNamespace->addUse(IFilterableField::class);
        $oNamespace->addUse(IEditableField::class);
        $oNamespace->addUse((string)$oCollectionFieldInterfaceName);

        $oClass->setComment('Base class that represents the \'' . $oColumn->getName() . '\' crud field from the \'' . $oTable->getName() . '\' table.');
        $oClass->addComment('This class is auto generated and should not be modified.');

        $oBeforeSaveMethod = $oClass->addMethod('sanitize');
        $oBeforeSaveMethod->addParameter('value');
        $oBeforeSaveMethod->setBody('return parent::sanitize($value);');

        $oIsUniqueKeyMethod = $oClass->addMethod('isUniqueKey');
        $oIsUniqueKeyMethod->setReturnType('bool');

        $sIsUniqueKey = 'false';
        if ($oTable->getUnique()) {
            $oUniqueIterator = $oTable->getUnique();
            foreach ($oUniqueIterator as $oUnique) {
                if ($oColumn->getName() === $oUnique->getName()) {
                    $sIsUniqueKey = 'true';
                }
            }
        }
        $oIsUniqueKeyMethod->addBody('return ' . $sIsUniqueKey . ';');

        if ($oColumn->getForm() == 'LOOKUP') {
            $mLookupData = $oColumn->getLookupsVisibleColumn();
            try {
                $oLookupGeneratorFactory = LookupGeneratorFactory::create($mLookupData);
                $oClass->addImplement(IFilterableLookupField::class);
                $oValidate = $oClass->addMethod('getLookups');
                $oValidate->addParameter('mSelectedItem', null);
                $oValidate->setBody($oLookupGeneratorFactory->getLookupsFunctionBody());
                $aParts = $oColumn->getLookupsVisibleColumn();

                $oVisibleValue = $oClass->addMethod('getVisibleValue');
                $oVisibleValue->addParameter('iItemId', null);
                $oVisibleValue->setBody($oLookupGeneratorFactory->getVisibleValueFunctionBody());
            } catch (Exception $e) {
                $this->output("<error>Could not generate lookups for field {$oColumn->getName()}</error>");
            }

            $oGetDatatypeMethod = $oClass->addMethod('getDataType');
            $oGetDatatypeMethod->setReturnType('string');
            $oGetDatatypeMethod->setBody("return 'lookup';");

            if (isset($aParts['full_class'])) {
                $oNamespace->addUse($aParts['full_class'] . 'Query');
                $oNamespace->addUse(Utils::class);
                $oNamespace->addUse(IFilterableLookupField::class);
            } else {
                if (isset($aParts['url'])) {
                    $oNamespace->addUse(Utils::class);
                    $oNamespace->addUse(IFilterableLookupField::class);
                }
            }
        }
        if ($oColumn->getRequired()) {
            $oClass->addImplement(IRequiredField::class);
            $oNamespace->addUse(IRequiredField::class);
        }
        if ($oColumn->getRequired()) {
            $oHasValidations = $oClass->addMethod('hasValidations');
            $oHasValidations->setBody('return true;');

            $oValidate = $oClass->addMethod('validate');
            $oValidate->addParameter('aPostedData');
            $oValidate->setBody($this->getValidateBody($oColumn));
        }

        $oClass->setAbstract();
        $oClass->addProperty('sFieldName', $oColumn->getName())->setVisibility('protected');
        $oClass->addProperty('sFieldLabel', $oColumn->getTitle())->setVisibility('protected');
        $oClass->addProperty('sIcon', $oColumn->getIcon())->setVisibility('protected');
        $oClass->addProperty('sPlaceHolder', $oColumn->getPlaceholder())->setVisibility('protected');
        $oClass->addProperty('sGetter', 'get' . $oColumn->getClassName())->setVisibility('protected');
        $oClass->addProperty('sFqModelClassname', $oTable->getModelClass(true))->setVisibility('protected');

        $oNamespace->addUse($sSuperClass);

        return (string)$oNamespace;
    }

    private function getBaseClassNamespace(Table $oTable): string
    {
        return $oTable->getCrudNamespace() . '\\' . $oTable->getPhpName() . '\\Field';
    }

    private function getSuperClass(Column $oColumn)
    {
        $aMap = [
            'BOOLEAN' => GenericBoolean::class,
            'LOOKUP' => GenericLookup::class,
            'FOREIGN_KEY' => GenericForeignKey::class,
            'BSN' => GenericBsn::class,
            'MONEY' => GenericMoney::class,
            'CHECKBOX' => GenericBoolean::class,
            'INTEGER' => GenericInteger::class,
            'EMAIL' => GenericEmail::class,
            'FLOAT' => GenericFloat::class,
            'COLOR' => GenericColor::class,
            'FILE' => GenericFile::class,
            'IMAGE' => GenericImage::class,
            'ICON' => GenericIcon::class,
            'STRING' => GenericString::class,
            'DATE' => GenericDate::class,
            'DATETIME' => GenericDateTime::class,
            'POSTCODE' => GenericPostcode::class,
            'TEXTAREA' => GenericTextarea::class,
            'URL' => GenericUrl::class,
            'PASSWORD' => GenericPassword::class,
        ];

        try {
            if (empty($oColumn->getForm())) {
                throw new LogicException("Please set the form tag on your field {$oColumn->getName()} of the table ");
            } else {
                if (!isset($aMap[(string)$oColumn->getForm()])) {
                    throw new LogicException("{$oColumn->getForm()} is not a supported form type for field {$oColumn->getName()}");
                }
            }
            $sForm = (string)$oColumn->getForm();
        } catch (Exception $e) {
            echo $e->getMessage();
            $sForm = 'STRING';
        }

        return $aMap[$sForm];
    }

    public function getValidateBody(Column $oColumn)
    {
        $aOut = [];
        $aOut[] = '$mResponse = false;';
        if ($oColumn->getRequired()) {
            $aOut[] = '$mParentResponse = parent::validate($aPostedData);';
            $aOut[] = PHP_EOL;
            $aOut[] = 'if(!isset($aPostedData[\'' . $oColumn->getName() . '\']))';
            $aOut[] = '{';
            $aOut[] = '     $mResponse = [];';
            $aOut[] = '     $mResponse[] = \'Het veld "' . $oColumn->getTitle() . '" verplicht maar nog niet ingevuld.\';';
            $aOut[] = '}';
            $aOut[] = 'if(!empty($mParentResponse)){';
            $aOut[] = '     $mResponse = array_merge($mResponse, $mParentResponse);';
            $aOut[] = '}';
        } else {
            $aOut[] = '$mResponse = parent::validate();';
        }

        $aOut[] = 'return $mResponse;';

        return join(PHP_EOL, $aOut);
    }

    public function createFieldClassPlaceholder(Table $oTable, Column $oColumn): string
    {
        $sGeneratedCrudNamespace = $this->getBaseClassNamespace($oTable);
        $sExtendsClass = $sGeneratedCrudNamespace . '\\Base\\' . $oColumn->getClassName();

        $oNamespace = new PhpNamespace($sGeneratedCrudNamespace);
        $oClass = new ClassType($oColumn->getClassName());

        $oClass->setComment('Skeleton subclass for representing ' . $oColumn->getName() . ' field from the ' . $oTable->getName() . ' table . ');
        $oClass->addComment('You should add additional methods to this class to meet the');
        $oClass->addComment('application requirements.  This class will only be generated as');
        $oClass->addComment('long as it does not already exist in the output directory.');

        $oClass->setExtends($sExtendsClass);
        $oClass->setFinal();

        $oNamespace->add($oClass);
        $oNamespace->addUse($sExtendsClass);

        return (string)$oNamespace;
    }

    private function makeEditField(Table $oTable)
    {
        $sBaseClassFile = $this->makeFileName($oTable, 'Edit', true);

        $sGeneratedCrudNamespace = $this->getBaseClassNamespace($oTable);
        $sExtendsClass = $sGeneratedCrudNamespace . '\\Base';

        $oNamespace = new PhpNamespace($sExtendsClass);
        $sSuperClass = new ClassType('Edit');
        $sSuperClass->addImplement(IEventField::class);

        $sSuperClass->setExtends(GenericEdit::class);

        if ($oTable->getDatabase()->getCustom()) {
            $sUrl = '/custom/' . strtolower($oTable->getDatabase()->getCustom() . '/' . $oTable->getModule()->getModuleDir() . '/' . $oTable->getName()) . '/edit?id=" . $oObject->getId() . "';
        } else {
            $sUrl = '/' . strtolower($oTable->getModule()->getModuleDir() . '/' . $oTable->getName()) . '/edit?id=" . $oObject->getId() . "';
        }
        $oGetEditUrlMethod = $sSuperClass->addMethod('getEditUrl');
        $oGetEditUrlMethod->addParameter('oObject');

        $aBody = [
            "DeferredAction::register('overview_url', Utils::getRequestUri());",
            'return "' . $sUrl . '";',
        ];

        $oGetEditUrlMethod->setBody(join(PHP_EOL, $aBody));

        $oGetIcon = $sSuperClass->addMethod('getIcon');
        $oGetIcon->setReturnType('string');
        $oGetIcon->setBody('return "edit";');

        $oNamespace->add($sSuperClass);
        $oNamespace->addUse(Utils::class);
        $oNamespace->addUse(DeferredAction::class);
        $oNamespace->addUse(GenericEdit::class);
        $oNamespace->addUse(IEventField::class);

        $this->output("Writing base edit field <info>{$sBaseClassFile}</info>");
        file_put_contents($sBaseClassFile, '<?php ' . PHP_EOL . $oNamespace);

        $sCustomClassFile = $this->makeFileName($oTable, 'Edit');

        $sCustomClass = $sGeneratedCrudNamespace;
        $oCustomNamespace = new PhpNamespace($sCustomClass);
        $oCustomClass = new ClassType('Edit');
        $oCustomClass->setFinal(true);
        $oCustomClass->addExtend($sExtendsClass . '\\Edit');
        $oCustomNamespace->add($oCustomClass);

        $oCustomNamespace->addUse($sExtendsClass . '\\Edit');

        echo "Writing file " . $sCustomClassFile . PHP_EOL;
        file_put_contents($sCustomClassFile, '<?php ' . PHP_EOL . $oCustomNamespace);
    }

    private function makeDeleteField(Table $oTable)
    {
        $sBaseClassFile = $this->makeFileName($oTable, 'Delete', true);

        $sGeneratedCrudNamespace = $this->getBaseClassNamespace($oTable);
        $sExtendsClass = $sGeneratedCrudNamespace . '\\Base';

        $oNamespace = new PhpNamespace($sExtendsClass);
        $sSuperClass = new ClassType('Delete');
        $sSuperClass->addImplement(IEventField::class);
        $sSuperClass->setAbstract(true);
        $oNamespace->add($sSuperClass);
        $oNamespace->addUse(GenericDelete::class);
        $oNamespace->addUse($oTable->getModelClass(true));
        $sSuperClass->setExtends(GenericDelete::class);

        $oDeleteUrl = $sSuperClass->addMethod('getDeleteUrl');
        $oDeleteUrl->addParameter('oObject', null);
        $oDeleteUrl->setBody($this->getDeleteBody($oTable));

        $oGetIcon = $sSuperClass->addMethod('getIcon');
        $oGetIcon->setReturnType('string');
        $oGetIcon->setBody('return "trash";');

        $oNamespace->addUse(IEventField::class);

        $oUnDeleteUrl = $sSuperClass->addMethod('getUnDeleteUrl');
        $oUnDeleteUrl->addParameter('oObject', null);
        $oUnDeleteUrl->setBody($this->getUnDeleteBody($oTable));
        $oNamespace->addUse(IEventField::class);

        $this->output("Writing base file <info>{$sBaseClassFile}</info>");
        file_put_contents($sBaseClassFile, '<?php ' . PHP_EOL . $oNamespace);

        $sCustomClassFile = $this->makeFileName($oTable, 'Delete');

        $sCustomClass = $sGeneratedCrudNamespace;
        $oCustomNamespace = new PhpNamespace($sCustomClass);
        $oCustomClass = new ClassType('Delete');
        $oCustomClass->setFinal(true);
        $oCustomClass->addExtend($sExtendsClass . '\\Delete');
        $oCustomNamespace->add($oCustomClass);

        $oCustomNamespace->addUse($sExtendsClass . '\\Delete');

        $this->output("Writing editable file <info>{$sCustomClassFile}</info>");
        file_put_contents($sCustomClassFile, '<?php ' . PHP_EOL . $oCustomNamespace);
    }

    private function getDeleteBody(Table $oTable): string
    {

        $aOut = [];
        $aOut[] = 'if($oObject instanceof ' . $oTable->getPhpName() . ')';
        $aOut[] = '{';
        $aOut[] = '     return "/' . strtolower($oTable->getCrudDir()) . '/' . strtolower($oTable->getModule()->getName()) . '/' . strtolower($oTable->getName()) . '/overview?_do=ConfirmDelete&id=" . $oObject->getId();';
        $aOut[] = '}';
        $aOut[] = "return '';";
        return join(PHP_EOL, $aOut);
    }

    private function getUnDeleteBody(Table $oTable): string
    {
        $aOut = [];
        $aOut[] = 'if($oObject instanceof ' . $oTable->getPhpName() . ')';
        $aOut[] = '{';
        $aOut[] = '     return "/' . strtolower($oTable->getCrudDir()) . '/' . strtolower($oTable->getName()) . '?_do=UnDelete&id=" . $oObject->getId();';
        $aOut[] = '}';
        $aOut[] = "return '';";
        return join(PHP_EOL, $aOut);
    }

    private function makeGenericCheckboxField(Table $oTable)
    {
        $this->output("Make generic checkbox field");

        $sBaseClassFile = $this->makeFileName($oTable, 'Checkbox', true);
        $sGeneratedCrudNamespace = $this->getBaseClassNamespace($oTable);
        $sExtendsClass = $sGeneratedCrudNamespace . '\\Base';
        $oNamespace = new PhpNamespace($sExtendsClass);
        $sSuperClass = new ClassType('Checkbox');
        $oNamespace->addUse(IField::class);
        $oNamespace->addUse(IEventField::class);
        $oNamespace->addUse(GenericCheckbox::class);
        $sSuperClass->addImplement(IField::class);
        $sSuperClass->addImplement(IEventField::class);
        $sSuperClass->setExtends(GenericCheckbox::class);
        $oNamespace->add($sSuperClass);

        $this->output("Writing generic checkbox field <info>{$sBaseClassFile}</info>");
        file_put_contents($sBaseClassFile, '<?php ' . PHP_EOL . $oNamespace);

        $sCustomClassFile = $this->makeFileName($oTable, 'Checkbox');
        $sCustomClass = $sGeneratedCrudNamespace;
        $oCustomNamespace = new PhpNamespace($sCustomClass);
        $oCustomClass = new ClassType('Checkbox');
        $oCustomClass->setFinal(true);
        $oCustomClass->addExtend($sExtendsClass . '\\Checkbox');
        $oCustomNamespace->add($oCustomClass);
        $oCustomNamespace->addUse($sExtendsClass . '\\Checkbox');

        $this->output("Writing generic checkbox edittable field <info>{$sCustomClassFile}</info>");
        file_put_contents($sCustomClassFile, '<?php ' . PHP_EOL . $oCustomNamespace);
    }

    private function makeOpenInApiField(Table $oTable)
    {
        if (!class_exists($oTable->getCrudNamespace() . '\\CrudApiTrait')) {
            echo "Not making OpenInApi object, this is probably a Crud at root level, skipping" . PHP_EOL;
            return;
        }

        $sClassName = 'OpenInApi';
        $sBaseClassFile = $this->makeFileName($oTable, 'OpenInApi', true);

        $sGeneratedCrudNamespace = $this->getBaseClassNamespace($oTable);

        $sExtendsClass = $sGeneratedCrudNamespace . '\\Base';

        $oNamespace = new PhpNamespace($sExtendsClass);
        $oSuperClass = new ClassType($sClassName);
        $oSuperClass->addComment($this->autoGeneratedMessage());

        $oSuperClass->addImplement(IFieldHasApi::class);
        $oSuperClass->addImplement(IEventField::class);

        $oSuperClass->setExtends(GenericOpenInApi::class);
        $oSuperClass->addTrait($oTable->getCrudNamespace() . '\\CrudApiTrait');

        if ($oTable->getModule() instanceof Module) {
            $oGetModule = $oSuperClass->addMethod('getModule');
            $oGetModule->setReturnType('string');
            $oGetModule->setBody('return "' . $oTable->getModule()->getName() . '";');
        }

        $oGetModuleDir = $oSuperClass->addMethod('getModuleDir');
        $oGetModuleDir->setReturnType('string');
        $oGetModuleDir->setBody('return "' . $oTable->getPhpName() . '";');

        $oNamespace->add($oSuperClass);

        $oNamespace->addUse(GenericOpenInApi::class);
        $oNamespace->addUse(IFieldHasApi::class);
        $oNamespace->addUse(IEventField::class);

        $this->output("Writing open in api file <info>{$sBaseClassFile}</info>");
        file_put_contents($sBaseClassFile, '<?php ' . PHP_EOL . $oNamespace);

        $sCustomClassFile = $this->makeFileName($oTable, $sClassName);

        $sCustomClass = $sGeneratedCrudNamespace;
        $oCustomNamespace = new PhpNamespace($sCustomClass);
        $oCustomClass = new ClassType($sClassName);
        $oCustomClass->setFinal(true);
        $oCustomClass->addExtend($sExtendsClass . '\\' . $sClassName);
        $oCustomNamespace->add($oCustomClass);

        //  $oCustomNamespace->addUse($sExtendsClass . '\\' . $sClassName . '\\CrudApiTrait');

        $this->output("Writing file <info>{$sCustomClassFile}</info>");
        file_put_contents($sCustomClassFile, '<?php ' . PHP_EOL . $oCustomNamespace);
    }

    private function autoGeneratedMessage()
    {
        return 'This code is generated and should not be modified by hand, your changes will be overwritten at the first re-run..';
    }
}
