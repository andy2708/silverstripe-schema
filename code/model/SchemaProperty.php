<?php

class SchemaProperty extends DataObject {
    
    private static $db = [
        'Title'         => 'Varchar(255)',
        'ValueStatic'   => 'Varchar(255)',
        'ValueDynamic'  => 'Varchar(255)',
        //'isPrimary'     => 'Boolean'
    ];

    private static $has_one = [
        'NestedSchema'  => 'SchemaInstance',
        'ParentSchema'  => 'SchemaInstance'
    ];

    private static $summary_fields = [
        'Title'             => 'Property',
        'getDescription'    => 'Value',
        //'isPrimary'         => 'Primary?'
    ];

    // private static $default_sort = [
    //     'isPrimary' => 'DESC'
    // ];

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
        
        //$fields->removeByName('isPrimary');

        $fields->replaceField(
            'Title',
            DropdownField::create('Title')
                ->setTitle('Title')
                ->setEmptyString('- Please Select -')
                ->setSource($this->populateProperties())
        );
        
        $fields->replaceField(
            'ValueDynamic',
            GroupedDropdownField::create('ValueDynamic')
                ->setTitle('Dynamic Value')
                /**
                 * Won't work as per {@link https://github.com/silverstripe/silverstripe-framework/issues/4987}, consider submitting a PR to fix.
                 */
                ->setEmptyString('[Optional]')
                ->setSource(['Disabled' => ['' => '[Optional]']] + $dvSource)
                ->setDescription('Priority #2 - Populate this property dynamically using the output of a method or property.')
        );

        $fields->fieldByName('Root.Main.ValueStatic')
            ->setDescription('Priority #1 - A Fixed value to be used at all times. This takes priority over any other "value" settings.');

        /**
         * @todo We need a way to include all DataObject instances which have no
         * specific SchemaInstance of their own, but inherit one based on the 
         * default schema configuration for objects of that type. These should be
         * nestable but currently are not (the quick fix is to add an empty
         * SchemaInstance (i.e. one with no properties) to each DataObject instance)
         */
        $nsSource = [];
        $schemas = SchemaInstance::get()
            ->exclude('RelatedObjectID', 0)
            ->sort('SchemaID', 'ASC');
        foreach($schemas as $schema) {
            $schemaType = $schema->getComponent('Schema')->getTitle();
            $relObjectTitle = $schema->getComponent('RelatedObject')->getTitle();
            $nsSource[$schemaType][] = $relObjectTitle;
        }

        $fields->replaceField(
            'NestedSchemaID',
            GroupedDropdownField::create('NestedSchemaID')
                ->setEmptyString('[Optional]')
                ->setSource($nsSource)
                ->setDescription('Priority #3 - This properties value can be represented by an existing schema. Link to the existing schema to prevent duplication.')
                
        );

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

        if (!empty($this->ValueStatic)) {
            return $this->ValueStatic;
        }

        if (!empty($this->ValueDynamic)) {
            return "{" . $this->ValueDynamic . "}";
        }

        $nestedSchema = $this->getComponent('NestedSchema');
        if (is_object($nestedSchema) && $nestedSchema->exists()) {
            return $nestedSchema->getTitle();
        }

        return "(not set)";
    }

    public function getValue($sids = []) {
    
        if (!empty($this->ValueStatic)) {
            return $this->ValueStatic;
        }
        
        if (!empty($this->ValueDynamic)) {

            // Check for valid class name to property/method name separator
            if(strpos($this->ValueDynamic, "::") !== false) {
                $separator = "::";
            } elseif (strpos($this->ValueDynamic, "->") !== false) {
                $separator = "->";
            } else {
                error_log('SchemaProperty::getValue() tried to process a dymanic value with an unexpected format - "' . $this->ValueDynamic . '" - (expects separator of "::" or "->" between class name and method/property name )');
                return "";
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
                    return "";
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
        
        $nestedSchema = $this->getComponent('NestedSchema');
        if (is_object($nestedSchema) && $nestedSchema->exists()) {
            if (in_array($nestedSchema->ID, $sids)) {
                return $nestedSchema->getSummary();
            } else {
                return $nestedSchema->getStructuredData(false, $sids);
            }
        }

        return "";
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
