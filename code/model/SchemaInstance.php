<?php

class SchemaInstance extends DataObject {

    private static $has_one = [
        'RelatedObject' => 'DataObject',
        'Schema'        => 'Schema'
    ];

    private static $has_many = [
        'Properties' => 'SchemaProperty'
    ];

    private static $summary_fields = [
        'Schema.Title'      => 'Type',
        'getTitle'          => 'Applied To',
        'Properties.Count'  => 'No. of Properties'
    ];

    public function getCMSFields() {

        $fields = parent::getCMSFields();

        $fields->findOrMakeTab('Root.Main')->Fields()->replaceField(
            'Schema',
            DropdownField::create('Schema')
                ->setEmptyString('- Please Select -')
                ->setSource(
                    Schema::get()->sort('SchemaOrgID', 'ASC')->map('ID', 'getTitle')
                )
        );

        if($this->exists()) {

            $schema = $this->getComponent('Schema');

            $fields->removeByName('Properties');
            $properties = GridField::create('Properties')
                ->setTitle('Properties')
                ->setList($this->getComponents('Properties'))
                ->setConfig(
                    $config = GridFieldConfig_RelationEditor::create()
                        ->removeComponentsByType('GridFieldAddExistingAutocompleter')
                        ->removeComponentsByType('GridFieldDeleteAction')
                        ->addComponent(new GridFieldDeleteAction(false))
                        // Replace default detail form with my 'auto create record' version
                        ->removeComponentsByType("GridFieldDetailForm")
                        ->addComponent(new GridFieldDetailForm_AutoCreate())
                )
                ->setDescription('Need to know what properties are available on \'' . $schema->Title . '\' and how the values must be formatted? Check out <a href="' . $schema->SchemaOrgURL . '" title="View the ' . $schema->Title . ' schema on schema.org" target="_blank">' . $schema->SchemaOrgURL . '</a>');
            $config->getComponentByType('GridFieldDataColumns')
                ->setFieldFormatting([
                    'isPrimary' => function($value, $item) {
                        return ($value) ? 'Yes' : 'No';
                    }
                ]);
            $fields->addFieldToTab('Root.Main', $properties);

            $defaultSchema = $this->getDefaultSchema();
            if(
                is_object($defaultSchema)
                && $defaultSchema->exists()
                && $this->ID != $defaultSchema->ID
            ) {
                $fields->insertBefore(
                    'Properties',
                    TextareaField::create('InheritedSchema')
                        ->setTitle('Default Configuration')
                        ->setValue($defaultSchema->getStructuredData(true, [], true))
                        ->setRows(15)
                        ->setDescription('"' . $this->RelatedObjectClass . '" objects inherit a default schema, of type "' . $defaultSchema->getComponent('Schema')->Title . '", configured as above.<br />You can append, modify or remove properties to make this particular instance of "' . $this->RelatedObjectClass . '" more specific.')
                        ->setAttribute('readonly', 'readonly')
                );
            } 

        }

        return $fields;
    }

    public function getTitle() {

        $title = "";

        $relObject = $this->getComponent('RelatedObject');
        if(is_object($relObject) && $relObject->exists()) {
            $title .= $relObject->getTitle();
            $title .= " (" . $relObject->ClassName . " #" . $relObject->ID . ")";
        } else if (empty($this->RelatedObjectID)) {
            $title .=  $this->RelatedObjectClass . " Default";
        } else {
            $title .= "Unknown DataObject";
        }

        $relSchema = $this->getComponent('Schema');
        if(!is_object($relSchema) || !$relSchema->exists()) {
            $title .= " > Unknown Schema";
        } else {
            $title .= " > " . $relSchema->SchemaOrgID;
        }

        return $title;

    }

    public function getDefaultSchema() {
        return SchemaInstance::get()
            ->filter([
                'RelatedObjectID' => 0,
                'SchemaID' => $this->SchemaID
            ])
            ->sort('Sort', 'ASC')
            ->first();
    }

    public function getStructuredData($encoded = true, $sids = [], $summaryOnly = false) {

        $data = [];
        $sids[] = $this->ID;

        $parentSchema = $this->getComponent('Schema');
        $properties = $this->getComponents('Properties');

        /**
         * If a default schema of this type exists, merge the default and specific
         * versions together, giving priority to the more specific versions properties
         */
        $defaultSchema = $this->getDefaultSchema();
        if(
            is_object($defaultSchema)
            && $defaultSchema->exists()
            && $this->ID != $defaultSchema->ID
        ) {
            // Convert HasManyList of this objects SchemaProperties to an ArrayList
            $properties = $properties->filterByCallback(
                function($item, $list) { return true; }
            );
            foreach($defaultSchema->getComponents('Properties') as $inheritedProp) {
                /*
                 * If no more specific version of this property exists, add in
                 * the inherited default version
                 */
                if($properties->find('Title', $inheritedProp->Title) == null) {
                    /*
                     * Overwrite the ParentSchemaID stored in the DB for this 
                     * SchemaProperty (the default SchemaInstance it is attached to)
                     * to simulate it being owned by the more specific SchemaInstance
                     * which inherits it.
                     */
                    $inheritedProp->ParentSchemaID = $this->ID;
                    $properties->add($inheritedProp);
                }
            }
        }
        
        $data['@context'] = "https://schema.org/";
        $data['@type'] = $parentSchema->Title;
        
        /**
         * The only context where a SchemaInstance will not have a related DataObject
         * is that of the 'default' SchemaInstances. When we push in default
         * SchemaInstances in SchemaExtension::getStructuredData(), we 'soft link'
         * them to the relevant DataObject by setting their RelatedObjectID field
         * accordingly.
         *
         * If ever you need to call SchemaInstance::getStructuredData() directly
         * on a 'default' schema, you will also need to set the RelatedObjectID 
         * field accordingly, otherwise 'ValueDynamic' lookups will fail in 
         * SchemaProperty::getValue()
         */
        $relObj = $this->getComponent('RelatedObject');
        foreach($properties as $property) {

            // Pass the related DataObject down to the property directly
            $property->soft_linked_object = $relObj;
            
            $value = (!$summaryOnly)
                ? $property->getValue($sids)
                : $property->getDescription();
            if(!empty($value)) {
                $data[$property->Title] = $value;
            }
        }

        return ($encoded)
            ? '<script type="application/ld+json">' . "\n"
                . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                . "\n" . '</script>'
            : $data;
    }

}