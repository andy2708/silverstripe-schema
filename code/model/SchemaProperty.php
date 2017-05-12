<?php

class SchemaProperty extends DataObject {
    
    private static $db = [
        'Title'         => 'Varchar(255)',
        'ValueStatic'   => 'Varchar(255)',
        'ValueDynamic'  => 'Varchar(255)'
    ];

    private static $has_one = [
        'NestedSchema'  => 'SchemaInstance',
        'ParentSchema'  => 'SchemaInstance'
    ];

    private static $summary_fields = [
        'Title'             => 'Property',
        'getDescription'    => 'Value'
    ];

    /**
     * @config
     */
    private static $dynamic_value_core_options = [];
    private static $dynamic_value_override_options = [];

    /**
     * Used to store a related object for properties which are inherited from
     * one of the 'default' SchemaInstances. {@link static::getRelatedObject()}
     * will not return a valid instance of a DataObject for these properties as
     * their ParentSchema (the default) will have a RelatedObjectID of 0, so we
     * have to pass down a 'soft linked' object.
     */
    public $soft_linked_object;

    public function getDynamicValueOptions() {

        $defaults = Config::inst()->get('SchemaProperty', 'core_dynamic_value_options');
        $overrides = Config::inst()->get('SchemaProperty', 'user_dynamic_value_options');

        if(isset($overrides)) {
            Config::merge_array_high_into_low($defaults, $overrides);
        }

        return $defaults;
    }

    public function getCMSFields() {
        
        $fields = parent::getCMSFields();
        
        $fields->replaceField(
            'Title',
            DropdownField::create('Title')
                ->setTitle('Title')
                ->setEmptyString('- Please Select -')
                ->setSource($this->populateProperties())
        );
        
        $nsSource = [];
        $schemas = SchemaInstance::get()
            ->exclude('RelatedObjectID', 0)
            ->sort('SchemaID', 'ASC');
        foreach($schemas as $schema) {
            $schemaType = $schema->getComponent('Schema')->getTitle();
            $nsSource[$schemaType][$schema->ID] = $schema->getTitle();
        }
        $fields->replaceField(
            'NestedSchemaID',
            GroupedDropdownField::create('NestedSchemaID')
                ->setTitle('Nested Schema')
                /**
                 * Won't work as per {@link https://github.com/silverstripe/silverstripe-framework/issues/4987}, consider submitting a PR to fix.
                 */
                ->setEmptyString('[Optional]')
                // Array addition is a hack to temporarily resolve the above
                ->setSource(['Disabled' => ['' => '[Optional]']] + $nsSource)
                ->setDescription('Priority #1 - This properties value can be represented by an existing schema. Link to the existing schema to prevent duplication.')
        );
        
        $dvSource = [];
        $dvSourceCandidates = $this->getDynamicValueOptions();        
        $relObjClass = $this->getComponent('ParentSchema')->RelatedObjectClass;
        foreach ($dvSourceCandidates as $category => $values) {
            foreach ($values as $key => $value) {
                
                if (
                    $relObjClass !== $category
                    && !is_subclass_of($relObjClass, $category)
                    && !preg_match('/^::/', $key)
                ) {
                    /*
                     * Do not include instance based method/property options
                     * where the source class is not of the same type as the 
                     * related DataObject.
                     */
                    continue;
                }

                if (!isset($value) || empty($value)) {
                    $value = preg_replace('/[\:\$\-\>]/', '', $key);
                }

                /*
                 * Prepend the key with class name, as when pulled out of the DB
                 * we will have no context to indicate the category (i.e. class name)
                 */
                $dvSource[$category][$category . $key] = $value;
            }
        }
        $fields->replaceField(
            'ValueDynamic',
            GroupedDropdownField::create('ValueDynamic')
                ->setTitle('Dynamic Value')
                /**
                 * Won't work as per {@link https://github.com/silverstripe/silverstripe-framework/issues/4987}, consider submitting a PR to fix.
                 */
                ->setEmptyString('[Optional]')
                // Array addition is a hack to temporarily resolve the above
                ->setSource(['Disabled' => ['' => '[Optional]']] + $dvSource)
                ->setDescription('Priority #2 - Populate this property dynamically using the output of a method or property.')
        );
        
        $fields->fieldByName('Root.Main.ValueStatic')
            ->setDescription('Priority #3 - A Fixed value to be used at all times.');

        $fields->changeFieldOrder([
            'Title',
            'NestedSchemaID',
            'ValueDynamic',
            'ValueStatic',
            'ParentSchemaID'    
        ]);

        if(
            !empty($this->NestedSchemaID)
            && empty($this->ValueDynamic)
            && empty($this->ValueStatic)
        ) {
            $fields->insertAfter(
                'NestedSchemaID',
                ReadonlyField::create('FallbackNote')
                    ->setTitle('Fallback Recommendation')
                    ->setValue('In some scenarios nesting schemas is not allowed (in order to prevent infinite nesting loops). It is highly reccomended that you add a dynamic or static value for this property, which can be served as a fallback in the above context.'));
        }

        return $fields;
    }

    public function getRelatedObject() {

        if(
            isset($this->soft_linked_object)
            && is_object($this->soft_linked_object)
        ) {
            return $this->soft_linked_object;
        }

        $parentSchema = $this->getComponent('ParentSchema');
        if(!is_object($parentSchema) || !$parentSchema->exists()) {
            error_log('SchemaProperty::getRelatedObject() - SchemaProperty (#' . $this->ID . ') has no ParentSchema defined!');
            return new DataObject();
        }

        $relatedObj = $parentSchema->getComponent('RelatedObject');
        if(!is_object($relatedObj) || !$relatedObj->exists()) {
            error_log('SchemaProperty::getRelatedObject() - Could not find DataObject with ClassName = "' . $parentSchema->RelatedObjectClass . '" and ID "' . $parentSchema->RelatedObjectID . '"');
            return new DataObject();
        }

        return $relatedObj;

    }

    public function getDescription() {

        $nestedSchema = $this->getComponent('NestedSchema');
        if (is_object($nestedSchema) && $nestedSchema->exists()) {
            return "{" . $nestedSchema->getTitle() . "}";
        }

        if (!empty($this->ValueDynamic)) {
            return "{" . $this->ValueDynamic . "}";
        }

        if (!empty($this->ValueStatic)) {
            return $this->ValueStatic;
        }

        return "(not set)";
    }

    public function getValue($sids = [], $allowNesting = true) {
    
        /*
         * Priority 1
         * (as long as nesting has not been prevented)
         */
        if($allowNesting) {
            $nestedSchema = $this->getComponent('NestedSchema');
            if (is_object($nestedSchema) && $nestedSchema->exists()) {
                /**
                 * To prevent infinite loops caused by mis-configured nested schemas
                 * (i.e Person->worksFor->Organization->employee->Person) susequent
                 * requests to include the same nested SchemaInstance will result
                 * in the properties dynamic or static value (if set) being returned
                 * instead. If no dynamic or static value is set for this property
                 * an empty string is returned, meaning this property is not included
                 * in the resulting SchemaInstance.
                 */
                if (in_array($nestedSchema->ID, $sids)) {
                    return $this->getValue([], false);
                } else {
                    return $nestedSchema->getStructuredData(false, $sids);
                }
            }
        }
        
        // Priority 2
        if (!empty($this->ValueDynamic)) {

            // Check for valid class name to property/method name separator
            if(strpos($this->ValueDynamic, "::") !== false) {
                $separator = "::";
            } elseif (strpos($this->ValueDynamic, "->") !== false) {
                $separator = "->";
            } else {
                error_log('SchemaProperty::getValue() tried to process a dymanic value with an unexpected format - "' . $this->ValueDynamic . '" - (expects separator of "::" or "->" between class name and method/property name )');
                // Fallback to any static value which might be defined
                return $this->ValueStatic;
            }
            
            // Split class name and property/method name by separator
            list($className, $fieldName) = explode($separator, $this->ValueDynamic, 2);
            if(preg_match('/\(\)$/', $fieldName)) {
                $isMethod = true;
                $fieldName = substr($fieldName, 0, -2);
            } else {
                $isMethod = false;
                /*
                 * Strip any '$' from property definitions (these are added below
                 * for static properties and are not needed for instance based
                 * properties)
                 */
                if(strpos($this->ValueDynamic, "$") !== false) {
                    $fieldName = preg_replace('/[\$]/', '', $fieldName);
                }
            }

            if($separator === "::") {
                $value = (!$isMethod) ? $className::${$fieldName} : $className::{$fieldName}();
            } else {

                $relatedObj = $this->getRelatedObject();
                if(
                    !$relatedObj->exists()
                    || (
                        $relatedObj->ClassName !== $className
                        && !is_subclass_of($relatedObj->ClassName, $className)
                    )
                ) {
                    error_log('SchemaProperty::getValue() tried to process a dymanic value which specifies an instance based (i.e. non-static) method/property on a class name which differs from, and/or does not extend, the related object type! (Related Object = "' . json_encode($relatedObj) . '", Requested Class = "' . $className . '"');
                    // Fallback to any static value which might be defined
                    return $this->ValueStatic;
                }

                $value = (!$isMethod)
                    ? $relatedObj->getField($fieldName)
                    : $relatedObj->{$fieldName}();

                if(is_object($value)) {
                    $value = (method_exists($value, 'getTitle'))
                        ? $value->getTitle()
                        : (string)$value;
                }
            }

            return $value;
        }
        
        /**
         * Priority 3
         * Might be empty (in which case this property will be excluded)
         */
        return $this->ValueStatic;

    }

    public function populateProperties() {
        $source = [];
        $parent = $this->getComponent('ParentSchema');
        if(is_object($parent) && $parent->exists()) {
            $properties = $parent->getComponent('Schema')->getProperties();
            foreach($properties as $property) {
                $source[$property->Name] = $property->Name;
            }
            asort($source);
        }
        return $source;
    }

}
