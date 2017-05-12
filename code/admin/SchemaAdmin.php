<?php

/**
 * Schema Admin
 *
 * An extenison of the ModelAdmin interface used for managing CRUD operations
 * on schema.org Schemas.
 *
 * @author Joe Harvey <joe.harvey@quadradigital.co.uk>
 */
class SchemaAdmin extends ModelAdmin {

    public static $managed_models = [
        'Schema' => ['title' => 'Enabled Schemas'],
        'SchemaInstance' => ['title' => 'Default Configurations']
    ];

    public static $url_segment = 'schemas';
    public static $menu_priority = 0.2;
    public static $menu_title = "Schemas";
    public static $menu_icon = '/silverstripe-schema/code/admin/images/menu-icons/16x16/schema-icon.png';

    public $showImportForm = false;

    // Overload the scaffolded GridField
    public function getEditForm($id = null, $fields = null) {

        $form = parent::getEditForm($id, $fields);

        if($this->modelClass == 'Schema') {

            $gridField = $form->Fields()->fieldByName(
                $this->sanitiseClassName($this->modelClass)
            );
            $config = $gridField->getConfig();

        }

        if($this->modelClass == 'SchemaInstance') {
            $gridField = $form->Fields()->fieldByName(
                $this->sanitiseClassName($this->modelClass)
            );
            $config = $gridField->getConfig();

            $config->getComponentByType('GridFieldDetailForm')->setItemEditFormCallback(
                function($form, $itemRequest) {
                    
                    $fields = $form->Fields();

                    $extendedClasses = [];
                    foreach(ClassInfo::subclassesFor('DataObject') as $candidate) {
                        if($candidate::has_extension('SchemaObjectExtension')) {
                            $extendedClasses[$candidate] = $candidate;
                        }
                    }

                    $currClass = $itemRequest->record->RelatedObjectClass;
                    $fields->insertBefore(
                        'SchemaID',
                        DropdownField::create('RelatedObjectClass')
                            ->setTitle('DataObject Type')
                            ->setEmptyString('- Please Select -')
                            ->setSource($extendedClasses)
                            ->setValue($currClass)
                            ->setDisabledItems(
                                array_diff(
                                    SchemaInstance::get()
                                        ->filter([
                                            'RelatedObjectID' => 0
                                        ])
                                        ->column('RelatedObjectClass'),
                                    [$currClass]
                                )
                            )
                    );

                }
            );
        }

        return $form;

    }

    public function getList() {

        $list = parent::getList();

        if($this->modelClass == 'SchemaInstance') {

            /*
             * Only include 'default' schema configurations, which are not tied
             * to a specific instance of a DataObject
             */
            $list = $list->filter([
                'RelatedObjectID' => 0
            ]);
        }

        return $list;
    }

}
