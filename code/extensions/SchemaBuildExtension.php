<?php

/**
 * Schema Build Extension
 *
 * An extenison of the /dev/build process used for synchronising/adding data
 * from schema.org
 *
 * @author Joe Harvey <joe.harvey@quadradigital.co.uk>
 */
class SchemaBuildExtension extends DevBuildController {

    private static $url_handlers = array(
        '' => 'build'
    );

    private static $allowed_actions = array(
        'build'
    );

    public function build($request) {

        parent::build($request);

        UpdateSchemasTask::updateDataSources();

    }

}
