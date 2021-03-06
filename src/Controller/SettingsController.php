<?php

namespace A2Global\CRMBundle\Controller;

use A2Global\CRMBundle\Builder\EntityBuilder;
use A2Global\CRMBundle\Component\Entity\Entity;
use A2Global\CRMBundle\Component\Field\ConfigurableFieldInterface;
use A2Global\CRMBundle\Component\Field\IdField;
use A2Global\CRMBundle\Factory\EntityFieldFactory;
use A2Global\CRMBundle\Filesystem\FileManager;
use A2Global\CRMBundle\Provider\EntityInfoProvider;
use A2Global\CRMBundle\Registry\EntityFieldRegistry;
use A2Global\CRMBundle\Utility\StringUtility;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/** @Route("/crm/settings/", name="crm_settings_") */
class SettingsController extends AbstractController
{
    protected $entityInfoProvider;

    protected $entityBuilder;

    protected $fileManager;

    protected $entityFieldFactory;

    protected $entityFieldRegistry;

    public function __construct(
        EntityInfoProvider $entityInfoProvider,
        EntityBuilder $entityBuilder,
        FileManager $fileManager,
        EntityFieldFactory $entityFieldFactory,
        EntityFieldRegistry $entityFieldRegistry
    )
    {
        $this->entityInfoProvider = $entityInfoProvider;
        $this->entityBuilder = $entityBuilder;
        $this->fileManager = $fileManager;
        $this->entityFieldFactory = $entityFieldFactory;
        $this->entityFieldRegistry = $entityFieldRegistry;
    }

    /** @Route("", name="homepage") */
    public function dashboard()
    {
        return $this->redirectToRoute('crm_settings_entity_list');
    }

    /** @Route("entity/list", name="entity_list") */
    public function entityList()
    {
        return $this->render('@A2CRM/settings/entity.list.html.twig', [
            'entityList' => $this->entityInfoProvider->getEntityList(),
        ]);
    }

    /** @Route("entity/edit/{entityName?}", name="entity_edit") */
    public function entityEdit(Request $request, string $entityName = null)
    {
        $isCreating = is_null($entityName);

        if (!$isCreating) {
            $entity = $this->entityInfoProvider->getEntity(StringUtility::normalize($entityName));
            $entityBefore = clone $entity;
        }
        $form = $this->createFormBuilder($entity ?? null, ['attr' => ['autocomplete' => 'off']])
            ->add('name', TextType::class)
            ->add('submit', SubmitType::class)
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            if ($isCreating) {
                $formData = $form->getData();
                $entityName = StringUtility::normalize($formData['name']);
                $entity = (new Entity($entityName))
                    ->addField(new IdField());
                $this->updateEntityFile($entity);
            } else {
                $this->removeEntityFile($entityBefore);
                $this->updateEntityFile($form->getData());
            }
            $request->getSession()->getFlashBag()->add('success', $isCreating ? 'Entity created' : 'Entity updated');

            return $this->redirectToRoute('crm_settings_entity_list', ['entityName' => $entityName]);
        }

        return $this->render('@A2CRM/settings/entity.edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /** @Route("entity/{entityName}/field/list", name="entity_field_list") */
    public function entityFieldList(string $entityName)
    {
        return $this->render('@A2CRM/settings/entity.field.list.html.twig', [
            'entity' => $this->entityInfoProvider->getEntity($entityName),
        ]);
    }

    /** @Route("entity/{entityName}/field/edit/{fieldName?}", name="entity_field_edit") */
    public function entityFieldEdit(Request $request, string $entityName, string $fieldName = null)
    {
        $isCreating = is_null($fieldName);
        $entity = $this->entityInfoProvider->getEntity($entityName);
        $field = $isCreating ? null : $entity->getField(StringUtility::toCamelCase($fieldName));
        $formData = [
            'name' => $isCreating ? '' : $field->getName(),
            'type' => $isCreating ? 'string' : $field->getType(),
        ];
        $form = $this->createFormBuilder($formData, [
            'attr' => ['autocomplete' => 'off'],
            'allow_extra_fields' => true,
        ])
            ->add('name', TextType::class)
            ->add('type', ChoiceType::class, [
                'choices' => $this->entityFieldRegistry->getFormFieldChoices(),
            ])
            ->add('submit', SubmitType::class)
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $form->getData();
            $field = $this->entityFieldFactory
                ->get($formData['type'])
                ->setName(StringUtility::normalize($formData['name']));

            if($field instanceof ConfigurableFieldInterface){
                $field->setConfigurationFromTheForm($form->getExtraData()['configuration']);
            }

            if ($isCreating) {
                $entity->addField($field);
            } else {
                $fieldNameBefore = StringUtility::toCamelCase($fieldName);
                $entity->updateField($fieldNameBefore, $field);
            }
            $this->updateEntityFile($entity);
            $request->getSession()->getFlashBag()->add('success', $isCreating ? 'Entity field added' : 'Entity field updated');

            return $this->redirectToRoute('crm_settings_entity_field_list', ['entityName' => $entityName]);
        }

        return $this->render('@A2CRM/settings/entity.field.edit.html.twig', [
            'entity' => $this->entityInfoProvider->getEntity($entityName),
            'field' => $field,
            'form' => $form->createView(),
        ]);
    }

    /** @Route("entity/{entityName}/field/remove/{fieldName}", name="entity_field_remove") */
    public function entityFieldRemove(Request $request, string $entityName, string $fieldName)
    {
        $entity = $this->entityInfoProvider->getEntity($entityName);
        $entity->removeField(StringUtility::toCamelCase($fieldName));
        $this->updateEntityFile($entity);
        $request->getSession()->getFlashBag()->add('warning', 'Entity field removed');

        return $this->redirectToRoute('crm_settings_entity_field_list', ['entityName' => $entityName]);
    }

    /** @Route("entity/{entityName}/field/configuration/{fieldType?}/{fieldName?}", name="entity_field_configuration") */
    public function entityFieldConfiguration(Request $request, string $entityName, string $fieldType, string $fieldName = null)
    {
        $isCreating = is_null($fieldName);
        $entity = $this->entityInfoProvider->getEntity($entityName);

        if($isCreating) {
            $field = $this->entityFieldFactory->get($fieldType);
        }else{
            $field = $entity->getField($fieldName);

            if($field->getType() != StringUtility::toCamelCase($fieldType)){
                $field = $this->entityFieldFactory->get($fieldType);
            }
        }
        $hasConfiguration = $field instanceof ConfigurableFieldInterface;

        return new JsonResponse([
            'hasConfiguration' => $hasConfiguration,
            'html' => $hasConfiguration ? $field->getConfigurationsFormControls($entity) : '',
        ]);
    }

    protected function removeEntityFile(Entity $entity)
    {
        $this->fileManager->remove(
            FileManager::CLASS_TYPE_ENTITY,
            StringUtility::toPascalCase($entity->getName())
        );
    }

    protected function updateEntityFile(Entity $entity)
    {
        $this->fileManager->save(
            FileManager::CLASS_TYPE_ENTITY,
            StringUtility::toPascalCase($entity->getName()),
            $this->entityBuilder->setEntity($entity)->getFileContent()
        );
    }
}
