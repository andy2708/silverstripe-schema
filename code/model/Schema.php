<?php

class Schema extends DataObject {
    
    private static $db = [
        'Title'             => 'Varchar(255)',
        'SchemaOrgID'       => 'Varchar(255)',
        //'PrimaryProperty'   => 'Varchar(255)',
        'SchemaOrgURL'      => 'Varchar(255)',
        'LocalDataSource'   => 'Varchar(255)', // Stored relative to the site root
        'LastUpdated'       => 'SS_DateTime'
    ];

    private static $has_many = [
        'SchemaInstances' => 'SchemaInstance'
    ];

    private static $summary_fields = [
        'Title'         => "Schema Type",
        'SchemaOrgURL'  => 'schema.org URL',
        'LastUpdated'   => 'Last Updated'
    ];

    /**
     * @var Array - Details of the where and how schema.org data is stored locally
     */
    public static $DataSource = [
        'directory' => 'silverstripe-schema/data-sources/',
        'format'    => 'jsonld'
    ];

    /**
     * @var String - The file name for the top level vocabulary tree/schema index
     */
    public static $VocabularyFileName = "VocabularyTree";

    public function getCMSFields() {

        $fields = parent::getCMSFields();

        $fields->replaceField(
            'SchemaOrgID',
            DropdownField::create('SchemaOrgID')
                ->setTitle('Schema')
                ->setEmptyString('- Please Select -')
                ->setSource($this->getSchemaIDs())
                ->setDisabledItems(
                    array_diff(
                        Schema::get()->column('SchemaOrgID'),
                        [$this->SchemaOrgID]
                    )
                )
        );

        $fields->replaceField(
            'Title',
            $fields->fieldByName('Root.Main.Title')
                ->performReadonlyTransformation()
        );

        // $source = $this->getProperties()->map('Name', 'Name');
        // asort($source);
        // $fields->replaceField(
        //     'PrimaryProperty',
        //     DropdownField::create('PrimaryProperty')
        //         ->setTitle('Primary Property')
        //         ->setEmptyString('- Please Select -')
        //         ->setSource($source)
        //         ->setDescription('The most descriptive property for this schema, this property will be required on schemas of this type. If in doubt, go with the \'name\' property.')
        // );

        $fields->replaceField(
            'SchemaOrgURL',
            $fields->fieldByName('Root.Main.SchemaOrgURL')
                ->setTitle('schema.org URL')
                ->performReadonlyTransformation()
        );

        $fields->replaceField(
            'LocalDataSource',
            $fields->fieldByName('Root.Main.LocalDataSource')
                ->performReadonlyTransformation()
        );

        $fields->replaceField(
            'LastUpdated',
            $fields->fieldByName('Root.Main.LastUpdated')
                ->performReadonlyTransformation()
        );

        if($this->exists()) {
            $fields->removeByName('SchemaInstances');
            $instancesGridField = GridField::create('SchemaInstances')
                ->setTitle('Instances')
                ->setList(
                    $this->getComponents('SchemaInstances')
                )
                ->setConfig(
                    $config = GridFieldConfig_RecordViewer::create()
                );
            $fields->addFieldToTab('Root.Main', $instancesGridField);
        }
        
        return $fields;
    }

    public function validate() {
        $result = parent::validate();
        if(empty($this->SchemaOrgID)) {
            $result->error('Schema is a required field!');
        }
        // } elseif ($this->exists() && empty($this->PrimaryProperty)) {
        //     $result->error('Primary Property is a required field!');
        // }
        return $result;
    }

    public static function getVocabularyFilePath() {
        return static::$DataSource['directory']
            . static::$VocabularyFileName
            . "."
            . static::$DataSource['format'];
    }

    /**
     * @return ArrayList
     */
    public function getProperties() {
        
        $properties = ArrayList::create();
        
        $data = json_decode(file_get_contents("../" . $this->LocalDataSource), true);
        
        if(!empty($data)) {

            if(isset($data['@context']) && is_array($data['@context'])) {
                $patterns = [];
                foreach($data['@context'] as $key => $value) {
                    $patterns[] = $key . ":";
                }
            }

            if(isset($data['@graph']) && is_array($data['@graph'])) {

                foreach($data['@graph'] as $item) {
                    
                    // Ignore the following entries:
                    if(
                        // Ignore any @graph entries without ID's
                        !isset($item['@id'])
                        /*
                         * Ignore any @graph entries where the ID references
                         * something which is not defined in the @contexts section
                         */
                        || (
                            isset($patterns)
                            && !preg_match("/^" . implode("|", $patterns) . "/", $item['@id'])
                        )
                        /*
                         * Ignore any @graph entries which are not examples of
                         * properties (i.e. classes and sub-classes)
                         */
                        || isset($item['rdfs:subClassOf'])
                        || (
                            isset($item['@type'])
                            && $item['@type'] !== 'rdf:Property'
                        )
                    ) {
                        continue;
                    }
                    
                    $segments = explode(':', $item['@id']);
                    $name = end($segments);

                    $range = [];
                    if(isset($item['schema:rangeIncludes'])) {
                        $acceptedTypes = $item['schema:rangeIncludes'];
                        $addToRange = function($type, &$arr) {
                            if(isset($type['@id'])) {
                                $segments = explode(':', $type['@id']);
                                $arr[] = end($segments);
                            }
                        };
                        // Check if there is a single accepted type or multiple
                        if(!isset($acceptedTypes[0])) {
                            $addToRange($acceptedTypes, $range);
                        } else {
                            foreach($acceptedTypes as $acceptedType) {
                                $addToRange($acceptedType, $range);
                            }
                        }
                    }

                    $properties->push(
                        ArrayData::create([
                            'Name'      => $name,
                            'Label'     => (isset($item['rdfs:label'])) ? $item['rdfs:label'] : '',
                            'Comment'   => (isset($item['rdfs:comment'])) ? $item['rdfs:comment'] : '',
                            'Range'     => $range
                        ])
                    );

                }

            }

        }

        return $properties; 
    }

    public function getSchemaIDs() {
        $source = [];
        $filepath = "../" . static::getVocabularyFilePath();
        $data = json_decode(file_get_contents($filepath), true);
        if(!empty($data)) {
            static::processSchema($data, $source);
        }
        return $source;
    }

    public function processSchema($schema, &$source = [], $depth = 0) {
        $prefix = str_pad('', $depth, ">");
        $prefix .= (!empty($prefix)) ? ' ' : '';
        $source[$schema["@id"]] = $prefix . $schema["name"];
        if(isset($schema["children"])) {
            $depth++;
            foreach($schema["children"] as $s) {
                static::processSchema($s, $source, $depth);
            }
        }
    }

    public function onBeforeWrite() {

        parent::onBeforeWrite();
        
        if(empty($this->Title)) {
            $segments = explode(':', $this->SchemaOrgID);
            $this->Title = end($segments);
        }

        if(empty($this->LocalDataSource)) {
            $this->LocalDataSource = static::$DataSource['directory']
                . $this->Title
                . "."
                . static::$DataSource['format'];
        }

        if(empty($this->SchemaOrgURL)) {
            $this->SchemaOrgURL = 'https://schema.org/' . $this->Title;
        }
        
        if(!file_exists('../' . $this->LocalDataSource)) {
            $bytes = file_put_contents(
                "../" . $this->LocalDataSource,
                fopen($this->SchemaOrgURL . "." . static::$DataSource['format'], 'r')
            );
            if($bytes !== false) {
                $this->LastUpdated = date('Y-m-d H:i:s', time());
            }
        }

    }

    public function onAfterDelete() {
        parent::onAfterDelete();
        // Delete the local data source file
        unlink("../" . $this->LocalDataSource);
    }

}