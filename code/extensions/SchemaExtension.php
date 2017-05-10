<?php

class SchemaExtension extends DataExtension {
    
    private static $has_many = [    
        'SchemaInstances' => 'SchemaInstance.RelatedObject'
    ];

    public function updateCMSfields(FieldList $fields) {

        $owner = $this->getOwner();

        $schemasGridField = GridField::create('SchemaInstances')
            ->setTitle('Schemas')
            ->setList(
                $owner->getComponents('SchemaInstances')
            )
            ->setConfig(
                $config = GridFieldConfig_RelationEditor::create()
                    ->removeComponentsByType('GridFieldAddExistingAutocompleter')
                    ->removeComponentsByType('GridFieldDeleteAction')
                    ->addComponent(new GridFieldDeleteAction(false))
            );
        
        if(!$owner->is_a('SchemaInstance')) {
            $fields->addFieldToTab('Root.SchemaInstances', $schemasGridField);
        }

        $defaultSchema = $owner->getDefaultSchema();
        if(is_object($defaultSchema) && $defaultSchema->exists()) {
            $fields->addFieldToTab(
                'Root.SchemaInstances',
                TextareaField::create('InheritedSchema')
                    ->setTitle('Inherited Schema')
                    ->setValue($owner->getStructuredData(true, true, true))
                    ->setRows(15)
                    ->setDescription('"' . $owner->ClassName . '" objects inherit a default schema configured as above.<br />You can append, modify or remove properties, by adding another schema of this type to this instance of "' . $owner->ClassName . '" to make it more specific.')
                    ->setAttribute('readonly', 'readonly')
            );
        }


        return $fields;
    }

    /** 
     * Attempts to find the default schema for object of this type (e.g. Member)
     * If it finds one, it's 'soft linked' to this particular instance of that
     * object (e.g. Member #17) by setting it's RelatedObjectID (which would
     * origianlly be 0, as it's a default schema) to the current objects ID. 
     * Calls to SchemaInstance->getComponent('RelatedObject') will then return
     * THIS DataObject, instead of returning an empty object (because
     * RelatedObjectID was 0)
     */
    public function getDefaultSchema() {
        $default = SchemaInstance::get()
            ->filter([
                'RelatedObjectID' => 0,
                'RelatedObjectClass' => $this->getOwner()->ClassName
            ])
            ->sort('Sort', 'ASC')
            ->first();
        if(!is_object($default) || !$default->exists()) {
            return SchemaInstance::create();
        }
        $default->RelatedObjectID = $this->getOwner()->ID;
        return $default;
    }

    public function getStructuredData($encoded = true, $inheritedOnly = false, $summaryOnly = false) {

        $owner = $this->getOwner();

        $data = [];

        if(!$inheritedOnly) {
            $schemas = $owner->getComponents('SchemaInstances');
            foreach($schemas as $schema) {
                $data[] = $schema->getStructuredData(false, [], $summaryOnly);
            }
        }

        $defaultSchema = $owner->getDefaultSchema();
        if(is_object($defaultSchema) && $defaultSchema->exists()) {
            /*
             * If the SchemaID of the default schema (for this type of DataObject)
             * does not exist in the HasManyList of related SchemaInstances, it
             * means there is no more specific schema added for this particular
             * DataObject instance.
             *
             * In which case, push in the default schema configuration for
             * DataObjects of this type.
             */
            if(
                $inheritedOnly
                || $schemas->find('SchemaID', $defaultSchema->SchemaID) == null
            ) {

                $data[] = $defaultSchema->getStructuredData(false, [], $summaryOnly);
            }
        }

        return ($encoded)
            ? '<script type="application/ld+json">' . "\n"
                . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                . "\n" . '</script>'
            : $data;
    }

}
