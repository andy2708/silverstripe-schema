<?php

class UpdateDataSourcesTask extends BuildTask {

    protected $title = 'Update Schema.org Data Sources';

    protected $description = 'Update schema.org Data Sources:
        This task downloads the most recent version(s) of these data sources to
        the /silverstripe-schema/data-sources/ directory, names them accordingly
        and updates the corresponding {@link Schema} objects.';

    protected $enabled = true;

    public function run($request) {

        $this->updateDataSources();

    }

    /**
     * Update DataSources
     *
     * Downloads the most recent version(s) of the schema.org vocabulary tree,
     * and any configured schemas, and stores them in the relevant location.
     *
     * @return Void
     */
    public static function updateDataSources() {

        $error = false;

        // Define the destination directory
        $directory = "../" . Schema::$DataSource['directory'];

        // Create the destination directory if it does not already exist
        if(!file_exists($directory) || !is_dir($directory)) {
            $made = mkdir($directory, 0755, true);
            if(!$made) {
                echo "'" . $directory . "' does not exist and could not be created!";
            }
        }

        // Process the schema.org Vocabulary Tree
        echo "<h2>schema.org Vocabulary Tree</h2>";
        $filename = "../" . Schema::getVocabularyFilePath();
        $bytes = file_put_contents($filename, fopen("http://schema.org/docs/tree.jsonld", 'r'));
        if($bytes === false) {
            $error = true;
            echo "<strong style=\"color: #FF0000\">Error: </strong>Unable to download schema.org Vocabulary Tree to " . $filename . "<br />";
        } else {
            echo "Downloaded schema.org Vocabulary Tree to " . $filename . "<br />";
        }
        echo "<br />";
        
        foreach(Schema::get()->sort('SchemaOrgID', 'ASC') as $schema) {
            echo "<h2>schema.org " . $schema->Title . " Schema</h2>";
            $bytes = file_put_contents(
                "../" . $schema->LocalDataSource,
                fopen($schema->SchemaOrgURL . "." . Schema::$DataSource['format'], 'r')
            );
            if($bytes === false) {
                $error = true;
                echo "<strong style=\"color: #FF0000\">Error: </strong>Unable to "
                    . "download schema.org " . $schema->Title . " Schema to "
                    . $schema->LocalDataSource . "<br />";
                continue;
            } else {
                $schema->LastUpdated = date('Y-m-d H:i:s', time());
                $schema->write();
                echo "Downloaded schema.org " . $schema->Title
                    . " Schema to " . $schema->LocalDataSource . "<br />";
                /**
                 * @todo Identify any existing SchemaInstances and/or SchemaEntries
                 * which reference properties or value formats not listed in this
                 * schema (i.e. have since been deprecated) and warn the user of this
                 */
            }
            echo "<br />";
        }

        echo (!$error) ? "<strong>Completed Successfully!</strong>" : "<strong>Completed With Errors</strong>";

    }

}