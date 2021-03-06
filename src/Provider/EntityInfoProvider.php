<?php

namespace A2Global\CRMBundle\Provider;

use A2Global\CRMBundle\Component\Entity\Entity;
use A2Global\CRMBundle\Component\Field\ChoiceField;
use A2Global\CRMBundle\Component\Field\FieldInterface;
use A2Global\CRMBundle\Factory\EntityFieldFactory;
use A2Global\CRMBundle\Filesystem\FileManager;
use A2Global\CRMBundle\Registry\EntityFieldRegistry;
use A2Global\CRMBundle\Utility\StringUtility;
use Doctrine\Common\Annotations\Reader;
use Doctrine\ORM\Mapping\JoinColumn;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;
use ReflectionClass;
use ReflectionProperty;

class EntityInfoProvider
{
    protected $fileManager;

    protected $entityFieldRegistry;

    protected $reader;

    protected $entityFieldFactory;

    public function __construct(
        FileManager $fileManager,
        EntityFieldRegistry $entityFieldRegistry,
        EntityFieldFactory $entityFieldFactory,
        Reader $reader
    )
    {
        $this->fileManager = $fileManager;
        $this->entityFieldRegistry = $entityFieldRegistry;
        $this->reader = $reader;
        $this->entityFieldFactory = $entityFieldFactory;
    }

    public function getEntityList(): array
    {
        $directory = $this->fileManager->getPath(FileManager::CLASS_TYPE_ENTITY);

        return array_map(function ($item) {
            return StringUtility::normalize(basename(substr($item, 0, -4)));
        }, glob($directory . '/*.php'));
    }

    public function getEntity($entityName): Entity
    {
        if (is_object($entityName)) {
            $entityName = StringUtility::getShortClassName($entityName);
        }
        $entity = new Entity(StringUtility::normalize($entityName));
        $class = 'App\\Entity\\' . StringUtility::toPascalCase($entityName);
        $reflection = new ReflectionClass($class);
        $tableAnnotation = $this->reader->getClassAnnotation($reflection, Table::class);

        if($tableAnnotation){
            $tableName = $tableAnnotation->name;
        }else{
            $tableName = StringUtility::toSnakeCase($entity->getName());
        }
        $entity->setTableName($tableName);

        foreach ($reflection->getProperties() as $property) {
            $field = $this->getField($property, $reflection);

            if ($field) {
                $entity->addField($field);
            }
        }

        return $entity;
    }

    protected function parseAnnotations(string $comment): array
    {
        $items = [];

        foreach (explode(PHP_EOL, $comment) as $line) {
            if (!preg_match('/\@(.+)\\\(.+)\((.*)\)/iUs', $line, $result)) {
                continue;
            }
            $options = [];

            if (trim($result[3])) {
                foreach (explode(',', $result[3]) as $parameter) {
                    preg_match("/^([^\=]+)(\=(.+))?$/iUs", $parameter, $subresult);
                    $options[trim($subresult[1])] = isset($subresult[3]) ? trim($subresult[3], ' "\'') : null;
                }
            }
            $items[$result[1]][$result[2]] = $options;
        }

        return $items;
    }

    protected function getField(ReflectionProperty $property, ReflectionClass $reflection): ?FieldInterface
    {
        $annotations = $this->parseAnnotations($property->getDocComment());
        $fieldName = $property->getName();

        if (isset($annotations['ORM']['Id'])) {
            return $this->entityFieldFactory->get('id');
        }

        if (isset($annotations['ORM']['Column']['type'])) {
            $fieldType = $annotations['ORM']['Column']['type'];

            if (in_array($fieldType, ['string', 'integer', 'float', 'boolean', 'date', 'datetime', 'text'])) {
                $constants = $reflection->getConstants();
                $choiceConstName = StringUtility::toConstantName('CHOICES_' . $fieldName);

                if (array_key_exists($choiceConstName, $constants)) {
                    $fieldType = 'choice';
                }
                $field = $this->entityFieldFactory->get($fieldType);
                $field->setName(StringUtility::normalize($fieldName));

                if ($field instanceof ChoiceField) {
                    $field->setChoices($constants[$choiceConstName]);
                }

                return $field;
            }
        }
        $annotations = $this->reader->getPropertyAnnotations($property);

        if (count($annotations) && $annotations[0] instanceof ManyToOne && $annotations[1] instanceof JoinColumn) {
            $field = $this->entityFieldFactory->get('relation');

            return $field
                ->setName(StringUtility::normalize($fieldName))
                ->setTargetEntity($annotations[0]->targetEntity);
        }

        return null;
    }
}